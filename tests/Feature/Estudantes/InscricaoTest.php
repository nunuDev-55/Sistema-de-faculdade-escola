<?php

namespace Tests\Feature\Estudantes;

use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Services\MatriculaService;
use App\Services\PlanoCurricularService;
use App\Support\Definicoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InscricaoTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private AnoLectivo $ano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $this->ano = $this->anoLectivo('2027');
    }

    private function matricular(Estudante $estudante, AnoLectivo $ano, int $anoCurricular = 1): Matricula
    {
        return app(MatriculaService::class)->matricular($estudante, $this->curso, $ano, $anoCurricular, 'laboral', $this->admin);
    }

    private function inscrever(Matricula $matricula, int $periodo, array $disciplinas)
    {
        $ano = $matricula->anoLectivo;

        return $this->actingAs($this->admin)->post("/matriculas/{$matricula->id}/inscricao/{$ano->periodos[$periodo - 1]->id}", ['disciplinas' => $disciplinas]);
    }

    /** As disciplinas do ecrã de inscrição, por código */
    private function situacao(Matricula $matricula, int $periodo): array
    {
        $ano = $matricula->anoLectivo;
        $resposta = $this->actingAs($this->admin)->get("/matriculas/{$matricula->id}/inscricao/{$ano->periodos[$periodo - 1]->id}")->assertOk();

        return collect($resposta->viewData('page')['props']['disciplinas'])->keyBy('codigo')->all();
    }

    public function test_inscreve_no_semestre_e_emite_o_comprovativo(): void
    {
        $matricula = $this->matricular($this->estudante(), $this->ano);

        $situacao = $this->situacao($matricula, 1);
        // No 1.º semestre do 1.º ano: as duas do semestre e a anual; Programação II é do 2.º semestre
        $this->assertSame(['00101', '00120', '00168'], collect($situacao)->keys()->sort()->values()->all());
        $this->assertSame('disponivel', $situacao['00168']['estado']);

        $resposta = $this->inscrever($matricula, 1, $this->ids('prog1', 'mat1', 'ingles'));

        $inscricao = InscricaoPeriodo::firstOrFail();
        $resposta->assertRedirect(route('inscricoes.comprovativo', $inscricao));
        $this->assertSame('IN2027/00001', $inscricao->codigo);
        $this->assertSame(3, $inscricao->inscricoes()->count());
        $this->assertEquals(16.5, $inscricao->inscricoes()->sum('creditos'));

        $this->get("/comprovativos/{$inscricao->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('matriculas/comprovativo')
                ->where('inscricao.codigo', 'IN2027/00001')
                ->has('disciplinas', 3));

        // Já inscrito: a disciplina aparece como inscrita e não se inscreve outra vez
        $this->assertSame('inscrita', $this->situacao($matricula, 1)['00168']['estado']);
        $this->inscrever($matricula, 1, $this->ids('prog1'))->assertSessionHasErrors('disciplinas');
    }

    public function test_a_precedencia_bloqueia_ate_aprovar_a_precedente(): void
    {
        $matricula = $this->matricular($this->estudante(), $this->ano);
        $this->inscrever($matricula, 1, $this->ids('prog1', 'mat1'))->assertSessionHasNoErrors();

        // Programação I ainda sem resultado: Programação II fica bloqueada
        $situacao = $this->situacao($matricula, 2);
        $this->assertSame('bloqueada', $situacao['00169']['estado']);
        $this->assertStringContainsString('Programação I', (string) $situacao['00169']['motivo']);
        $this->inscrever($matricula, 2, $this->ids('prog2'))->assertSessionHasErrors('disciplinas');

        // Aprovada Programação I (com a pauta), Programação II abre
        Inscricao::where('disciplina_id', $this->disciplinas['prog1']->id)->update(['estado' => 'aprovado', 'nota_final' => 14]);
        $this->inscrever($matricula, 2, $this->ids('prog2'))->assertSessionHasNoErrors();
        $this->assertSame(2, InscricaoPeriodo::count());
        $this->assertSame('IN2027/00002', InscricaoPeriodo::latest('id')->value('codigo'));
    }

    public function test_nao_passa_o_limite_de_creditos(): void
    {
        app(Definicoes::class)->guardar(['inscricao.limite_creditos' => '10']);
        $matricula = $this->matricular($this->estudante(), $this->ano);

        $this->inscrever($matricula, 1, $this->ids('prog1', 'mat1'))->assertSessionHasErrors('disciplinas');
        $this->assertSame(0, Inscricao::count());

        // Aos poucos também conta: 6 + 4,5 passa os 10
        $this->inscrever($matricula, 1, $this->ids('prog1'))->assertSessionHasNoErrors();
        $this->inscrever($matricula, 1, $this->ids('ingles'))->assertSessionHasErrors('disciplinas');
        $this->assertSame(1, Inscricao::count());
    }

    public function test_no_ano_seguinte_as_reprovadas_ficam_em_atraso(): void
    {
        $ana = $this->estudante();
        $primeira = $this->matricular($ana, $this->ano);
        $this->inscrever($primeira, 1, $this->ids('prog1', 'mat1', 'ingles'))->assertSessionHasNoErrors();
        Inscricao::where('disciplina_id', $this->disciplinas['prog1']->id)->update(['estado' => 'aprovado']);
        Inscricao::where('disciplina_id', $this->disciplinas['ingles']->id)->update(['estado' => 'aprovado']);
        Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->update(['estado' => 'reprovado']);

        $segunda = $this->matricular($ana, $this->anoLectivo('2028'), 2);
        $situacao = $this->situacao($segunda, 1);

        $this->assertSame('aprovada', $situacao['00168']['estado']);
        $this->assertSame('disponivel', $situacao['00101']['estado']);
        $this->assertTrue($situacao['00101']['em_atraso']);
        $this->assertSame('disponivel', $situacao['00062']['estado']);
        $this->assertFalse($situacao['00062']['em_atraso']);
        // Programação II é de 2.º semestre: não se faz num 1.º semestre
        $this->assertArrayNotHasKey('00169', $situacao);

        $this->inscrever($segunda, 1, $this->ids('mat1', 'bd1'))->assertSessionHasNoErrors();
        $this->assertTrue((bool) Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->where('estado', 'inscrito')->value('em_atraso'));
    }

    public function test_anula_uma_disciplina_e_pode_voltar_a_inscrever(): void
    {
        $matricula = $this->matricular($this->estudante(), $this->ano);
        $this->inscrever($matricula, 1, $this->ids('prog1', 'mat1'));
        $mat1 = Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->firstOrFail();

        $this->delete("/inscricoes/{$mat1->id}")->assertSessionHasNoErrors();
        $this->assertSame('anulado', $mat1->fresh()?->estado);
        $this->assertSame('disponivel', $this->situacao($matricula, 1)['00101']['estado']);

        $this->inscrever($matricula, 1, $this->ids('mat1'))->assertSessionHasNoErrors();
        $this->assertSame('inscrito', $mat1->fresh()?->estado);
        $this->assertSame(2, Inscricao::count());
    }

    public function test_no_ensino_geral_a_matricula_inscreve_em_todas_as_disciplinas_da_classe(): void
    {
        app(Definicoes::class)->guardar(['instituicao.tipo' => 'geral']);
        $classe = Curso::create(['codigo' => 'ESG1', 'nome' => 'Ensino Secundário Geral (1.º ciclo)', 'grau' => 'ensino_secundario', 'duracao_anos' => 3]);
        $servico = app(PlanoCurricularService::class);
        $plano = $servico->criar($classe, '2027', 'media_simples', $this->admin);
        foreach ([['PORT8', 'Português', 1], ['MAT8', 'Matemática', 1], ['PORT9', 'Português 9.ª', 2]] as [$codigo, $nome, $ano]) {
            $servico->adicionarDisciplina($plano, Disciplina::create(['codigo' => $codigo, 'nome' => $nome, 'creditos' => 0]), $ano, 0, true);
        }
        $servico->aprovar($plano, $this->admin);
        $ano = $this->anoLectivo('2028');
        $aluno = $this->estudante('Carla');

        $this->actingAs($this->admin)
            ->post("/estudantes/{$aluno->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $classe->id, 'ano_curricular' => 1, 'regime' => 'manha'])
            ->assertRedirect(route('estudantes.show', $aluno));

        $inscricao = InscricaoPeriodo::firstOrFail();
        $this->assertNull($inscricao->periodo_id);
        $this->assertSame(['MAT8', 'PORT8'], $inscricao->inscricoes()->with('disciplina')->get()->pluck('disciplina.codigo')->sort()->values()->all());

        // O regime do superior não serve no geral
        $this->post("/estudantes/{$this->estudante('Dino')->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $classe->id, 'ano_curricular' => 1, 'regime' => 'laboral'])
            ->assertSessionHasErrors('regime');
    }

    public function test_o_docente_nao_inscreve(): void
    {
        $matricula = $this->matricular($this->estudante(), $this->ano);
        $docente = $this->utilizadorComPerfil('Docente');

        $this->actingAs($docente)->get("/matriculas/{$matricula->id}/inscricao/{$this->ano->periodos[0]->id}")->assertForbidden();
        $this->post("/matriculas/{$matricula->id}/inscricao/{$this->ano->periodos[0]->id}", ['disciplinas' => $this->ids('prog1')])->assertForbidden();
    }
}

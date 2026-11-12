<?php

namespace Tests\Feature\Docencia;

use App\Models\AnoLectivo;
use App\Models\Docente;
use App\Models\Inscricao;
use App\Models\Leccionacao;
use App\Models\Matricula;
use App\Models\Turma;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use App\Services\TurmaService;
use App\Support\Definicoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class TurmasTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private AnoLectivo $ano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $this->ano = $this->anoLectivo('2027');
    }

    /** Matricula e inscreve no 1.º semestre nas disciplinas dadas. */
    private function inscrito(string $nome, array $chaves = ['prog1', 'mat1'], ?AnoLectivo $ano = null, int $anoCurricular = 1): Matricula
    {
        $ano ??= $this->ano;
        $matricula = app(MatriculaService::class)->matricular($this->estudante($nome), $this->curso, $ano, $anoCurricular, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($matricula, $ano->periodos[0], $this->ids(...$chaves), $this->admin);

        return $matricula;
    }

    private function turma(string $nome, int $capacidade = 40, int $anoCurricular = 1, ?AnoLectivo $ano = null, int $periodo = 0): Turma
    {
        $ano ??= $this->ano;

        return app(TurmaService::class)->criar($ano, $ano->periodos[$periodo], $this->curso, $anoCurricular, $nome, null, $capacidade);
    }

    /** Nomes dos estudantes da turma (pelas disciplinas do seu ano), por ordem alfabética. */
    private function nomes(Turma $turma): array
    {
        return Inscricao::whereIn('leccionacao_id', $turma->leccionacoes()->pluck('id'))->where('em_atraso', false)
            ->with('estudante')->get()->pluck('estudante.nome')->unique()->sort()->values()->all();
    }

    public function test_a_turma_nasce_com_as_disciplinas_do_semestre(): void
    {
        $this->actingAs($this->admin)->post('/docencia/turmas', [
            'ano_lectivo_id' => $this->ano->id, 'periodo_id' => $this->ano->periodos[0]->id, 'curso_id' => $this->curso->id,
            'ano_curricular' => 1, 'nome' => 'a', 'capacidade' => 40,
        ])->assertSessionHasNoErrors();

        $turma = Turma::firstOrFail();
        $this->assertSame('A', $turma->nome);
        // 1.º semestre: Programação I, Matemática I e a anual (Inglês); Programação II é do 2.º
        $this->assertEqualsCanonicalizing($this->ids('prog1', 'mat1', 'ingles'), $turma->leccionacoes()->pluck('disciplina_id')->all());

        $segundo = $this->turma('A', periodo: 1);
        $this->assertEqualsCanonicalizing($this->ids('prog2'), $segundo->leccionacoes()->pluck('disciplina_id')->all());

        // Não há duas turmas 1.º A no mesmo semestre
        $this->post('/docencia/turmas', [
            'ano_lectivo_id' => $this->ano->id, 'periodo_id' => $this->ano->periodos[0]->id, 'curso_id' => $this->curso->id,
            'ano_curricular' => 1, 'nome' => 'A', 'capacidade' => 40,
        ])->assertSessionHasErrors('nome');

        $this->get("/docencia/turmas/{$turma->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docencia/turma')->has('leccionacoes', 3)->where('turma.designacao', '1.º A'));
        $this->get('/docencia/turmas')->assertOk()->assertInertia(fn (Assert $page) => $page->component('docencia/turmas')->has('turmas', 1));
    }

    public function test_distribui_por_ordem_alfabetica_ate_a_lotacao(): void
    {
        foreach (['Carla Mondlane', 'Ana Macuácua', 'Bento Chissano'] as $nome) {
            $this->inscrito($nome);
        }
        $a = $this->turma('A', 2);
        $b = $this->turma('B', 2);

        $this->actingAs($this->admin)->post("/docencia/turmas/{$a->id}/distribuir")->assertSessionHas('sucesso');

        $this->assertSame(['Ana Macuácua', 'Bento Chissano'], $this->nomes($a));
        $this->assertSame(['Carla Mondlane'], $this->nomes($b));
        // Todas as disciplinas do semestre ficam na turma do estudante
        $this->assertSame(0, Inscricao::whereNull('leccionacao_id')->count());

        // Repetir não mexe em quem já tem turma
        $this->post("/docencia/turmas/{$b->id}/distribuir")->assertSessionHas('sucesso');
        $this->assertSame(['Carla Mondlane'], $this->nomes($b));

        // Sem lugar: avisa
        $this->inscrito('Dino Tembe');
        $this->inscrito('Ercília Sitoe');
        $this->post("/docencia/turmas/{$a->id}/distribuir")->assertSessionHas('erro');
        $this->assertCount(2, $this->nomes($b));
    }

    public function test_mudar_de_turma_respeita_a_lotacao(): void
    {
        foreach (['Ana Macuácua', 'Bento Chissano', 'Carla Mondlane'] as $nome) {
            $this->inscrito($nome);
        }
        $a = $this->turma('A', 2);
        $b = $this->turma('B', 2);
        $this->actingAs($this->admin)->post("/docencia/turmas/{$a->id}/distribuir");
        $carla = Matricula::whereHas('estudante', fn ($q) => $q->where('nome', 'Carla Mondlane'))->firstOrFail()->inscricoesPeriodo()->firstOrFail();

        $this->post("/docencia/turmas/{$b->id}/estudantes", ['inscricao_periodo_id' => $carla->id, 'turma_id' => $a->id])->assertSessionHasErrors('turma_id');

        $this->put("/docencia/turmas/{$a->id}", ['nome' => 'A', 'capacidade' => 3])->assertSessionHasNoErrors();
        $this->post("/docencia/turmas/{$b->id}/estudantes", ['inscricao_periodo_id' => $carla->id, 'turma_id' => $a->id])->assertSessionHasNoErrors();
        $this->assertSame(['Ana Macuácua', 'Bento Chissano', 'Carla Mondlane'], $this->nomes($a));
        $this->assertSame([], $this->nomes($b));

        // Com estudantes, a lotação não desce abaixo deles e a turma não se apaga
        $this->put("/docencia/turmas/{$a->id}", ['nome' => 'A', 'capacidade' => 2])->assertSessionHasErrors('capacidade');
        $this->delete("/docencia/turmas/{$a->id}")->assertSessionHasErrors('turma');
        $this->delete("/docencia/turmas/{$b->id}")->assertRedirect(route('turmas.index'));
        $this->assertNull(Turma::find($b->id));
    }

    public function test_a_disciplina_em_atraso_fica_na_turma_onde_e_dada(): void
    {
        $primeira = $this->inscrito('Ana Macuácua');
        Inscricao::where('disciplina_id', $this->disciplinas['prog1']->id)->update(['estado' => 'aprovado']);
        Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->update(['estado' => 'reprovado']);
        $ano2028 = $this->anoLectivo('2028');
        $segunda = app(MatriculaService::class)->matricular($primeira->estudante, $this->curso, $ano2028, 2, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($segunda, $ano2028->periodos[0], $this->ids('mat1', 'bd1'), $this->admin);

        $primeiroAno = $this->turma('A', anoCurricular: 1, ano: $ano2028);
        $segundoAno = $this->turma('A', anoCurricular: 2, ano: $ano2028);
        app(TurmaService::class)->distribuir($segundoAno);

        $bd1 = Inscricao::where('disciplina_id', $this->disciplinas['bd1']->id)->firstOrFail();
        $mat1 = Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->where('estado', 'inscrito')->firstOrFail();
        $this->assertSame($segundoAno->id, $bd1->leccionacao?->turma_id);
        $this->assertSame($primeiroAno->id, $mat1->leccionacao?->turma_id);
        // Na turma do 1.º ano não conta para a lotação
        $this->assertSame([], $this->nomes($primeiroAno));
        $this->assertSame(['Ana Macuácua'], $this->nomes($segundoAno));
    }

    public function test_o_docente_ve_so_as_disciplinas_que_lhe_foram_distribuidas(): void
    {
        $turma = $this->turma('A');
        $conta = $this->utilizadorComPerfil('Docente');
        $outra = $this->utilizadorComPerfil('Docente');
        $this->actingAs($this->admin)->post('/docencia/docentes', ['nome' => 'Carlos Mondlane', 'regime' => 'integral', 'user_id' => $conta->id, 'grau_academico' => 'mestre'])
            ->assertSessionHasNoErrors();
        $docente = Docente::firstOrFail();
        $prog1 = Leccionacao::where('turma_id', $turma->id)->where('disciplina_id', $this->disciplinas['prog1']->id)->firstOrFail();

        $this->put("/docencia/leccionacoes/{$prog1->id}", ['docente_id' => $docente->id])->assertSessionHasNoErrors();
        $this->assertSame($docente->id, $prog1->fresh()?->docente_id);

        // A mesma conta não se liga a dois docentes
        $this->post('/docencia/docentes', ['nome' => 'Outro', 'regime' => 'parcial', 'user_id' => $conta->id])->assertSessionHasErrors('user_id');

        $this->actingAs($conta)->get('/docencia')->assertRedirect(route('docencia.minhas'));
        $this->get('/docencia/minhas-turmas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docencia/minhas-turmas')->has('leccionacoes', 1)->where('leccionacoes.0.disciplina', 'Programação I'));
        $this->get('/docencia/turmas')->assertForbidden();
        $this->put("/docencia/leccionacoes/{$prog1->id}", ['docente_id' => null])->assertForbidden();

        $this->actingAs($outra)->get('/docencia/minhas-turmas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('docente', null)->has('leccionacoes', 0));
    }

    public function test_a_secretaria_gere_salas_docentes_e_turmas_mas_nao_distribui_servico(): void
    {
        $secretaria = $this->utilizadorComPerfil('Secretaria académica');
        $turma = $this->turma('A');
        $leccionacao = $turma->leccionacoes()->firstOrFail();

        $this->actingAs($secretaria)->post('/estrutura/salas', ['codigo' => 'b2-104', 'tipo' => 'laboratorio', 'capacidade' => 30])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salas', ['codigo' => 'B2-104', 'capacidade' => 30]);
        $this->get('/estrutura/salas')->assertOk()->assertInertia(fn (Assert $page) => $page->component('estrutura/salas')->has('salas', 1));

        $this->post('/docencia/docentes', ['nome' => 'Rosa Bila', 'regime' => 'parcial'])->assertSessionHasNoErrors();
        $this->get('/docencia/docentes')->assertOk()->assertInertia(fn (Assert $page) => $page->component('docencia/docentes')->has('docentes', 1));
        $this->get("/docencia/turmas/{$turma->id}")->assertOk();
        $this->put("/docencia/leccionacoes/{$leccionacao->id}", ['docente_id' => Docente::firstOrFail()->id])->assertForbidden();
    }

    public function test_no_ensino_geral_a_turma_e_do_ano_e_tem_as_disciplinas_da_classe(): void
    {
        app(Definicoes::class)->guardar(['instituicao.tipo' => 'geral']);
        $ano = $this->anoLectivo('2028');

        $turma = app(TurmaService::class)->criar($ano, $ano->periodos[0], $this->curso, 1, 'A', null, 40);

        $this->assertNull($turma->periodo_id);
        $this->assertEqualsCanonicalizing($this->ids('prog1', 'mat1', 'ingles', 'prog2'), $turma->leccionacoes()->pluck('disciplina_id')->all());
    }
}

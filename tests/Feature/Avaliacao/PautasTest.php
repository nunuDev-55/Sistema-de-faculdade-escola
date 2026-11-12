<?php

namespace Tests\Feature\Avaliacao;

use App\Models\AnoLectivo;
use App\Models\ComponenteAvaliacao;
use App\Models\Docente;
use App\Models\Inscricao;
use App\Models\Leccionacao;
use App\Models\Matricula;
use App\Models\Pauta;
use App\Models\User;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use App\Services\TurmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class PautasTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private AnoLectivo $ano;

    private User $professor;

    private Leccionacao $prog1;

    /** @var array<string, Matricula> */
    private array $matriculas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $this->ano = $this->anoLectivo('2027');
        foreach (['Ana Macuácua', 'Bento Chissano', 'Carla Mondlane'] as $nome) {
            $matricula = app(MatriculaService::class)->matricular($this->estudante($nome), $this->curso, $this->ano, 1, 'laboral', $this->admin);
            app(InscricaoService::class)->inscrever($matricula, $this->ano->periodos[0], $this->ids('prog1', 'mat1'), $this->admin);
            $this->matriculas[explode(' ', $nome)[0]] = $matricula;
        }
        $turmas = app(TurmaService::class);
        $turma = $turmas->criar($this->ano, $this->ano->periodos[0], $this->curso, 1, 'A', null, 40);
        $turmas->distribuir($turma);

        $this->professor = $this->utilizadorComPerfil('Docente');
        $docente = Docente::create(['nome' => 'Carlos Mondlane', 'user_id' => $this->professor->id]);
        $this->prog1 = Leccionacao::where('turma_id', $turma->id)->where('disciplina_id', $this->disciplinas['prog1']->id)->firstOrFail();
        $turmas->atribuirDocente($this->prog1, $docente);
    }

    /** A inscrição do estudante (pelo primeiro nome) em Programação I */
    private function inscricao(string $nome): Inscricao
    {
        return Inscricao::where('disciplina_id', $this->disciplinas['prog1']->id)
            ->whereHas('inscricaoPeriodo', fn ($q) => $q->where('matricula_id', $this->matriculas[$nome]->id))->firstOrFail();
    }

    private function planoDeDoisTestes(): array
    {
        $this->actingAs($this->professor)->put("/pautas/disciplina/{$this->prog1->id}/plano", ['componentes' => [
            ['nome' => '1.º teste', 'peso' => '50'],
            ['nome' => '2.º teste', 'peso' => '50'],
        ]])->assertSessionHasNoErrors();

        return ComponenteAvaliacao::orderBy('ordem')->pluck('id')->all();
    }

    private function pauta(string $epoca): Pauta
    {
        return Pauta::where('leccionacao_id', $this->prog1->id)->where('epoca', $epoca)->firstOrFail();
    }

    private function validarEPublicar(Pauta $pauta): void
    {
        $director = $this->utilizadorComPerfil('Director de curso');
        $this->actingAs($director)->post("/pautas/{$pauta->id}/validar")->assertSessionHasNoErrors();
        $this->post("/pautas/{$pauta->id}/publicar")->assertSessionHasNoErrors();
    }

    public function test_o_plano_de_avaliacao_soma_100_e_so_o_docente_da_disciplina_o_define(): void
    {
        $this->actingAs($this->professor)->get("/pautas/disciplina/{$this->prog1->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('avaliacao/disciplina')->has('estudantes', 3)->where('podeLancar', true));

        $this->put("/pautas/disciplina/{$this->prog1->id}/plano", ['componentes' => [
            ['nome' => '1.º teste', 'peso' => '40'], ['nome' => 'Trabalho', 'peso' => '40'],
        ]])->assertSessionHasErrors('componentes');

        $this->put("/pautas/disciplina/{$this->prog1->id}/plano", ['componentes' => [
            ['nome' => '1.º teste', 'peso' => '30'], ['nome' => '2.º teste', 'peso' => '30'],
            ['nome' => '1.º trabalho', 'peso' => '20'], ['nome' => '2.º trabalho', 'peso' => '20'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(4, ComponenteAvaliacao::count());

        // Outro docente não lança nem vê
        $outro = $this->utilizadorComPerfil('Docente');
        $this->actingAs($outro)->get("/pautas/disciplina/{$this->prog1->id}")->assertForbidden();
        $this->put("/pautas/disciplina/{$this->prog1->id}/plano", ['componentes' => [['nome' => 'Exame', 'peso' => '100']]])->assertForbidden();
    }

    public function test_da_frequencia_a_recorrencia_e_os_resultados_contam_para_o_estudante(): void
    {
        [$t1, $t2] = $this->planoDeDoisTestes();
        $ana = $this->inscricao('Ana');
        $bento = $this->inscricao('Bento');
        $carla = $this->inscricao('Carla');

        $this->actingAs($this->professor)->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [
            $ana->id => [$t1 => '16', $t2 => '14'],      // média 15: dispensada
            $bento->id => [$t1 => '12', $t2 => '10'],    // média 11: admitido a exame
            $carla->id => [$t1 => 'F', $t2 => '16'],     // média 8 (faltou ao 1.º): excluída
        ]])->assertSessionHasNoErrors();

        $frequencia = $this->pauta(Pauta::FREQUENCIA);
        $this->post("/pautas/{$frequencia->id}/submeter")->assertSessionHasNoErrors();
        $this->assertSame(Pauta::SUBMETIDA, $frequencia->fresh()?->estado);
        $situacoes = $frequencia->linhas()->pluck('situacao', 'inscricao_id');
        $this->assertSame('dispensado', $situacoes[$ana->id]);
        $this->assertSame('admitido', $situacoes[$bento->id]);
        $this->assertSame('excluido', $situacoes[$carla->id]);

        // Submetida, as notas já não mudam; o docente não valida
        $this->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [$ana->id => [$t1 => '20']]])->assertSessionHasErrors('notas');
        $this->post("/pautas/{$frequencia->id}/validar")->assertForbidden();

        $this->validarEPublicar($frequencia);
        $frequencia->refresh();
        $this->assertSame(Pauta::PUBLICADA, $frequencia->estado);
        $this->assertSame('PA2027/0001', $frequencia->codigo);
        $this->assertSame(Inscricao::APROVADO, $ana->fresh()?->estado);
        $this->assertEquals(15, $ana->fresh()?->nota_final);
        $this->assertSame('excluido', $carla->fresh()?->estado);
        $this->assertSame(Inscricao::INSCRITO, $bento->fresh()?->estado);

        // Programação II (precede-a Programação I) abre para a Ana no 2.º semestre
        $situacao = collect(app(InscricaoService::class)->situacao($this->matriculas['Ana'], $this->ano->periodos[1]))
            ->first(fn ($s) => $s['linha']->disciplina_id === $this->disciplinas['prog2']->id);
        $this->assertSame(InscricaoService::DISPONIVEL, $situacao['estado'] ?? null);

        // Exame normal: só o Bento; 6 no exame dá 8,5 de nota final e vai a recorrência
        $normal = $this->pauta(Pauta::NORMAL);
        $this->assertSame([$bento->id], $normal->linhas()->pluck('inscricao_id')->all());
        $this->actingAs($this->professor)->put("/pautas/{$normal->id}/exame", ['notas' => [$bento->id => '6']])->assertSessionHasNoErrors();
        $this->post("/pautas/{$normal->id}/submeter")->assertSessionHasNoErrors();
        $this->assertEquals(8.5, $normal->linhas()->value('nota_final'));
        $this->validarEPublicar($normal);
        $this->assertSame(Inscricao::INSCRITO, $bento->fresh()?->estado);

        // Recorrência: 14 dá 12,5 e aprova
        $recorrencia = $this->pauta(Pauta::RECORRENCIA);
        $this->actingAs($this->professor)->put("/pautas/{$recorrencia->id}/exame", ['notas' => [$bento->id => '14']])->assertSessionHasNoErrors();
        $this->post("/pautas/{$recorrencia->id}/submeter")->assertSessionHasNoErrors();
        $this->validarEPublicar($recorrencia);
        $this->assertSame(Inscricao::APROVADO, $bento->fresh()?->estado);
        $this->assertEquals(12.5, $bento->fresh()?->nota_final);
        $this->assertSame('PA2027/0003', $recorrencia->fresh()?->codigo);

        $this->get("/pautas/{$recorrencia->id}/imprimir")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('avaliacao/pauta-impressa')->has('linhas', 1)->where('final', true));
    }

    public function test_nao_se_submete_com_notas_em_falta_e_a_pauta_devolvida_volta_ao_docente(): void
    {
        [$t1, $t2] = $this->planoDeDoisTestes();
        $ana = $this->inscricao('Ana');
        $this->actingAs($this->professor)->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [$ana->id => [$t1 => '12,5', $t2 => '13']]])
            ->assertSessionHasNoErrors();
        $frequencia = $this->pauta(Pauta::FREQUENCIA);

        $this->post("/pautas/{$frequencia->id}/submeter")->assertSessionHasErrors('pauta');

        $this->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [$ana->id => [$t1 => '25']]])->assertSessionHasErrors('notas');

        foreach (['Bento', 'Carla'] as $nome) {
            $id = $this->inscricao($nome)->id;
            $this->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [$id => [$t1 => '10', $t2 => '10']]])->assertSessionHasNoErrors();
        }
        $this->post("/pautas/{$frequencia->id}/submeter")->assertSessionHasNoErrors();

        $director = $this->utilizadorComPerfil('Director de curso');
        $this->actingAs($director)->get('/pautas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('avaliacao/pautas')->has('pautas.data', 1));
        $this->post("/pautas/{$frequencia->id}/devolver", ['motivo' => 'A nota do 2.º teste da Ana está trocada.'])->assertSessionHasNoErrors();
        $frequencia->refresh();
        $this->assertSame(Pauta::RASCUNHO, $frequencia->estado);
        $this->assertSame('A nota do 2.º teste da Ana está trocada.', $frequencia->observacao);

        // De volta ao docente, já pode corrigir
        $this->actingAs($this->professor)->put("/pautas/disciplina/{$this->prog1->id}/notas", ['notas' => [$ana->id => [$t2 => '15']]])
            ->assertSessionHasNoErrors();
        $this->get('/pautas')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pautas.data', 1));
    }
}

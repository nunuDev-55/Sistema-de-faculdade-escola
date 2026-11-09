<?php

namespace Tests\Feature\Estudantes;

use App\Models\Curso;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Services\PlanoCurricularService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MatriculaTest extends TestCase
{
    use Cenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
    }

    public function test_matricula_no_plano_em_vigor_e_segue_para_a_inscricao(): void
    {
        $ano = $this->anoLectivo('2027');
        $estudante = $this->estudante();

        $resposta = $this->actingAs($this->admin)->post("/estudantes/{$estudante->id}/matriculas", [
            'ano_lectivo_id' => $ano->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1, 'regime' => 'laboral',
        ]);

        $matricula = Matricula::firstOrFail();
        $resposta->assertRedirect(route('inscricoes.create', [$matricula, $ano->periodos[0]]));
        $this->assertSame('MT2027/00001', $matricula->numero);
        $this->assertSame($this->plano->id, $matricula->plano_id);
        $this->assertSame('activa', $matricula->estado);
        $this->assertSame('laboral', $matricula->regime);

        $this->get('/matriculas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('matriculas/index')->has('matriculas.data', 1)->where('contagens.activa', fn ($n) => (int) $n === 1));
    }

    public function test_sem_plano_em_vigor_nao_se_matricula(): void
    {
        $ano = $this->anoLectivo('2027');
        $outro = Curso::create(['codigo' => 'LEC', 'nome' => 'Licenciatura em Economia', 'grau' => 'licenciatura', 'duracao_anos' => 4]);

        $this->actingAs($this->admin)
            ->post("/estudantes/{$this->estudante()->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $outro->id, 'ano_curricular' => 1])
            ->assertSessionHasErrors('curso_id');
        $this->assertSame(0, Matricula::count());
    }

    public function test_uma_matricula_por_ano_lectivo_e_dentro_da_duracao_do_curso(): void
    {
        $ano = $this->anoLectivo('2027');
        $estudante = $this->estudante();
        $dados = ['ano_lectivo_id' => $ano->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1];

        $this->actingAs($this->admin)->post("/estudantes/{$estudante->id}/matriculas", $dados)->assertSessionHasNoErrors();
        $this->post("/estudantes/{$estudante->id}/matriculas", $dados)->assertSessionHasErrors('ano_lectivo_id');

        $this->post("/estudantes/{$this->estudante('Rui')->id}/matriculas", [...$dados, 'ano_curricular' => 5])->assertSessionHasErrors('ano_curricular');
    }

    public function test_a_renovacao_mantem_o_plano_em_que_entrou(): void
    {
        $ano2027 = $this->anoLectivo('2027');
        $ano2028 = $this->anoLectivo('2028');
        $ana = $this->estudante();
        $this->actingAs($this->admin)
            ->post("/estudantes/{$ana->id}/matriculas", ['ano_lectivo_id' => $ano2027->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1]);

        // Entretanto entra em vigor o plano 2028
        $servico = app(PlanoCurricularService::class);
        $novo = $servico->novaVersao($this->plano, '2028', $this->admin);
        $servico->aprovar($novo, $this->admin);

        $this->post("/estudantes/{$ana->id}/matriculas", ['ano_lectivo_id' => $ano2028->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->plano->id, Matricula::where('ano_lectivo_id', $ano2028->id)->value('plano_id'));

        // Quem entra agora fica no plano novo
        $rui = $this->estudante('Rui');
        $this->post("/estudantes/{$rui->id}/matriculas", ['ano_lectivo_id' => $ano2028->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1]);
        $this->assertSame($novo->id, Matricula::where('estudante_id', $rui->id)->value('plano_id'));
    }

    public function test_suspender_reactivar_e_anular(): void
    {
        $ano = $this->anoLectivo('2027');
        $estudante = $this->estudante();
        $this->actingAs($this->admin)->post("/estudantes/{$estudante->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1]);
        $matricula = Matricula::firstOrFail();
        $this->post("/matriculas/{$matricula->id}/inscricao/{$ano->periodos[0]->id}", ['disciplinas' => $this->ids('prog1', 'mat1')])->assertSessionHasNoErrors();

        // O motivo é obrigatório
        $this->put("/matriculas/{$matricula->id}/estado", ['accao' => 'suspender'])->assertSessionHasErrors('motivo');
        $this->put("/matriculas/{$matricula->id}/estado", ['accao' => 'suspender', 'motivo' => 'Propinas em atraso'])->assertSessionHasNoErrors();
        $this->assertSame('suspensa', $matricula->fresh()?->estado);

        // Suspensa, não se inscreve
        $this->post("/matriculas/{$matricula->id}/inscricao/{$ano->periodos[0]->id}", ['disciplinas' => $this->ids('ingles')])->assertSessionHasErrors('matricula');

        $this->put("/matriculas/{$matricula->id}/estado", ['accao' => 'reactivar'])->assertSessionHasNoErrors();
        $this->assertSame('activa', $matricula->fresh()?->estado);

        $this->put("/matriculas/{$matricula->id}/estado", ['accao' => 'anular', 'motivo' => 'Desistiu'])->assertSessionHasNoErrors();
        $this->assertSame('anulada', $matricula->fresh()?->estado);
        $this->assertSame(0, Inscricao::where('estado', 'inscrito')->count());
        $this->assertSame(InscricaoPeriodo::ANULADA, InscricaoPeriodo::firstOrFail()->estado);

        // Anulada a matrícula, pode matricular-se outra vez no mesmo ano
        $this->post("/estudantes/{$estudante->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1])
            ->assertSessionHasNoErrors();
    }

    public function test_quem_nao_tem_permissao_nao_matricula(): void
    {
        $ano = $this->anoLectivo('2027');
        $tesouraria = $this->utilizadorComPerfil('Tesouraria');

        $this->actingAs($tesouraria)
            ->post("/estudantes/{$this->estudante()->id}/matriculas", ['ano_lectivo_id' => $ano->id, 'curso_id' => $this->curso->id, 'ano_curricular' => 1])
            ->assertForbidden();
        $this->get('/matriculas')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Portal;

use App\Models\ContaPortal;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class PortalEstudanteTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private Estudante $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $ano = $this->anoLectivo('2027');
        $this->ana = $this->estudante('Ana Macuácua');
        $matricula = app(MatriculaService::class)->matricular($this->ana, $this->curso, $ano, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($matricula, $ano->periodos[0], $this->ids('prog1', 'mat1'), $this->admin);
    }

    /** A secretaria dá acesso; devolve a senha que mostrou uma vez. */
    private function darAcesso(): string
    {
        $resposta = $this->actingAs($this->utilizadorComPerfil('Secretaria académica'))->post("/estudantes/{$this->ana->id}/portal");
        $resposta->assertSessionHas('senha_portal');
        auth()->guard('web')->logout();

        return (string) session('senha_portal');
    }

    public function test_o_estudante_entra_com_o_numero_e_ve_so_as_notas_publicadas(): void
    {
        $senha = $this->darAcesso();
        $this->assertSame(10, strlen($senha));

        $this->post('/portal/entrar', ['numero' => $this->ana->numero, 'password' => $senha])->assertRedirect(route('portal.painel'));
        $this->assertAuthenticatedAs(ContaPortal::firstOrFail(), 'estudante');

        $this->get('/portal')->assertOk()->assertInertia(fn (Assert $page) => $page->component('portal/painel')
            ->where('estudante.numero', $this->ana->numero)
            ->has('matriculas.0.inscricoes.0.disciplinas', 2)
            ->where('matriculas.0.inscricoes.0.disciplinas.0.resultado', null));

        // Publicada a pauta, a inscrição muda: aparece o resultado e a nota
        Inscricao::where('disciplina_id', $this->disciplinas['mat1']->id)->update(['estado' => 'aprovado', 'nota_final' => 14]);
        $this->get('/portal')->assertInertia(fn (Assert $page) => $page
            ->where('matriculas.0.inscricoes.0.disciplinas.0.nome', 'Matemática I')
            ->where('matriculas.0.inscricoes.0.disciplinas.0.resultado', 'Aprovado')
            ->where('matriculas.0.inscricoes.0.disciplinas.0.nota', fn ($n) => (float) $n === 14.0));

        // O portal não abre o sistema interno
        $this->get('/estudantes')->assertRedirect(route('login'));
        $this->get('/painel')->assertRedirect(route('login'));
    }

    public function test_senha_errada_conta_suspensa_e_sem_entrar(): void
    {
        $this->get('/portal')->assertRedirect(route('portal.entrar'));
        $senha = $this->darAcesso();

        $this->post('/portal/entrar', ['numero' => $this->ana->numero, 'password' => 'errada'])->assertSessionHasErrors('numero');
        $this->assertGuest('estudante');

        ContaPortal::firstOrFail()->update(['estado' => 'suspensa']);
        $this->post('/portal/entrar', ['numero' => $this->ana->numero, 'password' => $senha])->assertSessionHasErrors('numero');
        $this->assertGuest('estudante');
    }

    public function test_so_quem_gere_estudantes_da_acesso(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Tesouraria'))->post("/estudantes/{$this->ana->id}/portal")->assertForbidden();
        $this->assertSame(0, ContaPortal::count());
    }
}

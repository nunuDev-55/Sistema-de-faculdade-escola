<?php

namespace Tests\Feature\Financas;

use App\Models\AnoLectivo;
use App\Models\Cobranca;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class CobrancasTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private AnoLectivo $ano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $this->ano = $this->anoLectivo('2027');
    }

    private function inscrito(string $nome): Matricula
    {
        $matricula = app(MatriculaService::class)->matricular($this->estudante($nome), $this->curso, $this->ano, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($matricula, $this->ano->periodos[0], $this->ids('prog1', 'mat1'), $this->admin);

        return $matricula;
    }

    public function test_gera_a_taxa_de_inscricao_e_as_propinas_do_semestre_uma_vez(): void
    {
        $tesouraria = $this->utilizadorComPerfil('Tesouraria');
        $this->actingAs($tesouraria)->post('/financas/precos', [
            'ano_lectivo_id' => $this->ano->id, 'tipo' => 'propina', 'valor' => '3 500,00', 'prestacoes' => 5, 'dia_vencimento' => 10,
        ])->assertSessionHasNoErrors();
        $this->post('/financas/precos', [
            'ano_lectivo_id' => $this->ano->id, 'curso_id' => $this->curso->id, 'tipo' => 'inscricao', 'valor' => '1500', 'prestacoes' => 1, 'dia_vencimento' => 10,
        ])->assertSessionHasNoErrors();
        $matricula = $this->inscrito('Ana Macuácua');

        $this->post('/financas/gerar', ['ano_lectivo_id' => $this->ano->id])->assertSessionHas('sucesso');

        $cobrancas = Cobranca::where('matricula_id', $matricula->id)->orderBy('id')->get();
        $this->assertCount(6, $cobrancas);
        $this->assertSame('CB2027/00001', $cobrancas[0]->numero);
        $this->assertSame('1500.00', $cobrancas[0]->valor);
        $this->assertSame('3500.00', $cobrancas[1]->valor);
        $this->assertSame('Propina de Fevereiro de 2027', $cobrancas[1]->descricao);
        $this->assertSame('2027-02-10', $cobrancas[1]->vencimento->toDateString());
        $this->assertSame('2027-06-10', $cobrancas[5]->vencimento->toDateString());
        $this->assertSame('geradas', InscricaoPeriodo::firstOrFail()->estado_facturacao);

        // Repetir não duplica
        $this->post('/financas/gerar', ['ano_lectivo_id' => $this->ano->id])->assertSessionHas('sucesso');
        $this->assertSame(6, Cobranca::count());

        $this->get('/financas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('financas/index')->has('cobrancas.data', 6)->where('totais.valor', '19000.00'));
        $this->get('/financas/precos')->assertOk()->assertInertia(fn (Assert $page) => $page->component('financas/precos')->has('precos', 2));
    }

    public function test_o_preco_do_curso_vale_mais_e_sem_preco_avisa(): void
    {
        $this->actingAs($this->admin)->post('/financas/precos', [
            'ano_lectivo_id' => $this->ano->id, 'tipo' => 'propina', 'valor' => '3000', 'prestacoes' => 1, 'dia_vencimento' => 5,
        ]);
        $this->post('/financas/precos', [
            'ano_lectivo_id' => $this->ano->id, 'curso_id' => $this->curso->id, 'tipo' => 'propina', 'valor' => '4000', 'prestacoes' => 1, 'dia_vencimento' => 5,
        ]);
        $this->inscrito('Bento Chissano');
        $this->post('/financas/gerar', ['ano_lectivo_id' => $this->ano->id])->assertSessionHasNoErrors();
        $this->assertSame(['4000.00'], Cobranca::pluck('valor')->all());

        // Ano sem tabela de preços: as inscrições ficam por cobrar e avisa
        $ano2028 = $this->anoLectivo('2028');
        $matricula = app(MatriculaService::class)->matricular($this->estudante('Carla'), $this->curso, $ano2028, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($matricula, $ano2028->periodos[0], $this->ids('prog1'), $this->admin);
        $this->post('/financas/gerar', ['ano_lectivo_id' => $ano2028->id])->assertSessionHas('erro');
        $this->assertSame(1, Cobranca::count());
    }

    public function test_so_ve_financas_quem_tem_a_permissao(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Docente'))->get('/financas')->assertForbidden();
        $secretaria = $this->utilizadorComPerfil('Secretaria académica');
        $this->actingAs($secretaria)->get('/financas')->assertForbidden();
        $tesouraria = $this->utilizadorComPerfil('Tesouraria');
        $this->actingAs($tesouraria)->get('/financas')->assertOk();
    }
}

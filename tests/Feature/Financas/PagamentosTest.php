<?php

namespace Tests\Feature\Financas;

use App\Models\AnoLectivo;
use App\Models\Cobranca;
use App\Models\Estudante;
use App\Models\Pagamento;
use App\Models\Preco;
use App\Services\CobrancaService;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class PagamentosTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private Estudante $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $ano = $this->anoLectivo('2027');
        Preco::create(['ano_lectivo_id' => $ano->id, 'tipo' => 'propina', 'valor' => '3500.00', 'prestacoes' => 3, 'dia_vencimento' => 10]);
        $this->ana = $this->estudante('Ana Macuácua');
        $matricula = app(MatriculaService::class)->matricular($this->ana, $this->curso, $ano, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($matricula, $ano->periodos[0], $this->ids('prog1'), $this->admin);
        app(CobrancaService::class)->gerarDoAno(AnoLectivo::findOrFail($ano->id), $this->admin);
    }

    /** @return list<array{string, string}> pago e estado de cada cobrança, pela ordem de vencimento */
    private function cobrancas(): array
    {
        return Cobranca::orderBy('vencimento')->get()->map(fn (Cobranca $c) => [$c->pago, $c->estado])->all();
    }

    private function pagar(string $valor): void
    {
        $this->post("/estudantes/{$this->ana->id}/pagamentos", ['valor' => $valor, 'data' => now()->toDateString(), 'metodo' => 'mpesa', 'referencia' => 'ABC123'])
            ->assertSessionHasNoErrors();
    }

    public function test_o_pagamento_abate_as_mais_antigas_e_o_que_sobra_fica_como_adiantamento(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Tesouraria'));
        $this->pagar('5 000,00');

        $primeiro = Pagamento::firstOrFail();
        $this->assertSame('RC'.now()->year.'/0001', $primeiro->numero);
        $this->assertSame([['3500.00', 'paga'], ['1500.00', 'parcial'], ['0.00', 'aberta']], $this->cobrancas());

        $this->pagar('6000');
        $this->assertSame([['3500.00', 'paga'], ['3500.00', 'paga'], ['3500.00', 'paga']], $this->cobrancas());

        $this->get("/estudantes/{$this->ana->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('financas.credito', '500.00')->where('financas.divida', '0.00')->has('financas.pagamentos', 2));
        $this->get("/financas/recibos/{$primeiro->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('financas/recibo')->has('linhas', 2));
    }

    public function test_anular_um_recibo_volta_a_por_em_divida_e_o_adiantamento_abate_outra_vez(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Tesouraria'));
        $this->pagar('5000');
        $this->pagar('6000');
        $primeiro = Pagamento::orderBy('id')->firstOrFail();

        // A tesouraria regista, mas não anula
        $this->post("/financas/recibos/{$primeiro->id}/anular", ['motivo' => 'Talão repetido'])->assertForbidden();

        $direccao = $this->utilizadorComPerfil('Direcção');
        $this->actingAs($direccao)->post("/financas/recibos/{$primeiro->id}/anular", [])->assertSessionHasErrors('motivo');
        $this->post("/financas/recibos/{$primeiro->id}/anular", ['motivo' => 'Talão repetido'])->assertSessionHasNoErrors();

        $this->assertSame('anulado', $primeiro->fresh()?->estado);
        // Os 500 do 2.º recibo que sobravam abatem agora a 1.ª propina
        $this->assertSame([['500.00', 'parcial'], ['2000.00', 'parcial'], ['3500.00', 'paga']], $this->cobrancas());
    }

    public function test_quem_nao_e_da_tesouraria_nao_regista_nem_ve(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Secretaria académica'))
            ->post("/estudantes/{$this->ana->id}/pagamentos", ['valor' => '100', 'data' => now()->toDateString(), 'metodo' => 'numerario'])
            ->assertForbidden();
        $this->get("/estudantes/{$this->ana->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('financas', null));

        $this->actingAs($this->utilizadorComPerfil('Tesouraria'))
            ->post("/estudantes/{$this->ana->id}/pagamentos", ['valor' => '0', 'data' => now()->toDateString(), 'metodo' => 'numerario'])
            ->assertSessionHasErrors('valor');
        $this->assertSame(0, Pagamento::count());
    }
}

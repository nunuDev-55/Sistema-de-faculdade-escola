<?php

namespace Tests\Feature\Documentos;

use App\Models\Cobranca;
use App\Models\ContaPortal;
use App\Models\DocumentoEmitido;
use App\Models\Estudante;
use App\Models\Matricula;
use App\Models\PedidoDocumento;
use App\Models\Preco;
use App\Models\User;
use App\Services\EstudanteService;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class PedidosTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private Estudante $ana;

    private Matricula $matricula;

    private ContaPortal $conta;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $ano = $this->anoLectivo('2027');
        $this->ana = app(EstudanteService::class)->registar(['nome' => 'Ana Macuácua', 'categoria' => 'regular', 'sexo' => 'F'], $this->admin);
        $this->matricula = app(MatriculaService::class)->matricular($this->ana, $this->curso, $ano, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($this->matricula, $ano->periodos[0], $this->ids('prog1', 'mat1'), $this->admin);
        $this->conta = ContaPortal::create(['estudante_id' => $this->ana->id, 'password' => 'senha-de-teste', 'estado' => 'activa']);
        $this->secretaria = $this->utilizadorComPerfil('Secretaria académica');
        Preco::create(['ano_lectivo_id' => $ano->id, 'curso_id' => null, 'tipo' => 'doc_declaracao', 'valor' => '250.00', 'prestacoes' => 1, 'dia_vencimento' => 10]);
    }

    /** Pede no portal. actingAs(..., 'estudante') muda a guarda de origem do teste: a equipa entra sempre com 'web'. */
    private function pedirNoPortal(string $tipo, ?string $finalidade = null): PedidoDocumento
    {
        $this->actingAs($this->conta, 'estudante')
            ->post('/portal/pedidos', ['tipo' => $tipo, 'finalidade' => $finalidade])
            ->assertSessionHasNoErrors();

        return PedidoDocumento::latest('id')->firstOrFail();
    }

    public function test_o_pedido_do_portal_gera_o_emolumento_e_so_se_emite_depois_de_pago(): void
    {
        $pedido = $this->pedirNoPortal('declaracao_matricula', 'efeitos de bolsa de estudo');
        $this->assertMatchesRegularExpression('#^PD\d{4}/0001$#', $pedido->numero);
        $this->assertSame('portal', $pedido->origem);
        $cobranca = Cobranca::findOrFail($pedido->cobranca_id);
        $this->assertSame('250.00', $cobranca->valor);
        $this->assertSame('emolumento', $cobranca->tipo);
        $this->assertSame('aguarda_pagamento', $pedido->situacao());

        // O estudante vê o pedido e o emolumento por pagar
        $this->get('/portal')->assertInertia(fn (Assert $page) => $page
            ->where('pedidos.0.numero', $pedido->numero)
            ->where('pedidos.0.situacao', 'aguarda_pagamento')
            ->where('financas.divida', '250.00'));

        // Um segundo pedido igual, enquanto o primeiro está por tratar, não entra
        $this->post('/portal/pedidos', ['tipo' => 'declaracao_matricula'])->assertSessionHasErrors('tipo');

        // Sem pagar, a secretaria não emite
        $this->actingAs($this->secretaria, 'web')->post(route('pedidos.emitir', $pedido))->assertSessionHasErrors('pedido');
        $this->assertSame(0, DocumentoEmitido::count());

        // A tesouraria regista o pagamento; a secretaria emite e entrega
        $this->actingAs($this->utilizadorComPerfil('Tesouraria'), 'web')
            ->post("/estudantes/{$this->ana->id}/pagamentos", ['valor' => '250', 'data' => now()->toDateString(), 'metodo' => 'mpesa'])
            ->assertSessionHasNoErrors();
        $this->assertSame('por_emitir', $pedido->fresh()?->situacao());

        $resposta = $this->actingAs($this->secretaria, 'web')->post(route('pedidos.emitir', $pedido));
        $documento = DocumentoEmitido::firstOrFail();
        $resposta->assertRedirect(route('documentos.show', $documento));
        $this->assertSame('declaracao_matricula', $documento->tipo);
        $this->assertSame('efeitos de bolsa de estudo', $documento->finalidade);
        $this->assertSame(PedidoDocumento::EMITIDO, $pedido->fresh()?->estado);
        $this->post(route('pedidos.emitir', $pedido))->assertSessionHasErrors('pedido');

        $this->post(route('pedidos.entregar', $pedido))->assertSessionHasNoErrors();
        $this->assertSame(PedidoDocumento::ENTREGUE, $pedido->fresh()?->estado);
        $this->actingAs($this->conta, 'estudante')->get('/portal')
            ->assertInertia(fn (Assert $page) => $page->where('pedidos.0.situacao_rotulo', 'Entregue'));
    }

    public function test_sem_preco_nao_ha_emolumento_e_o_adiantamento_paga_logo(): void
    {
        // O histórico não tem preço: fica logo por emitir
        $historico = $this->pedirNoPortal('historico');
        $this->assertNull($historico->cobranca_id);
        $this->assertSame('por_emitir', $historico->situacao());

        // Com adiantamento na conta, o emolumento fica pago no momento do pedido
        $this->actingAs($this->utilizadorComPerfil('Tesouraria'), 'web')
            ->post("/estudantes/{$this->ana->id}/pagamentos", ['valor' => '1000', 'data' => now()->toDateString(), 'metodo' => 'numerario']);
        $declaracao = $this->pedirNoPortal('declaracao_frequencia');
        $this->assertSame('paga', Cobranca::findOrFail($declaracao->cobranca_id)->estado);
        $this->assertSame('por_emitir', $declaracao->fresh()?->situacao());
    }

    public function test_recusar_com_motivo_anula_o_emolumento_por_pagar(): void
    {
        $pedido = $this->pedirNoPortal('declaracao_matricula');

        $this->actingAs($this->secretaria, 'web')->post(route('pedidos.recusar', $pedido), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post(route('pedidos.recusar', $pedido), ['motivo' => 'Peça a declaração de frequência: já está inscrita.'])->assertSessionHasNoErrors();
        $this->assertSame(PedidoDocumento::RECUSADO, $pedido->fresh()?->estado);
        $this->assertSame('anulada', Cobranca::findOrFail($pedido->cobranca_id)->estado);

        $this->actingAs($this->conta, 'estudante')->get('/portal')->assertInertia(fn (Assert $page) => $page
            ->where('pedidos.0.situacao', 'recusado')
            ->where('pedidos.0.motivo_recusa', 'Peça a declaração de frequência: já está inscrita.')
            ->where('financas.divida', '0.00'));

        // Recusado, pode pedir outra vez
        $this->post('/portal/pedidos', ['tipo' => 'declaracao_matricula'])->assertSessionHasNoErrors();
    }

    public function test_a_fila_da_secretaria_e_as_permissoes(): void
    {
        $this->pedirNoPortal('declaracao_matricula');
        $this->pedirNoPortal('historico');

        $this->actingAs($this->secretaria, 'web')->get('/documentos/pedidos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documentos/pedidos')
            ->has('pedidos.data', 2)
            ->where('porEmitir', 1));

        $this->actingAs($this->utilizadorComPerfil('Docente'), 'web')->get('/documentos/pedidos')->assertForbidden();
        $pedido = PedidoDocumento::firstOrFail();
        $this->actingAs($this->utilizadorComPerfil('Direcção'), 'web')->post(route('pedidos.recusar', $pedido), ['motivo' => 'x'])->assertForbidden();

        // O portal não abre a fila interna
        auth()->guard('web')->logout();
        $this->actingAs($this->conta, 'estudante')->get('/documentos/pedidos')->assertRedirect(route('login'));
    }
}

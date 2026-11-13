<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\PedidoDocumento;
use App\Models\User;
use App\Services\PedidoDocumentoService;
use App\Support\Opcoes;
use App\Support\Pesquisa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A fila de pedidos de documentos da secretaria: emitir os pagos, recusar com motivo e marcar a entrega. */
class PedidoController extends Controller
{
    public function __construct(private readonly PedidoDocumentoService $servico) {}

    public function index(Request $request): Response
    {
        $situacao = (string) $request->query('situacao', 'por_tratar');
        $q = trim((string) $request->query('q', ''));

        $pedidos = PedidoDocumento::query()
            ->with(['estudante', 'cobranca', 'documento'])
            ->when($situacao === 'por_tratar', fn (Builder $p) => $p->whereIn('estado', [PedidoDocumento::PEDIDO, PedidoDocumento::EMITIDO]))
            ->when(in_array($situacao, [PedidoDocumento::ENTREGUE, PedidoDocumento::RECUSADO], true), fn (Builder $p) => $p->where('estado', $situacao))
            ->when($q !== '', fn (Builder $p) => $p->where(fn (Builder $w) => $w
                ->where('numero', 'like', Pesquisa::like($q))
                ->orWhereHas('estudante', fn (Builder $e) => $e->where('nome', 'like', Pesquisa::like($q))->orWhere('numero', 'like', Pesquisa::like($q)))))
            ->orderBy('created_at')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (PedidoDocumento $p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'tipo' => $p->nomeTipo(),
                'finalidade' => $p->finalidade,
                'estudante_id' => $p->estudante_id,
                'estudante' => $p->estudante->nome,
                'estudante_numero' => $p->estudante->numero,
                'origem' => $p->origem,
                'pedido_em' => $p->created_at->toIso8601String(),
                'emolumento' => $p->cobranca?->valor,
                'situacao' => $p->situacao(),
                'situacao_rotulo' => PedidoDocumento::SITUACOES[$p->situacao()] ?? $p->situacao(),
                'motivo_recusa' => $p->motivo_recusa,
                'documento_id' => $p->documento_id,
                'documento_numero' => $p->documento?->numero,
            ]);

        return Inertia::render('documentos/pedidos', [
            'pedidos' => $pedidos,
            'filtros' => ['situacao' => $situacao, 'q' => $q],
            'situacoes' => [
                ['valor' => 'por_tratar', 'rotulo' => 'Por tratar'],
                ...Opcoes::de([PedidoDocumento::ENTREGUE => 'Entregues', PedidoDocumento::RECUSADO => 'Recusados']),
                ['valor' => 'todos', 'rotulo' => 'Todos'],
            ],
            'porEmitir' => PedidoDocumento::with('cobranca')->where('estado', PedidoDocumento::PEDIDO)->get()->filter(fn (PedidoDocumento $p) => $p->pago())->count(),
        ]);
    }

    public function emitir(Request $request, PedidoDocumento $pedido): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $documento = $this->servico->emitir($pedido, $autor);

        return to_route('documentos.show', $documento)->with('sucesso', "Pedido {$pedido->numero}: documento {$documento->numero} emitido. Imprima-o e entregue-o.");
    }

    public function recusar(Request $request, PedidoDocumento $pedido): RedirectResponse
    {
        $v = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Escreva porque se recusa: o estudante vê o motivo no portal.']);
        /** @var User $autor */
        $autor = $request->user();
        $this->servico->recusar($pedido, (string) $v['motivo'], $autor);

        return back()->with('sucesso', "Pedido {$pedido->numero} recusado.");
    }

    public function entregar(Request $request, PedidoDocumento $pedido): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $this->servico->entregar($pedido, $autor);

        return back()->with('sucesso', "Pedido {$pedido->numero}: documento entregue.");
    }
}

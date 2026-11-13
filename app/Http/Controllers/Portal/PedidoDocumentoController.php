<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ContaPortal;
use App\Models\DocumentoEmitido;
use App\Services\PedidoDocumentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** O estudante pede um documento no portal; o emolumento aparece logo em "Por pagar". */
class PedidoDocumentoController extends Controller
{
    public function store(Request $request, PedidoDocumentoService $servico): RedirectResponse
    {
        $v = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(DocumentoEmitido::TIPOS))],
            'finalidade' => ['nullable', 'string', 'max:200'],
        ], ['tipo.required' => 'Escolha o documento que quer pedir.'], ['finalidade' => 'finalidade']);
        /** @var ContaPortal $conta */
        $conta = $request->user('estudante');
        $pedido = $servico->pedir($conta->estudante, (string) $v['tipo'], ($v['finalidade'] ?? null) ?: null, 'portal', null);
        $pedido->load('cobranca');

        return back()->with('sucesso', "Pedido {$pedido->numero} enviado."
            .($pedido->pago() ? ' A secretaria avisa quando estiver pronto.' : ' Pague o emolumento na tesouraria para a secretaria o emitir.'));
    }
}

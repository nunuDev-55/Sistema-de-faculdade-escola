<?php

namespace App\Http\Controllers\Financas;

use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\Estudante;
use App\Models\Pagamento;
use App\Models\User;
use App\Services\PagamentoService;
use App\Support\Definicoes;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pagamentos do estudante, recibos e anulação de recibos. */
class PagamentoController extends Controller
{
    public function __construct(private readonly PagamentoService $servico) {}

    public function store(Request $request, Estudante $estudante): RedirectResponse
    {
        $request->merge(['valor' => Dinheiro::lerNumero($request->input('valor'))]);
        $v = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'data' => ['required', 'date'],
            'metodo' => ['required', Rule::in(array_keys(Pagamento::METODOS))],
            'referencia' => ['nullable', 'string', 'max:60'],
        ], [], ['valor' => 'valor', 'data' => 'data', 'metodo' => 'forma de pagamento']);
        /** @var User $autor */
        $autor = $request->user();
        $pagamento = $this->servico->registar($estudante, (string) $v['valor'], CarbonImmutable::parse((string) $v['data']), (string) $v['metodo'], ($v['referencia'] ?? null) ?: null, $autor);

        return to_route('pagamentos.recibo', $pagamento)->with('sucesso', "Pagamento registado: recibo {$pagamento->numero}.");
    }

    public function anular(Request $request, Pagamento $pagamento): RedirectResponse
    {
        $v = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Escreva porque se anula o recibo: fica no registo.']);
        /** @var User $autor */
        $autor = $request->user();
        $this->servico->anular($pagamento, (string) $v['motivo'], $autor);

        return back()->with('sucesso', "Recibo {$pagamento->numero} anulado.");
    }

    /** O recibo numa folha A4, para imprimir ou guardar em PDF. */
    public function recibo(Pagamento $pagamento, Definicoes $definicoes): Response
    {
        $pagamento->load(['estudante', 'registadoPor', 'cobrancas']);

        return Inertia::render('financas/recibo', [
            'instituicao' => [
                'nome' => $definicoes->nome(),
                'nuit' => $definicoes->obter('instituicao.nuit'),
                'morada' => trim(implode(', ', array_filter([$definicoes->obter('instituicao.morada'), $definicoes->obter('instituicao.cidade')]))),
                'logotipo' => $definicoes->urlLogotipo(),
            ],
            'pagamento' => [
                'id' => $pagamento->id,
                'numero' => $pagamento->numero,
                'data' => $pagamento->data->toDateString(),
                'valor' => $pagamento->valor,
                'metodo' => Pagamento::METODOS[$pagamento->metodo] ?? $pagamento->metodo,
                'referencia' => $pagamento->referencia,
                'estado' => $pagamento->estado,
                'motivo_anulacao' => $pagamento->motivo_anulacao,
                'registado_por' => $pagamento->registadoPor?->name,
                'adiantamento' => $this->servico->livre($pagamento),
            ],
            'estudante' => ['id' => $pagamento->estudante->id, 'numero' => $pagamento->estudante->numero, 'nome' => $pagamento->estudante->nome],
            'linhas' => $pagamento->cobrancas->map(fn (Cobranca $c) => [
                'numero' => $c->numero,
                'descricao' => $c->descricao,
                'valor' => (string) $c->getRelationValue('pivot')?->getAttribute('valor'),
            ])->values(),
        ]);
    }
}

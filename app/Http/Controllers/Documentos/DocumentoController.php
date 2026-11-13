<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\DocumentoEmitido;
use App\Models\Estudante;
use App\Models\Matricula;
use App\Models\User;
use App\Services\DocumentoService;
use App\Support\CodigoQr;
use App\Support\Definicoes;
use App\Support\Opcoes;
use App\Support\Pesquisa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Documentos académicos: a lista dos emitidos, a emissão a partir da ficha, a folha para imprimir e a anulação. */
class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $servico) {}

    public function index(Request $request): Response
    {
        $tipo = (string) $request->query('tipo', '');
        $estado = (string) $request->query('estado', '');
        $q = trim((string) $request->query('q', ''));

        $documentos = DocumentoEmitido::query()
            ->with(['estudante', 'emitidoPor'])
            ->when(array_key_exists($tipo, DocumentoEmitido::TIPOS), fn (Builder $d) => $d->where('tipo', $tipo))
            ->when(in_array($estado, ['valido', 'anulado'], true), fn (Builder $d) => $d->where('estado', $estado))
            ->when($q !== '', fn (Builder $d) => $d->where(fn (Builder $w) => $w
                ->where('numero', 'like', Pesquisa::like($q))
                ->orWhereHas('estudante', fn (Builder $e) => $e->where('nome', 'like', Pesquisa::like($q))->orWhere('numero', 'like', Pesquisa::like($q)))))
            ->orderByDesc('emitido_em')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (DocumentoEmitido $d) => [
                'id' => $d->id,
                'numero' => $d->numero,
                'tipo' => $d->nomeTipo(),
                'estudante_id' => $d->estudante_id,
                'estudante' => $d->estudante->nome,
                'estudante_numero' => $d->estudante->numero,
                'finalidade' => $d->finalidade,
                'emitido_em' => $d->emitido_em->toIso8601String(),
                'emitido_por' => $d->emitidoPor?->name,
                'estado' => $d->estado,
            ]);

        return Inertia::render('documentos/index', [
            'documentos' => $documentos,
            'filtros' => ['tipo' => $tipo, 'estado' => $estado, 'q' => $q],
            'tipos' => Opcoes::de(DocumentoEmitido::TIPOS),
        ]);
    }

    public function store(Request $request, Estudante $estudante): RedirectResponse
    {
        $v = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(DocumentoEmitido::TIPOS))],
            'matricula_id' => ['nullable', 'integer', Rule::exists('matriculas', 'id')->where('estudante_id', $estudante->id)],
            'finalidade' => ['nullable', 'string', 'max:200'],
        ], [], ['tipo' => 'tipo de documento', 'matricula_id' => 'matrícula', 'finalidade' => 'finalidade']);
        /** @var User $autor */
        $autor = $request->user();
        $matricula = isset($v['matricula_id']) ? Matricula::findOrFail((int) $v['matricula_id']) : null;
        $documento = $this->servico->emitir($estudante, (string) $v['tipo'], $matricula, ($v['finalidade'] ?? null) ?: null, $autor);

        return to_route('documentos.show', $documento)->with('sucesso', "Documento {$documento->numero} emitido ({$documento->nomeTipo()}).");
    }

    public function anular(Request $request, DocumentoEmitido $documento): RedirectResponse
    {
        $v = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Escreva porque se anula o documento: fica no registo e na página de verificação.']);
        /** @var User $autor */
        $autor = $request->user();
        $this->servico->anular($documento, (string) $v['motivo'], $autor);

        return back()->with('sucesso', "Documento {$documento->numero} anulado.");
    }

    /** O documento numa folha A4, com o código e o QR de verificação, para imprimir ou guardar em PDF. */
    public function show(DocumentoEmitido $documento, Definicoes $definicoes): Response
    {
        $documento->load(['estudante', 'emitidoPor']);
        $url = route('verificar', $documento->codigoFormatado());

        return Inertia::render('documentos/documento', [
            'documento' => [
                'id' => $documento->id,
                'numero' => $documento->numero,
                'tipo' => $documento->tipo,
                'estado' => $documento->estado,
                'motivo_anulacao' => $documento->motivo_anulacao,
                'emitido_em' => $documento->emitido_em->toIso8601String(),
                'emitido_por' => $documento->emitidoPor?->name,
                'codigo' => $documento->codigoFormatado(),
                'url_verificacao' => $url,
                'qr' => CodigoQr::svg($url),
                'confere' => $this->servico->confere($documento),
            ],
            'conteudo' => $documento->dados(),
            'logotipo' => $definicoes->urlLogotipo(),
            'estudante' => ['id' => $documento->estudante->id, 'nome' => $documento->estudante->nome],
        ]);
    }
}

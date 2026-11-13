<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\DocumentoEmitido;
use App\Services\DocumentoService;
use App\Support\Definicoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página pública de verificação (RF-91): quem recebe um documento lê o QR ou escreve o código e vê se o documento
 * existe, se é válido ou foi anulado, e os dados essenciais para comparar com o papel. Não mostra mais do que isso.
 */
class VerificacaoController extends Controller
{
    public function __construct(private readonly DocumentoService $servico) {}

    public function formulario(Request $request, Definicoes $definicoes): Response|RedirectResponse
    {
        $codigo = trim((string) $request->query('codigo', ''));
        if ($codigo !== '') {
            return to_route('verificar', ['codigo' => $codigo]);
        }

        return Inertia::render('documentos/verificar', [
            'instituicao' => ['nome' => $definicoes->nome(), 'logotipo' => $definicoes->urlLogotipo()],
            'codigo' => '',
            'resultado' => null,
        ]);
    }

    public function mostrar(string $codigo, Definicoes $definicoes): Response
    {
        $documento = $this->servico->procurar($codigo);

        return Inertia::render('documentos/verificar', [
            'instituicao' => ['nome' => $definicoes->nome(), 'logotipo' => $definicoes->urlLogotipo()],
            'codigo' => strtoupper($codigo),
            'resultado' => $documento === null ? ['estado' => 'inexistente'] : $this->resultado($documento),
        ]);
    }

    /** @return array<string, mixed> */
    private function resultado(DocumentoEmitido $documento): array
    {
        $dados = $documento->dados();
        $estado = ! $this->servico->confere($documento) ? 'nao_confere' : ($documento->valido() ? 'valido' : 'anulado');
        if ($estado === 'nao_confere') {
            // Os dados guardados não são de confiança: não se mostram
            return ['estado' => $estado, 'numero' => $documento->numero];
        }

        return [
            'estado' => $estado,
            'numero' => $documento->numero,
            'tipo' => $documento->nomeTipo(),
            'titular' => $dados['estudante']['nome'] ?? $documento->estudante->nome,
            'estudante_numero' => $dados['estudante']['numero'] ?? $documento->estudante->numero,
            'curso' => $dados['curso']['nome'] ?? null,
            'ano_lectivo' => $dados['matricula']['ano_lectivo'] ?? null,
            'resumo' => $dados['resumo'] ?? null,
            'emitido_em' => $documento->emitido_em->toIso8601String(),
            'motivo_anulacao' => $estado === 'anulado' ? $documento->motivo_anulacao : null,
            'anulado_em' => $documento->anulado_em?->toIso8601String(),
        ];
    }
}

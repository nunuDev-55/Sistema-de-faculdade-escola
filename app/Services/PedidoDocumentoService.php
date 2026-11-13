<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\DocumentoEmitido;
use App\Models\Estudante;
use App\Models\Matricula;
use App\Models\PedidoDocumento;
use App\Models\Preco;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedidos de documentos (RF-92, docs/06 secção 5). O pedido gera o emolumento da tabela de preços (se houver preço
 * para o tipo de documento) como uma cobrança normal: paga-se na tesouraria e, se o estudante tiver adiantamento,
 * fica logo paga. A secretaria só emite com o emolumento pago; a emissão é a de App\Services\DocumentoService, por isso
 * as mesmas regras valem (notas só de pautas publicadas, certificado só com o curso concluído).
 */
class PedidoDocumentoService
{
    public function __construct(
        private Numeracao $numeracao,
        private CobrancaService $cobrancas,
        private PagamentoService $pagamentos,
        private DocumentoService $documentos,
    ) {}

    public function pedir(Estudante $estudante, string $tipo, ?string $finalidade, string $origem, ?User $autor): PedidoDocumento
    {
        if (! array_key_exists($tipo, DocumentoEmitido::TIPOS)) {
            throw ValidationException::withMessages(['tipo' => 'Escolha o documento que quer pedir.']);
        }
        $aberto = PedidoDocumento::where('estudante_id', $estudante->id)->where('tipo', $tipo)->where('estado', PedidoDocumento::PEDIDO)->first();
        if ($aberto !== null) {
            throw ValidationException::withMessages(['tipo' => "Já há um pedido de {$aberto->nomeTipo()} por tratar ({$aberto->numero})."]);
        }
        $matricula = $estudante->matriculas()->with(['curso', 'anoLectivo'])->where('estado', '!=', Matricula::ANULADA)->get()
            ->sortByDesc(fn (Matricula $m) => [$m->anoLectivo->inicio->timestamp, $m->id])->first();
        if ($matricula === null) {
            throw ValidationException::withMessages(['tipo' => 'Ainda não tem matrícula: não há documento a emitir.']);
        }
        $finalidade = $finalidade !== null && trim($finalidade) !== '' ? trim($finalidade) : null;

        return DB::transaction(function () use ($estudante, $tipo, $finalidade, $origem, $autor, $matricula) {
            $pedido = PedidoDocumento::create([
                'numero' => $this->numeracao->proximo('pedido_documento'),
                'estudante_id' => $estudante->id,
                'matricula_id' => $matricula->id,
                'tipo' => $tipo,
                'finalidade' => $finalidade,
                'estado' => PedidoDocumento::PEDIDO,
                'origem' => $origem,
            ]);

            $preco = $this->cobrancas->preco($matricula->ano_lectivo_id, $matricula->curso_id, Preco::EMOLUMENTOS[$tipo]);
            if ($preco !== null && bccomp($preco->valor, '0', 2) > 0) {
                $cobranca = Cobranca::create([
                    'numero' => $this->numeracao->proximo('cobranca', (int) $matricula->anoLectivo->inicio->format('Y')),
                    'estudante_id' => $estudante->id,
                    'matricula_id' => $matricula->id,
                    'tipo' => 'emolumento',
                    'descricao' => "Emolumento: {$pedido->nomeTipo()} (pedido {$pedido->numero})",
                    'valor' => $preco->valor,
                    'pago' => '0.00',
                    'vencimento' => now()->toDateString(),
                    'estado' => 'aberta',
                    'criado_por' => $autor?->id,
                ]);
                $pedido->update(['cobranca_id' => $cobranca->id]);
                // Um adiantamento que o estudante já tenha paga o emolumento logo
                $this->pagamentos->alocar($estudante);
            }
            activity('documentos')->performedOn($pedido)
                ->log("Pedido {$pedido->numero}: {$pedido->nomeTipo()} de {$estudante->nome} ({$estudante->numero})".($origem === 'portal' ? ', no portal' : ''));

            return $pedido;
        });
    }

    /** Emite o documento do pedido; só com o emolumento pago e enquanto o pedido está por tratar. */
    public function emitir(PedidoDocumento $pedido, User $autor): DocumentoEmitido
    {
        $pedido->load(['estudante', 'matricula', 'cobranca']);
        $this->exigirPorTratar($pedido);
        if (! $pedido->pago()) {
            throw ValidationException::withMessages(['pedido' => "O emolumento do pedido {$pedido->numero} ainda não está pago."]);
        }

        return DB::transaction(function () use ($pedido, $autor) {
            $documento = $this->documentos->emitir(
                $pedido->estudante,
                $pedido->tipo,
                in_array($pedido->tipo, DocumentoEmitido::DE_MATRICULA, true) ? $pedido->matricula : null,
                $pedido->finalidade,
                $autor,
            );
            $pedido->update(['estado' => PedidoDocumento::EMITIDO, 'documento_id' => $documento->id, 'tratado_por' => $autor->id]);

            return $documento;
        });
    }

    /** Recusa o pedido, com o motivo (que o estudante vê no portal); o emolumento ainda por pagar é anulado. */
    public function recusar(PedidoDocumento $pedido, string $motivo, User $autor): void
    {
        $pedido->load('cobranca');
        $this->exigirPorTratar($pedido);

        DB::transaction(function () use ($pedido, $motivo, $autor) {
            $cobranca = $pedido->cobranca;
            if ($cobranca !== null && bccomp($cobranca->pago, '0', 2) === 0) {
                $cobranca->update(['estado' => 'anulada']);
            }
            $pedido->update(['estado' => PedidoDocumento::RECUSADO, 'motivo_recusa' => $motivo, 'tratado_por' => $autor->id]);
            activity('documentos')->performedOn($pedido)->withProperties(['motivo' => $motivo])->log("Pedido {$pedido->numero} recusado");
        });
    }

    public function entregar(PedidoDocumento $pedido, User $autor): void
    {
        if ($pedido->estado !== PedidoDocumento::EMITIDO) {
            throw ValidationException::withMessages(['pedido' => "O pedido {$pedido->numero} não tem documento pronto a entregar."]);
        }
        $pedido->update(['estado' => PedidoDocumento::ENTREGUE, 'entregue_em' => now(), 'tratado_por' => $autor->id]);
        activity('documentos')->performedOn($pedido)->log("Pedido {$pedido->numero}: documento entregue");
    }

    private function exigirPorTratar(PedidoDocumento $pedido): void
    {
        if ($pedido->estado !== PedidoDocumento::PEDIDO) {
            throw ValidationException::withMessages(['pedido' => "O pedido {$pedido->numero} já foi tratado ("
                .mb_strtolower(PedidoDocumento::SITUACOES[$pedido->estado] ?? $pedido->estado).').']);
        }
    }
}

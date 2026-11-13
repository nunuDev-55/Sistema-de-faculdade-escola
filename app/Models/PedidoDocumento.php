<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de um documento académico (RF-92, docs/06 secção 5): pedido → (emolumento pago) → emitido → entregue,
 * ou recusado com o motivo. Só por App\Services\PedidoDocumentoService.
 *
 * @property int $id
 * @property string $numero
 * @property int $estudante_id
 * @property int|null $matricula_id
 * @property string $tipo
 * @property string|null $finalidade
 * @property string $estado
 * @property string $origem
 * @property int|null $cobranca_id
 * @property int|null $documento_id
 * @property string|null $motivo_recusa
 * @property int|null $tratado_por
 * @property Carbon|null $entregue_em
 * @property Carbon $created_at
 * @property-read Estudante $estudante
 * @property-read Matricula|null $matricula
 * @property-read Cobranca|null $cobranca
 * @property-read DocumentoEmitido|null $documento
 */
class PedidoDocumento extends Model
{
    public const PEDIDO = 'pedido';

    public const EMITIDO = 'emitido';

    public const ENTREGUE = 'entregue';

    public const RECUSADO = 'recusado';

    /** Como cada estado se mostra; "pedido" divide-se em "aguarda pagamento" e "por emitir" (ver situacao()). */
    public const SITUACOES = [
        'aguarda_pagamento' => 'Aguarda pagamento',
        'por_emitir' => 'Por emitir',
        self::EMITIDO => 'Pronto a levantar',
        self::ENTREGUE => 'Entregue',
        self::RECUSADO => 'Recusado',
    ];

    protected $table = 'pedidos_documento';

    protected $fillable = [
        'numero', 'estudante_id', 'matricula_id', 'tipo', 'finalidade', 'estado', 'origem', 'cobranca_id', 'documento_id',
        'motivo_recusa', 'tratado_por', 'entregue_em',
    ];

    protected function casts(): array
    {
        return ['entregue_em' => 'datetime'];
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return BelongsTo<Cobranca, $this> */
    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class, 'cobranca_id');
    }

    /** @return BelongsTo<DocumentoEmitido, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoEmitido::class, 'documento_id');
    }

    /** O emolumento está pago (ou não havia emolumento a pagar)? */
    public function pago(): bool
    {
        return $this->cobranca === null || $this->cobranca->estado === 'paga';
    }

    /** O estado como o estudante e a secretaria o vêem. */
    public function situacao(): string
    {
        if ($this->estado !== self::PEDIDO) {
            return $this->estado;
        }

        return $this->pago() ? 'por_emitir' : 'aguarda_pagamento';
    }

    public function nomeTipo(): string
    {
        return DocumentoEmitido::TIPOS[$this->tipo] ?? $this->tipo;
    }
}

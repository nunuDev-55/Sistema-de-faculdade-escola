<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Pagamento do estudante, com o seu recibo. Abate as cobranças mais antigas primeiro; o que sobra fica como
 * adiantamento. Um recibo emitido não se apaga: anula-se, com o motivo (RN-13). Só por App\Services\PagamentoService.
 *
 * @property int $id
 * @property string $numero
 * @property int $estudante_id
 * @property Carbon $data
 * @property string $valor
 * @property string $metodo
 * @property string|null $referencia
 * @property string $estado
 * @property string|null $motivo_anulacao
 * @property int|null $registado_por
 * @property-read Estudante $estudante
 * @property-read User|null $registadoPor
 */
class Pagamento extends Model
{
    public const METODOS = [
        'numerario' => 'Numerário',
        'banco' => 'Depósito ou transferência',
        'mpesa' => 'M-Pesa',
        'emola' => 'e-Mola',
        'pos' => 'Cartão (POS)',
    ];

    protected $fillable = ['numero', 'estudante_id', 'data', 'valor', 'metodo', 'referencia', 'estado', 'motivo_anulacao', 'registado_por', 'anulado_por', 'anulado_em'];

    protected function casts(): array
    {
        return ['data' => 'date', 'valor' => 'decimal:2', 'anulado_em' => 'datetime'];
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registado_por');
    }

    /** @return BelongsToMany<Cobranca, $this> */
    public function cobrancas(): BelongsToMany
    {
        return $this->belongsToMany(Cobranca::class, 'cobranca_pagamento', 'pagamento_id', 'cobranca_id')->withPivot('valor')->withTimestamps();
    }

    public function valido(): bool
    {
        return $this->estado === 'valido';
    }
}

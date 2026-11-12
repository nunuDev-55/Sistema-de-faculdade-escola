<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cobrança ao estudante (taxa de inscrição, propina do mês): faz o papel da factura do nsERP. Os pagamentos abatem
 * ao valor; criada e alterada só por App\Services\CobrancaService.
 *
 * @property int $id
 * @property string $numero
 * @property int $estudante_id
 * @property int|null $matricula_id
 * @property int|null $inscricao_periodo_id
 * @property string $tipo
 * @property string $descricao
 * @property string $valor
 * @property string $pago
 * @property Carbon $vencimento
 * @property string $estado
 * @property-read Estudante $estudante
 */
class Cobranca extends Model
{
    public const ESTADOS = [
        'aberta' => 'Por pagar',
        'parcial' => 'Paga em parte',
        'paga' => 'Paga',
        'anulada' => 'Anulada',
    ];

    protected $fillable = ['numero', 'estudante_id', 'matricula_id', 'inscricao_periodo_id', 'tipo', 'descricao', 'valor', 'pago', 'vencimento', 'estado', 'criado_por'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'pago' => 'decimal:2', 'vencimento' => 'date'];
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

    /** O que falta pagar. */
    public function emDivida(): string
    {
        return bcsub($this->valor, $this->pago, 2);
    }

    public function vencida(): bool
    {
        return in_array($this->estado, ['aberta', 'parcial'], true) && $this->vencimento->lt(now()->startOfDay());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Inscrição de uma matrícula num semestre (ou no ano todo, no ensino geral): é o que leva o número do
 * comprovativo e, mais tarde, as cobranças da inscrição. As disciplinas ficam em App\Models\Inscricao.
 *
 * @property int $id
 * @property string $codigo
 * @property int $matricula_id
 * @property int|null $periodo_id
 * @property string $estado
 * @property string $estado_facturacao
 * @property Carbon|null $confirmada_em
 * @property int|null $criado_por
 * @property-read Matricula $matricula
 * @property-read PeriodoLectivo|null $periodo
 */
class InscricaoPeriodo extends Model
{
    public const CONFIRMADA = 'confirmada';

    public const ANULADA = 'anulada';

    public const ESTADOS = [
        self::CONFIRMADA => 'Confirmada',
        self::ANULADA => 'Anulada',
    ];

    protected $table = 'inscricoes_periodo';

    protected $fillable = ['codigo', 'matricula_id', 'periodo_id', 'estado', 'estado_facturacao', 'confirmada_em', 'criado_por'];

    protected function casts(): array
    {
        return ['confirmada_em' => 'datetime'];
    }

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return BelongsTo<PeriodoLectivo, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLectivo::class, 'periodo_id');
    }

    /** @return HasMany<Inscricao, $this> */
    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class, 'inscricao_periodo_id');
    }

    /** "1.º semestre" ou "Ano lectivo" (ensino geral, inscrição anual). */
    public function nomePeriodo(): string
    {
        return $this->periodo?->nome() ?? 'Ano lectivo';
    }
}

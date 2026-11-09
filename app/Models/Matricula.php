<?php

namespace App\Models;

use App\Support\TipoInstituicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Matrícula de um estudante num ano lectivo: curso, plano curricular (o do ano em que entrou no curso),
 * ano curricular e regime. Muda de estado só por App\Services\MatriculaService (docs/06, secção 2).
 *
 * @property int $id
 * @property string $numero
 * @property int $estudante_id
 * @property int $curso_id
 * @property int $plano_id
 * @property int $ano_lectivo_id
 * @property int $ano_curricular
 * @property string|null $regime
 * @property string $estado
 * @property string|null $resultado
 * @property Carbon $data
 * @property string|null $motivo
 * @property int|null $criado_por
 * @property-read Estudante $estudante
 * @property-read Curso $curso
 * @property-read PlanoCurricular $plano
 * @property-read AnoLectivo $anoLectivo
 */
class Matricula extends Model
{
    public const ACTIVA = 'activa';

    public const SUSPENSA = 'suspensa';

    public const ANULADA = 'anulada';

    public const ESTADOS = [
        self::ACTIVA => 'Activa',
        self::SUSPENSA => 'Suspensa',
        self::ANULADA => 'Anulada',
        'transferida' => 'Transferida',
        'concluida' => 'Concluída',
    ];

    /** Regimes do ensino superior e turnos do ensino geral. */
    public const REGIMES = [
        TipoInstituicao::SUPERIOR => ['laboral' => 'Laboral', 'pos_laboral' => 'Pós-laboral'],
        TipoInstituicao::GERAL => ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'],
    ];

    protected $fillable = [
        'numero', 'estudante_id', 'curso_id', 'plano_id', 'ano_lectivo_id', 'ano_curricular', 'regime',
        'estado', 'resultado', 'data', 'motivo', 'criado_por',
    ];

    protected function casts(): array
    {
        return ['ano_curricular' => 'integer', 'data' => 'date'];
    }

    /** @return array<string, string> */
    public static function regimes(): array
    {
        return self::REGIMES[TipoInstituicao::actual()];
    }

    public static function rotuloRegime(?string $regime): ?string
    {
        if ($regime === null) {
            return null;
        }

        return self::REGIMES[TipoInstituicao::SUPERIOR][$regime] ?? self::REGIMES[TipoInstituicao::GERAL][$regime] ?? $regime;
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    /** @return BelongsTo<PlanoCurricular, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoCurricular::class, 'plano_id');
    }

    /** @return BelongsTo<AnoLectivo, $this> */
    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    /** @return HasMany<InscricaoPeriodo, $this> */
    public function inscricoesPeriodo(): HasMany
    {
        return $this->hasMany(InscricaoPeriodo::class, 'matricula_id');
    }

    public function activa(): bool
    {
        return $this->estado === self::ACTIVA;
    }
}

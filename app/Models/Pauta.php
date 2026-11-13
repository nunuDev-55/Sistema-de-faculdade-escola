<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pauta de uma disciplina numa turma, numa época (frequência, exame normal, recorrência).
 * Rascunho → submetida (docente) → validada → publicada (director de curso). Publicada, não se altera (RN-09)
 * e só então os resultados chegam às inscrições e ao portal (RN-16). Muda só por App\Services\PautaService.
 *
 * @property int $id
 * @property string|null $codigo
 * @property int $leccionacao_id
 * @property string $epoca
 * @property string $estado
 * @property string|null $observacao
 * @property Carbon|null $submetida_em
 * @property int|null $submetida_por
 * @property Carbon|null $validada_em
 * @property int|null $validada_por
 * @property Carbon|null $publicada_em
 * @property int|null $publicada_por
 * @property Carbon $updated_at
 * @property-read Leccionacao $leccionacao
 */
class Pauta extends Model
{
    public const FREQUENCIA = 'frequencia';

    public const NORMAL = 'normal';

    public const RECORRENCIA = 'recorrencia';

    public const EPOCAS = [
        self::FREQUENCIA => 'Frequência',
        self::NORMAL => 'Exame normal',
        self::RECORRENCIA => 'Exame de recorrência',
    ];

    public const RASCUNHO = 'rascunho';

    public const SUBMETIDA = 'submetida';

    public const VALIDADA = 'validada';

    public const PUBLICADA = 'publicada';

    public const ESTADOS = [
        self::RASCUNHO => 'Em lançamento',
        self::SUBMETIDA => 'Submetida',
        self::VALIDADA => 'Validada',
        self::PUBLICADA => 'Publicada',
    ];

    public const SITUACOES = [
        'excluido' => 'Excluído',
        'admitido' => 'Admitido a exame',
        'dispensado' => 'Dispensado',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
    ];

    protected $fillable = [
        'codigo', 'leccionacao_id', 'epoca', 'estado', 'observacao', 'submetida_em', 'submetida_por',
        'validada_em', 'validada_por', 'publicada_em', 'publicada_por',
    ];

    protected $attributes = ['estado' => self::RASCUNHO];

    protected function casts(): array
    {
        return ['submetida_em' => 'datetime', 'validada_em' => 'datetime', 'publicada_em' => 'datetime'];
    }

    /** @return BelongsTo<Leccionacao, $this> */
    public function leccionacao(): BelongsTo
    {
        return $this->belongsTo(Leccionacao::class, 'leccionacao_id');
    }

    /** @return HasMany<PautaLinha, $this> */
    public function linhas(): HasMany
    {
        return $this->hasMany(PautaLinha::class, 'pauta_id');
    }

    public function editavel(): bool
    {
        return $this->estado === self::RASCUNHO;
    }
}

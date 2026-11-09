<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ano lectivo; os períodos são gerados por App\Services\AnoLectivoService conforme o tipo de instituição.
 *
 * @property int $id
 * @property string $nome
 * @property Carbon $inicio
 * @property Carbon $fim
 * @property string $estado
 */
class AnoLectivo extends Model
{
    use RegistaAuditoria;

    public const ESTADOS = [
        'planeado' => 'Planeado',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
    ];

    protected $table = 'anos_lectivos';

    protected $fillable = ['nome', 'inicio', 'fim', 'estado'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date'];
    }

    /** @return HasMany<PeriodoLectivo, $this> */
    public function periodos(): HasMany
    {
        return $this->hasMany(PeriodoLectivo::class, 'ano_lectivo_id')->orderBy('numero');
    }

    protected function nomeAuditoria(): string
    {
        return 'calendario';
    }
}

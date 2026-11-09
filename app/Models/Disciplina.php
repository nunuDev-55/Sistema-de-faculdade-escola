<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Disciplina (unidade curricular), com créditos e carga horária.
 *
 * @property int $id
 * @property int|null $unidade_id
 * @property string $codigo
 * @property string $nome
 * @property string $creditos
 * @property int $horas_teoricas
 * @property int $horas_praticas
 * @property bool $activo
 * @property-read int|null $nos_planos_count
 */
class Disciplina extends Model
{
    use RegistaAuditoria;

    protected $fillable = ['unidade_id', 'codigo', 'nome', 'creditos', 'horas_teoricas', 'horas_praticas', 'activo'];

    protected function casts(): array
    {
        return ['creditos' => 'decimal:1', 'activo' => 'boolean', 'horas_teoricas' => 'integer', 'horas_praticas' => 'integer'];
    }

    /** @return BelongsTo<UnidadeOrganica, $this> */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UnidadeOrganica::class, 'unidade_id');
    }

    /** @return HasMany<PlanoDisciplina, $this> */
    public function nosPlanos(): HasMany
    {
        return $this->hasMany(PlanoDisciplina::class, 'disciplina_id');
    }
}

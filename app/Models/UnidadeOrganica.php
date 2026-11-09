<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Faculdade, escola ou departamento, em árvore.
 *
 * @property int $id
 * @property int|null $unidade_pai_id
 * @property string $tipo
 * @property string $codigo
 * @property string $nome
 * @property bool $activo
 * @property-read int|null $cursos_count
 */
class UnidadeOrganica extends Model
{
    use RegistaAuditoria;

    public const TIPOS = [
        'faculdade' => 'Faculdade',
        'escola' => 'Escola',
        'departamento' => 'Departamento',
    ];

    protected $table = 'unidades_organicas';

    protected $fillable = ['unidade_pai_id', 'tipo', 'codigo', 'nome', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unidade_pai_id');
    }

    public function filhas(): HasMany
    {
        return $this->hasMany(self::class, 'unidade_pai_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'unidade_id');
    }
}

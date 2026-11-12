<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Um componente do plano de avaliação de uma disciplina na turma (1.º teste, trabalho...), com o seu peso em %.
 *
 * @property int $id
 * @property int $leccionacao_id
 * @property string $nome
 * @property string $peso
 * @property Carbon|null $data
 * @property int $ordem
 */
class ComponenteAvaliacao extends Model
{
    protected $table = 'componentes_avaliacao';

    protected $fillable = ['leccionacao_id', 'nome', 'peso', 'data', 'ordem'];

    protected function casts(): array
    {
        return ['peso' => 'decimal:2', 'data' => 'date', 'ordem' => 'integer'];
    }

    /** @return BelongsTo<Leccionacao, $this> */
    public function leccionacao(): BelongsTo
    {
        return $this->belongsTo(Leccionacao::class, 'leccionacao_id');
    }

    /** @return HasMany<Nota, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'componente_id');
    }
}

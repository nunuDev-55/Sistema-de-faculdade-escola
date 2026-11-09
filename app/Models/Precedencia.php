<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Num plano, a disciplina só se faz depois de aprovar a precedente (RN-05).
 *
 * @property int $id
 * @property int $plano_id
 * @property int $disciplina_id
 * @property int $precedente_id
 * @property-read Disciplina $disciplina
 * @property-read Disciplina $precedente
 */
class Precedencia extends Model
{
    protected $table = 'precedencias';

    protected $fillable = ['plano_id', 'disciplina_id', 'precedente_id'];

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function precedente(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'precedente_id');
    }
}

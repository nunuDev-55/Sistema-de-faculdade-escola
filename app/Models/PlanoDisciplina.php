<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma disciplina dentro de uma versão do plano: em que ano e período se dá.
 *
 * @property int $id
 * @property int $plano_id
 * @property int $disciplina_id
 * @property int $ano
 * @property int $periodo
 * @property bool $obrigatoria
 * @property bool $nuclear
 * @property-read Disciplina $disciplina
 */
class PlanoDisciplina extends Model
{
    protected $table = 'plano_disciplinas';

    protected $fillable = ['plano_id', 'disciplina_id', 'ano', 'periodo', 'obrigatoria', 'nuclear'];

    protected function casts(): array
    {
        return ['ano' => 'integer', 'periodo' => 'integer', 'obrigatoria' => 'boolean', 'nuclear' => 'boolean'];
    }

    /** @return BelongsTo<PlanoCurricular, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoCurricular::class, 'plano_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }
}

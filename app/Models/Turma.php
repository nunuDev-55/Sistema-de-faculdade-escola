<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turma de um curso e ano (ou classe) num semestre; no ensino geral, no ano lectivo todo (período vazio).
 * As disciplinas que a turma tem são as suas leccionações. Criada e alterada por App\Services\TurmaService.
 *
 * @property int $id
 * @property int $ano_lectivo_id
 * @property int|null $periodo_id
 * @property int $curso_id
 * @property int $ano_curricular
 * @property string $nome
 * @property string|null $regime
 * @property int $capacidade
 * @property int|null $director_id
 * @property-read AnoLectivo $anoLectivo
 * @property-read PeriodoLectivo|null $periodo
 * @property-read Curso $curso
 * @property-read Docente|null $director
 */
class Turma extends Model
{
    protected $fillable = ['ano_lectivo_id', 'periodo_id', 'curso_id', 'ano_curricular', 'nome', 'regime', 'capacidade', 'director_id'];

    protected function casts(): array
    {
        return ['ano_curricular' => 'integer', 'capacidade' => 'integer'];
    }

    /** @return BelongsTo<AnoLectivo, $this> */
    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    /** @return BelongsTo<PeriodoLectivo, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLectivo::class, 'periodo_id');
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    /** @return BelongsTo<Docente, $this> */
    public function director(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'director_id');
    }

    /** @return HasMany<Leccionacao, $this> */
    public function leccionacoes(): HasMany
    {
        return $this->hasMany(Leccionacao::class, 'turma_id');
    }

    /** "LIG 1.º A" ou "8.ª A" */
    public function designacao(): string
    {
        return $this->ano_curricular.'.º '.$this->nome;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma disciplina dada a uma turma por um docente (distribuição de serviço). É onde vão pendurar-se o horário,
 * as aulas, o plano de avaliação e a pauta; os estudantes chegam-lhe pelas suas inscrições.
 *
 * @property int $id
 * @property int $turma_id
 * @property int $disciplina_id
 * @property int|null $docente_id
 * @property-read Turma $turma
 * @property-read Disciplina $disciplina
 * @property-read Docente|null $docente
 * @property-read int|null $inscricoes_count
 */
class Leccionacao extends Model
{
    protected $table = 'leccionacoes';

    protected $fillable = ['turma_id', 'disciplina_id', 'docente_id'];

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }

    /** @return BelongsTo<Docente, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    /**
     * As inscrições dos estudantes nesta disciplina desta turma (só as que não foram anuladas).
     *
     * @return HasMany<Inscricao, $this>
     */
    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class, 'leccionacao_id')->where('estado', '!=', Inscricao::ANULADO);
    }
}

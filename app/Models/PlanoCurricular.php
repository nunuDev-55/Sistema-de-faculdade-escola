<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versão do plano curricular de um curso. Nasce em rascunho; depois de aprovado não se altera (RN-15):
 * uma mudança cria uma versão nova. As alterações passam sempre por App\Services\PlanoCurricularService.
 *
 * @property int $id
 * @property int $curso_id
 * @property string $versao
 * @property string $estado
 * @property string $metodo_avaliacao
 * @property Carbon|null $aprovado_em
 * @property int|null $aprovado_por
 * @property int|null $criado_por
 * @property-read Curso $curso
 * @property-read int|null $linhas_count
 */
class PlanoCurricular extends Model
{
    public const RASCUNHO = 'rascunho';

    public const APROVADO = 'aprovado';

    public const SUBSTITUIDO = 'substituido';

    public const ESTADOS = [
        self::RASCUNHO => 'Rascunho',
        self::APROVADO => 'Em vigor',
        self::SUBSTITUIDO => 'Substituído',
    ];

    public const METODOS = [
        'media_simples' => 'Média simples',
        'media_ponderada' => 'Média ponderada pelos créditos',
    ];

    protected $table = 'planos_curriculares';

    protected $fillable = ['curso_id', 'versao', 'estado', 'metodo_avaliacao', 'aprovado_em', 'aprovado_por', 'criado_por'];

    protected function casts(): array
    {
        return ['aprovado_em' => 'date'];
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    /** @return HasMany<PlanoDisciplina, $this> */
    public function linhas(): HasMany
    {
        return $this->hasMany(PlanoDisciplina::class, 'plano_id');
    }

    /** @return HasMany<Precedencia, $this> */
    public function precedencias(): HasMany
    {
        return $this->hasMany(Precedencia::class, 'plano_id');
    }

    public function editavel(): bool
    {
        return $this->estado === self::RASCUNHO;
    }
}

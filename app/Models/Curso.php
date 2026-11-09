<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Curso (ensino superior) ou nível/ciclo (ensino geral).
 *
 * @property int $id
 * @property int|null $unidade_id
 * @property string $codigo
 * @property string $nome
 * @property string $grau
 * @property int $duracao_anos
 * @property int|null $director_id
 * @property bool $activo
 */
class Curso extends Model
{
    use RegistaAuditoria;

    public const GRAUS = [
        'licenciatura' => 'Licenciatura',
        'mestrado' => 'Mestrado',
        'doutoramento' => 'Doutoramento',
        'tecnico' => 'Técnico-profissional',
        'ensino_primario' => 'Ensino primário',
        'ensino_secundario' => 'Ensino secundário',
    ];

    protected $fillable = ['unidade_id', 'codigo', 'nome', 'grau', 'duracao_anos', 'director_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'duracao_anos' => 'integer'];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UnidadeOrganica::class, 'unidade_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function planos(): HasMany
    {
        return $this->hasMany(PlanoCurricular::class, 'curso_id');
    }

    /** O plano aprovado mais recente: é o que recebe as matrículas novas (cada estudante fica no plano em que entrou). */
    public function planoEmVigor(): ?PlanoCurricular
    {
        return $this->planos()->where('estado', PlanoCurricular::APROVADO)->orderByDesc('aprovado_em')->orderByDesc('id')->first();
    }
}

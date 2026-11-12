<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Docente. A ficha liga-se à conta com que entra no sistema (user_id): é por ela que o docente vê
 * as suas turmas e, mais tarde, lança notas e presenças só nelas (RN-10).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nome
 * @property string|null $numero
 * @property string|null $grau_academico
 * @property string|null $categoria
 * @property string $regime
 * @property int|null $unidade_id
 * @property string|null $telemovel
 * @property string|null $email
 * @property bool $activo
 * @property-read User|null $user
 * @property-read UnidadeOrganica|null $unidade
 * @property-read int|null $leccionacoes_count
 */
class Docente extends Model
{
    use RegistaAuditoria;

    public const GRAUS = [
        'bacharel' => 'Bacharel',
        'licenciado' => 'Licenciado',
        'mestre' => 'Mestre',
        'doutor' => 'Doutor',
    ];

    public const REGIMES = [
        'integral' => 'Tempo inteiro',
        'parcial' => 'Tempo parcial',
    ];

    protected $fillable = ['user_id', 'nome', 'numero', 'grau_academico', 'categoria', 'regime', 'unidade_id', 'telemovel', 'email', 'activo'];

    protected $attributes = ['activo' => true, 'regime' => 'integral'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<UnidadeOrganica, $this> */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UnidadeOrganica::class, 'unidade_id');
    }

    /** @return HasMany<Leccionacao, $this> */
    public function leccionacoes(): HasMany
    {
        return $this->hasMany(Leccionacao::class, 'docente_id');
    }

    protected function nomeAuditoria(): string
    {
        return 'docencia';
    }
}

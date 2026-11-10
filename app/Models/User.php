<?php

namespace App\Models;

use App\Support\Permissoes;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Utilizador da equipa da instituição (direcção, secretaria, docentes, tesouraria, administrador).
 * As contas dos portais (estudante, encarregado, candidato) terão modelos e guarda próprios.
 *
 * Campos da tabela users:
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property-read Carbon|null $email_verified_at
 * @property-write CarbonInterface|string|null $email_verified_at
 * @property string $password
 * @property bool $active
 * @property-read Carbon|null $last_login_at
 * @property-write CarbonInterface|string|null $last_login_at
 * @property string|null $remember_token
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /** Auditoria: regista só os campos que mudaram (nunca a senha). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('utilizadores')
            ->logOnly(['name', 'email', 'phone', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento) => match ($evento) {
                'created' => 'Utilizador criado',
                'updated' => 'Utilizador alterado',
                'deleted' => 'Utilizador apagado',
                default => $evento,
            });
    }

    public function isAdministrador(): bool
    {
        return $this->hasRole(Permissoes::ADMINISTRADOR);
    }

    /** @param Builder<User> $query */
    public function scopeAdministradoresActivos(Builder $query): void
    {
        $query->where('active', true)->role(Permissoes::ADMINISTRADOR);
    }
}

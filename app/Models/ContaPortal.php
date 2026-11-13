<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Conta do estudante no portal (guarda "estudante"). Entra com o número de estudante; nunca abre o sistema interno.
 *
 * @property int $id
 * @property int $estudante_id
 * @property string $password
 * @property string $estado
 * @property Carbon|null $last_login_at
 * @property-read Estudante $estudante
 */
class ContaPortal extends Authenticatable
{
    protected $table = 'contas_portal';

    protected $fillable = ['estudante_id', 'password', 'estado', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }

    public function activa(): bool
    {
        return $this->estado === 'activa';
    }
}

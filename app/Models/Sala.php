<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Sala de aula, laboratório ou anfiteatro, com a lotação (para os horários e os exames).
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $nome
 * @property string $tipo
 * @property int $capacidade
 * @property bool $activo
 */
class Sala extends Model
{
    use RegistaAuditoria;

    public const TIPOS = [
        'aula' => 'Sala de aula',
        'laboratorio' => 'Laboratório',
        'informatica' => 'Sala de informática',
        'anfiteatro' => 'Anfiteatro',
    ];

    protected $fillable = ['codigo', 'nome', 'tipo', 'capacidade', 'activo'];

    protected $attributes = ['activo' => true, 'tipo' => 'aula', 'capacidade' => 40];

    protected function casts(): array
    {
        return ['capacidade' => 'integer', 'activo' => 'boolean'];
    }

    protected function nomeAuditoria(): string
    {
        return 'calendario';
    }
}

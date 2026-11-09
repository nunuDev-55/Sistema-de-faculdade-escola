<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Encarregado de educação. Um encarregado pode ter vários educandos (irmãos): reconhece-se pelo telemóvel.
 *
 * @property int $id
 * @property string $nome
 * @property string|null $documento_numero
 * @property string|null $profissao
 * @property string|null $telemovel
 * @property string|null $email
 * @property string|null $morada
 * @property-read Pivot $pivot
 */
class Encarregado extends Model
{
    use RegistaAuditoria;

    public const PARENTESCOS = [
        'pai' => 'Pai',
        'mae' => 'Mãe',
        'irmao' => 'Irmão / irmã',
        'tio' => 'Tio / tia',
        'avo' => 'Avô / avó',
        'conjuge' => 'Cônjuge',
        'outro' => 'Outro',
    ];

    protected $fillable = ['nome', 'documento_numero', 'profissao', 'telemovel', 'email', 'morada'];

    /** @return BelongsToMany<Estudante, $this> */
    public function estudantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudante::class, 'estudante_encarregado', 'encarregado_id', 'estudante_id')
            ->withPivot(['parentesco', 'principal', 've_financas'])
            ->withTimestamps();
    }

    protected function nomeAuditoria(): string
    {
        return 'estudantes';
    }
}

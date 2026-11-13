<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A nota de um estudante num componente (teste, trabalho). Faltar conta como zero.
 *
 * @property int $id
 * @property int $componente_id
 * @property int $inscricao_id
 * @property string|null $valor
 * @property bool $faltou
 * @property int|null $lancada_por
 */
class Nota extends Model
{
    protected $fillable = ['componente_id', 'inscricao_id', 'valor', 'faltou', 'lancada_por'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'faltou' => 'boolean'];
    }

    /** @return BelongsTo<ComponenteAvaliacao, $this> */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(ComponenteAvaliacao::class, 'componente_id');
    }

    /** @return BelongsTo<Inscricao, $this> */
    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class, 'inscricao_id');
    }
}

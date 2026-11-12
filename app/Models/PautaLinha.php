<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha da pauta: a nota do estudante nessa época (média de frequência ou nota do exame), a nota final e a situação.
 *
 * @property int $id
 * @property int $pauta_id
 * @property int $inscricao_id
 * @property string|null $nota
 * @property bool $faltou
 * @property string|null $nota_final
 * @property string|null $situacao
 * @property-read Inscricao $inscricao
 * @property-read Pauta $pauta
 */
class PautaLinha extends Model
{
    protected $table = 'pauta_linhas';

    protected $fillable = ['pauta_id', 'inscricao_id', 'nota', 'faltou', 'nota_final', 'situacao'];

    protected function casts(): array
    {
        return ['nota' => 'decimal:2', 'nota_final' => 'decimal:2', 'faltou' => 'boolean'];
    }

    /** @return BelongsTo<Pauta, $this> */
    public function pauta(): BelongsTo
    {
        return $this->belongsTo(Pauta::class, 'pauta_id');
    }

    /** @return BelongsTo<Inscricao, $this> */
    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class, 'inscricao_id');
    }
}

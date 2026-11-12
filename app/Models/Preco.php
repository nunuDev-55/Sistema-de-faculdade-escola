<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um preço da tabela do ano lectivo: a taxa de inscrição de cada período ou a propina mensal, para um curso
 * (ou para todos, com o curso vazio). O preço de um curso vale mais do que o geral.
 *
 * @property int $id
 * @property int $ano_lectivo_id
 * @property int|null $curso_id
 * @property string $tipo
 * @property string $valor
 * @property int $prestacoes
 * @property int $dia_vencimento
 * @property-read Curso|null $curso
 */
class Preco extends Model
{
    use RegistaAuditoria;

    public const TIPOS = [
        'inscricao' => 'Taxa de inscrição (por período)',
        'propina' => 'Propina mensal',
    ];

    protected $fillable = ['ano_lectivo_id', 'curso_id', 'tipo', 'valor', 'prestacoes', 'dia_vencimento'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'prestacoes' => 'integer', 'dia_vencimento' => 'integer'];
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    protected function nomeAuditoria(): string
    {
        return 'financas';
    }
}

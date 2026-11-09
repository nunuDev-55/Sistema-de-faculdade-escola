<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Semestre (ensino superior) ou trimestre (ensino geral), com as datas de inscrição e de exames.
 *
 * @property int $id
 * @property int $ano_lectivo_id
 * @property string $tipo
 * @property int $numero
 * @property Carbon $inicio
 * @property Carbon $fim
 * @property Carbon|null $inscricoes_inicio
 * @property Carbon|null $inscricoes_fim
 * @property Carbon|null $exames_inicio
 * @property Carbon|null $exames_fim
 * @property-read AnoLectivo $ano
 */
class PeriodoLectivo extends Model
{
    use RegistaAuditoria;

    protected $table = 'periodos_lectivos';

    protected $fillable = ['ano_lectivo_id', 'tipo', 'numero', 'inicio', 'fim', 'inscricoes_inicio', 'inscricoes_fim', 'exames_inicio', 'exames_fim'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'inicio' => 'date',
            'fim' => 'date',
            'inscricoes_inicio' => 'date',
            'inscricoes_fim' => 'date',
            'exames_inicio' => 'date',
            'exames_fim' => 'date',
        ];
    }

    /** @return BelongsTo<AnoLectivo, $this> */
    public function ano(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class, 'ano_lectivo_id');
    }

    /** "1.º semestre", "2.º trimestre" */
    public function nome(): string
    {
        return $this->numero.'.º '.$this->tipo;
    }

    protected function nomeAuditoria(): string
    {
        return 'calendario';
    }
}

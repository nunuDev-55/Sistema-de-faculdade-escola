<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscrição numa disciplina (docs/06, secção 3). A nota final e o resultado chegam com as pautas;
 * "aprovado" é o que abre as precedências (RN-05).
 *
 * @property int $id
 * @property int $inscricao_periodo_id
 * @property int $estudante_id
 * @property int $disciplina_id
 * @property int|null $leccionacao_id
 * @property string $creditos
 * @property bool $em_atraso
 * @property string $estado
 * @property string|null $nota_final
 * @property Carbon|null $anulada_em
 * @property-read InscricaoPeriodo $inscricaoPeriodo
 * @property-read Disciplina $disciplina
 * @property-read Estudante $estudante
 * @property-read Leccionacao|null $leccionacao
 */
class Inscricao extends Model
{
    public const INSCRITO = 'inscrito';

    public const ANULADO = 'anulado';

    public const APROVADO = 'aprovado';

    public const ESTADOS = [
        self::INSCRITO => 'Inscrito',
        self::ANULADO => 'Anulado',
        self::APROVADO => 'Aprovado',
        'reprovado' => 'Reprovado',
        'excluido' => 'Excluído',
    ];

    protected $table = 'inscricoes';

    protected $fillable = ['inscricao_periodo_id', 'estudante_id', 'disciplina_id', 'leccionacao_id', 'creditos', 'em_atraso', 'estado', 'nota_final', 'anulada_em'];

    protected function casts(): array
    {
        return ['creditos' => 'decimal:1', 'em_atraso' => 'boolean', 'nota_final' => 'decimal:2', 'anulada_em' => 'datetime'];
    }

    /** @return BelongsTo<InscricaoPeriodo, $this> */
    public function inscricaoPeriodo(): BelongsTo
    {
        return $this->belongsTo(InscricaoPeriodo::class, 'inscricao_periodo_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }

    /** @return BelongsTo<Leccionacao, $this> */
    public function leccionacao(): BelongsTo
    {
        return $this->belongsTo(Leccionacao::class, 'leccionacao_id');
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }
}

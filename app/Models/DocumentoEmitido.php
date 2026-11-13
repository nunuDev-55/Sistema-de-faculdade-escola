<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Documento académico emitido: declaração de matrícula ou de frequência, histórico escolar ou certificado.
 * O conteúdo fica congelado (JSON) e assinado no momento da emissão; o código de verificação abre a página pública
 * que confirma a autenticidade (RF-91). Só por App\Services\DocumentoService; anula-se, nunca se apaga.
 *
 * @property int $id
 * @property string $numero
 * @property string $tipo
 * @property int $estudante_id
 * @property int|null $matricula_id
 * @property string|null $finalidade
 * @property string $conteudo
 * @property string $codigo_verificacao
 * @property string $assinatura
 * @property string $estado
 * @property string|null $motivo_anulacao
 * @property int|null $emitido_por
 * @property Carbon $emitido_em
 * @property int|null $anulado_por
 * @property Carbon|null $anulado_em
 * @property-read Estudante $estudante
 * @property-read Matricula|null $matricula
 * @property-read User|null $emitidoPor
 */
class DocumentoEmitido extends Model
{
    public const DECLARACAO_MATRICULA = 'declaracao_matricula';

    public const DECLARACAO_FREQUENCIA = 'declaracao_frequencia';

    public const HISTORICO = 'historico';

    public const CERTIFICADO = 'certificado';

    public const TIPOS = [
        self::DECLARACAO_MATRICULA => 'Declaração de matrícula',
        self::DECLARACAO_FREQUENCIA => 'Declaração de frequência',
        self::HISTORICO => 'Histórico escolar',
        self::CERTIFICADO => 'Certificado de habilitações',
    ];

    /** Os tipos que se referem a uma matrícula (um ano lectivo); o histórico e o certificado olham para o curso todo. */
    public const DE_MATRICULA = [self::DECLARACAO_MATRICULA, self::DECLARACAO_FREQUENCIA];

    protected $table = 'documentos_emitidos';

    protected $fillable = [
        'numero', 'tipo', 'estudante_id', 'matricula_id', 'finalidade', 'conteudo', 'codigo_verificacao', 'assinatura',
        'estado', 'motivo_anulacao', 'emitido_por', 'emitido_em', 'anulado_por', 'anulado_em',
    ];

    protected function casts(): array
    {
        return ['emitido_em' => 'datetime', 'anulado_em' => 'datetime'];
    }

    /** @return BelongsTo<Estudante, $this> */
    public function estudante(): BelongsTo
    {
        return $this->belongsTo(Estudante::class, 'estudante_id');
    }

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return BelongsTo<User, $this> */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /** @return array<string, mixed> */
    public function dados(): array
    {
        $dados = json_decode($this->conteudo, true);

        return is_array($dados) ? $dados : [];
    }

    public function valido(): bool
    {
        return $this->estado === 'valido';
    }

    public function nomeTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** O código como se imprime e se escreve: ABCD-EFGH-JKLM. */
    public function codigoFormatado(): string
    {
        return implode('-', str_split($this->codigo_verificacao, 4));
    }
}

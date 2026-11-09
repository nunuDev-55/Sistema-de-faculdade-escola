<?php

namespace App\Models;

use App\Models\Concerns\RegistaAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Estudante (ou aluno, no ensino geral). O número é gerado uma vez por App\Services\EstudanteService e
 * nunca se reutiliza (RN-08). O percurso fica nas matrículas (uma por ano lectivo) e nas inscrições.
 *
 * @property int $id
 * @property string $numero
 * @property string $nome
 * @property string|null $sexo
 * @property Carbon|null $data_nascimento
 * @property string|null $documento_tipo
 * @property string|null $documento_numero
 * @property string|null $nuit
 * @property string|null $nacionalidade
 * @property string|null $naturalidade
 * @property string|null $provincia
 * @property string|null $morada
 * @property string|null $telemovel
 * @property string|null $email
 * @property string|null $necessidades_especiais
 * @property string $categoria
 * @property string $estado
 * @property string|null $nota_privada
 * @property int|null $criado_por
 * @property Carbon $created_at
 */
class Estudante extends Model
{
    use RegistaAuditoria;

    public const SEXOS = ['M' => 'Masculino', 'F' => 'Feminino'];

    public const DOCUMENTOS = [
        'bi' => 'Bilhete de identidade',
        'passaporte' => 'Passaporte',
        'dire' => 'DIRE',
        'cedula' => 'Cédula pessoal',
        'certidao' => 'Certidão de nascimento',
    ];

    public const CATEGORIAS = [
        'regular' => 'Regular',
        'bolseiro' => 'Bolseiro',
        'funcionario' => 'Funcionário da instituição',
        'estrangeiro' => 'Estrangeiro',
        'transferido' => 'Transferido de outra instituição',
    ];

    public const ESTADOS = [
        'activo' => 'Activo',
        'suspenso' => 'Suspenso',
        'desistente' => 'Desistente',
        'transferido' => 'Transferido',
        'graduado' => 'Graduado',
    ];

    public const PROVINCIAS = [
        'Cabo Delgado', 'Gaza', 'Inhambane', 'Manica', 'Maputo Cidade', 'Maputo Província',
        'Nampula', 'Niassa', 'Sofala', 'Tete', 'Zambézia',
    ];

    protected $fillable = [
        'numero', 'nome', 'sexo', 'data_nascimento', 'documento_tipo', 'documento_numero', 'nuit', 'nacionalidade',
        'naturalidade', 'provincia', 'morada', 'telemovel', 'email', 'necessidades_especiais', 'categoria', 'estado',
        'nota_privada', 'criado_por',
    ];

    protected function casts(): array
    {
        return ['data_nascimento' => 'date'];
    }

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudante_id');
    }

    /** @return HasMany<Inscricao, $this> */
    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class, 'estudante_id');
    }

    /** @return BelongsToMany<Encarregado, $this> */
    public function encarregados(): BelongsToMany
    {
        return $this->belongsToMany(Encarregado::class, 'estudante_encarregado', 'estudante_id', 'encarregado_id')
            ->withPivot(['parentesco', 'principal', 've_financas'])
            ->withTimestamps();
    }

    protected function nomeAuditoria(): string
    {
        return 'estudantes';
    }
}

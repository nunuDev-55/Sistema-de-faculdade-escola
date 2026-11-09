<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\User;
use App\Support\TipoInstituicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula no ano lectivo e as suas mudanças de estado (docs/06-processos-e-estados.md, secção 2).
 *
 * Feita pela secretaria, a matrícula nasce activa; o rascunho fica para a matrícula pedida no portal.
 * O estudante fica no plano em que entrou no curso (RN-15): na renovação usa-se o plano da matrícula
 * anterior no mesmo curso, mesmo que entretanto outra versão tenha entrado em vigor.
 */
class MatriculaService
{
    public function __construct(private Numeracao $numeracao, private InscricaoService $inscricoes) {}

    public function matricular(Estudante $estudante, Curso $curso, AnoLectivo $ano, int $anoCurricular, ?string $regime, ?User $autor): Matricula
    {
        if ($estudante->estado !== 'activo') {
            throw ValidationException::withMessages(['estudante' => "O estudante está {$this->rotuloEstado($estudante)}: não se pode matricular."]);
        }
        if ($ano->estado === 'fechado') {
            throw ValidationException::withMessages(['ano_lectivo_id' => "O ano lectivo {$ano->nome} já está fechado."]);
        }
        if (! $curso->activo) {
            throw ValidationException::withMessages(['curso_id' => "{$curso->nome} está desactivado."]);
        }
        if ($anoCurricular < 1 || $anoCurricular > $curso->duracao_anos) {
            throw ValidationException::withMessages(['ano_curricular' => "{$curso->nome} tem {$curso->duracao_anos} anos: escolha de 1 a {$curso->duracao_anos}."]);
        }
        $jaTem = $estudante->matriculas()->where('ano_lectivo_id', $ano->id)
            ->whereIn('estado', [Matricula::ACTIVA, Matricula::SUSPENSA])->first();
        if ($jaTem !== null) {
            throw ValidationException::withMessages(['ano_lectivo_id' => "O estudante já tem a matrícula {$jaTem->numero} em {$ano->nome}."]);
        }

        $anterior = $estudante->matriculas()->where('curso_id', $curso->id)->where('estado', '!=', Matricula::ANULADA)->latest('id')->first();
        $plano = $anterior !== null ? $anterior->plano : $curso->planoEmVigor();
        if ($plano === null) {
            throw ValidationException::withMessages(['curso_id' => "{$curso->nome} ainda não tem plano curricular em vigor: aprove um plano antes de matricular."]);
        }
        if ($regime !== null && ! array_key_exists($regime, Matricula::regimes())) {
            throw ValidationException::withMessages(['regime' => 'Escolha um regime da lista.']);
        }

        return DB::transaction(function () use ($estudante, $curso, $ano, $anoCurricular, $regime, $autor, $plano) {
            $matricula = $estudante->matriculas()->create([
                'numero' => $this->numeracao->proximo('matricula', (int) $ano->inicio->format('Y')),
                'curso_id' => $curso->id,
                'plano_id' => $plano->id,
                'ano_lectivo_id' => $ano->id,
                'ano_curricular' => $anoCurricular,
                'regime' => $regime,
                'estado' => Matricula::ACTIVA,
                'data' => now()->toDateString(),
                'criado_por' => $autor?->id,
            ]);
            $this->registar($matricula, "Matrícula {$matricula->numero}: {$curso->nome}, {$anoCurricular}.º ano, {$ano->nome}");

            // No ensino geral não há escolha de disciplinas: o aluno fica inscrito em todas as da classe, o ano todo.
            if (! TipoInstituicao::eSuperior()) {
                $this->inscricoes->inscreverNoAno($matricula, $autor);
            }

            return $matricula;
        });
    }

    public function suspender(Matricula $matricula, string $motivo): void
    {
        $this->exigirEstado($matricula, Matricula::ACTIVA, 'suspender');
        DB::transaction(function () use ($matricula, $motivo) {
            $matricula->update(['estado' => Matricula::SUSPENSA, 'motivo' => $motivo]);
            $this->registar($matricula, "Matrícula {$matricula->numero} suspensa", ['motivo' => $motivo]);
        });
    }

    public function reactivar(Matricula $matricula): void
    {
        $this->exigirEstado($matricula, Matricula::SUSPENSA, 'reactivar');
        DB::transaction(function () use ($matricula) {
            $matricula->update(['estado' => Matricula::ACTIVA, 'motivo' => null]);
            $this->registar($matricula, "Matrícula {$matricula->numero} reactivada");
        });
    }

    /** Anula a matrícula e as inscrições que ainda não têm resultado. */
    public function anular(Matricula $matricula, string $motivo): void
    {
        if (! in_array($matricula->estado, [Matricula::ACTIVA, Matricula::SUSPENSA], true)) {
            throw ValidationException::withMessages(['matricula' => 'Só se anula uma matrícula activa ou suspensa.']);
        }

        DB::transaction(function () use ($matricula, $motivo) {
            $periodos = $matricula->inscricoesPeriodo()->pluck('id');
            Inscricao::whereIn('inscricao_periodo_id', $periodos)->where('estado', Inscricao::INSCRITO)
                ->update(['estado' => Inscricao::ANULADO, 'anulada_em' => now()]);
            InscricaoPeriodo::whereIn('id', $periodos)->update(['estado' => InscricaoPeriodo::ANULADA]);
            $matricula->update(['estado' => Matricula::ANULADA, 'motivo' => $motivo]);
            $this->registar($matricula, "Matrícula {$matricula->numero} anulada", ['motivo' => $motivo]);
        });
    }

    private function exigirEstado(Matricula $matricula, string $estado, string $accao): void
    {
        if ($matricula->estado !== $estado) {
            $actual = mb_strtolower(Matricula::ESTADOS[$matricula->estado] ?? $matricula->estado);
            throw ValidationException::withMessages(['matricula' => "A matrícula está {$actual}: não se pode {$accao}."]);
        }
    }

    private function rotuloEstado(Estudante $estudante): string
    {
        return mb_strtolower(Estudante::ESTADOS[$estudante->estado] ?? $estudante->estado);
    }

    /** @param array<string, mixed> $propriedades */
    private function registar(Matricula $matricula, string $descricao, array $propriedades = []): void
    {
        activity('matriculas')->performedOn($matricula)->withProperties($propriedades)->log($descricao);
    }
}

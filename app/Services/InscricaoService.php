<?php

namespace App\Services;

use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\PlanoDisciplina;
use App\Models\Precedencia;
use App\Models\User;
use App\Support\RegrasInscricao;
use App\Support\TipoInstituicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inscrição nas disciplinas (RN-05, docs/06-processos-e-estados.md, secções 3 e 7).
 *
 * Ensino superior: em cada semestre o estudante inscreve-se nas disciplinas desse semestre do seu ano e nas
 * dos anos anteriores que ainda não fez (em atraso), desde que tenha aprovado as precedentes e não passe o
 * limite de créditos. Uma disciplina de 1.º semestre só se faz num 1.º semestre (é quando há turmas); as
 * anuais fazem-se a partir do 1.º semestre. No ensino geral a inscrição é anual, em todas as disciplinas da classe.
 */
class InscricaoService
{
    public const DISPONIVEL = 'disponivel';

    public const BLOQUEADA = 'bloqueada';

    public const APROVADA = 'aprovada';

    public const INSCRITA = 'inscrita';

    public const EM_CURSO = 'em_curso';

    public function __construct(private Numeracao $numeracao) {}

    /**
     * O que o estudante pode fazer neste semestre: cada disciplina do plano que se dá neste semestre, até ao
     * seu ano, com o estado e, se estiver bloqueada, o motivo.
     *
     * @return list<array{linha: PlanoDisciplina, estado: string, em_atraso: bool, motivo: string|null}>
     */
    public function situacao(Matricula $matricula, PeriodoLectivo $periodo): array
    {
        $linhas = $matricula->plano->linhas()->with('disciplina')->orderBy('ano')->orderBy('periodo')->get()
            ->filter(fn (PlanoDisciplina $l) => $l->ano <= $matricula->ano_curricular && $this->dadaNoPeriodo($l, $periodo->numero));
        $precedencias = $matricula->plano->precedencias()->with('precedente')->get()->groupBy('disciplina_id');

        $historico = Inscricao::where('estudante_id', $matricula->estudante_id)->where('estado', '!=', Inscricao::ANULADO)
            ->get(['disciplina_id', 'estado', 'inscricao_periodo_id']);
        $aprovadas = $historico->where('estado', Inscricao::APROVADO)->pluck('disciplina_id')->flip();
        $emCurso = $historico->where('estado', Inscricao::INSCRITO);
        $doPeriodo = $this->inscricaoDoPeriodo($matricula, $periodo);

        $situacao = [];
        foreach ($linhas as $linha) {
            $id = $linha->disciplina_id;
            $motivo = null;
            if ($aprovadas->has($id)) {
                $estado = self::APROVADA;
            } elseif ($doPeriodo !== null && $emCurso->where('inscricao_periodo_id', $doPeriodo->id)->where('disciplina_id', $id)->isNotEmpty()) {
                $estado = self::INSCRITA;
            } elseif ($emCurso->where('disciplina_id', $id)->isNotEmpty()) {
                $estado = self::EM_CURSO;
                $motivo = 'Já está inscrito noutro semestre e ainda não tem resultado.';
            } else {
                $faltam = ($precedencias->get($id) ?? collect())
                    ->reject(fn (Precedencia $p) => $aprovadas->has($p->precedente_id))
                    ->map(fn (Precedencia $p) => $p->precedente->nome);
                $estado = $faltam->isEmpty() ? self::DISPONIVEL : self::BLOQUEADA;
                $motivo = $faltam->isEmpty() ? null : 'Falta aprovar '.$faltam->join(', ', ' e ').'.';
            }
            $situacao[] = ['linha' => $linha, 'estado' => $estado, 'em_atraso' => $linha->ano < $matricula->ano_curricular, 'motivo' => $motivo];
        }

        return $situacao;
    }

    /**
     * Inscreve nas disciplinas escolhidas (acrescenta, se já houver inscrição neste semestre).
     *
     * @param  list<int>  $disciplinas
     */
    public function inscrever(Matricula $matricula, PeriodoLectivo $periodo, array $disciplinas, ?User $autor): InscricaoPeriodo
    {
        if (! TipoInstituicao::eSuperior()) {
            throw ValidationException::withMessages(['disciplinas' => 'No ensino geral a inscrição faz-se na matrícula, em todas as disciplinas da classe.']);
        }
        if (! $matricula->activa()) {
            throw ValidationException::withMessages(['matricula' => "A matrícula {$matricula->numero} não está activa: não se pode inscrever."]);
        }
        abort_if($periodo->ano_lectivo_id !== $matricula->ano_lectivo_id, 404);
        if ($disciplinas === []) {
            throw ValidationException::withMessages(['disciplinas' => 'Escolha pelo menos uma disciplina.']);
        }

        $situacao = collect($this->situacao($matricula, $periodo))->keyBy(fn (array $s) => $s['linha']->disciplina_id);
        $erros = [];
        $novas = [];
        foreach (array_unique($disciplinas) as $id) {
            $s = $situacao->get($id);
            if ($s === null) {
                $erros[] = 'Uma das disciplinas escolhidas não se dá no '.$periodo->nome().' do plano do estudante.';
            } elseif ($s['estado'] !== self::DISPONIVEL) {
                $erros[] = $s['linha']->disciplina->nome.': '.($s['motivo'] ?? $this->rotulo($s['estado']));
            } else {
                $novas[] = $s;
            }
        }
        if ($erros !== []) {
            throw ValidationException::withMessages(['disciplinas' => implode(' ', $erros)]);
        }

        $limite = RegrasInscricao::limiteCreditos();
        $total = $this->creditosInscritos($matricula, $periodo)
            + array_sum(array_map(fn (array $s) => (float) $s['linha']->disciplina->creditos, $novas));
        if ($total > $limite + 0.001) {
            throw ValidationException::withMessages(['disciplinas' => 'Com estas disciplinas ficaria com '.self::creditos($total).' créditos no '
                .$periodo->nome().'; o limite é '.self::creditos($limite).'.']);
        }

        return DB::transaction(function () use ($matricula, $periodo, $novas, $autor) {
            $inscricao = $this->abrir($matricula, $periodo, $autor);
            foreach ($novas as $s) {
                $this->acrescentar($inscricao, $s['linha'], $s['em_atraso']);
            }
            $codigos = implode(', ', array_map(fn (array $s) => $s['linha']->disciplina->codigo, $novas));
            $this->registar($inscricao, "Inscrição {$inscricao->codigo} no {$periodo->nome()}: {$codigos}");

            return $inscricao;
        });
    }

    /** Ensino geral: inscrição anual em todas as disciplinas da classe (chamada na matrícula). */
    public function inscreverNoAno(Matricula $matricula, ?User $autor): InscricaoPeriodo
    {
        $linhas = $matricula->plano->linhas()->with('disciplina')->where('ano', $matricula->ano_curricular)->get();
        if ($linhas->isEmpty()) {
            throw ValidationException::withMessages(['ano_curricular' => "O plano {$matricula->plano->versao} não tem disciplinas da {$matricula->ano_curricular}.ª classe."]);
        }

        return DB::transaction(function () use ($matricula, $linhas, $autor) {
            $inscricao = $this->abrir($matricula, null, $autor);
            foreach ($linhas as $linha) {
                $this->acrescentar($inscricao, $linha, false);
            }
            $this->registar($inscricao, "Inscrição {$inscricao->codigo} em {$linhas->count()} disciplinas da {$matricula->ano_curricular}.ª classe");

            return $inscricao;
        });
    }

    /** Anula a inscrição numa disciplina que ainda não tem resultado. */
    public function anularDisciplina(Inscricao $inscricao): void
    {
        if ($inscricao->estado !== Inscricao::INSCRITO) {
            throw ValidationException::withMessages(['inscricao' => 'Só se anula uma inscrição que ainda não tem resultado.']);
        }

        DB::transaction(function () use ($inscricao) {
            $inscricao->update(['estado' => Inscricao::ANULADO, 'anulada_em' => now()]);
            $periodo = $inscricao->inscricaoPeriodo;
            if (! $periodo->inscricoes()->where('estado', '!=', Inscricao::ANULADO)->exists()) {
                $periodo->update(['estado' => InscricaoPeriodo::ANULADA]);
            }
            $this->registar($periodo, "Inscrição em {$inscricao->disciplina->codigo} anulada");
        });
    }

    /** Créditos em que a matrícula já está inscrita neste semestre. */
    public function creditosInscritos(Matricula $matricula, PeriodoLectivo $periodo): float
    {
        $inscricao = $this->inscricaoDoPeriodo($matricula, $periodo);

        return $inscricao === null ? 0.0 : (float) $inscricao->inscricoes()->where('estado', Inscricao::INSCRITO)->sum('creditos');
    }

    public function inscricaoDoPeriodo(Matricula $matricula, ?PeriodoLectivo $periodo): ?InscricaoPeriodo
    {
        return $matricula->inscricoesPeriodo()->where('periodo_id', $periodo?->id)->first();
    }

    /** 4.5 → "4,5"; 6.0 → "6" */
    public static function creditos(float $valor): string
    {
        return str_replace('.', ',', (string) round($valor, 1));
    }

    public function rotulo(string $estado): string
    {
        return [
            self::DISPONIVEL => 'Pode inscrever-se',
            self::BLOQUEADA => 'Bloqueada',
            self::APROVADA => 'Já aprovou',
            self::INSCRITA => 'Já está inscrito',
            self::EM_CURSO => 'Em curso',
        ][$estado] ?? $estado;
    }

    /** Uma disciplina de 1.º semestre só se dá em 1.º semestre; as anuais (período 0) a partir do 1.º. */
    private function dadaNoPeriodo(PlanoDisciplina $linha, int $numero): bool
    {
        return $linha->periodo === $numero || ($linha->periodo === 0 && $numero === 1);
    }

    /** A inscrição do semestre (cria-a com o número do comprovativo, ou reabre-a se tinha sido anulada). */
    private function abrir(Matricula $matricula, ?PeriodoLectivo $periodo, ?User $autor): InscricaoPeriodo
    {
        $inscricao = $this->inscricaoDoPeriodo($matricula, $periodo);
        if ($inscricao === null) {
            return $matricula->inscricoesPeriodo()->create([
                'codigo' => $this->numeracao->proximo('inscricao', (int) $matricula->anoLectivo->inicio->format('Y')),
                'periodo_id' => $periodo?->id,
                'estado' => InscricaoPeriodo::CONFIRMADA,
                'confirmada_em' => now(),
                'criado_por' => $autor?->id,
            ]);
        }
        if ($inscricao->estado === InscricaoPeriodo::ANULADA) {
            $inscricao->update(['estado' => InscricaoPeriodo::CONFIRMADA, 'confirmada_em' => now()]);
        }

        return $inscricao;
    }

    private function acrescentar(InscricaoPeriodo $inscricao, PlanoDisciplina $linha, bool $emAtraso): void
    {
        // Uma disciplina anulada neste semestre volta a ficar inscrita (a linha é a mesma).
        $inscricao->inscricoes()->updateOrCreate(
            ['disciplina_id' => $linha->disciplina_id],
            [
                'estudante_id' => $inscricao->matricula->estudante_id,
                'creditos' => $linha->disciplina->creditos,
                'em_atraso' => $emAtraso,
                'estado' => Inscricao::INSCRITO,
                'anulada_em' => null,
            ],
        );
    }

    /** @param array<string, mixed> $propriedades */
    private function registar(InscricaoPeriodo $inscricao, string $descricao, array $propriedades = []): void
    {
        activity('inscricoes')->performedOn($inscricao)->withProperties($propriedades)->log($descricao);
    }
}

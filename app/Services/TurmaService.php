<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Leccionacao;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\PlanoDisciplina;
use App\Models\Turma;
use App\Support\TipoInstituicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turmas e distribuição de serviço (RF-43, RF-51).
 *
 * A turma nasce com as disciplinas (leccionações) que os seus estudantes fazem nesse semestre: as do plano em
 * vigor e as dos planos em que estão os estudantes já matriculados. Os estudantes distribuem-se por ordem
 * alfabética até à lotação de cada turma do mesmo curso, ano e regime; uma disciplina em atraso fica numa
 * turma onde essa disciplina é dada.
 */
class TurmaService
{
    public function criar(AnoLectivo $ano, ?PeriodoLectivo $periodo, Curso $curso, int $anoCurricular, string $nome, ?string $regime, int $capacidade): Turma
    {
        $superior = TipoInstituicao::eSuperior();
        if ($superior && $periodo === null) {
            throw ValidationException::withMessages(['periodo_id' => 'Escolha o semestre da turma.']);
        }
        if ($periodo !== null && $periodo->ano_lectivo_id !== $ano->id) {
            throw ValidationException::withMessages(['periodo_id' => "Esse período não é do ano lectivo {$ano->nome}."]);
        }
        if (! $superior) {
            $periodo = null; // no ensino geral a turma é do ano todo
        }
        if ($anoCurricular < 1 || $anoCurricular > $curso->duracao_anos) {
            throw ValidationException::withMessages(['ano_curricular' => "{$curso->nome} tem {$curso->duracao_anos} anos: escolha de 1 a {$curso->duracao_anos}."]);
        }
        if ($regime !== null && ! array_key_exists($regime, Matricula::regimes())) {
            throw ValidationException::withMessages(['regime' => 'Escolha um regime da lista.']);
        }
        $existe = Turma::where('ano_lectivo_id', $ano->id)->where('periodo_id', $periodo?->id)
            ->where('curso_id', $curso->id)->where('ano_curricular', $anoCurricular)->where('nome', $nome)->exists();
        if ($existe) {
            throw ValidationException::withMessages(['nome' => "Já existe a turma {$anoCurricular}.º {$nome} deste curso neste período."]);
        }

        $linhas = $this->disciplinasDoPeriodo($ano, $periodo, $curso, $anoCurricular);
        if ($linhas->isEmpty()) {
            throw ValidationException::withMessages(['curso_id' => "{$curso->nome} não tem disciplinas do {$anoCurricular}.º ano neste período (veja o plano curricular em vigor)."]);
        }

        return DB::transaction(function () use ($ano, $periodo, $curso, $anoCurricular, $nome, $regime, $capacidade, $linhas) {
            $turma = Turma::create([
                'ano_lectivo_id' => $ano->id,
                'periodo_id' => $periodo?->id,
                'curso_id' => $curso->id,
                'ano_curricular' => $anoCurricular,
                'nome' => $nome,
                'regime' => $regime,
                'capacidade' => $capacidade,
            ]);
            foreach ($linhas as $disciplinaId) {
                $turma->leccionacoes()->create(['disciplina_id' => $disciplinaId]);
            }
            $this->registar($turma, "Turma {$turma->designacao()} de {$curso->nome} criada com {$linhas->count()} disciplinas");

            return $turma;
        });
    }

    public function alterar(Turma $turma, string $nome, ?string $regime, int $capacidade, ?Docente $director): void
    {
        $existe = Turma::where('ano_lectivo_id', $turma->ano_lectivo_id)->where('periodo_id', $turma->periodo_id)
            ->where('curso_id', $turma->curso_id)->where('ano_curricular', $turma->ano_curricular)
            ->where('nome', $nome)->where('id', '!=', $turma->id)->exists();
        if ($existe) {
            throw ValidationException::withMessages(['nome' => "Já existe a turma {$turma->ano_curricular}.º {$nome} deste curso neste período."]);
        }
        if ($regime !== null && ! array_key_exists($regime, Matricula::regimes())) {
            throw ValidationException::withMessages(['regime' => 'Escolha um regime da lista.']);
        }
        $ocupados = $this->ocupacao(collect([$turma]))->get($turma->id, 0);
        if ($capacidade < $ocupados) {
            throw ValidationException::withMessages(['capacidade' => "A turma já tem {$ocupados} estudantes: a lotação não pode ser menor."]);
        }

        DB::transaction(function () use ($turma, $nome, $regime, $capacidade, $director) {
            $turma->update(['nome' => $nome, 'regime' => $regime, 'capacidade' => $capacidade, 'director_id' => $director?->id]);
            $this->registar($turma, "Turma {$turma->designacao()} alterada");
        });
    }

    /** Só se apaga uma turma sem estudantes. */
    public function apagar(Turma $turma): void
    {
        if (Inscricao::whereIn('leccionacao_id', $turma->leccionacoes()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['turma' => 'A turma já tem estudantes: mude-os de turma antes de a apagar.']);
        }
        DB::transaction(function () use ($turma) {
            $designacao = $turma->designacao();
            $turma->leccionacoes()->delete();
            $turma->delete();
            activity('docencia')->withProperties(['turma' => $designacao])->log("Turma {$designacao} apagada");
        });
    }

    public function acrescentarDisciplina(Turma $turma, Disciplina $disciplina): Leccionacao
    {
        if ($turma->leccionacoes()->where('disciplina_id', $disciplina->id)->exists()) {
            throw ValidationException::withMessages(['disciplina_id' => "{$disciplina->nome} já está nesta turma."]);
        }

        return DB::transaction(function () use ($turma, $disciplina) {
            $leccionacao = $turma->leccionacoes()->create(['disciplina_id' => $disciplina->id]);
            $this->registar($turma, "Disciplina {$disciplina->codigo} acrescentada à turma {$turma->designacao()}");

            return $leccionacao;
        });
    }

    /** Distribuição de serviço: o docente que dá esta disciplina nesta turma (ou ninguém, por atribuir). */
    public function atribuirDocente(Leccionacao $leccionacao, ?Docente $docente): void
    {
        if ($docente !== null && ! $docente->activo) {
            throw ValidationException::withMessages(['docente_id' => "{$docente->nome} está inactivo."]);
        }
        DB::transaction(function () use ($leccionacao, $docente) {
            $leccionacao->update(['docente_id' => $docente?->id]);
            $disciplina = $leccionacao->disciplina->codigo;
            $this->registar($leccionacao->turma, $docente === null
                ? "Disciplina {$disciplina} da turma {$leccionacao->turma->designacao()} fica por atribuir"
                : "{$docente->nome} dá {$disciplina} na turma {$leccionacao->turma->designacao()}");
        });
    }

    /**
     * Coloca nas turmas do grupo (mesmo ano, período, curso, ano curricular e regime) os estudantes inscritos que
     * ainda não têm turma: por ordem alfabética, em blocos iguais (a turma A fica com os primeiros nomes, a B com os
     * seguintes), sem passar a lotação. Quem já tem turma fica onde está e recebe nela as disciplinas que ainda não
     * tinham turma. Pode repetir-se: só mexe em quem falta colocar.
     *
     * @return array{colocados: int, sem_lugar: int}
     */
    public function distribuir(Turma $turma): array
    {
        return DB::transaction(function () use ($turma) {
            $grupo = $this->grupo($turma)->with('leccionacoes')->orderBy('nome')->get();
            $ocupacao = $this->ocupacao($grupo)->all();
            $porColocar = [];
            foreach ($this->inscricoesDoGrupo($turma) as $inscricao) {
                $actual = $this->turmaDe($inscricao, $grupo);
                if ($actual === null) {
                    $porColocar[] = $inscricao;
                } else {
                    $this->colocar($inscricao, $actual);
                }
            }

            // Cada turma enche até à sua parte do total (ou à lotação, se for menor)
            $alvo = (int) ceil((array_sum($ocupacao) + count($porColocar)) / max(1, $grupo->count()));
            $colocados = 0;
            $semLugar = 0;
            foreach ($porColocar as $inscricao) {
                $destino = $grupo->first(fn (Turma $t) => ($ocupacao[$t->id] ?? 0) < min($t->capacidade, $alvo))
                    ?? $grupo->first(fn (Turma $t) => ($ocupacao[$t->id] ?? 0) < $t->capacidade);
                if ($destino === null) {
                    $semLugar++;

                    continue;
                }
                $ocupacao[$destino->id] = ($ocupacao[$destino->id] ?? 0) + 1;
                $this->colocar($inscricao, $destino);
                $colocados++;
            }
            $this->registar($turma, "Distribuição dos estudantes do {$turma->ano_curricular}.º ano: {$colocados} colocados"
                .($semLugar > 0 ? ", {$semLugar} sem lugar" : ''));

            return ['colocados' => $colocados, 'sem_lugar' => $semLugar];
        });
    }

    /** Passa o estudante para outra turma do mesmo grupo (as disciplinas em atraso ficam onde estão). */
    public function mudarDeTurma(InscricaoPeriodo $inscricao, Turma $destino): void
    {
        $matricula = $inscricao->matricula;
        if ($matricula->ano_lectivo_id !== $destino->ano_lectivo_id || $matricula->curso_id !== $destino->curso_id
            || $matricula->ano_curricular !== $destino->ano_curricular || $inscricao->periodo_id !== $destino->periodo_id) {
            throw ValidationException::withMessages(['turma_id' => 'Essa turma não é do curso, ano e período do estudante.']);
        }
        $ocupados = $this->ocupacao(collect([$destino]))->get($destino->id, 0);
        $jaEsta = $inscricao->inscricoes()->whereIn('leccionacao_id', $destino->leccionacoes()->pluck('id'))->exists();
        if (! $jaEsta && $ocupados >= $destino->capacidade) {
            throw ValidationException::withMessages(['turma_id' => "A turma {$destino->designacao()} está cheia ({$destino->capacidade} estudantes)."]);
        }

        DB::transaction(function () use ($inscricao, $destino, $matricula) {
            $destino->load('leccionacoes');
            foreach ($inscricao->inscricoes()->where('estado', '!=', Inscricao::ANULADO)->where('em_atraso', false)->get() as $linha) {
                $leccionacao = $destino->leccionacoes->firstWhere('disciplina_id', $linha->disciplina_id);
                $linha->update(['leccionacao_id' => $leccionacao?->id]);
            }
            $this->registar($destino, "{$matricula->estudante->nome} passou para a turma {$destino->designacao()}");
        });
    }

    /**
     * Quantos estudantes tem cada turma (contam só as disciplinas do seu ano, não as em atraso).
     *
     * @param  Collection<int, Turma>  $turmas
     * @return Collection<array-key, int>
     */
    public function ocupacao(Collection $turmas): Collection
    {
        if ($turmas->isEmpty()) {
            return collect();
        }

        return Inscricao::query()
            ->join('leccionacoes', 'leccionacoes.id', '=', 'inscricoes.leccionacao_id')
            ->whereIn('leccionacoes.turma_id', $turmas->pluck('id'))
            ->where('inscricoes.em_atraso', false)
            ->where('inscricoes.estado', '!=', Inscricao::ANULADO)
            ->groupBy('leccionacoes.turma_id')
            ->selectRaw('leccionacoes.turma_id as turma, count(distinct inscricoes.estudante_id) as total')
            ->pluck('total', 'turma')
            ->map(fn ($n) => (int) $n);
    }

    /**
     * As inscrições (por semestre) que pertencem a este grupo de turmas, por ordem alfabética do estudante.
     *
     * @return Collection<int, InscricaoPeriodo>
     */
    public function inscricoesDoGrupo(Turma $turma): Collection
    {
        return InscricaoPeriodo::query()
            ->where('inscricoes_periodo.estado', InscricaoPeriodo::CONFIRMADA)
            ->where('inscricoes_periodo.periodo_id', $turma->periodo_id)
            ->whereHas('matricula', fn (Builder $m) => $m->where('ano_lectivo_id', $turma->ano_lectivo_id)
                ->where('curso_id', $turma->curso_id)
                ->where('ano_curricular', $turma->ano_curricular)
                ->where('estado', Matricula::ACTIVA)
                ->when($turma->regime !== null, fn (Builder $q) => $q->where('regime', $turma->regime)))
            ->with(['matricula.estudante', 'inscricoes' => fn ($q) => $q->where('estado', '!=', Inscricao::ANULADO)])
            ->get()
            ->sortBy(fn (InscricaoPeriodo $i) => mb_strtolower($i->matricula->estudante->nome))
            ->values();
    }

    /** Quantos estudantes inscritos do grupo desta turma ainda não estão em nenhuma das suas turmas. */
    public function semTurma(Turma $turma): int
    {
        $grupo = $this->grupo($turma)->with('leccionacoes')->get();

        return $this->inscricoesDoGrupo($turma)->filter(fn (InscricaoPeriodo $i) => $this->turmaDe($i, $grupo) === null)->count();
    }

    /** @return Builder<Turma> */
    public function grupo(Turma $turma): Builder
    {
        return Turma::query()
            ->where('ano_lectivo_id', $turma->ano_lectivo_id)
            ->where('periodo_id', $turma->periodo_id)
            ->where('curso_id', $turma->curso_id)
            ->where('ano_curricular', $turma->ano_curricular)
            ->where('regime', $turma->regime);
    }

    /**
     * A turma em que o estudante já está, pelas disciplinas do seu ano.
     *
     * @param  Collection<int, Turma>  $grupo
     */
    private function turmaDe(InscricaoPeriodo $inscricao, Collection $grupo): ?Turma
    {
        foreach ($inscricao->inscricoes as $linha) {
            if ($linha->em_atraso || $linha->leccionacao_id === null) {
                continue;
            }
            $turma = $grupo->first(fn (Turma $t) => $t->leccionacoes->contains('id', $linha->leccionacao_id));
            if ($turma !== null) {
                return $turma;
            }
        }

        return null;
    }

    /** Dá turma às disciplinas do estudante que ainda não a têm. */
    private function colocar(InscricaoPeriodo $inscricao, Turma $turma): void
    {
        foreach ($inscricao->inscricoes as $linha) {
            if ($linha->leccionacao_id !== null) {
                continue;
            }
            $leccionacao = $linha->em_atraso
                ? $this->leccionacaoParaAtraso($linha, $turma)
                : $turma->leccionacoes->firstWhere('disciplina_id', $linha->disciplina_id);
            if ($leccionacao !== null) {
                $linha->update(['leccionacao_id' => $leccionacao->id]);
            }
        }
    }

    /** Uma disciplina em atraso vai para a turma (do mesmo período e curso) onde ela é dada, a menos cheia. */
    private function leccionacaoParaAtraso(Inscricao $linha, Turma $turma): ?Leccionacao
    {
        return Leccionacao::query()
            ->where('disciplina_id', $linha->disciplina_id)
            ->whereHas('turma', fn (Builder $t) => $t->where('ano_lectivo_id', $turma->ano_lectivo_id)
                ->where('periodo_id', $turma->periodo_id)->where('curso_id', $turma->curso_id))
            ->withCount('inscricoes')
            ->orderBy('inscricoes_count')
            ->orderBy('id')
            ->first();
    }

    /**
     * As disciplinas que se dão neste período ao ano curricular: as do plano em vigor e as dos planos em que
     * estão os estudantes já matriculados nesse curso e ano.
     *
     * @return Collection<array-key, mixed>
     */
    private function disciplinasDoPeriodo(AnoLectivo $ano, ?PeriodoLectivo $periodo, Curso $curso, int $anoCurricular): Collection
    {
        $planos = Matricula::where('ano_lectivo_id', $ano->id)->where('curso_id', $curso->id)
            ->where('ano_curricular', $anoCurricular)->where('estado', Matricula::ACTIVA)
            ->distinct()->pluck('plano_id');
        $vigor = $curso->planoEmVigor();
        if ($vigor !== null) {
            $planos->push($vigor->id);
        }

        return PlanoDisciplina::query()
            ->whereIn('plano_id', $planos->unique()->values())
            ->where('ano', $anoCurricular)
            ->when($periodo !== null, fn (Builder $q) => $q->where(fn (Builder $p) => $periodo?->numero === 1
                ? $p->whereIn('periodo', [0, 1])
                : $p->where('periodo', $periodo?->numero)))
            ->distinct()
            ->pluck('disciplina_id')
            ->values();
    }

    private function registar(Turma $turma, string $descricao): void
    {
        activity('docencia')->performedOn($turma)->log($descricao);
    }
}

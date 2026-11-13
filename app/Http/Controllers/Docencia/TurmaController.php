<?php

namespace App\Http\Controllers\Docencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docencia\TurmaRequest;
use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Leccionacao;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\Turma;
use App\Services\TurmaService;
use App\Support\Opcoes;
use App\Support\TipoInstituicao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** Turmas do semestre (ou do ano, no ensino geral), as suas disciplinas e docentes, e os estudantes. */
class TurmaController extends Controller
{
    public function __construct(private readonly TurmaService $servico) {}

    public function index(Request $request): Response
    {
        $anos = AnoLectivo::with('periodos')->orderByDesc('inicio')->get();
        $omissao = AnoLectivo::emCurso();
        $ano = $anos->firstWhere('id', (int) $request->query('ano', (string) ($omissao->id ?? 0))) ?? $anos->first();
        $superior = TipoInstituicao::eSuperior();
        $periodo = null;
        if ($ano !== null && $superior) {
            $periodo = $ano->periodos->firstWhere('id', (int) $request->query('periodo', '0')) ?? $ano->periodoEmCurso() ?? $ano->periodos->first();
        }
        $curso = (int) $request->query('curso', 0);

        $turmas = $ano === null ? collect() : Turma::query()
            ->with(['curso', 'director', 'leccionacoes'])
            ->where('ano_lectivo_id', $ano->id)
            ->where('periodo_id', $periodo?->id)
            ->when($curso > 0, fn ($q) => $q->where('curso_id', $curso))
            ->orderBy('curso_id')->orderBy('ano_curricular')->orderBy('nome')
            ->get();
        $ocupacao = $this->servico->ocupacao($turmas);

        return Inertia::render('docencia/turmas', [
            'turmas' => $turmas->map(fn (Turma $t) => [
                'id' => $t->id,
                'designacao' => $t->designacao(),
                'curso' => $t->curso->nome,
                'ano_curricular' => $t->ano_curricular,
                'nome' => $t->nome,
                'regime' => Matricula::rotuloRegime($t->regime),
                'capacidade' => $t->capacidade,
                'estudantes' => $ocupacao->get($t->id, 0),
                'disciplinas' => $t->leccionacoes->count(),
                'por_atribuir' => $t->leccionacoes->whereNull('docente_id')->count(),
                'director' => $t->director?->nome,
            ])->values(),
            'filtros' => [
                'ano' => $ano !== null ? (string) $ano->id : '',
                'periodo' => $periodo !== null ? (string) $periodo->id : '',
                'curso' => $curso > 0 ? (string) $curso : '',
            ],
            'anos' => $anos->map(fn (AnoLectivo $a) => [
                'valor' => (string) $a->id,
                'rotulo' => $a->nome,
                'periodos' => $a->periodos->map(fn (PeriodoLectivo $p) => ['valor' => (string) $p->id, 'rotulo' => $p->nome()])->values(),
            ])->values(),
            'cursos' => Curso::where('activo', true)->orderBy('nome')->get()
                ->map(fn (Curso $c) => ['valor' => (string) $c->id, 'rotulo' => $c->nome, 'duracao' => $c->duracao_anos])->values(),
            'regimes' => Opcoes::de(Matricula::regimes()),
            'superior' => $superior,
        ]);
    }

    public function store(TurmaRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $periodo = isset($v['periodo_id']) ? PeriodoLectivo::find((int) $v['periodo_id']) : null;
        $turma = $this->servico->criar(
            AnoLectivo::findOrFail((int) $v['ano_lectivo_id']),
            $periodo,
            Curso::findOrFail((int) $v['curso_id']),
            (int) $v['ano_curricular'],
            (string) $v['nome'],
            ($v['regime'] ?? null) ?: null,
            (int) $v['capacidade'],
        );

        return to_route('turmas.show', $turma)->with('sucesso', "Turma {$turma->designacao()} criada com as disciplinas do plano. Atribua agora os docentes e distribua os estudantes.");
    }

    public function show(Turma $turma): Response
    {
        $turma->load(['curso', 'anoLectivo', 'periodo', 'director', 'leccionacoes' => fn ($q) => $q->with(['disciplina', 'docente'])->withCount('inscricoes')]);
        $ids = $turma->leccionacoes->pluck('id');
        $linhas = Inscricao::with(['estudante', 'inscricaoPeriodo.matricula'])->whereIn('leccionacao_id', $ids)
            ->where('estado', '!=', Inscricao::ANULADO)->get();
        $estudantes = $linhas->groupBy('estudante_id')->map(function (Collection $das) {
            /** @var Inscricao $primeira */
            $primeira = $das->sortBy('em_atraso')->first();

            return [
                'id' => $primeira->estudante->id,
                'numero' => $primeira->estudante->numero,
                'nome' => $primeira->estudante->nome,
                'inscricao_periodo_id' => $primeira->inscricao_periodo_id,
                'matricula' => $primeira->inscricaoPeriodo->matricula->numero,
                'ano_curricular' => $primeira->inscricaoPeriodo->matricula->ano_curricular,
                'em_atraso' => $das->every(fn (Inscricao $i) => $i->em_atraso),
                'disciplinas' => $das->count(),
            ];
        })->sortBy(fn (array $e) => [$e['em_atraso'], mb_strtolower($e['nome'])])->values();

        $grupo = $this->servico->grupo($turma)->where('id', '!=', $turma->id)->orderBy('nome')->get();
        $ocupacaoGrupo = $this->servico->ocupacao($grupo);
        $naTurma = $turma->leccionacoes->pluck('disciplina_id')->all();

        return Inertia::render('docencia/turma', [
            'turma' => [
                'id' => $turma->id,
                'designacao' => $turma->designacao(),
                'nome' => $turma->nome,
                'curso' => $turma->curso->nome,
                'ano_curricular' => $turma->ano_curricular,
                'ano_lectivo' => $turma->anoLectivo->nome,
                'periodo' => $turma->periodo?->nome(),
                'regime' => $turma->regime,
                'regime_rotulo' => Matricula::rotuloRegime($turma->regime),
                'capacidade' => $turma->capacidade,
                'director_id' => $turma->director_id,
                'director' => $turma->director?->nome,
                'ocupados' => $estudantes->where('em_atraso', false)->count(),
                'sem_turma' => $this->servico->semTurma($turma),
            ],
            'leccionacoes' => $turma->leccionacoes->sortBy(fn (Leccionacao $l) => $l->disciplina->nome)->map(fn (Leccionacao $l) => [
                'id' => $l->id,
                'codigo' => $l->disciplina->codigo,
                'disciplina' => $l->disciplina->nome,
                'creditos' => (float) $l->disciplina->creditos,
                'docente_id' => $l->docente_id,
                'docente' => $l->docente?->nome,
                'inscritos' => (int) $l->inscricoes_count,
            ])->values(),
            'estudantes' => $estudantes,
            'outrasTurmas' => $grupo->map(fn (Turma $t) => [
                'valor' => (string) $t->id,
                'rotulo' => $t->designacao().' ('.$ocupacaoGrupo->get($t->id, 0).' de '.$t->capacidade.')',
            ])->values(),
            'docentes' => Docente::where('activo', true)->orderBy('nome')->get()
                ->map(fn (Docente $d) => ['valor' => (string) $d->id, 'rotulo' => $d->nome])->values(),
            'disciplinas' => Disciplina::where('activo', true)->whereNotIn('id', $naTurma)->orderBy('nome')->get()
                ->map(fn (Disciplina $d) => ['valor' => (string) $d->id, 'rotulo' => "{$d->codigo} · {$d->nome}"])->values(),
            'regimes' => Opcoes::de(Matricula::regimes()),
        ]);
    }

    public function update(TurmaRequest $request, Turma $turma): RedirectResponse
    {
        $v = $request->validated();
        $this->servico->alterar(
            $turma,
            (string) $v['nome'],
            ($v['regime'] ?? null) ?: null,
            (int) $v['capacidade'],
            isset($v['director_id']) ? Docente::find((int) $v['director_id']) : null,
        );

        return back()->with('sucesso', 'Turma guardada.');
    }

    public function destroy(Turma $turma): RedirectResponse
    {
        $this->servico->apagar($turma);

        return to_route('turmas.index')->with('sucesso', 'Turma apagada.');
    }

    public function distribuir(Turma $turma): RedirectResponse
    {
        $r = $this->servico->distribuir($turma);
        $mensagem = $r['colocados'] === 0 ? 'Não havia estudantes por colocar.' : "{$r['colocados']} estudantes colocados nas turmas.";
        if ($r['sem_lugar'] > 0) {
            return back()->with('erro', "{$mensagem} {$r['sem_lugar']} ficaram sem lugar: aumente a lotação ou crie outra turma.");
        }

        return back()->with('sucesso', $mensagem);
    }

    public function mudarEstudante(Request $request, Turma $turma): RedirectResponse
    {
        $v = $request->validate([
            'inscricao_periodo_id' => ['required', 'integer', 'exists:inscricoes_periodo,id'],
            'turma_id' => ['required', 'integer', 'exists:turmas,id'],
        ]);
        $destino = Turma::findOrFail((int) $v['turma_id']);
        abort_if($destino->id === $turma->id, 422);
        $this->servico->mudarDeTurma(InscricaoPeriodo::findOrFail((int) $v['inscricao_periodo_id']), $destino);

        return back()->with('sucesso', "Estudante passou para a turma {$destino->designacao()}.");
    }

    public function acrescentarDisciplina(Request $request, Turma $turma): RedirectResponse
    {
        $v = $request->validate(['disciplina_id' => ['required', 'integer', 'exists:disciplinas,id']]);
        $l = $this->servico->acrescentarDisciplina($turma, Disciplina::findOrFail((int) $v['disciplina_id']));

        return back()->with('sucesso', "{$l->disciplina->nome} acrescentada à turma.");
    }

    public function atribuirDocente(Request $request, Leccionacao $leccionacao): RedirectResponse
    {
        $v = $request->validate(['docente_id' => ['nullable', 'integer', 'exists:docentes,id']]);
        $docente = isset($v['docente_id']) ? Docente::find((int) $v['docente_id']) : null;
        $this->servico->atribuirDocente($leccionacao, $docente);

        return back()->with('sucesso', $docente === null
            ? "{$leccionacao->disciplina->nome} fica por atribuir."
            : "{$docente->nome} dá {$leccionacao->disciplina->nome} nesta turma.");
    }
}

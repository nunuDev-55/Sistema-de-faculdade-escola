<?php

namespace App\Http\Controllers\Estudantes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudantes\EstadoMatriculaRequest;
use App\Http\Requests\Estudantes\MatriculaRequest;
use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Estudante;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\User;
use App\Services\MatriculaService;
use App\Support\Opcoes;
use App\Support\Pesquisa;
use App\Support\TipoInstituicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Matrículas do ano lectivo: lista, nova matrícula (a partir da ficha do estudante) e mudanças de estado. */
class MatriculaController extends Controller
{
    public function index(Request $request): Response
    {
        $anos = AnoLectivo::orderByDesc('inicio')->get();
        $omissao = $anos->firstWhere('estado', 'aberto') ?? $anos->first();
        $anoId = (int) $request->query('ano', (string) ($omissao->id ?? 0));
        $curso = (int) $request->query('curso', 0);
        $estado = (string) $request->query('estado', '');
        $q = trim((string) $request->query('q', ''));

        $base = Matricula::query()->where('ano_lectivo_id', $anoId)
            ->when($curso > 0, fn (Builder $query) => $query->where('curso_id', $curso));
        $contagens = (clone $base)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $matriculas = $base
            ->when($estado !== '', fn (Builder $query) => $query->where('estado', $estado))
            ->when($q !== '', fn (Builder $query) => $query->whereHas('estudante', fn (Builder $e) => $e->where(fn (Builder $w) => $w->where('nome', 'like', Pesquisa::like($q))->orWhere('numero', 'like', Pesquisa::like($q)))))
            ->with(['estudante', 'curso'])
            ->withCount(['inscricoesPeriodo as inscricoes' => fn (Builder $i) => $i->where('estado', 'confirmada')])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Matricula $m) => [
                'id' => $m->id,
                'numero' => $m->numero,
                'estudante_id' => $m->estudante_id,
                'estudante_numero' => $m->estudante->numero,
                'estudante' => $m->estudante->nome,
                'curso' => $m->curso->nome,
                'ano_curricular' => $m->ano_curricular,
                'regime' => Matricula::rotuloRegime($m->regime),
                'estado' => $m->estado,
                'estado_rotulo' => Matricula::ESTADOS[$m->estado] ?? $m->estado,
                'inscricoes' => (int) $m->getAttribute('inscricoes'),
                'data' => $m->data->toDateString(),
            ]);

        return Inertia::render('matriculas/index', [
            'matriculas' => $matriculas,
            'filtros' => ['ano' => $anoId > 0 ? (string) $anoId : '', 'curso' => $curso > 0 ? (string) $curso : '', 'estado' => $estado, 'q' => $q],
            'contagens' => $contagens,
            'anos' => $anos->map(fn (AnoLectivo $a) => ['valor' => (string) $a->id, 'rotulo' => $a->nome])->values(),
            'cursos' => Curso::orderBy('nome')->get()->map(fn (Curso $c) => ['valor' => (string) $c->id, 'rotulo' => $c->nome])->values(),
            'estados' => Opcoes::de(Matricula::ESTADOS),
        ]);
    }

    public function store(MatriculaRequest $request, Estudante $estudante, MatriculaService $servico): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $ano = AnoLectivo::findOrFail((int) $request->validated('ano_lectivo_id'));
        $matricula = $servico->matricular(
            $estudante,
            Curso::findOrFail((int) $request->validated('curso_id')),
            $ano,
            (int) $request->validated('ano_curricular'),
            $request->validated('regime') ?: null,
            $autor,
        );

        // No superior segue-se a escolha das disciplinas do semestre que está a decorrer (ou do próximo)
        if (TipoInstituicao::eSuperior() && $autor->can('inscricoes.gerir')) {
            $hoje = now()->toDateString();
            $periodo = $ano->periodos->first(fn (PeriodoLectivo $p) => $hoje <= $p->fim->toDateString()) ?? $ano->periodos->first();
            if ($periodo !== null) {
                return to_route('inscricoes.create', [$matricula, $periodo])
                    ->with('sucesso', "Matrícula {$matricula->numero} feita. Escolha agora as disciplinas do {$periodo->nome()}.");
            }
        }

        return to_route('estudantes.show', $estudante)->with('sucesso', "Matrícula {$matricula->numero} feita.");
    }

    public function estado(EstadoMatriculaRequest $request, Matricula $matricula, MatriculaService $servico): RedirectResponse
    {
        $motivo = (string) $request->validated('motivo');
        $accao = (string) $request->validated('accao');
        if ($accao === 'suspender') {
            $servico->suspender($matricula, $motivo);
        } elseif ($accao === 'reactivar') {
            $servico->reactivar($matricula);
        } else {
            $servico->anular($matricula, $motivo);
        }

        $rotulo = mb_strtolower(Matricula::ESTADOS[$matricula->fresh()->estado ?? ''] ?? '');

        return back()->with('sucesso', "Matrícula {$matricula->numero} {$rotulo}.");
    }
}

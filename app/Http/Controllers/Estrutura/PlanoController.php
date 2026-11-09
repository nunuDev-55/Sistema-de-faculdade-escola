<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\PlanoDisciplinaRequest;
use App\Http\Requests\Estrutura\PlanoRequest;
use App\Http\Requests\Estrutura\PrecedenciaRequest;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PlanoCurricular;
use App\Models\PlanoDisciplina;
use App\Models\Precedencia;
use App\Models\User;
use App\Services\PlanoCurricularService;
use App\Support\TipoInstituicao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Planos curriculares: as regras (rascunho, precedências, aprovação, versões) estão no PlanoCurricularService. */
class PlanoController extends Controller
{
    public function __construct(private readonly PlanoCurricularService $planos) {}

    public function store(PlanoRequest $request, Curso $curso): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plano = $this->planos->criar($curso, trim((string) $request->validated('versao')), (string) ($request->validated('metodo_avaliacao') ?? 'media_simples'), $user);

        return to_route('estrutura.planos.show', $plano)->with('sucesso', "Plano {$plano->versao} criado em rascunho. Acrescente as disciplinas.");
    }

    public function show(Request $request, PlanoCurricular $plano): Response
    {
        /** @var User $user */
        $user = $request->user();
        $plano->load(['curso', 'linhas.disciplina', 'precedencias.disciplina', 'precedencias.precedente']);
        $noPlano = $plano->linhas->pluck('disciplina_id')->all();
        $editavel = $plano->editavel();

        return Inertia::render('estrutura/plano', [
            'plano' => [
                'id' => $plano->id,
                'versao' => $plano->versao,
                'estado' => $plano->estado,
                'estado_rotulo' => PlanoCurricular::ESTADOS[$plano->estado] ?? $plano->estado,
                'metodo' => PlanoCurricular::METODOS[$plano->metodo_avaliacao] ?? $plano->metodo_avaliacao,
                'aprovado_em' => $plano->aprovado_em?->toDateString(),
            ],
            'curso' => [
                'id' => $plano->curso->id,
                'codigo' => $plano->curso->codigo,
                'nome' => $plano->curso->nome,
                'duracao_anos' => $plano->curso->duracao_anos,
            ],
            'linhas' => $plano->linhas
                ->sortBy([['ano', 'asc'], ['periodo', 'asc']])
                ->map(fn (PlanoDisciplina $l) => [
                    'id' => $l->id,
                    'disciplina_id' => $l->disciplina_id,
                    'codigo' => $l->disciplina->codigo,
                    'nome' => $l->disciplina->nome,
                    'creditos' => (float) $l->disciplina->creditos,
                    'ano' => $l->ano,
                    'periodo' => $l->periodo,
                    'obrigatoria' => $l->obrigatoria,
                ])->values(),
            'precedencias' => $plano->precedencias->map(fn (Precedencia $p) => [
                'id' => $p->id,
                'disciplina' => $p->disciplina->codigo.' · '.$p->disciplina->nome,
                'precedente' => $p->precedente->codigo.' · '.$p->precedente->nome,
            ])->values(),
            'disponiveis' => $editavel
                ? Disciplina::where('activo', true)->whereNotIn('id', $noPlano)->orderBy('nome')->get()
                    ->map(fn (Disciplina $d) => ['valor' => (string) $d->id, 'rotulo' => $d->codigo.' · '.$d->nome.' ('.(float) $d->creditos.' cr.)'])->values()
                : [],
            'periodosPorAno' => TipoInstituicao::periodosPorAno(),
            'tipoPeriodo' => TipoInstituicao::tipoPeriodo(),
            'pode' => [
                'editar' => $editavel && $user->can('estrutura.gerir'),
                'aprovar' => $editavel && $user->can('estrutura.aprovar'),
                'novaVersao' => ! $editavel && $user->can('estrutura.gerir'),
            ],
            'sugestaoVersao' => (string) (now()->year + 1),
        ]);
    }

    public function adicionarDisciplina(PlanoDisciplinaRequest $request, PlanoCurricular $plano): RedirectResponse
    {
        $disciplina = Disciplina::findOrFail($request->integer('disciplina_id'));
        $this->planos->adicionarDisciplina($plano, $disciplina, $request->integer('ano'), $request->integer('periodo'), $request->boolean('obrigatoria', true));

        return back()->with('sucesso', "{$disciplina->nome} acrescentada ao plano.");
    }

    public function removerDisciplina(PlanoCurricular $plano, PlanoDisciplina $linha): RedirectResponse
    {
        $this->planos->removerDisciplina($plano, $linha);

        return back()->with('sucesso', 'Disciplina retirada do plano.');
    }

    public function adicionarPrecedencia(PrecedenciaRequest $request, PlanoCurricular $plano): RedirectResponse
    {
        $this->planos->adicionarPrecedencia($plano, $request->integer('disciplina_id'), $request->integer('precedente_id'));

        return back()->with('sucesso', 'Precedência acrescentada.');
    }

    public function removerPrecedencia(PlanoCurricular $plano, Precedencia $precedencia): RedirectResponse
    {
        $this->planos->removerPrecedencia($plano, $precedencia);

        return back()->with('sucesso', 'Precedência retirada.');
    }

    public function aprovar(Request $request, PlanoCurricular $plano): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->planos->aprovar($plano, $user);

        return back()->with('sucesso', "Plano {$plano->versao} aprovado: está em vigor para as matrículas novas.");
    }

    public function novaVersao(PlanoRequest $request, PlanoCurricular $plano): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $novo = $this->planos->novaVersao($plano, trim((string) $request->validated('versao')), $user);

        return to_route('estrutura.planos.show', $novo)->with('sucesso', "Versão {$novo->versao} criada em rascunho, com as disciplinas da {$plano->versao}.");
    }
}

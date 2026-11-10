<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\CursoRequest;
use App\Models\Curso;
use App\Models\PlanoCurricular;
use App\Models\UnidadeOrganica;
use App\Support\Opcoes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CursoController extends Controller
{
    public function index(): Response
    {
        $cursos = Curso::with(['unidade', 'planos'])->orderBy('nome')->get();

        return Inertia::render('estrutura/cursos', [
            'cursos' => $cursos->map(fn (Curso $c) => $this->paraEcra($c))->values(),
            'unidades' => $this->unidades(),
            'graus' => Opcoes::de(Curso::GRAUS),
        ]);
    }

    public function show(Curso $curso): Response
    {
        $curso->load(['unidade', 'planos' => fn ($q) => $q->withCount('linhas')->orderByDesc('id')]);

        return Inertia::render('estrutura/curso', [
            'curso' => $this->paraEcra($curso),
            'planos' => $curso->planos->map(fn (PlanoCurricular $p) => [
                'id' => $p->id,
                'versao' => $p->versao,
                'estado' => $p->estado,
                'estado_rotulo' => PlanoCurricular::ESTADOS[$p->estado] ?? $p->estado,
                'disciplinas' => (int) $p->linhas_count,
                'metodo' => PlanoCurricular::METODOS[$p->metodo_avaliacao] ?? $p->metodo_avaliacao,
                'aprovado_em' => $p->aprovado_em?->toDateString(),
            ])->values(),
            'unidades' => $this->unidades(),
            'graus' => Opcoes::de(Curso::GRAUS),
            'metodos' => Opcoes::de(PlanoCurricular::METODOS),
            'sugestaoVersao' => (string) now()->year,
        ]);
    }

    public function store(CursoRequest $request): RedirectResponse
    {
        $curso = Curso::create($request->validated());

        return to_route('estrutura.cursos.show', $curso)->with('sucesso', "Curso {$curso->nome} criado. Acrescente-lhe um plano curricular.");
    }

    public function update(CursoRequest $request, Curso $curso): RedirectResponse
    {
        $curso->update($request->validated());

        return back()->with('sucesso', 'Curso actualizado.');
    }

    /** @return array<string, mixed> */
    private function paraEcra(Curso $c): array
    {
        $vigor = $c->planos->where('estado', PlanoCurricular::APROVADO)->sortByDesc('aprovado_em')->first();

        return [
            'id' => $c->id,
            'codigo' => $c->codigo,
            'nome' => $c->nome,
            'grau' => $c->grau,
            'grau_rotulo' => Curso::GRAUS[$c->grau] ?? $c->grau,
            'duracao_anos' => $c->duracao_anos,
            'unidade' => $c->unidade?->nome,
            'unidade_id' => $c->unidade_id,
            'activo' => $c->activo,
            'plano_em_vigor' => $vigor?->versao,
        ];
    }

    /** @return list<array{valor: string, rotulo: string}> */
    private function unidades(): array
    {
        return UnidadeOrganica::where('activo', true)->orderBy('nome')->get()
            ->map(fn (UnidadeOrganica $u) => ['valor' => (string) $u->id, 'rotulo' => $u->nome])->values()->all();
    }
}

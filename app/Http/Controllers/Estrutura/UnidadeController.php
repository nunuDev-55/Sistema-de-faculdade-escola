<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\UnidadeRequest;
use App\Models\UnidadeOrganica;
use App\Support\Opcoes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Faculdades, escolas e departamentos (a árvore da instituição). */
class UnidadeController extends Controller
{
    public function index(): Response
    {
        $unidades = UnidadeOrganica::with('pai')->withCount('cursos')->orderBy('nome')->get();

        return Inertia::render('estrutura/unidades', [
            'unidades' => $unidades->map(fn (UnidadeOrganica $u) => [
                'id' => $u->id,
                'tipo' => $u->tipo,
                'tipo_rotulo' => UnidadeOrganica::TIPOS[$u->tipo] ?? $u->tipo,
                'codigo' => $u->codigo,
                'nome' => $u->nome,
                'pai' => $u->pai?->nome,
                'unidade_pai_id' => $u->unidade_pai_id,
                'activo' => $u->activo,
                'cursos' => (int) $u->cursos_count,
            ])->values(),
            'tipos' => Opcoes::de(UnidadeOrganica::TIPOS),
        ]);
    }

    public function store(UnidadeRequest $request): RedirectResponse
    {
        $unidade = UnidadeOrganica::create($request->validated());

        return back()->with('sucesso', "{$unidade->nome} criada.");
    }

    public function update(UnidadeRequest $request, UnidadeOrganica $unidade): RedirectResponse
    {
        $unidade->update($request->validated());

        return back()->with('sucesso', 'Unidade actualizada.');
    }
}

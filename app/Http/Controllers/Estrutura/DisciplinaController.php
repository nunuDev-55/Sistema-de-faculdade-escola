<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\DisciplinaRequest;
use App\Models\Disciplina;
use App\Models\UnidadeOrganica;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisciplinaController extends Controller
{
    public function index(): Response
    {
        $disciplinas = Disciplina::with('unidade')->withCount('nosPlanos')->orderBy('nome')->get();

        return Inertia::render('estrutura/disciplinas', [
            'disciplinas' => $disciplinas->map(fn (Disciplina $d) => [
                'id' => $d->id,
                'codigo' => $d->codigo,
                'nome' => $d->nome,
                'creditos' => (float) $d->creditos,
                'horas_teoricas' => $d->horas_teoricas,
                'horas_praticas' => $d->horas_praticas,
                'unidade' => $d->unidade?->nome,
                'unidade_id' => $d->unidade_id,
                'activo' => $d->activo,
                'planos' => (int) $d->nos_planos_count,
            ])->values(),
            'unidades' => UnidadeOrganica::where('activo', true)->orderBy('nome')->get()
                ->map(fn (UnidadeOrganica $u) => ['valor' => (string) $u->id, 'rotulo' => $u->nome])->values(),
        ]);
    }

    public function store(DisciplinaRequest $request): RedirectResponse
    {
        $disciplina = Disciplina::create($request->validated());

        return back()->with('sucesso', "Disciplina {$disciplina->codigo} · {$disciplina->nome} criada.");
    }

    public function update(DisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $disciplina->update($request->validated());

        return back()->with('sucesso', 'Disciplina actualizada.');
    }
}

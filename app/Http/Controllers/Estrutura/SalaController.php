<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\SalaRequest;
use App\Models\Sala;
use App\Support\Opcoes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Salas, laboratórios e anfiteatros, com a lotação (para os horários e os exames). */
class SalaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('estrutura/salas', [
            'salas' => Sala::orderBy('codigo')->get()->map(fn (Sala $s) => [
                'id' => $s->id,
                'codigo' => $s->codigo,
                'nome' => $s->nome,
                'tipo' => $s->tipo,
                'tipo_rotulo' => Sala::TIPOS[$s->tipo] ?? $s->tipo,
                'capacidade' => $s->capacidade,
                'activo' => $s->activo,
            ])->values(),
            'tipos' => Opcoes::de(Sala::TIPOS),
        ]);
    }

    public function store(SalaRequest $request): RedirectResponse
    {
        $sala = Sala::create($request->validated());

        return back()->with('sucesso', "Sala {$sala->codigo} criada.");
    }

    public function update(SalaRequest $request, Sala $sala): RedirectResponse
    {
        $sala->update($request->validated());

        return back()->with('sucesso', "Sala {$sala->codigo} actualizada.");
    }
}

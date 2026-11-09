<?php

namespace App\Http\Controllers\Estudantes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudantes\EncarregadoRequest;
use App\Models\Encarregado;
use App\Models\Estudante;
use App\Services\EstudanteService;
use Illuminate\Http\RedirectResponse;

/** Encarregados de educação ligados ao estudante. */
class EncarregadoController extends Controller
{
    public function store(EncarregadoRequest $request, Estudante $estudante, EstudanteService $servico): RedirectResponse
    {
        $v = $request->validated();
        [$encarregado, $jaExistia] = $servico->ligarEncarregado(
            $estudante,
            [
                'nome' => (string) $v['nome'],
                'telemovel' => $v['telemovel'] ?? null,
                'documento_numero' => $v['documento_numero'] ?? null,
                'profissao' => $v['profissao'] ?? null,
                'email' => $v['email'] ?? null,
                'morada' => $v['morada'] ?? null,
            ],
            (string) $v['parentesco'],
            (bool) ($v['principal'] ?? false),
            (bool) ($v['ve_financas'] ?? true),
        );

        return back()->with('sucesso', $jaExistia
            ? "{$encarregado->nome} já era encarregado de outro estudante (mesmo telemóvel): ficou ligado também a este."
            : "Encarregado {$encarregado->nome} acrescentado.");
    }

    public function destroy(Estudante $estudante, Encarregado $encarregado, EstudanteService $servico): RedirectResponse
    {
        $servico->desligarEncarregado($estudante, $encarregado);

        return back()->with('sucesso', "{$encarregado->nome} deixou de ser encarregado deste estudante.");
    }
}

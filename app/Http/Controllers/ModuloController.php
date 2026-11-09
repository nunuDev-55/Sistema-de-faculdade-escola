<?php

namespace App\Http\Controllers;

use App\Support\Modulos;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ecrã "em construção" de um módulo ainda por fazer: mostra o que está planeado (docs/ROADMAP.md).
 * Cada módulo que for feito substitui esta rota pelos seus próprios controladores e ecrãs.
 */
class ModuloController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $modulo = Modulos::todos()[$slug] ?? abort(404);

        return Inertia::render('em-construcao', [
            'titulo' => $modulo['titulo'],
            'descricao' => $modulo['descricao'],
            'planeado' => $modulo['planeado'],
        ]);
    }
}

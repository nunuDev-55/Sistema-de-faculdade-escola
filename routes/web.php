<?php

use App\Http\Controllers\InicioController;
use App\Http\Controllers\ModuloController;
use App\Support\Modulos;
use Illuminate\Support\Facades\Route;

// O sistema interno: só a equipa (guarda "web"). Os portais (estudante, encarregado, candidato) e o site
// público virão nas suas fases, com as suas rotas e guardas (ver docs/05-arquitectura.md).

Route::get('/', fn () => to_route('dashboard'));

Route::middleware(['auth:web'])->group(function () {
    Route::get('painel', InicioController::class)->name('dashboard');

    // Cada módulo exige a sua permissão (catálogo em App\Support\Permissoes).
    // No esqueleto abrem num ecrã "em construção"; cada um substitui a sua rota quando for feito.
    foreach (Modulos::todos() as $slug => $modulo) {
        Route::get(ltrim($modulo['href'], '/'), [ModuloController::class, 'show'])
            ->middleware('can:'.$modulo['permissao'])
            ->defaults('slug', $slug)
            ->name('modulos.'.$slug);
    }
});

// Logótipo da instituição: público, porque vai nos documentos e nos portais (implementa-se na Configuração).
Route::get('instituicao/logotipo', fn () => abort(404))->name('instituicao.logotipo');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

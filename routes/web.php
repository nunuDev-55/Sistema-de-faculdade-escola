<?php

use App\Http\Controllers\Administracao\AuditoriaController;
use App\Http\Controllers\Administracao\ConfiguracaoController;
use App\Http\Controllers\Administracao\PerfilController;
use App\Http\Controllers\Administracao\UtilizadorController;
use App\Http\Controllers\Estrutura\CalendarioController;
use App\Http\Controllers\Estrutura\CursoController;
use App\Http\Controllers\Estrutura\DisciplinaController;
use App\Http\Controllers\Estrutura\PlanoController;
use App\Http\Controllers\Estrutura\UnidadeController;
use App\Http\Controllers\Estudantes\EncarregadoController;
use App\Http\Controllers\Estudantes\EstudanteController;
use App\Http\Controllers\Estudantes\InscricaoController;
use App\Http\Controllers\Estudantes\MatriculaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\ModuloController;
use App\Support\Modulos;
use Illuminate\Support\Facades\Route;

// O sistema interno: só a equipa (guarda "web"). Os portais (estudante, encarregado, candidato) e o site
// público virão nas suas fases, com as suas rotas e guardas (ver docs/05-arquitectura.md).

Route::get('/', fn () => to_route('dashboard'));

Route::middleware(['auth:web'])->group(function () {
    Route::get('painel', InicioController::class)->name('dashboard');

    // Estrutura académica: unidades orgânicas, cursos, disciplinas, planos curriculares e calendário
    Route::middleware('can:estrutura.ver')->prefix('estrutura')->name('estrutura.')->group(function () {
        Route::get('/', fn () => to_route('estrutura.cursos.index'))->name('index');
        Route::get('cursos', [CursoController::class, 'index'])->name('cursos.index');
        Route::get('cursos/{curso}', [CursoController::class, 'show'])->name('cursos.show');
        Route::get('disciplinas', [DisciplinaController::class, 'index'])->name('disciplinas.index');
        Route::get('unidades', [UnidadeController::class, 'index'])->name('unidades.index');
        Route::get('planos/{plano}', [PlanoController::class, 'show'])->name('planos.show');
        Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');

        Route::middleware('can:estrutura.gerir')->group(function () {
            Route::post('cursos', [CursoController::class, 'store'])->name('cursos.store');
            Route::put('cursos/{curso}', [CursoController::class, 'update'])->name('cursos.update');
            Route::post('disciplinas', [DisciplinaController::class, 'store'])->name('disciplinas.store');
            Route::put('disciplinas/{disciplina}', [DisciplinaController::class, 'update'])->name('disciplinas.update');
            Route::post('unidades', [UnidadeController::class, 'store'])->name('unidades.store');
            Route::put('unidades/{unidade}', [UnidadeController::class, 'update'])->name('unidades.update');

            Route::post('cursos/{curso}/planos', [PlanoController::class, 'store'])->name('planos.store');
            Route::controller(PlanoController::class)->prefix('planos/{plano}')->name('planos.')->group(function () {
                Route::post('disciplinas', 'adicionarDisciplina')->name('disciplinas.store');
                Route::delete('disciplinas/{linha}', 'removerDisciplina')->name('disciplinas.destroy');
                Route::post('precedencias', 'adicionarPrecedencia')->name('precedencias.store');
                Route::delete('precedencias/{precedencia}', 'removerPrecedencia')->name('precedencias.destroy');
                Route::post('versao', 'novaVersao')->name('versao');
            });
        });
        Route::post('planos/{plano}/aprovar', [PlanoController::class, 'aprovar'])->middleware('can:estrutura.aprovar')->name('planos.aprovar');

        Route::middleware('can:calendario.gerir')->group(function () {
            Route::post('calendario', [CalendarioController::class, 'store'])->name('calendario.store');
            Route::put('calendario/periodos/{periodo}', [CalendarioController::class, 'actualizarPeriodo'])->name('calendario.periodos.update');
        });
    });

    // Estudantes e encarregados; o comprovativo de inscrição vê-se a partir da ficha
    Route::middleware('can:estudantes.ver')->group(function () {
        Route::get('estudantes', [EstudanteController::class, 'index'])->name('estudantes.index');
        Route::middleware('can:estudantes.gerir')->group(function () {
            Route::get('estudantes/novo', [EstudanteController::class, 'create'])->name('estudantes.create');
            Route::post('estudantes', [EstudanteController::class, 'store'])->name('estudantes.store');
            Route::get('estudantes/{estudante}/editar', [EstudanteController::class, 'edit'])->name('estudantes.edit');
            Route::put('estudantes/{estudante}', [EstudanteController::class, 'update'])->name('estudantes.update');
            Route::post('estudantes/{estudante}/encarregados', [EncarregadoController::class, 'store'])->name('estudantes.encarregados.store');
            Route::delete('estudantes/{estudante}/encarregados/{encarregado}', [EncarregadoController::class, 'destroy'])->name('estudantes.encarregados.destroy');
        });
        Route::get('estudantes/{estudante}', [EstudanteController::class, 'show'])->whereNumber('estudante')->name('estudantes.show');
        Route::get('comprovativos/{inscricaoPeriodo}', [InscricaoController::class, 'comprovativo'])->name('inscricoes.comprovativo');
    });

    // Matrículas no ano lectivo e inscrição semestral nas disciplinas
    Route::get('matriculas', [MatriculaController::class, 'index'])->middleware('can:matriculas.criar')->name('matriculas.index');
    Route::post('estudantes/{estudante}/matriculas', [MatriculaController::class, 'store'])->middleware('can:matriculas.criar')->name('matriculas.store');
    Route::put('matriculas/{matricula}/estado', [MatriculaController::class, 'estado'])->middleware('can:matriculas.estado')->name('matriculas.estado');
    Route::middleware('can:inscricoes.gerir')->group(function () {
        Route::get('matriculas/{matricula}/inscricao/{periodo}', [InscricaoController::class, 'create'])->name('inscricoes.create');
        Route::post('matriculas/{matricula}/inscricao/{periodo}', [InscricaoController::class, 'store'])->name('inscricoes.store');
        Route::delete('inscricoes/{inscricao}', [InscricaoController::class, 'destroy'])->name('inscricoes.destroy');
    });

    // Administração: contas da equipa, perfis e permissões, auditoria (do nsERP)
    Route::prefix('administracao')->group(function () {
        Route::get('utilizadores', [UtilizadorController::class, 'index'])->middleware('can:utilizadores.ver')->name('utilizadores.index');
        Route::post('utilizadores', [UtilizadorController::class, 'store'])->middleware('can:utilizadores.gerir')->name('utilizadores.store');
        Route::put('utilizadores/{utilizador}', [UtilizadorController::class, 'update'])->middleware('can:utilizadores.gerir')->name('utilizadores.update');
        Route::post('utilizadores/{utilizador}/estado', [UtilizadorController::class, 'alternarEstado'])->middleware('can:utilizadores.desactivar')->name('utilizadores.estado');

        Route::middleware('can:perfis.gerir')->group(function () {
            Route::get('perfis', [PerfilController::class, 'index'])->name('perfis.index');
            Route::post('perfis', [PerfilController::class, 'store'])->name('perfis.store');
            Route::put('perfis/{perfil}', [PerfilController::class, 'update'])->name('perfis.update');
            Route::delete('perfis/{perfil}', [PerfilController::class, 'destroy'])->name('perfis.destroy');
        });

        Route::get('auditoria', [AuditoriaController::class, 'index'])->middleware('can:auditoria.ver')->name('auditoria.index');
    });

    // Administração → Configuração: dados da instituição e regras de avaliação
    Route::middleware('can:configuracao.gerir')->prefix('administracao/configuracao')->name('configuracao.')
        ->controller(ConfiguracaoController::class)->group(function () {
            Route::get('/', fn () => to_route('configuracao.instituicao'))->name('index');
            Route::get('instituicao', 'instituicao')->name('instituicao');
            Route::post('instituicao', 'guardarInstituicao')->name('instituicao.guardar');
            Route::get('avaliacao', 'avaliacao')->name('avaliacao');
            Route::put('avaliacao', 'guardarAvaliacao')->name('avaliacao.guardar');
        });

    // Os módulos ainda por fazer abrem num ecrã "em construção"; cada um exige a sua permissão
    // (catálogo em App\Support\Permissoes) e substitui a sua rota quando for feito ('feito' => true).
    foreach (Modulos::todos() as $slug => $modulo) {
        if ($modulo['feito'] ?? false) {
            continue;
        }
        Route::get(ltrim($modulo['href'], '/'), [ModuloController::class, 'show'])
            ->middleware('can:'.$modulo['permissao'])
            ->defaults('slug', $slug)
            ->name('modulos.'.$slug);
    }
});

// Logótipo da instituição: público, porque vai nos documentos e na página de entrada.
Route::get('instituicao/logotipo', [ConfiguracaoController::class, 'logotipo'])->name('instituicao.logotipo');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

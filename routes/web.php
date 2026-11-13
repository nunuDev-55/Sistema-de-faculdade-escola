<?php

use App\Http\Controllers\Administracao\AuditoriaController;
use App\Http\Controllers\Administracao\ConfiguracaoController;
use App\Http\Controllers\Administracao\PerfilController;
use App\Http\Controllers\Administracao\UtilizadorController;
use App\Http\Controllers\Avaliacao\AvaliacaoController;
use App\Http\Controllers\Avaliacao\PautaController;
use App\Http\Controllers\Docencia\DocenteController;
use App\Http\Controllers\Docencia\MinhasTurmasController;
use App\Http\Controllers\Docencia\TurmaController;
use App\Http\Controllers\Estrutura\CalendarioController;
use App\Http\Controllers\Estrutura\CursoController;
use App\Http\Controllers\Estrutura\DisciplinaController;
use App\Http\Controllers\Estrutura\PlanoController;
use App\Http\Controllers\Estrutura\SalaController;
use App\Http\Controllers\Estrutura\UnidadeController;
use App\Http\Controllers\Estudantes\EncarregadoController;
use App\Http\Controllers\Estudantes\EstudanteController;
use App\Http\Controllers\Estudantes\InscricaoController;
use App\Http\Controllers\Estudantes\MatriculaController;
use App\Http\Controllers\Financas\FinancasController;
use App\Http\Controllers\Financas\PagamentoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\ModuloController;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Http\Request;
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
        Route::get('salas', [SalaController::class, 'index'])->name('salas.index');

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
            Route::post('salas', [SalaController::class, 'store'])->name('salas.store');
            Route::put('salas/{sala}', [SalaController::class, 'update'])->name('salas.update');
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

    // Docência: turmas, distribuição de serviço e docentes; o docente vê as suas turmas
    Route::prefix('docencia')->group(function () {
        Route::get('/', function (Request $request) {
            /** @var User $user */
            $user = $request->user();
            abort_unless($user->can('turmas.ver') || $user->can('aulas.registar'), 403);

            return $user->can('turmas.ver') ? to_route('turmas.index') : to_route('docencia.minhas');
        })->name('docencia.index');
        Route::get('minhas-turmas', MinhasTurmasController::class)->middleware('can:aulas.registar')->name('docencia.minhas');

        Route::middleware('can:turmas.ver')->group(function () {
            Route::get('turmas', [TurmaController::class, 'index'])->name('turmas.index');
            Route::get('turmas/{turma}', [TurmaController::class, 'show'])->name('turmas.show');
            Route::get('docentes', [DocenteController::class, 'index'])->name('docentes.index');
        });
        Route::middleware('can:turmas.gerir')->controller(TurmaController::class)->group(function () {
            Route::post('turmas', 'store')->name('turmas.store');
            Route::put('turmas/{turma}', 'update')->name('turmas.update');
            Route::delete('turmas/{turma}', 'destroy')->name('turmas.destroy');
            Route::post('turmas/{turma}/distribuir', 'distribuir')->name('turmas.distribuir');
            Route::post('turmas/{turma}/estudantes', 'mudarEstudante')->name('turmas.estudantes.mudar');
        });
        Route::middleware('can:distribuicao.gerir')->controller(TurmaController::class)->group(function () {
            Route::post('turmas/{turma}/disciplinas', 'acrescentarDisciplina')->name('turmas.disciplinas.store');
            Route::put('leccionacoes/{leccionacao}', 'atribuirDocente')->name('leccionacoes.docente');
        });
        Route::middleware('can:docentes.gerir')->group(function () {
            Route::post('docentes', [DocenteController::class, 'store'])->name('docentes.store');
            Route::put('docentes/{docente}', [DocenteController::class, 'update'])->name('docentes.update');
        });
    });

    // Avaliação: plano de avaliação, notas e pautas. Quem lança e quem vê decide-se na disciplina (PautaService)
    Route::prefix('pautas')->group(function () {
        Route::get('/', [PautaController::class, 'index'])->middleware('can:pautas.ver')->name('pautas.index');
        Route::get('disciplina/{leccionacao}', [AvaliacaoController::class, 'show'])->name('avaliacao.show');
        Route::put('disciplina/{leccionacao}/plano', [AvaliacaoController::class, 'plano'])->name('avaliacao.plano');
        Route::put('disciplina/{leccionacao}/notas', [AvaliacaoController::class, 'notas'])->name('avaliacao.notas');
        Route::put('{pauta}/exame', [AvaliacaoController::class, 'exame'])->name('pautas.exame');
        Route::post('{pauta}/submeter', [AvaliacaoController::class, 'submeter'])->name('pautas.submeter');
        Route::get('{pauta}/imprimir', [PautaController::class, 'imprimir'])->name('pautas.imprimir');
        Route::middleware('can:pautas.validar')->controller(AvaliacaoController::class)->group(function () {
            Route::post('{pauta}/validar', 'validar')->name('pautas.validar');
            Route::post('{pauta}/devolver', 'devolver')->name('pautas.devolver');
            Route::post('{pauta}/publicar', 'publicar')->name('pautas.publicar');
        });
    });

    // Finanças do estudante: cobranças, tabela de preços e geração das cobranças do ano
    Route::middleware('can:financas.ver')->prefix('financas')->controller(FinancasController::class)->group(function () {
        Route::get('/', 'index')->name('financas.index');
        Route::get('precos', 'precos')->name('financas.precos');
        Route::post('precos', 'guardarPreco')->middleware('can:financas.configurar')->name('financas.precos.guardar');
        Route::delete('precos/{preco}', 'apagarPreco')->middleware('can:financas.configurar')->name('financas.precos.apagar');
        Route::post('gerar', 'gerar')->middleware('can:cobrancas.gerar')->name('financas.gerar');
    });
    Route::middleware('can:financas.ver')->group(function () {
        Route::get('financas/recibos/{pagamento}', [PagamentoController::class, 'recibo'])->name('pagamentos.recibo');
        Route::post('estudantes/{estudante}/pagamentos', [PagamentoController::class, 'store'])->middleware('can:pagamentos.registar')->name('pagamentos.store');
        Route::post('financas/recibos/{pagamento}/anular', [PagamentoController::class, 'anular'])->middleware('can:pagamentos.anular')->name('pagamentos.anular');
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

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Definicoes;
use App\Support\Modulos;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página Início (painel). No esqueleto, mostra o estado da instalação e as caixas de cada módulo que o
 * utilizador pode ver. À medida que os módulos forem feitos, cada caixa passa a mostrar números reais
 * (matrículas do ano, pautas por publicar, propinas recebidas e vencidas; ver docs/02-requisitos.md RF-111).
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $caixas = [];
        foreach (Modulos::LISTA as $modulo) {
            if ($user->can($modulo['permissao'])) {
                $caixas[] = [
                    'titulo' => $modulo['titulo'],
                    'href' => $modulo['href'],
                    'descricao' => $modulo['descricao'],
                ];
            }
        }

        return Inertia::render('dashboard', [
            'configuracaoEmFalta' => $user->can('configuracao.gerir') ? app(Definicoes::class)->emFalta() : [],
            'caixas' => $caixas,
        ]);
    }
}

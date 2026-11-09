<?php

namespace App\Http\Middleware;

use App\Support\Definicoes;
use App\Support\Permissoes;
use App\Support\TipoInstituicao;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user('web');
        $definicoes = app(Definicoes::class);

        return [
            ...parent::share($request),
            // O nome do sistema (nsEDU) e a instituição que o usa
            'sistema' => config('nsedu.produto.nome'),
            'instituicao' => fn () => [
                'nome' => $definicoes->nomeCurto(),
                'logotipo' => $definicoes->urlLogotipo(),
            ],
            // Os termos conforme o tipo de instituição (Curso/Classe, Semestre/Trimestre)
            'termos' => fn () => TipoInstituicao::termos(),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email', 'phone']),
                'perfil' => $user?->getRoleNames()->first(),
                // O frontend usa esta lista só para mostrar ou esconder menus e botões.
                // A protecção verdadeira está sempre no backend (rotas e controladores).
                'permissoes' => $user === null ? [] : ($user->isAdministrador()
                    ? Permissoes::todas()
                    : $user->getAllPermissions()->pluck('name')->all()),
            ],
            'flash' => [
                'sucesso' => fn () => $request->session()->get('sucesso'),
                'erro' => fn () => $request->session()->get('erro'),
            ],
        ];
    }
}

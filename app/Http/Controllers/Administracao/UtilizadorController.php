<?php

namespace App\Http\Controllers\Administracao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracao\UtilizadorRequest;
use App\Models\User;
use App\Services\UtilizadorService;
use App\Support\Pesquisa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** Contas da equipa: criar, alterar o perfil, activar e desactivar (do nsERP). */
class UtilizadorController extends Controller
{
    public function __construct(private readonly UtilizadorService $servico) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'pesquisa' => ['nullable', 'string', 'max:100'],
            'perfil' => ['nullable', 'string', 'max:50'],
            'estado' => ['nullable', 'in:activos,inactivos'],
        ]);

        $utilizadores = User::query()
            ->with('roles:id,name')
            ->when($filtros['pesquisa'] ?? null, function (Builder $query, string $texto) {
                $termo = Pesquisa::like($texto);
                $query->where(fn (Builder $q) => $q->where('name', 'like', $termo)->orWhere('email', 'like', $termo)->orWhere('phone', 'like', $termo));
            })
            ->when($filtros['perfil'] ?? null, fn (Builder $query, string $perfil) => $query->whereHas('roles', fn (Builder $r) => $r->where('name', $perfil)))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('active', $estado === 'activos'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'active' => $u->active,
                'perfil' => $u->getRoleNames()->first(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('administracao/utilizadores', [
            'utilizadores' => $utilizadores,
            'filtros' => [
                'pesquisa' => (string) ($filtros['pesquisa'] ?? ''),
                'perfil' => (string) ($filtros['perfil'] ?? ''),
                'estado' => (string) ($filtros['estado'] ?? ''),
            ],
            'perfis' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'resumo' => ['total' => User::count(), 'activos' => User::where('active', true)->count()],
        ]);
    }

    public function store(UtilizadorRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, phone?: string|null, perfil: string, password: string} $dados */
        $dados = $request->validated();
        $user = $this->servico->criar($dados);

        return back()->with('sucesso', "Conta de {$user->name} criada com o perfil {$dados['perfil']}.");
    }

    public function update(UtilizadorRequest $request, User $utilizador): RedirectResponse
    {
        /** @var array{name: string, email: string, phone?: string|null, perfil: string, password?: string|null} $dados */
        $dados = $request->validated();
        $this->servico->actualizar($utilizador, $dados);

        return back()->with('sucesso', 'Alterações guardadas.');
    }

    public function alternarEstado(Request $request, User $utilizador): RedirectResponse
    {
        /** @var User $quem */
        $quem = $request->user();
        $this->servico->alternarEstado($utilizador, $quem);

        return back()->with('sucesso', $utilizador->active
            ? "{$utilizador->name} foi activado."
            : "{$utilizador->name} foi desactivado e as suas sessões foram terminadas.");
    }
}

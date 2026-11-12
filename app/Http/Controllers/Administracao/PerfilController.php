<?php

namespace App\Http\Controllers\Administracao;

use App\Http\Controllers\Controller;
use App\Support\Permissoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Perfis e a matriz de permissões (do nsERP). O catálogo está em App\Support\Permissoes. */
class PerfilController extends Controller
{
    public function index(): Response
    {
        $perfis = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('name = ? desc', [Permissoes::ADMINISTRADOR])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'nome' => $role->name,
                'utilizadores' => (int) $role->getAttribute('users_count'),
                'permissoes' => $role->permissions->pluck('name')->values(),
                'sistema' => Permissoes::eDeSistema($role->name),
                'acessoTotal' => $role->name === Permissoes::ADMINISTRADOR,
            ])->values();

        return Inertia::render('administracao/perfis', [
            'perfis' => $perfis,
            'catalogo' => collect(Permissoes::CATALOGO)->map(fn (array $modulo, string $chave) => [
                'chave' => $chave,
                'label' => $modulo['label'],
                'permissoes' => collect($modulo['permissoes'])->map(fn (string $label, string $nome) => ['nome' => $nome, 'label' => $label])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            ['nome' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->where('guard_name', 'web')]],
            ['nome.unique' => 'Já existe um perfil com esse nome.'],
            ['nome' => 'nome do perfil'],
        );

        // new + save (e não Role::create, que devolve o contrato do Spatie e não o modelo que a auditoria pede)
        $role = new Role(['name' => trim((string) $dados['nome']), 'guard_name' => 'web']);
        $role->save();
        activity('perfis')->performedOn($role)->event('created')->log("Perfil criado: {$role->name}");

        return back()->with('sucesso', "Perfil {$role->name} criado. Escolha agora as suas permissões.");
    }

    public function update(Request $request, Role $perfil): RedirectResponse
    {
        abort_if($perfil->name === Permissoes::ADMINISTRADOR, 403, 'O perfil Administrador tem sempre acesso total.');

        $dados = $request->validate([
            'permissoes' => ['present', 'array'],
            'permissoes.*' => ['string', Rule::in(Permissoes::todas())],
        ]);

        $antes = $perfil->permissions->pluck('name')->sort()->values()->all();
        $depois = collect((array) $dados['permissoes'])->unique()->sort()->values()->all();
        $perfil->syncPermissions($depois);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($antes !== $depois) {
            activity('perfis')
                ->performedOn($perfil)
                ->event('permissoes')
                ->withProperties([
                    'old' => ['permissoes' => implode(', ', $antes) ?: '(nenhuma)'],
                    'attributes' => ['permissoes' => implode(', ', $depois) ?: '(nenhuma)'],
                ])
                ->log("Permissões do perfil {$perfil->name} alteradas");
        }

        return back()->with('sucesso', "Permissões do perfil {$perfil->name} guardadas.");
    }

    public function destroy(Role $perfil): RedirectResponse
    {
        abort_if(Permissoes::eDeSistema($perfil->name), 403, 'Os perfis que vêm com o sistema não se apagam.');
        if ($perfil->users()->exists()) {
            return back()->with('erro', "O perfil {$perfil->name} ainda tem utilizadores. Mude-lhes o perfil antes de o apagar.");
        }

        $nome = $perfil->name;
        $perfil->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('perfis')->event('deleted')->withProperties(['perfil' => $nome])->log("Perfil apagado: {$nome}");

        return back()->with('sucesso', "Perfil {$nome} apagado.");
    }
}

<?php

namespace App\Http\Controllers\Docencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docencia\DocenteRequest;
use App\Models\Docente;
use App\Models\UnidadeOrganica;
use App\Models\User;
use App\Support\Opcoes;
use App\Support\Pesquisa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Fichas dos docentes, ligadas à conta com que entram no sistema (RF-50). */
class DocenteController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $docentes = Docente::query()
            ->with(['user', 'unidade'])
            ->withCount('leccionacoes')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w->where('nome', 'like', Pesquisa::like($q))
                ->orWhere('numero', 'like', Pesquisa::like($q))->orWhere('email', 'like', Pesquisa::like($q))))
            ->orderByDesc('activo')
            ->orderBy('nome')
            ->get();

        $ligadas = Docente::whereNotNull('user_id')->pluck('user_id')->all();

        return Inertia::render('docencia/docentes', [
            'docentes' => $docentes->map(fn (Docente $d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'numero' => $d->numero,
                'grau_academico' => $d->grau_academico,
                'grau_rotulo' => Docente::GRAUS[$d->grau_academico ?? ''] ?? null,
                'categoria' => $d->categoria,
                'regime' => $d->regime,
                'regime_rotulo' => Docente::REGIMES[$d->regime] ?? $d->regime,
                'unidade_id' => $d->unidade_id,
                'unidade' => $d->unidade?->nome,
                'telemovel' => $d->telemovel,
                'email' => $d->email,
                'user_id' => $d->user_id,
                'conta' => $d->user?->email,
                'activo' => $d->activo,
                'disciplinas' => (int) $d->leccionacoes_count,
            ])->values(),
            'q' => $q,
            'graus' => Opcoes::de(Docente::GRAUS),
            'regimes' => Opcoes::de(Docente::REGIMES),
            'unidades' => UnidadeOrganica::where('activo', true)->orderBy('nome')->get()
                ->map(fn (UnidadeOrganica $u) => ['valor' => (string) $u->id, 'rotulo' => $u->nome])->values(),
            // Contas que se podem ligar: as que ainda não têm ficha de docente (a do próprio docente entra no ecrã)
            'contas' => User::where('active', true)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['valor' => (string) $u->id, 'rotulo' => "{$u->name} ({$u->email})", 'livre' => ! in_array($u->id, $ligadas, true)])
                ->values(),
        ]);
    }

    public function store(DocenteRequest $request): RedirectResponse
    {
        $docente = Docente::create($request->validated());

        return back()->with('sucesso', "Docente {$docente->nome} registado.");
    }

    public function update(DocenteRequest $request, Docente $docente): RedirectResponse
    {
        $docente->update($request->validated());

        return back()->with('sucesso', 'Ficha do docente guardada.');
    }
}

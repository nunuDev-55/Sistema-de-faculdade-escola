<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ContaPortal;
use App\Support\Definicoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Entrada no portal do estudante: número de estudante e senha (guarda "estudante"). */
class EntradaController extends Controller
{
    public function create(Definicoes $definicoes): Response
    {
        return Inertia::render('portal/entrar', ['instituicao' => ['nome' => $definicoes->nome(), 'logotipo' => $definicoes->urlLogotipo()]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate(['numero' => ['required', 'string', 'max:20'], 'password' => ['required', 'string']], [], ['numero' => 'número de estudante', 'password' => 'senha']);
        $chave = 'portal:'.Str::lower(trim((string) $v['numero'])).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            throw ValidationException::withMessages(['numero' => 'Muitas tentativas. Espere '.RateLimiter::availableIn($chave).' segundos e tente outra vez.']);
        }

        $conta = ContaPortal::whereHas('estudante', fn ($q) => $q->where('numero', trim((string) $v['numero'])))->first();
        if ($conta === null || ! Hash::check((string) $v['password'], $conta->password)) {
            RateLimiter::hit($chave);
            throw ValidationException::withMessages(['numero' => 'Número de estudante ou senha errados.']);
        }
        if (! $conta->activa()) {
            throw ValidationException::withMessages(['numero' => 'O seu acesso ao portal está suspenso. Fale com a secretaria.']);
        }

        RateLimiter::clear($chave);
        Auth::guard('estudante')->login($conta);
        $request->session()->regenerate();
        $conta->forceFill(['last_login_at' => now()])->saveQuietly();
        activity('acessos')->causedBy($conta)->performedOn($conta->estudante)->log('Entrou no portal do estudante');

        return redirect()->intended(route('portal.painel'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('estudante')->logout();
        $request->session()->regenerateToken();

        return to_route('portal.entrar');
    }
}

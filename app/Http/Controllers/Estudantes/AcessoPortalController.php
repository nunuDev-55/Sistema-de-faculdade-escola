<?php

namespace App\Http\Controllers\Estudantes;

use App\Http\Controllers\Controller;
use App\Models\ContaPortal;
use App\Models\Estudante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A secretaria dá ao estudante acesso ao portal: uma senha nova, mostrada uma só vez para lha entregar.
 * Também a suspende (e volta a activar).
 */
class AcessoPortalController extends Controller
{
    public function store(Estudante $estudante): RedirectResponse
    {
        // Sem letras que se confundem (0/O, 1/l/I) para se poder ditar ou escrever num papel
        $senha = Str::of(Str::password(24, true, true, false))->replaceMatches('/[0O1lI]/', '')->substr(0, 10)->value();

        DB::transaction(function () use ($estudante, $senha) {
            $conta = ContaPortal::firstOrNew(['estudante_id' => $estudante->id]);
            $nova = ! $conta->exists;
            $conta->password = $senha;
            $conta->estado = 'activa';
            $conta->save();
            activity('estudantes')->performedOn($estudante)->log($nova ? 'Acesso ao portal criado' : 'Senha do portal renovada');
        });

        return back()->with('senha_portal', $senha)
            ->with('sucesso', "Acesso ao portal pronto. Entregue a senha ao estudante: entra com o número {$estudante->numero}.");
    }

    public function alternar(Estudante $estudante): RedirectResponse
    {
        $conta = ContaPortal::where('estudante_id', $estudante->id)->firstOrFail();
        $conta->update(['estado' => $conta->activa() ? 'suspensa' : 'activa']);
        activity('estudantes')->performedOn($estudante)->log($conta->activa() ? 'Acesso ao portal reactivado' : 'Acesso ao portal suspenso');

        return back()->with('sucesso', $conta->activa() ? 'Acesso ao portal reactivado.' : 'Acesso ao portal suspenso.');
    }
}

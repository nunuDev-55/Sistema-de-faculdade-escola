<?php

namespace App\Services;

use App\Models\Encarregado;
use App\Models\Estudante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registo do estudante e dos seus encarregados. O número de estudante é atribuído na transacção que grava
 * o registo (RN-08, RN-12): um erro desfaz também o número, e o número nunca se repete.
 */
class EstudanteService
{
    public function __construct(private Numeracao $numeracao) {}

    /** @param array<string, mixed> $dados */
    public function registar(array $dados, ?User $autor): Estudante
    {
        return DB::transaction(function () use ($dados, $autor) {
            return Estudante::create([
                ...$dados,
                'numero' => $this->numeracao->proximo('estudante'),
                'estado' => $dados['estado'] ?? 'activo',
                'criado_por' => $autor?->id,
            ]);
        });
    }

    /**
     * Liga um encarregado ao estudante. Se já houver um encarregado com o mesmo telemóvel (irmãos), é esse que
     * se liga: um encarregado, vários educandos. Devolve o encarregado e se já existia.
     *
     * @param  array{nome: string, telemovel?: string|null, documento_numero?: string|null, profissao?: string|null, email?: string|null, morada?: string|null}  $dados
     * @return array{0: Encarregado, 1: bool}
     */
    public function ligarEncarregado(Estudante $estudante, array $dados, string $parentesco, bool $principal, bool $veFinancas): array
    {
        return DB::transaction(function () use ($estudante, $dados, $parentesco, $principal, $veFinancas) {
            $telemovel = $this->limparTelemovel($dados['telemovel'] ?? null);
            $existente = $telemovel !== null ? Encarregado::where('telemovel', $telemovel)->first() : null;
            $encarregado = $existente ?? Encarregado::create([...$dados, 'telemovel' => $telemovel]);

            // O primeiro encarregado é o principal; marcar outro como principal tira a marca ao anterior.
            $principal = $principal || ! $estudante->encarregados()->exists();
            if ($principal) {
                DB::table('estudante_encarregado')->where('estudante_id', $estudante->id)->update(['principal' => false]);
            }

            $estudante->encarregados()->syncWithoutDetaching([
                $encarregado->id => ['parentesco' => $parentesco, 'principal' => $principal, 've_financas' => $veFinancas],
            ]);
            activity('estudantes')->performedOn($estudante)
                ->withProperties(['encarregado' => $encarregado->nome, 'parentesco' => $parentesco])
                ->log("Encarregado {$encarregado->nome} ligado ao estudante {$estudante->numero}");

            return [$encarregado, $existente !== null];
        });
    }

    public function desligarEncarregado(Estudante $estudante, Encarregado $encarregado): void
    {
        DB::transaction(function () use ($estudante, $encarregado) {
            $estudante->encarregados()->detach($encarregado->id);
            activity('estudantes')->performedOn($estudante)
                ->withProperties(['encarregado' => $encarregado->nome])
                ->log("Encarregado {$encarregado->nome} desligado do estudante {$estudante->numero}");
        });
    }

    /** "+258 84 123 4567" e "841234567" são o mesmo número: guarda-se só com os algarismos. */
    public function limparTelemovel(?string $telemovel): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telemovel) ?? '';
        if (strlen($digitos) === 12 && str_starts_with($digitos, '258')) {
            $digitos = substr($digitos, 3);
        }

        return $digitos === '' ? null : $digitos;
    }
}

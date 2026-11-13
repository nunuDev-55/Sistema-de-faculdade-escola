<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numeração sequencial sem saltos nem repetidos (RN-12, ver docs/02-requisitos.md).
 *
 * O número é reservado com SELECT ... FOR UPDATE: duas pessoas a validar ao mesmo tempo esperam
 * uma pela outra e recebem números diferentes. Tem de ser chamado dentro da transacção que grava o
 * registo, para que um erro desfaça também a reserva do número. (Herdado do nsERP.)
 */
class Numeracao
{
    /** Formatos de cada série: [formato, nº de dígitos]. {ano} e {n} são substituídos. */
    public const FORMATOS = [
        'estudante' => ['{ano}/{n}', 5],
        'candidatura' => ['CD{ano}/{n}', 5],
        'matricula' => ['MT{ano}/{n}', 5],
        'inscricao' => ['IN{ano}/{n}', 5],
        'pauta' => ['PA{ano}/{n}', 4],
        'cobranca' => ['CB{ano}/{n}', 5],
        'recibo' => ['RC{ano}/{n}', 4],
        'documento' => ['DC{ano}/{n}', 4],
        'pedido_documento' => ['PD{ano}/{n}', 4],
    ];

    /** Nome legível de cada série (Configuração → Numeração). */
    public const NOMES = [
        'estudante' => 'Número de estudante',
        'candidatura' => 'Candidaturas',
        'matricula' => 'Matrículas',
        'inscricao' => 'Inscrições semestrais',
        'pauta' => 'Pautas',
        'cobranca' => 'Cobranças',
        'recibo' => 'Recibos',
        'documento' => 'Documentos académicos emitidos',
        'pedido_documento' => 'Pedidos de documentos',
    ];

    public function proximo(string $serie, ?int $ano = null): string
    {
        $ano ??= (int) now()->format('Y');

        if (DB::transactionLevel() === 0) {
            throw new \LogicException('A numeração tem de ser pedida dentro de uma transacção.');
        }

        DB::table('numeracoes')->insertOrIgnore([
            'serie' => $serie, 'ano' => $ano, 'ultimo' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $linha = DB::table('numeracoes')->where('serie', $serie)->where('ano', $ano)->lockForUpdate()->first();
        $numero = $linha->ultimo + 1;

        DB::table('numeracoes')->where('id', $linha->id)->update(['ultimo' => $numero, 'updated_at' => now()]);

        return self::formatar($serie, $ano, $numero);
    }

    public static function formatar(string $serie, int $ano, int $numero): string
    {
        [$formato, $digitos] = self::FORMATOS[$serie] ?? ['{n}', 4];

        return strtr($formato, ['{ano}' => (string) $ano, '{n}' => str_pad((string) $numero, $digitos, '0', STR_PAD_LEFT)]);
    }
}

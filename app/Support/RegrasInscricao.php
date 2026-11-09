<?php

namespace App\Support;

/**
 * Regras da inscrição semestral (RN-05): o limite de créditos por semestre. Lê-se da Configuração
 * (definição "inscricao.limite_creditos") e, se ainda não foi gravado, do config/nsedu.php.
 */
final class RegrasInscricao
{
    public static function limiteCreditos(): float
    {
        $valor = app(Definicoes::class)->obter('inscricao.limite_creditos');

        return $valor !== null && is_numeric($valor) ? (float) $valor : (float) config('nsedu.inscricao.limite_creditos', 36);
    }
}

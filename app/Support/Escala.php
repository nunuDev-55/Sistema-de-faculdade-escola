<?php

namespace App\Support;

/**
 * Notas: nunca com float (como o dinheiro). Viajam em texto, guardam-se em decimal(5,2) e arredondam-se
 * só no fim, da forma configurada (ver docs/05-arquitectura.md, regra 2; RN-01).
 */
final class Escala
{
    /** Lê uma nota escrita à moçambicana ("12,5") e devolve-a normalizada ("12.50"), ou null se vazia. */
    public static function lerNumero(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }
        $texto = trim(str_replace([' ', ','], ['', '.'], $texto));
        if ($texto === '' || ! is_numeric($texto)) {
            return null;
        }

        return number_format((float) $texto, 2, '.', '');
    }

    /** A nota é válida dentro da escala (0 até ao máximo configurado)? */
    public static function valida(string $nota): bool
    {
        $valor = (float) $nota;

        return $valor >= 0 && $valor <= (float) config('nsedu.avaliacao.escala_maxima');
    }

    /** Arredonda uma nota às casas decimais configuradas. */
    public static function arredondar(string $nota): string
    {
        return number_format((float) $nota, (int) config('nsedu.avaliacao.casas_decimais'), '.', '');
    }

    /**
     * Situação do estudante numa disciplina, a partir da média de frequência (ensino superior, RN-02).
     * Os limites mudam-se em Configuração.
     */
    public static function situacaoFrequencia(string $media): string
    {
        $valor = (float) $media;
        if ($valor < (float) config('nsedu.avaliacao.minimo_admissao_exame')) {
            return 'excluido';
        }
        if ($valor >= (float) config('nsedu.avaliacao.minimo_dispensa_exame')) {
            return 'dispensado';
        }

        return 'admitido';
    }
}

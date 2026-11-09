<?php

namespace App\Support;

/** Listas de opções para as caixas de escolha dos formulários (valor + rótulo). */
final class Opcoes
{
    /**
     * @param  array<string, string>  $mapa
     * @return list<array{valor: string, rotulo: string}>
     */
    public static function de(array $mapa): array
    {
        $lista = [];
        foreach ($mapa as $valor => $rotulo) {
            $lista[] = ['valor' => (string) $valor, 'rotulo' => $rotulo];
        }

        return $lista;
    }
}

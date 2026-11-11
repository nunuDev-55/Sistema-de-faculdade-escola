<?php

namespace App\Support;

/** Pesquisas escritas pelas pessoas nas listas (do nsERP). */
final class Pesquisa
{
    /** Prepara texto para um LIKE '%...%', tratando % e _ como letras normais. */
    public static function like(string $texto): string
    {
        return '%'.addcslashes(trim($texto), '\\%_').'%';
    }
}

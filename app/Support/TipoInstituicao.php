<?php

namespace App\Support;

/**
 * O mesmo código serve dois tipos de instituição, escolhidos em Configuração (ver docs/02-requisitos.md):
 *
 * - superior: universidade ou instituto (cursos, semestres, créditos, inscrição por disciplina, exames e recorrência)
 * - geral:    escola do ensino primário e secundário (classes, trimestres, passagem de classe)
 *
 * Só muda os termos e algumas regras; a estrutura é a mesma. Os ecrãs pedem os termos por useTermos() no frontend.
 */
final class TipoInstituicao
{
    public const SUPERIOR = 'superior';

    public const GERAL = 'geral';

    /** Termos de cada tipo, para os ecrãs e os documentos. */
    public const TERMOS = [
        self::SUPERIOR => [
            'curso' => 'Curso',
            'periodo' => 'Semestre',
            'ano_curricular' => 'Ano',
            'disciplina' => 'Disciplina',
            'turma' => 'Turma',
        ],
        self::GERAL => [
            'curso' => 'Classe',
            'periodo' => 'Trimestre',
            'ano_curricular' => 'Classe',
            'disciplina' => 'Disciplina',
            'turma' => 'Turma',
        ],
    ];

    public static function actual(): string
    {
        $tipo = app(Definicoes::class)->obter('instituicao.tipo', (string) config('nsedu.instituicao.tipo'));

        return $tipo === self::GERAL ? self::GERAL : self::SUPERIOR;
    }

    public static function eSuperior(): bool
    {
        return self::actual() === self::SUPERIOR;
    }

    /** Semestres por ano (superior) ou trimestres por ano (geral). */
    public static function periodosPorAno(): int
    {
        return self::eSuperior() ? 2 : 3;
    }

    /** "semestre" ou "trimestre", para os períodos lectivos gerados. */
    public static function tipoPeriodo(): string
    {
        return self::eSuperior() ? 'semestre' : 'trimestre';
    }

    /** @return array<string, string> */
    public static function termos(): array
    {
        return self::TERMOS[self::actual()];
    }
}

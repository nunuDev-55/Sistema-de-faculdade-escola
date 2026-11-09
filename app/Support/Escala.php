<?php

namespace App\Support;

/**
 * Escala de classificação e notas.
 *
 * As notas nunca andam em float para contas de dinheiro, mas para comparação e arredondamento de nota usam-se
 * como número. Guardam-se em decimal(5,2) e arredondam-se só no fim (ver docs/05-arquitectura.md, regra 2).
 *
 * A escala é configurável por **intervalos** (padrão do Frappe Education, ver docs/08-estudo-sistemas-academicos.md):
 * cada instituição define os seus (0–20, 0–100, A–F…) e o grau de cada intervalo. Os valores de origem estão em
 * config/nsedu.php; quando o módulo de Configuração existir, a escala passa a vir da base de dados.
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

    public static function maxima(): float
    {
        return (float) config('nsedu.avaliacao.escala_maxima');
    }

    /** A nota é válida dentro da escala (0 até ao máximo configurado)? */
    public static function valida(string $nota): bool
    {
        $valor = (float) $nota;

        return $valor >= 0 && $valor <= self::maxima();
    }

    /** Arredonda uma nota às casas decimais configuradas. */
    public static function arredondar(string $nota): string
    {
        return number_format((float) $nota, (int) config('nsedu.avaliacao.casas_decimais'), '.', '');
    }

    /**
     * O grau (menção qualitativa) de uma nota, a partir dos intervalos configurados: a nota cai no intervalo
     * cujo mínimo e máximo a contêm (ex.: 16,0 → "Muito Bom"). Vazio se não houver intervalo definido.
     */
    public static function grau(string $nota): ?string
    {
        // Arredonda primeiro às casas da escala: os intervalos são contíguos a esse passo (9,49 → 9,50)
        $valor = round((float) $nota, self::casasDecimais());
        foreach (self::intervalos() as $intervalo) {
            if ($valor >= $intervalo['min'] && $valor <= $intervalo['max']) {
                return $intervalo['grau'];
            }
        }

        return null;
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

    public static function aprovado(string $nota): bool
    {
        return (float) $nota >= (float) config('nsedu.avaliacao.nota_minima_aprovacao');
    }

    /**
     * Intervalos da escala, ordenados pelo mínimo.
     *
     * @return list<array{min: float, max: float, grau: string}>
     */
    public static function intervalos(): array
    {
        /** @var list<array{min: float|int, max: float|int, grau: string}> $config */
        $config = config('nsedu.avaliacao.intervalos', []);
        $intervalos = array_map(
            fn (array $i): array => ['min' => (float) $i['min'], 'max' => (float) $i['max'], 'grau' => (string) $i['grau']],
            $config,
        );
        usort($intervalos, fn (array $a, array $b) => $a['min'] <=> $b['min']);

        return $intervalos;
    }

    /**
     * Valida uma escala configurada pelo administrador (regra do Frappe Education): os intervalos começam em 0,
     * cada mínimo é menor do que o máximo, não se sobrepõem nem deixam falhas, e o último chega ao máximo da escala.
     * Como as notas têm casas decimais, dois intervalos são contíguos quando o seguinte começa um passo depois
     * do anterior (com 2 casas: 9,49 → 9,50). Devolve a lista de problemas (vazia = válida), para a ficha os mostrar.
     *
     * @param  list<array{min: float, max: float, grau: string}>  $intervalos
     * @return list<string>
     */
    public static function problemasDosIntervalos(array $intervalos): array
    {
        if ($intervalos === []) {
            return ['Defina pelo menos um intervalo para a escala.'];
        }

        usort($intervalos, fn (array $a, array $b) => $a['min'] <=> $b['min']);
        $problemas = [];
        $passo = 10 ** -self::casasDecimais();
        $margem = 0.000001;

        if (abs($intervalos[0]['min']) > $margem) {
            $problemas[] = 'O primeiro intervalo tem de começar em 0.';
        }

        foreach ($intervalos as $n => $i) {
            if ($i['min'] >= $i['max']) {
                $problemas[] = "O intervalo \"{$i['grau']}\" tem o mínimo igual ou acima do máximo.";
            }
            if ($n === 0) {
                continue;
            }
            $anterior = $intervalos[$n - 1];
            if ($i['min'] <= $anterior['max'] + $margem) {
                $problemas[] = "Os intervalos \"{$anterior['grau']}\" e \"{$i['grau']}\" sobrepõem-se.";
            } elseif ($i['min'] - $anterior['max'] > $passo + $margem) {
                $problemas[] = "Entre \"{$anterior['grau']}\" e \"{$i['grau']}\" há notas sem intervalo.";
            }
        }

        $ultimo = $intervalos[count($intervalos) - 1];
        if (abs($ultimo['max'] - self::maxima()) > $margem) {
            $problemas[] = 'O último intervalo tem de chegar ao máximo da escala ('.self::maxima().').';
        }

        return $problemas;
    }

    private static function casasDecimais(): int
    {
        return (int) config('nsedu.avaliacao.casas_decimais');
    }
}

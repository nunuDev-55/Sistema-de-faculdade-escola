<?php

namespace App\Support;

/**
 * Escala de classificação e notas.
 *
 * As notas guardam-se em decimal(5,2) e arredondam-se só no fim (ver docs/05-arquitectura.md, regra 2).
 *
 * A escala é configurável por **intervalos** (padrão do Frappe Education, ver docs/08-estudo-sistemas-academicos.md):
 * cada instituição define os seus em Administração → Configuração → Avaliação. Enquanto não os gravar, valem os de
 * origem em config/nsedu.php.
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
        return (float) self::parametro('escala_maxima');
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
        return number_format((float) $nota, self::casasDecimais(), '.', '');
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
        if ($valor < (float) self::parametro('minimo_admissao_exame')) {
            return 'excluido';
        }
        if ($valor >= (float) self::parametro('minimo_dispensa_exame')) {
            return 'dispensado';
        }

        return 'admitido';
    }

    /** Nota final de quem foi a exame: a média de frequência e a do exame, com o peso do exame da Configuração (RN-03). */
    public static function notaFinal(string $mediaFrequencia, string $exame): string
    {
        $peso = max(0.0, min(100.0, (float) self::parametro('peso_exame'))) / 100;

        return self::arredondar((string) ((float) $mediaFrequencia * (1 - $peso) + (float) $exame * $peso));
    }

    public static function aprovado(string $nota): bool
    {
        return (float) $nota >= (float) self::parametro('nota_minima_aprovacao');
    }

    /**
     * Intervalos da escala, ordenados pelo mínimo.
     *
     * @return list<array{min: float, max: float, grau: string}>
     */
    public static function intervalos(): array
    {
        $guardados = self::parametro('intervalos');

        return self::normalizar(is_array($guardados) ? $guardados : []);
    }

    /**
     * @param  array<int|string, mixed>  $intervalos
     * @return list<array{min: float, max: float, grau: string}>
     */
    public static function normalizar(array $intervalos): array
    {
        $lista = [];
        foreach ($intervalos as $i) {
            if (is_array($i)) {
                $lista[] = ['min' => (float) ($i['min'] ?? 0), 'max' => (float) ($i['max'] ?? 0), 'grau' => trim((string) ($i['grau'] ?? ''))];
            }
        }
        usort($lista, fn (array $a, array $b) => $a['min'] <=> $b['min']);

        return $lista;
    }

    /**
     * Valida uma escala (regra do Frappe Education): os intervalos começam em 0, cada mínimo é menor do que o
     * máximo, não se sobrepõem nem deixam falhas, e o último chega ao máximo da escala. Como as notas têm casas
     * decimais, dois intervalos são contíguos quando o seguinte começa um passo depois do anterior (com 2 casas:
     * 9,49 → 9,50). Devolve a lista de problemas (vazia = válida), para a ficha os mostrar.
     * Ao gravar uma escala nova, passa-se o máximo e as casas que vão ser gravados.
     *
     * @param  list<array{min: float, max: float, grau: string}>  $intervalos
     * @return list<string>
     */
    public static function problemasDosIntervalos(array $intervalos, ?float $maxima = null, ?int $casas = null): array
    {
        if ($intervalos === []) {
            return ['Defina pelo menos um intervalo para a escala.'];
        }

        $maxima ??= self::maxima();
        $casas ??= self::casasDecimais();
        usort($intervalos, fn (array $a, array $b) => $a['min'] <=> $b['min']);
        $problemas = [];
        $passo = 1 / (10 ** $casas);
        $margem = 0.000001;

        if (abs($intervalos[0]['min']) > $margem) {
            $problemas[] = 'O primeiro intervalo tem de começar em 0.';
        }

        foreach ($intervalos as $n => $i) {
            if ($i['grau'] === '') {
                $problemas[] = 'Todos os intervalos precisam do nome do grau.';
            }
            if ($i['min'] >= $i['max']) {
                $problemas[] = "O intervalo \"{$i['grau']}\" tem o mínimo igual ou acima do máximo.";
            }
            if ($n === 0) {
                continue;
            }
            $anterior = $intervalos[$n - 1];
            if ($i['min'] <= $anterior['max'] + $margem) {
                $problemas[] = "Os intervalos \"{$anterior['grau']}\" e \"{$i['grau']}\" sobrepõem-se.";
            } elseif ($passo + $margem < $i['min'] - $anterior['max']) {
                $problemas[] = "Entre \"{$anterior['grau']}\" e \"{$i['grau']}\" há notas sem intervalo.";
            }
        }

        $ultimo = $intervalos[count($intervalos) - 1];
        if (abs($ultimo['max'] - $maxima) > $margem) {
            $problemas[] = 'O último intervalo tem de chegar ao máximo da escala ('.$maxima.').';
        }

        return array_values(array_unique($problemas));
    }

    /**
     * Os valores da avaliação: os gravados em Configuração (tabela definicoes) ou, sem eles, os de config/nsedu.php.
     */
    public static function parametro(string $chave): mixed
    {
        $guardado = app(Definicoes::class)->obter('avaliacao.'.$chave);
        if ($guardado !== null) {
            return $chave === 'intervalos' ? json_decode($guardado, true) : $guardado;
        }

        return config('nsedu.avaliacao.'.$chave);
    }

    private static function casasDecimais(): int
    {
        return (int) self::parametro('casas_decimais');
    }
}

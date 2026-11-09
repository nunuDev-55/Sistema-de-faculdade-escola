<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Configuração da instituição (chave → valor), lida da base de dados uma vez por pedido.
 * O que é da instituição (nome, sigla, cores, logótipo, tipo, regras pedagógicas) vive aqui, nunca no código,
 * para o mesmo sistema servir qualquer instituição (ver docs/05-arquitectura.md). Registado como singleton por
 * pedido em AppServiceProvider.
 */
class Definicoes
{
    /** Cor principal de origem (azul académico); muda-se em Configuração. */
    public const COR = '#1f3a8a';

    public const COR_DESTAQUE = '#0ea5b7';

    /** @var array<string, string|null>|null */
    private ?array $valores = null;

    /** @return array<string, string|null> */
    public function todas(): array
    {
        if ($this->valores === null) {
            $this->valores = DB::table('definicoes')->pluck('valor', 'chave')->all();
        }

        return $this->valores;
    }

    public function obter(string $chave, ?string $omissao = null): ?string
    {
        $valor = $this->todas()[$chave] ?? null;

        return ($valor === null || $valor === '') ? $omissao : $valor;
    }

    /**
     * Grava um conjunto de definições e deixa o registo na auditoria.
     *
     * @param  array<string, string|null>  $valores
     * @return array<string, string|null>
     */
    public function guardar(array $valores, ?string $descricao = null): array
    {
        foreach ($valores as $chave => $valor) {
            DB::table('definicoes')->updateOrInsert(
                ['chave' => $chave],
                ['valor' => $valor, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->valores = null;

        activity('configuracao')
            ->withProperties(['chaves' => array_keys($valores)])
            ->log($descricao ?? 'Configuração alterada');

        return $this->todas();
    }

    /** Nome curto da instituição: a sigla, se houver; senão o nome. */
    public function nomeCurto(): string
    {
        return $this->obter('instituicao.sigla') ?: $this->nome();
    }

    public function nome(): string
    {
        return (string) $this->obter('instituicao.nome', (string) config('nsedu.instituicao.nome'));
    }

    /**
     * As cores da instituição, já validadas, com o texto legível por cima de cada uma.
     *
     * @return array{cor: string, sobre_cor: string, destaque: string, sobre_destaque: string}
     */
    public function cores(): array
    {
        $cor = $this->corValida($this->obter('instituicao.cor'), self::COR);
        $destaque = $this->corValida($this->obter('instituicao.cor_destaque'), self::COR_DESTAQUE);

        return [
            'cor' => $cor,
            'sobre_cor' => $this->textoLegivel($cor),
            'destaque' => $destaque,
            'sobre_destaque' => $this->textoLegivel($destaque),
        ];
    }

    /**
     * Nome, logótipo e cor para as páginas de erro (403, 404, 500...). Nunca falha: a página de erro pode
     * aparecer justamente porque a base de dados está em baixo. (Herdado do nsERP.)
     *
     * @return array{nome: string, logotipo: string|null, cor: string}
     */
    public static function paraPaginaDeErro(): array
    {
        try {
            $d = app(self::class);

            return ['nome' => $d->nomeCurto(), 'logotipo' => $d->urlLogotipo(), 'cor' => $d->cores()['cor']];
        } catch (\Throwable) {
            return ['nome' => (string) config('nsedu.produto.nome'), 'logotipo' => null, 'cor' => self::COR];
        }
    }

    public function urlLogotipo(): ?string
    {
        return $this->obter('instituicao.logotipo') ? route('instituicao.logotipo') : null;
    }

    /**
     * O que falta configurar para o sistema funcionar bem (aviso no Início, como no nsERP).
     *
     * @return list<string>
     */
    public function emFalta(): array
    {
        $falta = [];
        if (! $this->obter('instituicao.nome')) {
            $falta[] = 'O nome da instituição ainda não está configurado.';
        }
        if (! $this->obter('instituicao.nuit')) {
            $falta[] = 'Falta o NUIT da instituição (aparece nos documentos).';
        }
        if (! $this->obter('instituicao.logotipo')) {
            $falta[] = 'Ainda não carregou o logótipo da instituição.';
        }

        return $falta;
    }

    private function corValida(?string $valor, string $omissao): string
    {
        return ($valor !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) === 1) ? $valor : $omissao;
    }

    /** Preto ou branco, conforme o que se lê melhor por cima da cor (luminância relativa). */
    private function textoLegivel(string $hex): string
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $luminancia = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminancia > 0.6 ? '#111111' : '#ffffff';
    }
}

<?php

namespace App\Services;

use App\Models\ComponenteAvaliacao;
use App\Models\Docente;
use App\Models\Inscricao;
use App\Models\Leccionacao;
use App\Models\Nota;
use App\Models\Pauta;
use App\Models\PautaLinha;
use App\Models\User;
use App\Support\Escala;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avaliação e pautas (docs/06-processos-e-estados.md, secções 3 e 4; RN-02, RN-03, RN-09, RN-10).
 *
 * Frequência: o docente define o plano de avaliação (pesos que somam 100 %) e lança as notas; a média decide se o
 * estudante fica excluído, admitido a exame ou dispensado. Exame normal e recorrência: a nota final junta a média de
 * frequência e a do exame. Cada pauta passa por rascunho → submetida → validada → publicada, e só ao publicar os
 * resultados chegam às inscrições (é isso que abre as precedências).
 */
class PautaService
{
    public function __construct(private Numeracao $numeracao) {}

    /** Lança notas nesta disciplina quem a dá (a distribuição de serviço, RN-10); o administrador também, para corrigir. */
    public function podeLancar(User $user, Leccionacao $leccionacao): bool
    {
        if ($user->isAdministrador()) {
            return true;
        }

        return $user->can('notas.lancar') && $leccionacao->docente_id !== null
            && Docente::where('user_id', $user->id)->where('id', $leccionacao->docente_id)->exists();
    }

    /** Vêem todas as pautas a direcção, os directores de curso e a secretaria; o docente vê as suas. */
    public function podeVer(User $user, Leccionacao $leccionacao): bool
    {
        return ($user->can('pautas.ver') && ($user->can('pautas.validar') || $user->can('turmas.ver')))
            || $this->podeLancar($user, $leccionacao);
    }

    /** A pauta de frequência da disciplina (nasce em lançamento na primeira vez). */
    public function frequencia(Leccionacao $leccionacao): Pauta
    {
        return Pauta::firstOrCreate(['leccionacao_id' => $leccionacao->id, 'epoca' => Pauta::FREQUENCIA], ['estado' => Pauta::RASCUNHO]);
    }

    /**
     * O plano de avaliação: os componentes e os seus pesos (somam 100 %). Só enquanto a frequência está em lançamento.
     *
     * @param  list<array{id?: int|null, nome: string, peso: string, data?: string|null}>  $componentes
     */
    public function definirPlano(Leccionacao $leccionacao, array $componentes): void
    {
        if (! $this->frequencia($leccionacao)->editavel()) {
            throw ValidationException::withMessages(['componentes' => 'A pauta de frequência já foi submetida: o plano de avaliação já não se altera.']);
        }
        $soma = array_sum(array_map(fn (array $c) => (float) $c['peso'], $componentes));
        if (abs($soma - 100) > 0.001) {
            throw ValidationException::withMessages(['componentes' => 'Os pesos têm de somar 100 % (agora somam '.str_replace('.', ',', (string) round($soma, 2)).' %).']);
        }

        DB::transaction(function () use ($leccionacao, $componentes) {
            $existentes = $leccionacao->componentes()->get()->keyBy('id');
            $manter = [];
            foreach (array_values($componentes) as $i => $c) {
                $dados = ['nome' => trim($c['nome']), 'peso' => $c['peso'], 'data' => $c['data'] ?? null, 'ordem' => $i + 1];
                $id = $c['id'] ?? null;
                $componente = $id !== null ? $existentes->get($id) : null;
                if ($componente !== null) {
                    $componente->update($dados);
                } else {
                    $componente = $leccionacao->componentes()->create($dados);
                }
                $manter[] = $componente->id;
            }
            // Os componentes retirados levam as suas notas
            $leccionacao->componentes()->whereNotIn('id', $manter)->delete();
            $this->registar($this->frequencia($leccionacao), 'Plano de avaliação de '.$leccionacao->disciplina->nome.': '
                .implode(', ', array_map(fn (array $c) => trim($c['nome']).' '.$c['peso'].'%', $componentes)));
        });
    }

    /**
     * Lança as notas dos componentes. Cada nota é escrita como no papel: "12,5", "F" (faltou, conta zero) ou vazio.
     *
     * @param  array<int|string, array<int|string, string|null>>  $notas  inscrição → componente → nota
     */
    public function lancarFrequencia(Leccionacao $leccionacao, array $notas, User $autor): void
    {
        $pauta = $this->frequencia($leccionacao);
        $this->garantirEmLancamento($pauta);
        $componentes = $leccionacao->componentes()->get()->keyBy('id');
        if ($componentes->isEmpty()) {
            throw ValidationException::withMessages(['notas' => 'Defina primeiro o plano de avaliação (os testes, os trabalhos e os seus pesos).']);
        }
        $inscricoes = $leccionacao->inscricoes()->pluck('id')->flip();

        $gravar = [];
        $erros = [];
        foreach ($notas as $inscricaoId => $porComponente) {
            if (! $inscricoes->has((int) $inscricaoId)) {
                continue;
            }
            foreach ($porComponente as $componenteId => $texto) {
                if (! $componentes->has((int) $componenteId)) {
                    continue;
                }
                $lida = $this->lerNota($texto);
                if ($lida === false) {
                    $erros[] = '"'.$texto.'"';

                    continue;
                }
                $gravar[] = ['componente_id' => (int) $componenteId, 'inscricao_id' => (int) $inscricaoId, ...$lida];
            }
        }
        if ($erros !== []) {
            throw ValidationException::withMessages(['notas' => 'Notas que não se percebem: '.implode(', ', array_unique($erros))
                .'. Escreva um número de 0 a '.str_replace('.', ',', (string) Escala::maxima()).', ou F para quem faltou.']);
        }

        DB::transaction(function () use ($gravar, $autor, $pauta, $leccionacao) {
            foreach ($gravar as $g) {
                Nota::updateOrCreate(
                    ['componente_id' => $g['componente_id'], 'inscricao_id' => $g['inscricao_id']],
                    ['valor' => $g['valor'], 'faltou' => $g['faltou'], 'lancada_por' => $autor->id],
                );
            }
            $this->registar($pauta, 'Notas de frequência de '.$leccionacao->disciplina->nome.' lançadas ('.count($gravar).')');
        });
    }

    /**
     * Lança as notas de uma pauta de exame (normal ou recorrência).
     *
     * @param  array<int|string, string|null>  $notas  inscrição → nota ("12,5", "F" ou vazio)
     */
    public function lancarExame(Pauta $pauta, array $notas): void
    {
        $this->garantirEmLancamento($pauta);
        if ($pauta->epoca === Pauta::FREQUENCIA) {
            throw ValidationException::withMessages(['notas' => 'As notas de frequência lançam-se por componente.']);
        }
        $linhas = $pauta->linhas()->get()->keyBy('inscricao_id');
        $erros = [];
        $gravar = [];
        foreach ($notas as $inscricaoId => $texto) {
            $linha = $linhas->get((int) $inscricaoId);
            if ($linha === null) {
                continue;
            }
            $lida = $this->lerNota($texto);
            if ($lida === false) {
                $erros[] = '"'.$texto.'"';

                continue;
            }
            $gravar[] = [$linha, $lida];
        }
        if ($erros !== []) {
            throw ValidationException::withMessages(['notas' => 'Notas que não se percebem: '.implode(', ', array_unique($erros)).'.']);
        }

        DB::transaction(function () use ($gravar, $pauta) {
            foreach ($gravar as [$linha, $lida]) {
                $linha->update(['nota' => $lida['valor'], 'faltou' => $lida['faltou']]);
            }
            $this->registar($pauta, 'Notas do '.mb_strtolower(Pauta::EPOCAS[$pauta->epoca]).' lançadas ('.count($gravar).')');
        });
    }

    /**
     * Média de frequência: cada nota vezes o peso; faltar conta zero. Sem todas as notas lançadas não há média.
     *
     * @param  Collection<int, ComponenteAvaliacao>  $componentes
     * @param  Collection<array-key, Nota>  $notas  as notas do estudante, por componente
     */
    public function mediaFrequencia(Collection $componentes, Collection $notas): ?string
    {
        if ($componentes->isEmpty()) {
            return null;
        }
        $soma = 0.0;
        foreach ($componentes as $componente) {
            $nota = $notas->get($componente->id);
            if ($nota === null || (! $nota->faltou && $nota->valor === null)) {
                return null;
            }
            $soma += ($nota->faltou ? 0.0 : (float) $nota->valor) * (float) $componente->peso / 100;
        }

        return Escala::arredondar((string) $soma);
    }

    /** O docente entrega a pauta: fica com as médias e as situações e deixa de se poder alterar até ser devolvida. */
    public function submeter(Pauta $pauta, User $autor): void
    {
        $this->garantirEmLancamento($pauta);

        DB::transaction(function () use ($pauta, $autor) {
            if ($pauta->epoca === Pauta::FREQUENCIA) {
                $this->fecharFrequencia($pauta);
            } else {
                $this->fecharExame($pauta);
            }
            $pauta->update(['estado' => Pauta::SUBMETIDA, 'submetida_em' => now(), 'submetida_por' => $autor->id, 'observacao' => null]);
            $this->registar($pauta, 'Pauta de '.mb_strtolower(Pauta::EPOCAS[$pauta->epoca]).' submetida');
        });
    }

    public function validar(Pauta $pauta, User $autor): void
    {
        $this->exigirEstado($pauta, Pauta::SUBMETIDA, 'validar');
        DB::transaction(function () use ($pauta, $autor) {
            $pauta->update(['estado' => Pauta::VALIDADA, 'validada_em' => now(), 'validada_por' => $autor->id]);
            $this->registar($pauta, 'Pauta de '.mb_strtolower(Pauta::EPOCAS[$pauta->epoca]).' validada');
        });
    }

    /** Volta ao docente para corrigir, com o motivo. */
    public function devolver(Pauta $pauta, string $motivo): void
    {
        if (! in_array($pauta->estado, [Pauta::SUBMETIDA, Pauta::VALIDADA], true)) {
            throw ValidationException::withMessages(['pauta' => 'Só se devolve uma pauta submetida ou validada.']);
        }
        DB::transaction(function () use ($pauta, $motivo) {
            $pauta->update(['estado' => Pauta::RASCUNHO, 'observacao' => $motivo, 'validada_em' => null, 'validada_por' => null]);
            $this->registar($pauta, 'Pauta de '.mb_strtolower(Pauta::EPOCAS[$pauta->epoca]).' devolvida ao docente', ['motivo' => $motivo]);
        });
    }

    /** Publica: leva número e os resultados passam para as inscrições; abre a época seguinte para quem precisa. */
    public function publicar(Pauta $pauta, User $autor): void
    {
        $this->exigirEstado($pauta, Pauta::VALIDADA, 'publicar');

        DB::transaction(function () use ($pauta, $autor) {
            $ano = (int) $pauta->leccionacao->turma->anoLectivo->inicio->format('Y');
            $pauta->update([
                'codigo' => $this->numeracao->proximo('pauta', $ano),
                'estado' => Pauta::PUBLICADA,
                'publicada_em' => now(),
                'publicada_por' => $autor->id,
            ]);

            $seguinte = [];
            foreach ($pauta->linhas()->with('inscricao')->get() as $linha) {
                $resultado = match ($linha->situacao) {
                    'dispensado', 'aprovado' => Inscricao::APROVADO,
                    'excluido' => 'excluido',
                    // Admitido a exame, ou reprovado no exame normal: segue para a época seguinte
                    'admitido' => null,
                    default => $pauta->epoca === Pauta::NORMAL ? null : 'reprovado',
                };
                if ($resultado === null) {
                    $seguinte[] = $linha->inscricao_id;
                } elseif ($linha->inscricao->estado === Inscricao::INSCRITO) {
                    $linha->inscricao->update(['estado' => $resultado, 'nota_final' => $linha->nota_final ?? $linha->nota]);
                }
            }

            $proxima = match ($pauta->epoca) {
                Pauta::FREQUENCIA => Pauta::NORMAL,
                Pauta::NORMAL => Pauta::RECORRENCIA,
                default => null,
            };
            if ($proxima !== null && $seguinte !== []) {
                $nova = Pauta::firstOrCreate(['leccionacao_id' => $pauta->leccionacao_id, 'epoca' => $proxima], ['estado' => Pauta::RASCUNHO]);
                foreach ($seguinte as $inscricaoId) {
                    $nova->linhas()->firstOrCreate(['inscricao_id' => $inscricaoId]);
                }
            }
            $this->registar($pauta, "Pauta {$pauta->codigo} de ".mb_strtolower(Pauta::EPOCAS[$pauta->epoca]).' publicada');
        });
    }

    /** Frequência: as médias e as situações (excluído, admitido a exame, dispensado) de cada estudante. */
    private function fecharFrequencia(Pauta $pauta): void
    {
        $leccionacao = $pauta->leccionacao;
        $componentes = $leccionacao->componentes()->get();
        if ($componentes->isEmpty()) {
            throw ValidationException::withMessages(['pauta' => 'A disciplina ainda não tem plano de avaliação.']);
        }
        $inscricoes = $leccionacao->inscricoes()->get();
        if ($inscricoes->isEmpty()) {
            throw ValidationException::withMessages(['pauta' => 'A turma ainda não tem estudantes nesta disciplina.']);
        }
        $notas = Nota::whereIn('componente_id', $componentes->pluck('id'))->get()->groupBy('inscricao_id');

        $medias = [];
        $faltam = 0;
        foreach ($inscricoes as $inscricao) {
            $media = $this->mediaFrequencia($componentes, ($notas->get($inscricao->id) ?? collect())->keyBy('componente_id'));
            if ($media === null) {
                $faltam++;
            }
            $medias[$inscricao->id] = $media;
        }
        if ($faltam > 0) {
            throw ValidationException::withMessages(['pauta' => ($faltam === 1 ? 'Falta lançar notas de 1 estudante' : "Falta lançar notas de {$faltam} estudantes")
                .': escreva a nota de cada componente, ou F para quem faltou, antes de submeter.']);
        }

        $pauta->linhas()->delete();
        foreach ($medias as $inscricaoId => $media) {
            $situacao = Escala::situacaoFrequencia((string) $media);
            $pauta->linhas()->create([
                'inscricao_id' => $inscricaoId,
                'nota' => $media,
                'nota_final' => $situacao === 'admitido' ? null : $media,
                'situacao' => $situacao,
            ]);
        }
    }

    /** Exame: a nota final (frequência e exame) e se aprovou; quem faltou reprova nesta época. */
    private function fecharExame(Pauta $pauta): void
    {
        $linhas = $pauta->linhas()->get();
        $faltam = $linhas->filter(fn (PautaLinha $l) => $l->nota === null && ! $l->faltou)->count();
        if ($faltam > 0) {
            throw ValidationException::withMessages(['pauta' => ($faltam === 1 ? 'Falta a nota de 1 estudante' : "Faltam as notas de {$faltam} estudantes")
                .': escreva a nota, ou F para quem faltou, antes de submeter.']);
        }
        $medias = PautaLinha::whereHas('pauta', fn ($q) => $q->where('leccionacao_id', $pauta->leccionacao_id)->where('epoca', Pauta::FREQUENCIA))
            ->pluck('nota', 'inscricao_id');

        foreach ($linhas as $linha) {
            if ($linha->faltou || $linha->nota === null) {
                $linha->update(['nota_final' => null, 'situacao' => 'reprovado']);

                continue;
            }
            $exame = (string) $linha->nota;
            $final = Escala::notaFinal((string) ($medias->get($linha->inscricao_id) ?? $exame), $exame);
            $linha->update(['nota_final' => $final, 'situacao' => Escala::aprovado($final) ? 'aprovado' : 'reprovado']);
        }
    }

    /**
     * "12,5" → 12.50; "F" → faltou; vazio → sem nota; outra coisa → false.
     *
     * @return array{valor: string|null, faltou: bool}|false
     */
    private function lerNota(?string $texto): array|false
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return ['valor' => null, 'faltou' => false];
        }
        if (in_array(mb_strtoupper($texto), ['F', 'FALTOU'], true)) {
            return ['valor' => null, 'faltou' => true];
        }
        $numero = Escala::lerNumero($texto);
        if ($numero === null || ! is_numeric($numero) || ! Escala::valida($numero)) {
            return false;
        }

        return ['valor' => Escala::arredondar($numero), 'faltou' => false];
    }

    private function garantirEmLancamento(Pauta $pauta): void
    {
        if (! $pauta->editavel()) {
            $estado = mb_strtolower(Pauta::ESTADOS[$pauta->estado] ?? $pauta->estado);
            throw ValidationException::withMessages(['notas' => "A pauta está {$estado}: as notas já não se alteram"
                .($pauta->estado === Pauta::PUBLICADA ? ' (corrige-se por revisão de nota).' : ' (peça que a devolvam).')]);
        }
    }

    private function exigirEstado(Pauta $pauta, string $estado, string $accao): void
    {
        if ($pauta->estado !== $estado) {
            $actual = mb_strtolower(Pauta::ESTADOS[$pauta->estado] ?? $pauta->estado);
            throw ValidationException::withMessages(['pauta' => "A pauta está {$actual}: não se pode {$accao}."]);
        }
    }

    /** @param array<string, mixed> $propriedades */
    private function registar(Pauta $pauta, string $descricao, array $propriedades = []): void
    {
        activity('pautas')->performedOn($pauta)->withProperties($propriedades)->log($descricao);
    }
}

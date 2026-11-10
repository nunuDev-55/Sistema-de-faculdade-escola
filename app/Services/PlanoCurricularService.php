<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PlanoCurricular;
use App\Models\PlanoDisciplina;
use App\Models\Precedencia;
use App\Models\User;
use App\Support\TipoInstituicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida do plano curricular (docs/06-processos-e-estados.md):
 * rascunho → aprovado (em vigor) → substituído quando outra versão é aprovada.
 *
 * Só o rascunho se altera (RN-15). Cada estudante fica no plano em que entrou, por isso um plano aprovado
 * nunca muda: uma alteração faz-se numa versão nova, que copia a anterior.
 */
class PlanoCurricularService
{
    public function criar(Curso $curso, string $versao, string $metodo, ?User $autor): PlanoCurricular
    {
        return DB::transaction(function () use ($curso, $versao, $metodo, $autor) {
            if ($curso->planos()->where('versao', $versao)->exists()) {
                throw ValidationException::withMessages(['versao' => "O curso já tem um plano com a versão {$versao}."]);
            }

            $plano = $curso->planos()->create([
                'versao' => $versao,
                'estado' => PlanoCurricular::RASCUNHO,
                'metodo_avaliacao' => $metodo,
                'criado_por' => $autor?->id,
            ]);
            $this->registar($plano, 'Plano curricular criado em rascunho');

            return $plano;
        });
    }

    public function adicionarDisciplina(PlanoCurricular $plano, Disciplina $disciplina, int $ano, int $periodo, bool $obrigatoria): PlanoDisciplina
    {
        $this->garantirRascunho($plano);
        $duracao = $plano->curso->duracao_anos;
        if ($ano < 1 || $ano > $duracao) {
            throw ValidationException::withMessages(['ano' => "O curso tem {$duracao} anos: escolha um ano de 1 a {$duracao}."]);
        }
        $periodos = TipoInstituicao::periodosPorAno();
        if ($periodo < 0 || $periodo > $periodos) {
            throw ValidationException::withMessages(['periodo' => 'Escolha um período de 1 a '.$periodos.' (ou anual).']);
        }
        if (! $disciplina->activo) {
            throw ValidationException::withMessages(['disciplina_id' => "{$disciplina->nome} está desactivada."]);
        }
        if ($plano->linhas()->where('disciplina_id', $disciplina->id)->exists()) {
            throw ValidationException::withMessages(['disciplina_id' => "{$disciplina->nome} já está neste plano."]);
        }

        return DB::transaction(function () use ($plano, $disciplina, $ano, $periodo, $obrigatoria) {
            $linha = $plano->linhas()->create([
                'disciplina_id' => $disciplina->id,
                'ano' => $ano,
                'periodo' => $periodo,
                'obrigatoria' => $obrigatoria,
            ]);
            $this->registar($plano, "Disciplina {$disciplina->codigo} acrescentada ({$ano}.º ano)", ['disciplina' => $disciplina->codigo]);

            return $linha;
        });
    }

    public function removerDisciplina(PlanoCurricular $plano, PlanoDisciplina $linha): void
    {
        $this->garantirRascunho($plano);
        abort_if($linha->plano_id !== $plano->id, 404);

        DB::transaction(function () use ($plano, $linha) {
            // As precedências que envolvem a disciplina deixam de fazer sentido neste plano
            $plano->precedencias()
                ->where(fn ($q) => $q->where('disciplina_id', $linha->disciplina_id)->orWhere('precedente_id', $linha->disciplina_id))
                ->delete();
            $codigo = $linha->disciplina->codigo;
            $linha->delete();
            $this->registar($plano, "Disciplina {$codigo} retirada", ['disciplina' => $codigo]);
        });
    }

    public function adicionarPrecedencia(PlanoCurricular $plano, int $disciplinaId, int $precedenteId): Precedencia
    {
        $this->garantirRascunho($plano);
        if ($disciplinaId === $precedenteId) {
            throw ValidationException::withMessages(['precedente_id' => 'Uma disciplina não pode ser precedente de si própria.']);
        }

        /** @var PlanoDisciplina|null $depois */
        $depois = $plano->linhas()->with('disciplina')->where('disciplina_id', $disciplinaId)->first();
        /** @var PlanoDisciplina|null $antes */
        $antes = $plano->linhas()->with('disciplina')->where('disciplina_id', $precedenteId)->first();
        if ($depois === null || $antes === null) {
            throw ValidationException::withMessages(['precedente_id' => 'As duas disciplinas têm de estar neste plano.']);
        }
        if (! $this->vemAntes($antes, $depois)) {
            throw ValidationException::withMessages(['precedente_id' => "{$antes->disciplina->nome} tem de vir num período anterior ao de {$depois->disciplina->nome}."]);
        }
        if ($plano->precedencias()->where('disciplina_id', $disciplinaId)->where('precedente_id', $precedenteId)->exists()) {
            throw ValidationException::withMessages(['precedente_id' => 'Essa precedência já existe.']);
        }

        return DB::transaction(function () use ($plano, $antes, $depois) {
            $precedencia = $plano->precedencias()->create([
                'disciplina_id' => $depois->disciplina_id,
                'precedente_id' => $antes->disciplina_id,
            ]);
            $this->registar($plano, "Precedência: {$depois->disciplina->codigo} depois de {$antes->disciplina->codigo}");

            return $precedencia;
        });
    }

    public function removerPrecedencia(PlanoCurricular $plano, Precedencia $precedencia): void
    {
        $this->garantirRascunho($plano);
        abort_if($precedencia->plano_id !== $plano->id, 404);

        DB::transaction(function () use ($plano, $precedencia) {
            $precedencia->delete();
            $this->registar($plano, 'Precedência retirada');
        });
    }

    /** Aprova o rascunho: passa a ser o plano em vigor do curso e o que estava em vigor fica substituído. */
    public function aprovar(PlanoCurricular $plano, User $autor): void
    {
        $this->garantirRascunho($plano);
        if (! $plano->linhas()->exists()) {
            throw ValidationException::withMessages(['plano' => 'O plano ainda não tem disciplinas: acrescente-as antes de o aprovar.']);
        }

        DB::transaction(function () use ($plano, $autor) {
            PlanoCurricular::where('curso_id', $plano->curso_id)
                ->where('estado', PlanoCurricular::APROVADO)
                ->update(['estado' => PlanoCurricular::SUBSTITUIDO]);

            $plano->estado = PlanoCurricular::APROVADO;
            $plano->aprovado_em = now();
            $plano->aprovado_por = $autor->id;
            $plano->save();
            $this->registar($plano, "Plano {$plano->versao} aprovado: passa a estar em vigor");
        });
    }

    /** Nova versão em rascunho, com as disciplinas e as precedências da versão de origem. */
    public function novaVersao(PlanoCurricular $origem, string $versao, ?User $autor): PlanoCurricular
    {
        return DB::transaction(function () use ($origem, $versao, $autor) {
            $novo = $this->criar($origem->curso, $versao, $origem->metodo_avaliacao, $autor);

            foreach ($origem->linhas as $linha) {
                $novo->linhas()->create($linha->only(['disciplina_id', 'ano', 'periodo', 'obrigatoria', 'nuclear']));
            }
            foreach ($origem->precedencias as $precedencia) {
                $novo->precedencias()->create($precedencia->only(['disciplina_id', 'precedente_id']));
            }
            $this->registar($novo, "Versão criada a partir da {$origem->versao}");

            return $novo;
        });
    }

    /** A disciplina "antes" dá-se num período anterior ao da disciplina "depois"? (as anuais só precedem anos seguintes) */
    private function vemAntes(PlanoDisciplina $antes, PlanoDisciplina $depois): bool
    {
        if ($antes->ano !== $depois->ano) {
            return $antes->ano < $depois->ano;
        }

        return $antes->periodo !== 0 && $depois->periodo !== 0 && $antes->periodo < $depois->periodo;
    }

    private function garantirRascunho(PlanoCurricular $plano): void
    {
        if (! $plano->editavel()) {
            throw ValidationException::withMessages(['plano' => "O plano {$plano->versao} já foi aprovado e não se altera: crie uma versão nova."]);
        }
    }

    /** @param array<string, mixed> $propriedades */
    private function registar(PlanoCurricular $plano, string $descricao, array $propriedades = []): void
    {
        activity('estrutura')->performedOn($plano)->withProperties($propriedades)->log($descricao);
    }
}

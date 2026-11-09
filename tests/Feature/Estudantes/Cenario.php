<?php

namespace Tests\Feature\Estudantes;

use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Estudante;
use App\Models\PlanoCurricular;
use App\Models\User;
use App\Services\AnoLectivoService;
use App\Services\EstudanteService;
use App\Services\PlanoCurricularService;
use Carbon\CarbonImmutable;

/**
 * Um curso de Informática de Gestão com o plano 2027 aprovado, como o da UNA:
 *
 * 1.º ano, 1.º semestre: Programação I (6), Matemática I (6); anual: Inglês (4,5)
 * 1.º ano, 2.º semestre: Programação II (6), que precede Programação I
 * 2.º ano, 1.º semestre: Base de Dados I (6)
 */
trait Cenario
{
    protected User $admin;

    protected Curso $curso;

    protected PlanoCurricular $plano;

    /** @var array<string, Disciplina> */
    protected array $disciplinas = [];

    protected function montarCurso(): void
    {
        $this->admin = $this->administrador();
        $this->curso = Curso::create(['codigo' => 'LIG', 'nome' => 'Licenciatura em Informática de Gestão', 'grau' => 'licenciatura', 'duracao_anos' => 4]);
        $servico = app(PlanoCurricularService::class);
        $this->plano = $servico->criar($this->curso, '2027', 'media_simples', $this->admin);

        foreach ([
            'prog1' => ['00168', 'Programação I', 6, 1, 1],
            'mat1' => ['00101', 'Matemática I', 6, 1, 1],
            'ingles' => ['00120', 'Inglês', 4.5, 1, 0],
            'prog2' => ['00169', 'Programação II', 6, 1, 2],
            'bd1' => ['00062', 'Base de Dados I', 6, 2, 1],
        ] as $chave => [$codigo, $nome, $creditos, $ano, $periodo]) {
            $this->disciplinas[$chave] = Disciplina::create(['codigo' => $codigo, 'nome' => $nome, 'creditos' => $creditos, 'horas_teoricas' => 30, 'horas_praticas' => 30]);
            $servico->adicionarDisciplina($this->plano, $this->disciplinas[$chave], $ano, $periodo, true);
        }
        $servico->adicionarPrecedencia($this->plano, $this->disciplinas['prog2']->id, $this->disciplinas['prog1']->id);
        $servico->aprovar($this->plano, $this->admin);
    }

    protected function anoLectivo(string $nome): AnoLectivo
    {
        return app(AnoLectivoService::class)->criar($nome, CarbonImmutable::parse($nome.'-02-01'), CarbonImmutable::parse($nome.'-12-15'));
    }

    protected function estudante(string $nome = 'Ana Macuácua'): Estudante
    {
        return app(EstudanteService::class)->registar(['nome' => $nome, 'categoria' => 'regular'], $this->admin);
    }

    /** @return list<int> */
    protected function ids(string ...$chaves): array
    {
        return array_map(fn (string $c) => $this->disciplinas[$c]->id, $chaves);
    }
}

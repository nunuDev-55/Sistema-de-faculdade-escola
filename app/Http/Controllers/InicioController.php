<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Estudante;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\PlanoCurricular;
use App\Models\User;
use App\Support\Definicoes;
use App\Support\Modulos;
use App\Support\TipoInstituicao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página Início: o ano e o período em curso e uma caixa por módulo que o utilizador pode ver.
 * Os módulos já feitos mostram o seu número principal e o que precisa de atenção (RF-111).
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $ano = AnoLectivo::emCurso();
        $periodo = $ano?->periodoEmCurso();

        $caixas = [];
        foreach (Modulos::LISTA as $slug => $modulo) {
            if ($user->canAny($modulo['permissoes'] ?? [$modulo['permissao']])) {
                $caixas[] = [
                    'titulo' => $modulo['titulo'],
                    'href' => $modulo['href'],
                    'descricao' => $modulo['descricao'],
                    ...$this->numeros($slug, $ano, $periodo),
                ];
            }
        }

        return Inertia::render('dashboard', [
            'configuracaoEmFalta' => $user->can('configuracao.gerir') ? app(Definicoes::class)->emFalta() : [],
            'calendario' => $ano === null ? null : [
                'ano' => $ano->nome,
                'periodo' => $periodo?->nome(),
                'inscricoes' => $periodo !== null ? $this->prazoInscricoes($periodo) : null,
            ],
            'caixas' => $caixas,
        ]);
    }

    private function prazoInscricoes(PeriodoLectivo $periodo): ?string
    {
        if ($periodo->inscricoes_inicio === null || $periodo->inscricoes_fim === null) {
            return null;
        }
        $hoje = now()->startOfDay();
        if ($hoje->lessThan($periodo->inscricoes_inicio)) {
            return 'Inscrições de '.$periodo->inscricoes_inicio->format('d/m').' a '.$periodo->inscricoes_fim->format('d/m');
        }

        return $hoje->lessThanOrEqualTo($periodo->inscricoes_fim)
            ? 'Inscrições abertas até '.$periodo->inscricoes_fim->format('d/m')
            : 'Inscrições fechadas a '.$periodo->inscricoes_fim->format('d/m');
    }

    /**
     * O número principal de cada módulo feito e o que precisa de atenção.
     *
     * @return array{numero?: int, legenda?: string, alerta?: string|null}
     */
    private function numeros(string $slug, ?AnoLectivo $ano, ?PeriodoLectivo $periodo): array
    {
        return match ($slug) {
            'estudantes' => [
                'numero' => Estudante::where('estado', 'activo')->count(),
                'legenda' => 'estudantes activos',
            ],
            'matriculas' => $ano === null ? ['alerta' => 'Ainda não há ano lectivo: crie-o em Estrutura → Calendário.'] : [
                'numero' => Matricula::where('ano_lectivo_id', $ano->id)->where('estado', Matricula::ACTIVA)->count(),
                'legenda' => 'matrículas activas em '.$ano->nome,
                'alerta' => $this->semInscricao($ano, $periodo),
            ],
            'estrutura' => [
                'numero' => Curso::where('activo', true)->count(),
                'legenda' => TipoInstituicao::eSuperior() ? 'cursos activos' : 'classes e ciclos activos',
                'alerta' => $this->planosPorAprovar(),
            ],
            default => [],
        };
    }

    /** No superior: matrículas activas que ainda não se inscreveram no semestre em curso. */
    private function semInscricao(AnoLectivo $ano, ?PeriodoLectivo $periodo): ?string
    {
        if ($periodo === null || ! TipoInstituicao::eSuperior()) {
            return null;
        }
        $quantas = Matricula::where('ano_lectivo_id', $ano->id)->where('estado', Matricula::ACTIVA)
            ->whereDoesntHave('inscricoesPeriodo', fn ($q) => $q->where('periodo_id', $periodo->id)->where('estado', 'confirmada'))
            ->count();

        return $quantas === 0 ? null : ($quantas === 1 ? '1 ainda sem inscrição' : "{$quantas} ainda sem inscrição").' no '.$periodo->nome();
    }

    private function planosPorAprovar(): ?string
    {
        $rascunhos = PlanoCurricular::where('estado', PlanoCurricular::RASCUNHO)->count();

        return $rascunhos === 0 ? null : ($rascunhos === 1 ? '1 plano curricular por aprovar' : "{$rascunhos} planos curriculares por aprovar");
    }
}

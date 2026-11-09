<?php

namespace App\Http\Controllers\Estudantes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudantes\InscricaoRequest;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\User;
use App\Services\InscricaoService;
use App\Support\Definicoes;
use App\Support\RegrasInscricao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Inscrição semestral nas disciplinas e o seu comprovativo. */
class InscricaoController extends Controller
{
    public function create(Matricula $matricula, PeriodoLectivo $periodo, InscricaoService $servico): Response
    {
        abort_if($periodo->ano_lectivo_id !== $matricula->ano_lectivo_id, 404);
        $matricula->load(['estudante', 'curso', 'plano', 'anoLectivo']);
        $inscricao = $servico->inscricaoDoPeriodo($matricula, $periodo);

        return Inertia::render('matriculas/inscricao', [
            'matricula' => [
                'id' => $matricula->id,
                'numero' => $matricula->numero,
                'activa' => $matricula->activa(),
                'estado_rotulo' => Matricula::ESTADOS[$matricula->estado] ?? $matricula->estado,
                'curso' => $matricula->curso->nome,
                'ano_curricular' => $matricula->ano_curricular,
                'plano' => $matricula->plano->versao,
                'ano_lectivo' => $matricula->anoLectivo->nome,
            ],
            'estudante' => ['id' => $matricula->estudante->id, 'numero' => $matricula->estudante->numero, 'nome' => $matricula->estudante->nome],
            'periodo' => [
                'id' => $periodo->id,
                'nome' => $periodo->nome(),
                'inscricoes_inicio' => $periodo->inscricoes_inicio?->toDateString(),
                'inscricoes_fim' => $periodo->inscricoes_fim?->toDateString(),
            ],
            'disciplinas' => array_map(fn (array $s) => [
                'disciplina_id' => $s['linha']->disciplina_id,
                'codigo' => $s['linha']->disciplina->codigo,
                'nome' => $s['linha']->disciplina->nome,
                'creditos' => (float) $s['linha']->disciplina->creditos,
                'ano' => $s['linha']->ano,
                'anual' => $s['linha']->periodo === 0,
                'obrigatoria' => $s['linha']->obrigatoria,
                'em_atraso' => $s['em_atraso'],
                'estado' => $s['estado'],
                'estado_rotulo' => $servico->rotulo($s['estado']),
                'motivo' => $s['motivo'],
                'inscricao_id' => $s['estado'] === InscricaoService::INSCRITA && $inscricao !== null
                    ? $inscricao->inscricoes()->where('disciplina_id', $s['linha']->disciplina_id)->value('id')
                    : null,
            ], $servico->situacao($matricula, $periodo)),
            'inscricao' => $inscricao === null ? null : ['id' => $inscricao->id, 'codigo' => $inscricao->codigo, 'estado' => $inscricao->estado],
            'limite' => RegrasInscricao::limiteCreditos(),
        ]);
    }

    public function store(InscricaoRequest $request, Matricula $matricula, PeriodoLectivo $periodo, InscricaoService $servico): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $inscricao = $servico->inscrever($matricula, $periodo, $request->disciplinas(), $autor);

        return to_route('inscricoes.comprovativo', $inscricao)
            ->with('sucesso', "Inscrição {$inscricao->codigo} confirmada. Pode imprimir o comprovativo.");
    }

    public function destroy(Inscricao $inscricao, InscricaoService $servico): RedirectResponse
    {
        $servico->anularDisciplina($inscricao);

        return back()->with('sucesso', "Inscrição em {$inscricao->disciplina->nome} anulada.");
    }

    /** Comprovativo para imprimir: a folha que o estudante leva (docs/07, o "comprovativo de inscrição" do eDondzo). */
    public function comprovativo(Request $request, InscricaoPeriodo $inscricaoPeriodo, Definicoes $definicoes): Response
    {
        $inscricaoPeriodo->load(['matricula.estudante', 'matricula.curso', 'matricula.anoLectivo', 'periodo', 'inscricoes' => fn ($q) => $q->with('disciplina')->where('estado', '!=', Inscricao::ANULADO)]);
        $matricula = $inscricaoPeriodo->matricula;
        /** @var User $user */
        $user = $request->user();
        $linhas = $inscricaoPeriodo->inscricoes->sortBy(fn (Inscricao $i) => [$i->em_atraso ? 1 : 0, $i->disciplina->nome])->values();

        return Inertia::render('matriculas/comprovativo', [
            'instituicao' => [
                'nome' => $definicoes->nome(),
                'nuit' => $definicoes->obter('instituicao.nuit'),
                'morada' => trim(implode(', ', array_filter([$definicoes->obter('instituicao.morada'), $definicoes->obter('instituicao.cidade')]))),
                'telefone' => $definicoes->obter('instituicao.telefone'),
                'email' => $definicoes->obter('instituicao.email'),
                'logotipo' => $definicoes->urlLogotipo(),
            ],
            'inscricao' => [
                'id' => $inscricaoPeriodo->id,
                'codigo' => $inscricaoPeriodo->codigo,
                'estado' => $inscricaoPeriodo->estado,
                'periodo' => $inscricaoPeriodo->nomePeriodo(),
                'periodo_id' => $inscricaoPeriodo->periodo_id,
                'confirmada_em' => $inscricaoPeriodo->confirmada_em?->toIso8601String(),
            ],
            'matricula' => [
                'id' => $matricula->id,
                'numero' => $matricula->numero,
                'curso' => $matricula->curso->nome,
                'ano_curricular' => $matricula->ano_curricular,
                'regime' => Matricula::rotuloRegime($matricula->regime),
                'ano_lectivo' => $matricula->anoLectivo->nome,
            ],
            'estudante' => [
                'id' => $matricula->estudante->id,
                'numero' => $matricula->estudante->numero,
                'nome' => $matricula->estudante->nome,
                'documento' => $matricula->estudante->documento_numero,
            ],
            'disciplinas' => $linhas->map(fn (Inscricao $i) => [
                'codigo' => $i->disciplina->codigo,
                'nome' => $i->disciplina->nome,
                'creditos' => (float) $i->creditos,
                'em_atraso' => $i->em_atraso,
                'estado' => Inscricao::ESTADOS[$i->estado] ?? $i->estado,
            ])->values(),
            'emitidoPor' => $user->name,
            'podeInscrever' => $user->can('inscricoes.gerir'),
        ]);
    }
}

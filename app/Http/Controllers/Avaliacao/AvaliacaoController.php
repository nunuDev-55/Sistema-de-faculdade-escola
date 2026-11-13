<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Models\ComponenteAvaliacao;
use App\Models\Inscricao;
use App\Models\Leccionacao;
use App\Models\Nota;
use App\Models\Pauta;
use App\Models\PautaLinha;
use App\Models\User;
use App\Services\PautaService;
use App\Support\Escala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A avaliação de uma disciplina numa turma: plano de avaliação, folha de notas e pautas de cada época. */
class AvaliacaoController extends Controller
{
    public function __construct(private readonly PautaService $servico) {}

    public function show(Request $request, Leccionacao $leccionacao): Response
    {
        $user = $this->utilizador($request);
        abort_unless($this->servico->podeVer($user, $leccionacao), 403);

        $leccionacao->load(['disciplina', 'docente', 'turma.curso', 'turma.periodo', 'turma.anoLectivo']);
        $frequencia = $this->servico->frequencia($leccionacao);
        $componentes = $leccionacao->componentes()->get();
        $inscricoes = $leccionacao->inscricoes()->with('estudante')->get()
            ->sortBy(fn (Inscricao $i) => mb_strtolower($i->estudante->nome))->values();
        $notas = Nota::whereIn('componente_id', $componentes->pluck('id'))->get()->groupBy('inscricao_id');
        $pautas = $leccionacao->pautas()->with('linhas')->get()->keyBy('epoca');

        return Inertia::render('avaliacao/disciplina', [
            'leccionacao' => [
                'id' => $leccionacao->id,
                'disciplina' => $leccionacao->disciplina->nome,
                'codigo' => $leccionacao->disciplina->codigo,
                'turma' => $leccionacao->turma->designacao(),
                'turma_id' => $leccionacao->turma_id,
                'curso' => $leccionacao->turma->curso->nome,
                'periodo' => $leccionacao->turma->periodo?->nome() ?? 'Ano lectivo',
                'ano_lectivo' => $leccionacao->turma->anoLectivo->nome,
                'docente' => $leccionacao->docente?->nome,
            ],
            'componentes' => $componentes->map(fn (ComponenteAvaliacao $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'peso' => (float) $c->peso,
                'data' => $c->data?->toDateString(),
            ])->values(),
            'estudantes' => $inscricoes->map(function (Inscricao $i) use ($notas, $componentes) {
                $minhas = ($notas->get($i->id) ?? collect())->keyBy('componente_id');

                return [
                    'inscricao_id' => $i->id,
                    'numero' => $i->estudante->numero,
                    'nome' => $i->estudante->nome,
                    'em_atraso' => $i->em_atraso,
                    'notas' => $componentes->mapWithKeys(fn (ComponenteAvaliacao $c) => [(string) $c->id => $this->texto($minhas->get($c->id))])->all(),
                    'media' => $this->servico->mediaFrequencia($componentes, $minhas),
                ];
            })->values(),
            'pautas' => collect(Pauta::EPOCAS)->keys()->mapWithKeys(fn (string $epoca) => [$epoca => $this->pauta($pautas->get($epoca) ?? ($epoca === Pauta::FREQUENCIA ? $frequencia : null))])->all(),
            'escala' => [
                'maxima' => Escala::maxima(),
                'aprovacao' => (float) Escala::parametro('nota_minima_aprovacao'),
                'admissao' => (float) Escala::parametro('minimo_admissao_exame'),
                'dispensa' => (float) Escala::parametro('minimo_dispensa_exame'),
                'peso_exame' => (float) Escala::parametro('peso_exame'),
            ],
            'podeLancar' => $this->servico->podeLancar($user, $leccionacao),
            'podeValidar' => $user->can('pautas.validar'),
        ]);
    }

    public function plano(Request $request, Leccionacao $leccionacao): RedirectResponse
    {
        abort_unless($this->servico->podeLancar($this->utilizador($request), $leccionacao), 403);
        $v = $request->validate([
            'componentes' => ['required', 'array', 'min:1', 'max:10'],
            'componentes.*.id' => ['nullable', 'integer'],
            'componentes.*.nome' => ['required', 'string', 'max:60'],
            'componentes.*.peso' => ['required'],
            'componentes.*.data' => ['nullable', 'date'],
        ], ['componentes.*.nome.required' => 'Dê um nome a cada componente (por exemplo, 1.º teste).']);

        $componentes = [];
        foreach ((array) $v['componentes'] as $c) {
            $peso = Escala::lerNumero((string) $c['peso']);
            if ($peso === null || ! is_numeric($peso) || (float) $peso <= 0 || (float) $peso > 100) {
                return back()->withErrors(['componentes' => "O peso de {$c['nome']} tem de ser um número de 1 a 100."]);
            }
            $componentes[] = ['id' => isset($c['id']) ? (int) $c['id'] : null, 'nome' => (string) $c['nome'], 'peso' => $peso, 'data' => $c['data'] ?? null];
        }
        $this->servico->definirPlano($leccionacao, $componentes);

        return back()->with('sucesso', 'Plano de avaliação guardado.');
    }

    public function notas(Request $request, Leccionacao $leccionacao): RedirectResponse
    {
        $user = $this->utilizador($request);
        abort_unless($this->servico->podeLancar($user, $leccionacao), 403);
        $v = $request->validate(['notas' => ['present', 'array']]);
        $this->servico->lancarFrequencia($leccionacao, (array) $v['notas'], $user);

        return back()->with('sucesso', 'Notas guardadas.');
    }

    public function exame(Request $request, Pauta $pauta): RedirectResponse
    {
        abort_unless($this->servico->podeLancar($this->utilizador($request), $pauta->leccionacao), 403);
        $v = $request->validate(['notas' => ['present', 'array']]);
        $this->servico->lancarExame($pauta, (array) $v['notas']);

        return back()->with('sucesso', 'Notas do exame guardadas.');
    }

    public function submeter(Request $request, Pauta $pauta): RedirectResponse
    {
        $user = $this->utilizador($request);
        abort_unless($this->servico->podeLancar($user, $pauta->leccionacao), 403);
        $this->servico->submeter($pauta, $user);

        return back()->with('sucesso', 'Pauta submetida. Segue para validação do director de curso.');
    }

    public function validar(Request $request, Pauta $pauta): RedirectResponse
    {
        $this->servico->validar($pauta, $this->utilizador($request));

        return back()->with('sucesso', 'Pauta validada. Pode publicá-la.');
    }

    public function devolver(Request $request, Pauta $pauta): RedirectResponse
    {
        $v = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Diga ao docente o que tem de corrigir.']);
        $this->servico->devolver($pauta, (string) $v['motivo']);

        return back()->with('sucesso', 'Pauta devolvida ao docente.');
    }

    public function publicar(Request $request, Pauta $pauta): RedirectResponse
    {
        $this->servico->publicar($pauta, $this->utilizador($request));
        $pauta->refresh();

        return back()->with('sucesso', "Pauta {$pauta->codigo} publicada: os resultados já contam para os estudantes.");
    }

    /** @return array<string, mixed>|null */
    private function pauta(?Pauta $pauta): ?array
    {
        if ($pauta === null) {
            return null;
        }

        return [
            'id' => $pauta->id,
            'codigo' => $pauta->codigo,
            'estado' => $pauta->estado,
            'estado_rotulo' => Pauta::ESTADOS[$pauta->estado] ?? $pauta->estado,
            'observacao' => $pauta->observacao,
            'publicada_em' => $pauta->publicada_em?->toIso8601String(),
            'linhas' => $pauta->linhas->mapWithKeys(fn (PautaLinha $l) => [(string) $l->inscricao_id => [
                'nota' => $l->faltou ? 'F' : ($l->nota === null ? '' : str_replace('.', ',', $l->nota)),
                'nota_final' => $l->nota_final === null ? null : (float) $l->nota_final,
                'situacao' => $l->situacao,
                'situacao_rotulo' => Pauta::SITUACOES[$l->situacao ?? ''] ?? null,
            ]])->all(),
        ];
    }

    /** A nota como se escreve na folha: "12,5", "F" ou vazio. */
    private function texto(?Nota $nota): string
    {
        if ($nota === null) {
            return '';
        }
        if ($nota->faltou) {
            return 'F';
        }

        return $nota->valor === null ? '' : rtrim(rtrim(str_replace('.', ',', $nota->valor), '0'), ',');
    }

    private function utilizador(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

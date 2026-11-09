<?php

namespace App\Http\Controllers\Estudantes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudantes\EstudanteRequest;
use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Encarregado;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\User;
use App\Services\EstudanteService;
use App\Support\Opcoes;
use App\Support\TipoInstituicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Lista, registo e ficha do estudante (com as matrículas, as inscrições e os encarregados). */
class EstudanteController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');
        $curso = (int) $request->query('curso', 0);
        $digitos = preg_replace('/\D+/', '', $q) ?? '';

        $estudantes = Estudante::query()
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q, $digitos) {
                $w->where('nome', 'like', "%{$q}%")->orWhere('numero', 'like', "%{$q}%")->orWhere('documento_numero', 'like', "%{$q}%");
                if (strlen($digitos) >= 6) {
                    $w->orWhere('telemovel', 'like', "%{$digitos}%");
                }
            }))
            ->when($estado !== '', fn (Builder $query) => $query->where('estado', $estado))
            ->when($curso > 0, fn (Builder $query) => $query->whereHas('matriculas', fn (Builder $m) => $m->where('curso_id', $curso)->where('estado', '!=', Matricula::ANULADA)))
            ->with(['matriculas' => fn ($m) => $m->with(['curso', 'anoLectivo'])->where('estado', '!=', Matricula::ANULADA)->orderByDesc('id')])
            ->orderBy('nome')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Estudante $e) {
                /** @var Matricula|null $ultima */
                $ultima = $e->matriculas->sortByDesc(fn (Matricula $m) => $m->anoLectivo->inicio->timestamp)->first();

                return [
                    'id' => $e->id,
                    'numero' => $e->numero,
                    'nome' => $e->nome,
                    'curso' => $ultima?->curso->nome,
                    'ano_curricular' => $ultima?->ano_curricular,
                    'ano_lectivo' => $ultima?->anoLectivo->nome,
                    'telemovel' => $e->telemovel,
                    'estado' => $e->estado,
                    'estado_rotulo' => Estudante::ESTADOS[$e->estado] ?? $e->estado,
                ];
            });

        return Inertia::render('estudantes/index', [
            'estudantes' => $estudantes,
            'filtros' => ['q' => $q, 'estado' => $estado, 'curso' => $curso > 0 ? (string) $curso : ''],
            'estados' => Opcoes::de(Estudante::ESTADOS),
            'cursos' => Curso::orderBy('nome')->get()->map(fn (Curso $c) => ['valor' => (string) $c->id, 'rotulo' => $c->nome])->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('estudantes/formulario', ['estudante' => null, ...$this->opcoesFormulario()]);
    }

    public function store(EstudanteRequest $request, EstudanteService $servico): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $estudante = $servico->registar($request->validated(), $autor);

        return to_route('estudantes.show', $estudante)->with('sucesso', "Estudante registado com o número {$estudante->numero}.");
    }

    public function show(Estudante $estudante): Response
    {
        $estudante->load([
            'encarregados' => fn ($q) => $q->orderByDesc('estudante_encarregado.principal')->orderBy('nome'),
            'matriculas' => fn ($q) => $q->with([
                'curso', 'plano', 'anoLectivo.periodos',
                'inscricoesPeriodo' => fn ($i) => $i->with(['periodo', 'inscricoes' => fn ($d) => $d->where('estado', '!=', Inscricao::ANULADO)]),
            ]),
        ]);
        $matriculas = $estudante->matriculas->sortByDesc(fn (Matricula $m) => [$m->anoLectivo->inicio->timestamp, $m->id])->values();

        return Inertia::render('estudantes/ficha', [
            'estudante' => $this->dados($estudante),
            'encarregados' => $estudante->encarregados->map(fn (Encarregado $e) => [
                'id' => $e->id,
                'nome' => $e->nome,
                'parentesco' => Encarregado::PARENTESCOS[(string) $e->pivot->getAttribute('parentesco')] ?? $e->pivot->getAttribute('parentesco'),
                'principal' => (bool) $e->pivot->getAttribute('principal'),
                've_financas' => (bool) $e->pivot->getAttribute('ve_financas'),
                'telemovel' => $e->telemovel,
                'email' => $e->email,
                'profissao' => $e->profissao,
                'outros_educandos' => $e->estudantes()->where('estudantes.id', '!=', $estudante->id)->pluck('nome')->all(),
            ])->values(),
            'matriculas' => $matriculas->map(fn (Matricula $m) => $this->matricula($m))->values(),
            'opcoesMatricula' => $this->opcoesMatricula($matriculas->first()),
            'parentescos' => Opcoes::de(Encarregado::PARENTESCOS),
            'superior' => TipoInstituicao::eSuperior(),
        ]);
    }

    public function edit(Estudante $estudante): Response
    {
        return Inertia::render('estudantes/formulario', ['estudante' => $this->dados($estudante), ...$this->opcoesFormulario()]);
    }

    public function update(EstudanteRequest $request, Estudante $estudante): RedirectResponse
    {
        $estudante->update($request->validated());

        return to_route('estudantes.show', $estudante)->with('sucesso', 'Dados do estudante guardados.');
    }

    /** @return array<string, mixed> */
    private function dados(Estudante $e): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->numero,
            'nome' => $e->nome,
            'sexo' => $e->sexo,
            'sexo_rotulo' => Estudante::SEXOS[$e->sexo ?? ''] ?? null,
            'data_nascimento' => $e->data_nascimento?->toDateString(),
            'documento_tipo' => $e->documento_tipo,
            'documento_rotulo' => Estudante::DOCUMENTOS[$e->documento_tipo ?? ''] ?? null,
            'documento_numero' => $e->documento_numero,
            'nuit' => $e->nuit,
            'nacionalidade' => $e->nacionalidade,
            'naturalidade' => $e->naturalidade,
            'provincia' => $e->provincia,
            'morada' => $e->morada,
            'telemovel' => $e->telemovel,
            'email' => $e->email,
            'necessidades_especiais' => $e->necessidades_especiais,
            'categoria' => $e->categoria,
            'categoria_rotulo' => Estudante::CATEGORIAS[$e->categoria] ?? $e->categoria,
            'estado' => $e->estado,
            'estado_rotulo' => Estudante::ESTADOS[$e->estado] ?? $e->estado,
            'nota_privada' => $e->nota_privada,
            'registado_em' => $e->created_at->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function matricula(Matricula $m): array
    {
        $porPeriodo = $m->inscricoesPeriodo->keyBy(fn (InscricaoPeriodo $i) => (string) $i->periodo_id);
        $resumo = fn (?InscricaoPeriodo $i) => $i === null ? null : [
            'id' => $i->id,
            'codigo' => $i->codigo,
            'estado' => $i->estado,
            'disciplinas' => $i->inscricoes->count(),
            'creditos' => (float) $i->inscricoes->sum(fn (Inscricao $d) => (float) $d->creditos),
            'em_atraso' => $i->inscricoes->where('em_atraso', true)->count(),
        ];

        return [
            'id' => $m->id,
            'numero' => $m->numero,
            'ano_lectivo' => $m->anoLectivo->nome,
            'curso' => $m->curso->nome,
            'curso_id' => $m->curso_id,
            'ano_curricular' => $m->ano_curricular,
            'regime' => Matricula::rotuloRegime($m->regime),
            'plano' => $m->plano->versao,
            'plano_id' => $m->plano_id,
            'estado' => $m->estado,
            'estado_rotulo' => Matricula::ESTADOS[$m->estado] ?? $m->estado,
            'motivo' => $m->motivo,
            'data' => $m->data->toDateString(),
            'periodos' => $m->anoLectivo->periodos->map(fn (PeriodoLectivo $p) => [
                'id' => $p->id,
                'nome' => $p->nome(),
                'inscricao' => $resumo($porPeriodo->get((string) $p->id)),
            ])->values(),
            'inscricao_anual' => $resumo($porPeriodo->get('')),
        ];
    }

    /** @return array<string, mixed> */
    private function opcoesMatricula(?Matricula $ultima): array
    {
        $cursos = Curso::where('activo', true)->with('planos')->orderBy('nome')->get();
        $anos = AnoLectivo::where('estado', '!=', 'fechado')->orderByDesc('inicio')->get();
        // Sugestão: o ano lectivo a seguir ao da última matrícula (ou o que está aberto), o mesmo curso e o ano seguinte
        $proximoAno = $ultima !== null
            ? $anos->filter(fn (AnoLectivo $a) => $a->inicio->greaterThan($ultima->anoLectivo->inicio))->last()
            : $anos->firstWhere('estado', 'aberto') ?? $anos->first();

        return [
            'anos' => $anos->map(fn (AnoLectivo $a) => ['valor' => (string) $a->id, 'rotulo' => $a->nome])->values(),
            'cursos' => $cursos->map(fn (Curso $c) => [
                'valor' => (string) $c->id,
                'rotulo' => $c->nome,
                'duracao' => $c->duracao_anos,
                'tem_plano' => $c->planos->contains('estado', 'aprovado'),
            ])->values(),
            'regimes' => Opcoes::de(Matricula::regimes()),
            'sugestao' => [
                'ano_lectivo_id' => $proximoAno !== null ? (string) $proximoAno->id : '',
                'curso_id' => $ultima !== null ? (string) $ultima->curso_id : '',
                'ano_curricular' => $ultima !== null ? (string) min($ultima->ano_curricular + 1, $ultima->curso->duracao_anos) : '1',
                'regime' => $ultima->regime ?? '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function opcoesFormulario(): array
    {
        return [
            'sexos' => Opcoes::de(Estudante::SEXOS),
            'documentos' => Opcoes::de(Estudante::DOCUMENTOS),
            'categorias' => Opcoes::de(Estudante::CATEGORIAS),
            'estados' => Opcoes::de(Estudante::ESTADOS),
            'provincias' => array_map(fn (string $p) => ['valor' => $p, 'rotulo' => $p], Estudante::PROVINCIAS),
        ];
    }
}

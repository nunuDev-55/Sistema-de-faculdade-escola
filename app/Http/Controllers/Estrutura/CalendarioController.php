<?php

namespace App\Http\Controllers\Estrutura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estrutura\AnoLectivoRequest;
use App\Http\Requests\Estrutura\PeriodoRequest;
use App\Models\AnoLectivo;
use App\Models\PeriodoLectivo;
use App\Models\User;
use App\Services\AnoLectivoService;
use App\Support\TipoInstituicao;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Anos lectivos e os seus semestres ou trimestres. */
class CalendarioController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $proximo = now()->year + 1;

        return Inertia::render('estrutura/calendario', [
            'anos' => AnoLectivo::with('periodos')->orderByDesc('inicio')->get()->map(fn (AnoLectivo $a) => [
                'id' => $a->id,
                'nome' => $a->nome,
                'inicio' => $a->inicio->toDateString(),
                'fim' => $a->fim->toDateString(),
                'estado' => AnoLectivo::ESTADOS[$a->estado] ?? $a->estado,
                'periodos' => $a->periodos->map(fn (PeriodoLectivo $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome(),
                    'inicio' => $p->inicio->toDateString(),
                    'fim' => $p->fim->toDateString(),
                    'inscricoes_inicio' => $p->inscricoes_inicio?->toDateString(),
                    'inscricoes_fim' => $p->inscricoes_fim?->toDateString(),
                    'exames_inicio' => $p->exames_inicio?->toDateString(),
                    'exames_fim' => $p->exames_fim?->toDateString(),
                ])->values(),
            ])->values(),
            'tipoPeriodo' => TipoInstituicao::tipoPeriodo(),
            'periodosPorAno' => TipoInstituicao::periodosPorAno(),
            'podeGerir' => $user->can('calendario.gerir'),
            'sugestao' => ['nome' => (string) $proximo, 'inicio' => $proximo.'-02-01', 'fim' => $proximo.'-12-15'],
        ]);
    }

    public function store(AnoLectivoRequest $request, AnoLectivoService $servico): RedirectResponse
    {
        $ano = $servico->criar(
            trim((string) $request->validated('nome')),
            CarbonImmutable::parse((string) $request->validated('inicio')),
            CarbonImmutable::parse((string) $request->validated('fim')),
        );

        return back()->with('sucesso', "Ano lectivo {$ano->nome} criado com os seus ".TipoInstituicao::periodosPorAno().' '.TipoInstituicao::tipoPeriodo().'s.');
    }

    public function actualizarPeriodo(PeriodoRequest $request, PeriodoLectivo $periodo): RedirectResponse
    {
        $ano = $periodo->ano;
        $inicio = (string) $request->validated('inicio');
        $fim = (string) $request->validated('fim');
        if ($inicio < $ano->inicio->toDateString() || $fim > $ano->fim->toDateString()) {
            throw ValidationException::withMessages(['periodo' => 'O '.$periodo->nome().' tem de ficar dentro do ano lectivo '.$ano->nome.'.']);
        }

        $periodo->update($request->validated());

        return back()->with('sucesso', 'Datas do '.$periodo->nome().' guardadas.');
    }
}

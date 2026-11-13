<?php

namespace App\Http\Controllers\Financas;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Cobranca;
use App\Models\Curso;
use App\Models\Preco;
use App\Models\User;
use App\Services\CobrancaService;
use App\Support\Dinheiro;
use App\Support\Opcoes;
use App\Support\Pesquisa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Finanças do estudante: as cobranças do ano, a tabela de preços e a geração das cobranças. */
class FinancasController extends Controller
{
    public function index(Request $request): Response
    {
        $anos = AnoLectivo::orderByDesc('inicio')->get();
        $ano = $anos->firstWhere('id', (int) $request->query('ano', (string) (AnoLectivo::emCurso()->id ?? 0))) ?? $anos->first();
        $estado = (string) $request->query('estado', '');
        $q = trim((string) $request->query('q', ''));
        $hoje = now()->toDateString();

        $base = Cobranca::query()->whereHas('estudante')
            ->when($ano !== null, fn (Builder $c) => $c->whereHas('matricula', fn (Builder $m) => $m->where('ano_lectivo_id', $ano->id ?? 0)));
        $totais = [
            'valor' => Dinheiro::normalizar((clone $base)->where('estado', '!=', 'anulada')->sum('valor')),
            'pago' => Dinheiro::normalizar((clone $base)->where('estado', '!=', 'anulada')->sum('pago')),
            'vencido' => Dinheiro::normalizar((clone $base)->whereIn('estado', ['aberta', 'parcial'])->where('vencimento', '<', $hoje)->selectRaw('coalesce(sum(valor - pago), 0) as divida')->value('divida')),
        ];

        $cobrancas = $base
            ->with('estudante')
            ->when($estado === 'vencidas', fn (Builder $c) => $c->whereIn('estado', ['aberta', 'parcial'])->where('vencimento', '<', $hoje))
            ->when($estado !== '' && $estado !== 'vencidas', fn (Builder $c) => $c->where('estado', $estado))
            ->when($q !== '', fn (Builder $c) => $c->whereHas('estudante', fn (Builder $e) => $e->where(fn (Builder $w) => $w->where('nome', 'like', Pesquisa::like($q))->orWhere('numero', 'like', Pesquisa::like($q)))))
            ->orderBy('vencimento')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Cobranca $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'estudante_id' => $c->estudante_id,
                'estudante' => $c->estudante->nome,
                'estudante_numero' => $c->estudante->numero,
                'descricao' => $c->descricao,
                'valor' => $c->valor,
                'pago' => $c->pago,
                'vencimento' => $c->vencimento->toDateString(),
                'estado' => $c->estado,
                'estado_rotulo' => Cobranca::ESTADOS[$c->estado] ?? $c->estado,
                'vencida' => $c->vencida(),
            ]);

        return Inertia::render('financas/index', [
            'cobrancas' => $cobrancas,
            'totais' => $totais,
            'filtros' => ['ano' => $ano !== null ? (string) $ano->id : '', 'estado' => $estado, 'q' => $q],
            'anos' => $anos->map(fn (AnoLectivo $a) => ['valor' => (string) $a->id, 'rotulo' => $a->nome])->values(),
            'estados' => [...Opcoes::de(Cobranca::ESTADOS), ['valor' => 'vencidas', 'rotulo' => 'Vencidas']],
        ]);
    }

    public function precos(Request $request): Response
    {
        $anos = AnoLectivo::orderByDesc('inicio')->get();
        $ano = $anos->firstWhere('id', (int) $request->query('ano', (string) (AnoLectivo::emCurso()->id ?? 0))) ?? $anos->first();

        return Inertia::render('financas/precos', [
            'precos' => $ano === null ? [] : Preco::with('curso')->where('ano_lectivo_id', $ano->id)->orderByRaw('curso_id is not null')->orderBy('tipo')->get()
                ->map(fn (Preco $p) => [
                    'id' => $p->id,
                    'curso_id' => $p->curso_id,
                    'curso' => $p->curso->nome ?? 'Todos os cursos',
                    'tipo' => $p->tipo,
                    'tipo_rotulo' => Preco::TIPOS[$p->tipo] ?? $p->tipo,
                    'valor' => $p->valor,
                    'prestacoes' => $p->prestacoes,
                    'dia_vencimento' => $p->dia_vencimento,
                ])->values(),
            'ano' => $ano?->only(['id', 'nome']),
            'anos' => $anos->map(fn (AnoLectivo $a) => ['valor' => (string) $a->id, 'rotulo' => $a->nome])->values(),
            'cursos' => Curso::where('activo', true)->orderBy('nome')->get()->map(fn (Curso $c) => ['valor' => (string) $c->id, 'rotulo' => $c->nome])->values(),
            'tipos' => Opcoes::de(Preco::TIPOS),
        ]);
    }

    public function guardarPreco(Request $request): RedirectResponse
    {
        $request->merge(['valor' => Dinheiro::lerNumero($request->input('valor'))]);
        $v = $request->validate([
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'tipo' => ['required', Rule::in(array_keys(Preco::TIPOS))],
            'valor' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'prestacoes' => ['required', 'integer', 'min:1', 'max:12'],
            'dia_vencimento' => ['required', 'integer', 'min:1', 'max:28'],
        ], [], ['valor' => 'valor', 'prestacoes' => 'meses', 'dia_vencimento' => 'dia de pagamento']);

        Preco::updateOrCreate(
            ['ano_lectivo_id' => $v['ano_lectivo_id'], 'curso_id' => $v['curso_id'] ?? null, 'tipo' => $v['tipo']],
            ['valor' => Dinheiro::arredondar((string) $v['valor']), 'prestacoes' => $v['tipo'] === 'propina' ? $v['prestacoes'] : 1, 'dia_vencimento' => $v['dia_vencimento']],
        );

        return back()->with('sucesso', 'Preço guardado. As cobranças já geradas não mudam.');
    }

    public function apagarPreco(Preco $preco): RedirectResponse
    {
        $preco->delete();

        return back()->with('sucesso', 'Preço retirado da tabela.');
    }

    public function gerar(Request $request, CobrancaService $servico): RedirectResponse
    {
        $v = $request->validate(['ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id']]);
        /** @var User $autor */
        $autor = $request->user();
        $r = $servico->gerarDoAno(AnoLectivo::findOrFail((int) $v['ano_lectivo_id']), $autor);
        $mensagem = $r['cobrancas'] === 0 ? 'Não havia cobranças por gerar.' : "{$r['cobrancas']} cobranças geradas para {$r['inscricoes']} inscrições.";

        return $r['sem_preco'] > 0
            ? back()->with('erro', "{$mensagem} {$r['sem_preco']} inscrições ficaram por cobrar: falta o preço na tabela.")
            : back()->with('sucesso', $mensagem);
    }
}

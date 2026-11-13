<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\ContaPortal;
use App\Models\DocumentoEmitido;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\Pagamento;
use App\Models\PedidoDocumento;
use App\Services\PagamentoService;
use App\Support\Definicoes;
use App\Support\Dinheiro;
use App\Support\Opcoes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * O portal do estudante: os seus dados, matrículas, disciplinas e notas, propinas e pagamentos.
 * As notas só aparecem depois de publicada a pauta; antes, a disciplina diz "Por lançar" (RN-16).
 */
class PainelController extends Controller
{
    public function __invoke(Request $request, Definicoes $definicoes, PagamentoService $pagamentos): Response
    {
        /** @var ContaPortal $conta */
        $conta = $request->user('estudante');
        $estudante = $conta->estudante;
        $estudante->load(['matriculas' => fn ($q) => $q->with(['curso', 'anoLectivo', 'inscricoesPeriodo' => fn ($i) => $i->with(['periodo', 'inscricoes.disciplina'])])->where('estado', '!=', Matricula::ANULADA)]);
        $cobrancas = Cobranca::where('estudante_id', $estudante->id)->where('estado', '!=', 'anulada')->orderBy('vencimento')->get();
        $abertas = $cobrancas->whereIn('estado', ['aberta', 'parcial']);

        return Inertia::render('portal/painel', [
            'instituicao' => ['nome' => $definicoes->nome(), 'logotipo' => $definicoes->urlLogotipo()],
            'estudante' => ['numero' => $estudante->numero, 'nome' => $estudante->nome, 'email' => $estudante->email, 'telemovel' => $estudante->telemovel],
            'matriculas' => $estudante->matriculas->sortByDesc(fn (Matricula $m) => $m->anoLectivo->inicio->timestamp)->map(fn (Matricula $m) => [
                'numero' => $m->numero,
                'ano_lectivo' => $m->anoLectivo->nome,
                'curso' => $m->curso->nome,
                'ano_curricular' => $m->ano_curricular,
                'estado' => Matricula::ESTADOS[$m->estado] ?? $m->estado,
                'inscricoes' => $m->inscricoesPeriodo->where('estado', InscricaoPeriodo::CONFIRMADA)->map(fn (InscricaoPeriodo $ip) => [
                    'codigo' => $ip->codigo,
                    'periodo' => $ip->nomePeriodo(),
                    'disciplinas' => $ip->inscricoes->where('estado', '!=', Inscricao::ANULADO)->sortBy(fn (Inscricao $i) => $i->disciplina->nome)->map(fn (Inscricao $i) => [
                        'codigo' => $i->disciplina->codigo,
                        'nome' => $i->disciplina->nome,
                        'creditos' => (float) $i->creditos,
                        'em_atraso' => $i->em_atraso,
                        // Só as pautas publicadas mudam o estado da inscrição: enquanto está "inscrito", não há nota para mostrar
                        'resultado' => $i->estado === Inscricao::INSCRITO ? null : (Inscricao::ESTADOS[$i->estado] ?? $i->estado),
                        'nota' => $i->estado === Inscricao::INSCRITO || $i->nota_final === null ? null : (float) $i->nota_final,
                    ])->values(),
                ])->values(),
            ])->values(),
            'financas' => [
                'divida' => Dinheiro::somar($abertas->map(fn (Cobranca $c) => $c->emDivida())),
                'vencido' => Dinheiro::somar($abertas->filter(fn (Cobranca $c) => $c->vencida())->map(fn (Cobranca $c) => $c->emDivida())),
                'credito' => $pagamentos->credito($estudante),
                'cobrancas' => $abertas->map(fn (Cobranca $c) => [
                    'descricao' => $c->descricao,
                    'vencimento' => $c->vencimento->toDateString(),
                    'falta' => $c->emDivida(),
                    'vencida' => $c->vencida(),
                ])->values(),
                'pagamentos' => Pagamento::where('estudante_id', $estudante->id)->where('estado', 'valido')->orderByDesc('data')->limit(10)->get()
                    ->map(fn (Pagamento $p) => ['numero' => $p->numero, 'data' => $p->data->toDateString(), 'valor' => $p->valor])->values(),
            ],
            'pedidos' => PedidoDocumento::with(['cobranca', 'documento'])->where('estudante_id', $estudante->id)->latest('id')->limit(10)->get()
                ->map(fn (PedidoDocumento $p) => [
                    'numero' => $p->numero,
                    'tipo' => $p->nomeTipo(),
                    'finalidade' => $p->finalidade,
                    'pedido_em' => $p->created_at->toIso8601String(),
                    'emolumento' => $p->cobranca?->valor,
                    'situacao' => $p->situacao(),
                    'situacao_rotulo' => PedidoDocumento::SITUACOES[$p->situacao()] ?? $p->situacao(),
                    'motivo_recusa' => $p->motivo_recusa,
                    'documento_numero' => $p->documento?->numero,
                ])->values(),
            'tiposDocumento' => Opcoes::de(DocumentoEmitido::TIPOS),
        ]);
    }
}

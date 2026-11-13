<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Pauta;
use App\Models\PautaLinha;
use App\Models\User;
use App\Services\PautaService;
use App\Support\Definicoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** As pautas da instituição (para validar e publicar) e a pauta para imprimir. */
class PautaController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $estado = (string) $request->query('estado', $user->can('pautas.validar') ? Pauta::SUBMETIDA : '');
        $epoca = (string) $request->query('epoca', '');
        $todas = $user->can('pautas.validar') || $user->can('turmas.ver');
        $docente = Docente::where('user_id', $user->id)->value('id');

        $pautas = Pauta::query()
            ->with(['leccionacao.disciplina', 'leccionacao.docente', 'leccionacao.turma.curso', 'leccionacao.turma.periodo', 'leccionacao.turma.anoLectivo'])
            ->when(! $todas, fn (Builder $q) => $q->whereHas('leccionacao', fn (Builder $l) => $l->where('docente_id', $docente ?? 0)))
            ->when($estado !== '', fn (Builder $q) => $q->where('estado', $estado))
            ->when($epoca !== '', fn (Builder $q) => $q->where('epoca', $epoca))
            ->withCount('linhas')
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Pauta $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'leccionacao_id' => $p->leccionacao_id,
                'disciplina' => $p->leccionacao->disciplina->nome,
                'turma' => $p->leccionacao->turma->designacao(),
                'curso' => $p->leccionacao->turma->curso->nome,
                'periodo' => ($p->leccionacao->turma->periodo?->nome() ?? 'Ano lectivo').' · '.$p->leccionacao->turma->anoLectivo->nome,
                'docente' => $p->leccionacao->docente?->nome,
                'epoca' => Pauta::EPOCAS[$p->epoca] ?? $p->epoca,
                'estado' => $p->estado,
                'estado_rotulo' => Pauta::ESTADOS[$p->estado] ?? $p->estado,
                'estudantes' => (int) $p->getAttribute('linhas_count'),
                'actualizada' => $p->updated_at->toIso8601String(),
            ]);

        return Inertia::render('avaliacao/pautas', [
            'pautas' => $pautas,
            'filtros' => ['estado' => $estado, 'epoca' => $epoca],
            'estados' => collect(Pauta::ESTADOS)->map(fn (string $r, string $v) => ['valor' => $v, 'rotulo' => $r])->values(),
            'epocas' => collect(Pauta::EPOCAS)->map(fn (string $r, string $v) => ['valor' => $v, 'rotulo' => $r])->values(),
        ]);
    }

    /** A pauta numa folha A4 (imprimir ou guardar em PDF), com as assinaturas. */
    public function imprimir(Request $request, Pauta $pauta, PautaService $servico, Definicoes $definicoes): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($servico->podeVer($user, $pauta->leccionacao), 403);
        $pauta->load(['leccionacao.disciplina', 'leccionacao.docente', 'leccionacao.turma.curso', 'leccionacao.turma.periodo', 'leccionacao.turma.anoLectivo', 'linhas.inscricao.estudante']);
        $l = $pauta->leccionacao;

        return Inertia::render('avaliacao/pauta-impressa', [
            'instituicao' => ['nome' => $definicoes->nome(), 'logotipo' => $definicoes->urlLogotipo()],
            'pauta' => [
                'id' => $pauta->id,
                'codigo' => $pauta->codigo,
                'epoca' => Pauta::EPOCAS[$pauta->epoca] ?? $pauta->epoca,
                'estado' => $pauta->estado,
                'estado_rotulo' => Pauta::ESTADOS[$pauta->estado] ?? $pauta->estado,
                'publicada_em' => $pauta->publicada_em?->toIso8601String(),
                'disciplina' => $l->disciplina->nome,
                'codigo_disciplina' => $l->disciplina->codigo,
                'turma' => $l->turma->designacao(),
                'curso' => $l->turma->curso->nome,
                'periodo' => ($l->turma->periodo?->nome() ?? 'Ano lectivo').' · '.$l->turma->anoLectivo->nome,
                'docente' => $l->docente?->nome,
                'leccionacao_id' => $l->id,
            ],
            'linhas' => $pauta->linhas->sortBy(fn (PautaLinha $x) => mb_strtolower($x->inscricao->estudante->nome))->map(fn (PautaLinha $x) => [
                'numero' => $x->inscricao->estudante->numero,
                'nome' => $x->inscricao->estudante->nome,
                'nota' => $x->faltou ? 'F' : ($x->nota === null ? null : (float) $x->nota),
                'nota_final' => $x->nota_final === null ? null : (float) $x->nota_final,
                'situacao' => Pauta::SITUACOES[$x->situacao ?? ''] ?? null,
            ])->values(),
            'final' => $pauta->epoca !== Pauta::FREQUENCIA,
        ]);
    }
}

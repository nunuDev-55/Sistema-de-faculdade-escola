<?php

namespace App\Http\Controllers\Administracao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracao\AvaliacaoRequest;
use App\Http\Requests\Administracao\InstituicaoRequest;
use App\Models\Curso;
use App\Support\Definicoes;
use App\Support\Escala;
use App\Support\TipoInstituicao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administração → Configuração: os dados da instituição (que tiram o aviso do Início) e as regras de avaliação.
 * Tudo fica na tabela definicoes, nada no código (docs/05-arquitectura.md, regra 6).
 */
class ConfiguracaoController extends Controller
{
    private const CAMPOS_INSTITUICAO = ['nome', 'sigla', 'nuit', 'morada', 'cidade', 'provincia', 'telefone', 'email', 'tipo', 'cor', 'cor_destaque'];

    public function __construct(private readonly Definicoes $definicoes) {}

    public function instituicao(): Response
    {
        $d = $this->definicoes;
        $cores = $d->cores();

        return Inertia::render('administracao/configuracao/instituicao', [
            'dados' => [
                'nome' => $d->obter('instituicao.nome', ''),
                'sigla' => $d->obter('instituicao.sigla', ''),
                'nuit' => $d->obter('instituicao.nuit', ''),
                'morada' => $d->obter('instituicao.morada', ''),
                'cidade' => $d->obter('instituicao.cidade', ''),
                'provincia' => $d->obter('instituicao.provincia', ''),
                'telefone' => $d->obter('instituicao.telefone', ''),
                'email' => $d->obter('instituicao.email', ''),
                'tipo' => TipoInstituicao::actual(),
                'cor' => $cores['cor'],
                'cor_destaque' => $cores['destaque'],
            ],
            'logotipo' => $d->urlLogotipo(),
            'emFalta' => $d->emFalta(),
            'temCursos' => Curso::exists(),
        ]);
    }

    public function guardarInstituicao(InstituicaoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $valores = [];
        foreach (self::CAMPOS_INSTITUICAO as $campo) {
            $valor = $dados[$campo] ?? null;
            $valores['instituicao.'.$campo] = $valor === null || $valor === '' ? null : (string) $valor;
        }

        $antigo = $this->definicoes->obter('instituicao.logotipo');
        $ficheiro = $request->file('logotipo');
        if ($ficheiro instanceof UploadedFile) {
            $caminho = $ficheiro->store('instituicao', 'local');
            if ($caminho !== false) {
                $valores['instituicao.logotipo'] = $caminho;
            }
        } elseif ($request->boolean('remover_logotipo')) {
            $valores['instituicao.logotipo'] = null;
        }

        $this->definicoes->guardar($valores, 'Dados da instituição alterados');

        if ($antigo !== null && array_key_exists('instituicao.logotipo', $valores) && $antigo !== $valores['instituicao.logotipo']) {
            Storage::disk('local')->delete($antigo);
        }

        return back()->with('sucesso', 'Dados da instituição guardados.');
    }

    public function avaliacao(): Response
    {
        return Inertia::render('administracao/configuracao/avaliacao', [
            'dados' => [
                'escala_maxima' => (float) Escala::parametro('escala_maxima'),
                'casas_decimais' => (int) Escala::parametro('casas_decimais'),
                'nota_minima_aprovacao' => (float) Escala::parametro('nota_minima_aprovacao'),
                'minimo_admissao_exame' => (float) Escala::parametro('minimo_admissao_exame'),
                'minimo_dispensa_exame' => (float) Escala::parametro('minimo_dispensa_exame'),
                'assiduidade_minima' => (int) Escala::parametro('assiduidade_minima'),
                'intervalos' => Escala::intervalos(),
            ],
        ]);
    }

    public function guardarAvaliacao(AvaliacaoRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $intervalos = Escala::normalizar($v['intervalos']);

        $this->definicoes->guardar([
            'avaliacao.escala_maxima' => (string) (float) $v['escala_maxima'],
            'avaliacao.casas_decimais' => (string) (int) $v['casas_decimais'],
            'avaliacao.nota_minima_aprovacao' => (string) (float) $v['nota_minima_aprovacao'],
            'avaliacao.minimo_admissao_exame' => (string) (float) $v['minimo_admissao_exame'],
            'avaliacao.minimo_dispensa_exame' => (string) (float) $v['minimo_dispensa_exame'],
            'avaliacao.assiduidade_minima' => (string) (int) $v['assiduidade_minima'],
            'avaliacao.intervalos' => (string) json_encode($intervalos),
        ], 'Regras de avaliação alteradas');

        return back()->with('sucesso', 'Regras de avaliação guardadas.');
    }

    /** Público: o logótipo vai nos documentos e na página de entrada. */
    public function logotipo(): StreamedResponse
    {
        $caminho = $this->definicoes->obter('instituicao.logotipo');
        abort_if($caminho === null || ! Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}

<?php

namespace App\Http\Controllers\Administracao;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Encarregado;
use App\Models\Estudante;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\PeriodoLectivo;
use App\Models\PlanoCurricular;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\UnidadeOrganica;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/** Quem fez o quê e quando: o registo de auditoria, com filtros por pessoa, módulo e datas (do nsERP). */
class AuditoriaController extends Controller
{
    /** Nomes legíveis dos módulos (log_name) */
    private const MODULOS = [
        'acessos' => 'Acessos',
        'configuracao' => 'Configuração',
        'estrutura' => 'Estrutura académica',
        'calendario' => 'Calendário',
        'estudantes' => 'Estudantes e encarregados',
        'matriculas' => 'Matrículas',
        'inscricoes' => 'Inscrições',
        'docencia' => 'Docentes e turmas',
        'utilizadores' => 'Utilizadores',
        'perfis' => 'Perfis e permissões',
    ];

    /** Descrição dos registos que os modelos gravam sozinhos (RegistaAuditoria) */
    private const EVENTOS = [
        'created' => 'Criado',
        'updated' => 'Alterado',
        'deleted' => 'Apagado',
    ];

    /** Nomes legíveis dos campos alterados */
    private const CAMPOS = [
        'name' => 'Nome',
        'email' => 'E-mail',
        'phone' => 'Telemóvel',
        'active' => 'Estado',
        'perfil' => 'Perfil',
        'permissoes' => 'Permissões',
        'nome' => 'Nome',
        'codigo' => 'Código',
        'tipo' => 'Tipo',
        'grau' => 'Grau',
        'duracao_anos' => 'Duração (anos)',
        'unidade_id' => 'Unidade orgânica',
        'unidade_pai_id' => 'Pertence a',
        'director_id' => 'Director',
        'activo' => 'Estado',
        'creditos' => 'Créditos',
        'horas_teoricas' => 'Horas teóricas',
        'horas_praticas' => 'Horas práticas',
        'inicio' => 'Início',
        'fim' => 'Fim',
        'estado' => 'Estado',
        'inscricoes_inicio' => 'Início das inscrições',
        'inscricoes_fim' => 'Fim das inscrições',
        'exames_inicio' => 'Início dos exames',
        'exames_fim' => 'Fim dos exames',
        'numero' => 'Número',
        'sexo' => 'Sexo',
        'data_nascimento' => 'Data de nascimento',
        'documento_tipo' => 'Tipo de documento',
        'documento_numero' => 'Documento',
        'nuit' => 'NUIT',
        'nacionalidade' => 'Nacionalidade',
        'naturalidade' => 'Naturalidade',
        'provincia' => 'Província',
        'morada' => 'Morada',
        'telemovel' => 'Telemóvel',
        'necessidades_especiais' => 'Necessidades especiais',
        'categoria' => 'Categoria',
        'nota_privada' => 'Nota interna',
        'profissao' => 'Profissão',
        'motivo' => 'Motivo',
        'disciplina' => 'Disciplina',
        'encarregado' => 'Encarregado',
        'parentesco' => 'Parentesco',
        'ip' => 'IP',
        'capacidade' => 'Lotação',
        'grau_academico' => 'Grau académico',
        'regime' => 'Regime',
        'user_id' => 'Conta de acesso',
    ];

    /** Nomes legíveis das definições gravadas em Configuração (chaves da tabela definicoes) */
    private const DEFINICOES = [
        'instituicao.nome' => 'Nome da instituição',
        'instituicao.sigla' => 'Sigla',
        'instituicao.tipo' => 'Tipo de instituição',
        'instituicao.nuit' => 'NUIT',
        'instituicao.morada' => 'Morada',
        'instituicao.cidade' => 'Cidade',
        'instituicao.provincia' => 'Província',
        'instituicao.telefone' => 'Telefone',
        'instituicao.email' => 'E-mail',
        'instituicao.cor' => 'Cor principal',
        'instituicao.cor_destaque' => 'Cor de destaque',
        'instituicao.logotipo' => 'Logótipo',
        'avaliacao.escala_maxima' => 'Nota máxima',
        'avaliacao.casas_decimais' => 'Casas decimais',
        'avaliacao.nota_minima_aprovacao' => 'Nota para aprovar',
        'avaliacao.minimo_admissao_exame' => 'Mínimo para exame',
        'avaliacao.minimo_dispensa_exame' => 'Mínimo para dispensa',
        'avaliacao.assiduidade_minima' => 'Assiduidade mínima',
        'avaliacao.intervalos' => 'Graus da escala',
        'inscricao.limite_creditos' => 'Limite de créditos',
    ];

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'utilizador' => ['nullable', 'integer'],
            'modulo' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::MODULOS))],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        $registos = Activity::query()
            ->with(['causer', 'subject'])
            ->when($filtros['utilizador'] ?? null, fn (Builder $q, $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($filtros['modulo'] ?? null, fn (Builder $q, $modulo) => $q->where('log_name', $modulo))
            ->when($filtros['de'] ?? null, fn (Builder $q, $de) => $q->where('created_at', '>=', Carbon::parse((string) $de)->startOfDay()))
            ->when($filtros['ate'] ?? null, fn (Builder $q, $ate) => $q->where('created_at', '<=', Carbon::parse((string) $ate)->endOfDay()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'data' => $a->created_at?->toIso8601String(),
                'modulo' => self::MODULOS[$a->log_name ?? ''] ?? $a->log_name,
                'evento' => $a->event,
                'descricao' => self::EVENTOS[$a->description] ?? $a->description,
                'utilizador' => $a->causer instanceof User ? $a->causer->name : 'Sistema',
                'registo' => $this->registoQueAcrescenta($a),
                'alteracoes' => $this->alteracoes($a),
                'detalhes' => $this->detalhes($a),
            ]);

        return Inertia::render('administracao/auditoria', [
            'registos' => $registos,
            'filtros' => [
                'utilizador' => isset($filtros['utilizador']) ? (string) $filtros['utilizador'] : '',
                'modulo' => (string) ($filtros['modulo'] ?? ''),
                'de' => (string) ($filtros['de'] ?? ''),
                'ate' => (string) ($filtros['ate'] ?? ''),
            ],
            'utilizadores' => User::orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->values(),
            'modulos' => collect(self::MODULOS)->map(fn (string $rotulo, string $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
        ]);
    }

    /**
     * O registo, só quando acrescenta alguma coisa: não se repete o número que a descrição já tem
     * ("Matrícula MT2027/00041 suspensa") nem a própria pessoa quando entrou no sistema.
     */
    private function registoQueAcrescenta(Activity $a): ?string
    {
        $registo = $this->descreverRegisto($a);
        if ($registo === null || ($a->subject instanceof User && $a->causer instanceof User && $a->subject->is($a->causer))) {
            return null;
        }
        $identificacao = (string) preg_replace('/^(Matrícula|Inscrição|Perfil) /u', '', $registo);

        return str_contains($a->description, $identificacao) ? null : $registo;
    }

    /** O registo a que a acção diz respeito, como as pessoas o reconhecem. */
    private function descreverRegisto(Activity $a): ?string
    {
        $s = $a->subject;

        return match (true) {
            $s instanceof User => $s->name,
            $s instanceof Role => 'Perfil '.$s->name,
            $s instanceof Estudante => "{$s->numero} · {$s->nome}",
            $s instanceof Encarregado => $s->nome,
            $s instanceof Matricula => 'Matrícula '.$s->numero,
            $s instanceof InscricaoPeriodo => 'Inscrição '.$s->codigo,
            $s instanceof PlanoCurricular => 'Plano '.$s->versao.' de '.$s->curso->nome,
            $s instanceof Curso => $s->nome,
            $s instanceof Disciplina => "{$s->codigo} · {$s->nome}",
            $s instanceof UnidadeOrganica => $s->nome,
            $s instanceof AnoLectivo => 'Ano lectivo '.$s->nome,
            $s instanceof Turma => 'Turma '.$s->designacao().' de '.$s->curso->nome,
            $s instanceof Docente => $s->nome,
            $s instanceof Sala => 'Sala '.$s->codigo,
            $s instanceof PeriodoLectivo => $s->nome().' de '.$s->ano->nome,
            default => is_string($perfil = $this->propriedades($a)->get('perfil')) ? 'Perfil '.$perfil : null,
        };
    }

    /** @return list<array{campo: string, antes: string, depois: string}> */
    private function alteracoes(Activity $a): array
    {
        $novos = (array) $this->propriedades($a)->get('attributes', []);
        $antigos = (array) $this->propriedades($a)->get('old', []);
        $lista = [];
        foreach ($novos as $campo => $valor) {
            $lista[] = [
                'campo' => self::CAMPOS[$campo] ?? (string) $campo,
                'antes' => $this->formatar((string) $campo, $antigos[$campo] ?? null),
                'depois' => $this->formatar((string) $campo, $valor),
            ];
        }

        return $lista;
    }

    /** O que os serviços deixam além das alterações: o motivo, a disciplina, as definições que mudaram... */
    private function detalhes(Activity $a): ?string
    {
        $partes = [];
        foreach ($this->propriedades($a)->except(['attributes', 'old', 'perfil']) as $chave => $valor) {
            if ($chave === 'chaves' && is_array($valor)) {
                $partes[] = 'Alterado: '.implode(', ', array_map(fn ($c) => self::DEFINICOES[$c] ?? $c, $valor));
            } elseif (is_scalar($valor) && $valor !== '') {
                $partes[] = (self::CAMPOS[$chave] ?? ucfirst((string) $chave)).': '.$valor;
            }
        }

        return $partes === [] ? null : implode(' · ', $partes);
    }

    /** @return Collection<string, mixed> */
    private function propriedades(Activity $a): Collection
    {
        return collect($a->getAttribute('properties'));
    }

    private function formatar(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if ($campo === 'active') {
            return $valor ? 'Activo' : 'Inactivo';
        }
        if ($campo === 'activo') {
            return $valor ? 'Activo' : 'Inactivo';
        }
        if (in_array($campo, ['inicio', 'fim', 'inscricoes_inicio', 'inscricoes_fim', 'exames_inicio', 'exames_fim', 'data_nascimento'], true)) {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        }
        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }

        return is_scalar($valor) ? (string) $valor : (string) json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}

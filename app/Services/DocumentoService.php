<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\DocumentoEmitido;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\PlanoDisciplina;
use App\Models\User;
use App\Support\Definicoes;
use App\Support\Dinheiro;
use App\Support\Escala;
use App\Support\TipoInstituicao;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Emissão de documentos académicos (RF-90, RF-91).
 *
 * O documento leva tudo o que mostra (dados do estudante, do curso, as disciplinas e as notas) congelado no momento
 * da emissão: se a ficha mudar depois, o documento continua a dizer o que dizia. O conteúdo é assinado com a chave da
 * aplicação (HMAC); a página pública de verificação confere a assinatura, por isso uma alteração directa na base de
 * dados aparece como "não confere" (por isso a APP_KEY tem de se guardar com as cópias de segurança: com outra chave,
 * nenhum documento antigo confere). Só se emite o que os registos sustentam: notas apenas de pautas publicadas
 * (RN-09) e o certificado só com todas as disciplinas obrigatórias do plano aprovadas.
 */
class DocumentoService
{
    /** Sem letras e algarismos que se confundem ao ler em papel (0/O, 1/I). */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    private const POR_EXTENSO = [
        'zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez',
        'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis', 'dezassete', 'dezoito', 'dezanove', 'vinte',
    ];

    public function __construct(private Numeracao $numeracao, private Definicoes $definicoes) {}

    public function emitir(Estudante $estudante, string $tipo, ?Matricula $matricula, ?string $finalidade, User $autor): DocumentoEmitido
    {
        if (! array_key_exists($tipo, DocumentoEmitido::TIPOS)) {
            throw ValidationException::withMessages(['tipo' => 'Escolha o tipo de documento.']);
        }
        if ($matricula !== null && $matricula->estudante_id !== $estudante->id) {
            throw ValidationException::withMessages(['matricula_id' => 'Esta matrícula não é deste estudante.']);
        }
        $matricula ??= $this->ultimaMatricula($estudante);
        if ($matricula === null) {
            throw ValidationException::withMessages(['matricula_id' => "{$estudante->nome} ainda não tem matrícula."]);
        }
        $matricula->loadMissing(['curso', 'anoLectivo']);
        $finalidade = $finalidade !== null && trim($finalidade) !== '' ? trim($finalidade) : null;

        $conteudo = match ($tipo) {
            DocumentoEmitido::DECLARACAO_MATRICULA => $this->declaracaoMatricula($estudante, $matricula),
            DocumentoEmitido::DECLARACAO_FREQUENCIA => $this->declaracaoFrequencia($estudante, $matricula),
            DocumentoEmitido::HISTORICO => $this->historico($estudante, $matricula),
            default => $this->certificado($estudante, $matricula),
        };
        if ($finalidade !== null) {
            $conteudo['texto'][] = 'O presente documento destina-se a '.rtrim($finalidade, '.').'.';
        }

        return DB::transaction(function () use ($estudante, $tipo, $matricula, $finalidade, $conteudo, $autor) {
            $agora = now()->startOfSecond();
            $numero = $this->numeracao->proximo('documento', (int) $agora->format('Y'));
            $codigo = $this->novoCodigo();
            $json = (string) json_encode([
                ...$this->cabecalho($estudante, $matricula, $tipo, $agora),
                ...$conteudo,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $documento = DocumentoEmitido::create([
                'numero' => $numero,
                'tipo' => $tipo,
                'estudante_id' => $estudante->id,
                'matricula_id' => in_array($tipo, DocumentoEmitido::DE_MATRICULA, true) ? $matricula->id : null,
                'finalidade' => $finalidade,
                'conteudo' => $json,
                'codigo_verificacao' => $codigo,
                'assinatura' => self::assinar($numero, $tipo, $estudante->id, $codigo, $json, $agora),
                'estado' => 'valido',
                'emitido_por' => $autor->id,
                'emitido_em' => $agora,
            ]);
            activity('documentos')->performedOn($documento)
                ->log("Documento {$numero} emitido: {$documento->nomeTipo()} de {$estudante->nome} ({$estudante->numero})");

            return $documento;
        });
    }

    /** Anula o documento: continua a existir, e a página de verificação passa a dizer que foi anulado e porquê. */
    public function anular(DocumentoEmitido $documento, string $motivo, User $autor): void
    {
        if (! $documento->valido()) {
            throw ValidationException::withMessages(['motivo' => "O documento {$documento->numero} já está anulado."]);
        }
        $documento->update(['estado' => 'anulado', 'motivo_anulacao' => $motivo, 'anulado_por' => $autor->id, 'anulado_em' => now()]);
        activity('documentos')->performedOn($documento)->withProperties(['motivo' => $motivo])->log("Documento {$documento->numero} anulado");
    }

    /** A assinatura confere com o conteúdo guardado? Falha se alguém mexer no documento fora do sistema. */
    public function confere(DocumentoEmitido $documento): bool
    {
        return hash_equals(
            self::assinar($documento->numero, $documento->tipo, $documento->estudante_id, $documento->codigo_verificacao, $documento->conteudo, $documento->emitido_em),
            $documento->assinatura,
        );
    }

    /** Procura pelo código, como vem do QR ou escrito à mão ("abcd-efgh-jkmn", com ou sem traços). */
    public function procurar(string $codigo): ?DocumentoEmitido
    {
        $codigo = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $codigo));
        if (strlen($codigo) !== 12) {
            return null;
        }

        return DocumentoEmitido::with('estudante')->where('codigo_verificacao', $codigo)->first();
    }

    /** HMAC-SHA256 com a chave da aplicação sobre o que identifica o documento e o conteúdo, tal como foi gravado. */
    public static function assinar(string $numero, string $tipo, int $estudanteId, string $codigo, string $conteudo, CarbonInterface $emitidoEm): string
    {
        $chave = (string) config('app.key');

        return hash_hmac('sha256', implode('|', [$numero, $tipo, $estudanteId, $codigo, $emitidoEm->format('Y-m-d H:i:s'), $conteudo]), $chave);
    }

    private function novoCodigo(): string
    {
        do {
            $codigo = '';
            for ($i = 0; $i < 12; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
        } while (DocumentoEmitido::where('codigo_verificacao', $codigo)->exists());

        return $codigo;
    }

    private function ultimaMatricula(Estudante $estudante): ?Matricula
    {
        return $estudante->matriculas()->with(['curso', 'anoLectivo'])->where('estado', '!=', Matricula::ANULADA)->get()
            ->sortByDesc(fn (Matricula $m) => [$m->anoLectivo->inicio->timestamp, $m->id])->first();
    }

    /* ------------------------------------------------------------------ conteúdos */

    /** @return array<string, mixed> */
    private function declaracaoMatricula(Estudante $estudante, Matricula $matricula): array
    {
        $this->exigirActiva($matricula);

        return [
            'texto' => [
                'Para os devidos efeitos, declara-se que '.$this->identificacao($estudante).', '
                .$this->genero($estudante, 'está matriculado', 'está matriculada').' '.$this->ondeEsta($matricula)
                .', no ano lectivo de '.$matricula->anoLectivo->nome
                .($matricula->regime ? ', em regime '.mb_strtolower((string) Matricula::rotuloRegime($matricula->regime)) : '')
                .", com a matrícula n.º {$matricula->numero}.",
            ],
            'disciplinas' => [],
            'resumo' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function declaracaoFrequencia(Estudante $estudante, Matricula $matricula): array
    {
        $this->exigirActiva($matricula);
        $disciplinas = Inscricao::with(['disciplina', 'inscricaoPeriodo.periodo'])
            ->whereHas('inscricaoPeriodo', fn ($q) => $q->where('matricula_id', $matricula->id)->where('estado', InscricaoPeriodo::CONFIRMADA))
            ->where('estado', '!=', Inscricao::ANULADO)
            ->get()
            ->sortBy(fn (Inscricao $i) => [$i->inscricaoPeriodo->periodo?->numero ?? 0, $i->disciplina->nome])
            ->values();
        if ($disciplinas->isEmpty()) {
            throw ValidationException::withMessages(['tipo' => "{$estudante->nome} ainda não está inscrito em nenhuma disciplina neste ano: emita antes a declaração de matrícula."]);
        }

        return [
            'texto' => [
                'Para os devidos efeitos, declara-se que '.$this->identificacao($estudante).', frequenta, no ano lectivo de '
                .$matricula->anoLectivo->nome.', '.$this->ondeEsta($matricula).', '
                .$this->genero($estudante, 'estando inscrito', 'estando inscrita').' nas disciplinas abaixo indicadas.',
            ],
            'disciplinas' => $disciplinas->map(fn (Inscricao $i) => [
                'ano_lectivo' => $matricula->anoLectivo->nome,
                'periodo' => $i->inscricaoPeriodo->nomePeriodo(),
                'codigo' => $i->disciplina->codigo,
                'nome' => $i->disciplina->nome,
                'creditos' => $this->numero($i->creditos),
                'nota' => null,
            ])->all(),
            'resumo' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function historico(Estudante $estudante, Matricula $matricula): array
    {
        $aprovadas = $this->aprovadas($estudante, $matricula->curso);
        if ($aprovadas->isEmpty()) {
            throw ValidationException::withMessages(['tipo' => "{$estudante->nome} ainda não tem disciplinas aprovadas em pautas publicadas no curso de {$matricula->curso->nome}."]);
        }

        return [
            'texto' => [
                'Certifica-se, de acordo com os registos desta instituição, que '.$this->identificacao($estudante).', '
                .'do curso de '.$matricula->curso->nome
                .', obteve aprovação nas disciplinas abaixo indicadas, com as classificações que se seguem, na escala de 0 a '
                .$this->numero((string) Escala::maxima()).' valores.',
            ],
            'disciplinas' => $this->linhasAprovadas($aprovadas),
            'resumo' => $this->resumo($aprovadas),
        ];
    }

    /** @return array<string, mixed> */
    private function certificado(Estudante $estudante, Matricula $matricula): array
    {
        $aprovadas = $this->aprovadas($estudante, $matricula->curso);
        $aprovadasIds = $aprovadas->map(fn (Inscricao $i) => (int) $i->disciplina_id)->all();
        $emFalta = PlanoDisciplina::with('disciplina')->where('plano_id', $matricula->plano_id)->where('obrigatoria', true)->get()
            ->reject(fn (PlanoDisciplina $l) => in_array((int) $l->disciplina_id, $aprovadasIds, true));
        if ($aprovadas->isEmpty() || $emFalta->isNotEmpty()) {
            $nomes = $emFalta->take(3)->map(fn (PlanoDisciplina $l) => $l->disciplina->nome)->implode(', ');
            throw ValidationException::withMessages(['tipo' => "{$estudante->nome} ainda não concluiu o curso: "
                .($emFalta->count() === 1 ? 'falta aprovar 1 disciplina' : "faltam aprovar {$emFalta->count()} disciplinas")
                .($nomes !== '' ? " ({$nomes}".($emFalta->count() > 3 ? ', …' : '').')' : '').'. Pode emitir o histórico escolar.']);
        }
        $resumo = $this->resumo($aprovadas);
        $final = (int) round((float) $resumo['media'], 0, PHP_ROUND_HALF_UP);
        $resumo['media_final'] = (string) $final;
        $resumo['grau'] = Escala::grau((string) $final);
        $grau = $resumo['grau'] !== null ? ', '.$resumo['grau'] : '';
        $extenso = self::POR_EXTENSO[$final] ?? (string) $final;

        return [
            'texto' => [
                'Certifica-se que '.$this->identificacao($estudante).', '
                .'concluiu o curso de '.$matricula->curso->nome
                .' ('.(Curso::GRAUS[$matricula->curso->grau] ?? $matricula->curso->grau).'), com a média final de '
                ."{$final} ({$extenso}) valores{$grau}, tendo obtido aprovação nas disciplinas abaixo indicadas.",
            ],
            'disciplinas' => $this->linhasAprovadas($aprovadas),
            'resumo' => $resumo,
        ];
    }

    /* ------------------------------------------------------------------ apoio */

    /**
     * As disciplinas aprovadas no curso (só as que vieram de pautas publicadas têm o estado "aprovado"), a mais
     * recente de cada disciplina, por ordem de ano lectivo e período.
     *
     * @return Collection<int, Inscricao>
     */
    private function aprovadas(Estudante $estudante, Curso $curso): Collection
    {
        return Inscricao::with(['disciplina', 'inscricaoPeriodo.periodo', 'inscricaoPeriodo.matricula.anoLectivo'])
            ->where('estudante_id', $estudante->id)
            ->where('estado', Inscricao::APROVADO)
            ->whereNotNull('nota_final')
            ->whereHas('inscricaoPeriodo.matricula', fn ($q) => $q->where('curso_id', $curso->id))
            ->get()
            ->sortByDesc('id')->unique('disciplina_id')
            ->sortBy(fn (Inscricao $i) => [
                $i->inscricaoPeriodo->matricula->anoLectivo->inicio->timestamp,
                $i->inscricaoPeriodo->periodo?->numero ?? 0,
                $i->disciplina->nome,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Inscricao>  $aprovadas
     * @return array<int, array<string, string|null>>
     */
    private function linhasAprovadas(Collection $aprovadas): array
    {
        return $aprovadas->map(fn (Inscricao $i) => [
            'ano_lectivo' => $i->inscricaoPeriodo->matricula->anoLectivo->nome,
            'periodo' => $i->inscricaoPeriodo->nomePeriodo(),
            'codigo' => $i->disciplina->codigo,
            'nome' => $i->disciplina->nome,
            'creditos' => $this->numero($i->creditos),
            'nota' => $this->numero(Escala::arredondar((string) $i->nota_final)),
        ])->values()->all();
    }

    /**
     * Créditos obtidos e média ponderada pelos créditos (média simples se as disciplinas não tiverem créditos).
     *
     * @param  Collection<int, Inscricao>  $aprovadas
     * @return array{disciplinas: int, creditos: string, media: string, media_final: string|null, grau: string|null}
     */
    private function resumo(Collection $aprovadas): array
    {
        $creditos = '0';
        $pontos = '0';
        $soma = '0';
        foreach ($aprovadas as $i) {
            $creditos = bcadd($creditos, (string) $i->creditos, 2);
            $pontos = bcadd($pontos, bcmul((string) $i->nota_final, (string) $i->creditos, 4), 4);
            $soma = bcadd($soma, (string) $i->nota_final, 4);
        }
        $media = bccomp($creditos, '0', 2) > 0
            ? bcdiv($pontos, $creditos, 4)
            : bcdiv($soma, (string) $aprovadas->count(), 4);

        return [
            'disciplinas' => $aprovadas->count(),
            'creditos' => $this->numero($creditos),
            'media' => Dinheiro::arredondar($media, 2),
            'media_final' => null,
            'grau' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function cabecalho(Estudante $estudante, Matricula $matricula, string $tipo, CarbonInterface $agora): array
    {
        $cidade = $this->definicoes->obter('instituicao.cidade');

        return [
            'titulo' => DocumentoEmitido::TIPOS[$tipo],
            'instituicao' => [
                'nome' => $this->definicoes->nome(),
                'nuit' => $this->definicoes->obter('instituicao.nuit'),
                'morada' => trim(implode(', ', array_filter([$this->definicoes->obter('instituicao.morada'), $cidade]))),
            ],
            'estudante' => [
                'nome' => $estudante->nome,
                'numero' => $estudante->numero,
                'documento' => $estudante->documento_numero
                    ? (Estudante::DOCUMENTOS[(string) $estudante->documento_tipo] ?? 'Documento').' n.º '.$estudante->documento_numero
                    : null,
                'data_nascimento' => $estudante->data_nascimento?->toDateString(),
                'naturalidade' => $estudante->naturalidade,
                'nacionalidade' => $estudante->nacionalidade,
            ],
            'curso' => [
                'nome' => $matricula->curso->nome,
                'grau' => Curso::GRAUS[$matricula->curso->grau] ?? $matricula->curso->grau,
            ],
            'matricula' => in_array($tipo, DocumentoEmitido::DE_MATRICULA, true) ? [
                'numero' => $matricula->numero,
                'ano_lectivo' => $matricula->anoLectivo->nome,
                'ano_curricular' => $matricula->ano_curricular,
                'regime' => Matricula::rotuloRegime($matricula->regime),
            ] : null,
            'local_data' => ($cidade ? $cidade.', ' : '').$agora->day.' de '.self::MESES[$agora->month - 1].' de '.$agora->year,
        ];
    }

    private function exigirActiva(Matricula $matricula): void
    {
        if (! $matricula->activa()) {
            throw ValidationException::withMessages(['matricula_id' => "A matrícula {$matricula->numero} não está activa ("
                .mb_strtolower(Matricula::ESTADOS[$matricula->estado] ?? $matricula->estado).'): não se declara uma matrícula que não está em vigor.']);
        }
    }

    /** "Ana Macuácua, portadora do Bilhete de identidade n.º 110100000001A, estudante n.º 2026/00007" */
    private function identificacao(Estudante $estudante): string
    {
        $texto = $estudante->nome;
        if ($estudante->documento_numero) {
            $nome = Estudante::DOCUMENTOS[(string) $estudante->documento_tipo] ?? 'documento';
            $artigo = in_array($estudante->documento_tipo, ['cedula', 'certidao'], true) ? 'da' : 'do';
            $texto .= ', '.$this->genero($estudante, 'portador', 'portadora')." {$artigo} {$nome} n.º {$estudante->documento_numero}";
        }
        $termo = TipoInstituicao::eSuperior() ? 'estudante' : $this->genero($estudante, 'aluno', 'aluna');

        return $texto.", {$termo} n.º {$estudante->numero}";
    }

    /** "no 2.º ano do curso de Licenciatura em Informática de Gestão" (superior) ou "na 8.ª Classe" (geral). */
    private function ondeEsta(Matricula $matricula): string
    {
        if (TipoInstituicao::eSuperior()) {
            return "no {$matricula->ano_curricular}.º ano do curso de {$matricula->curso->nome}";
        }

        return 'em '.$matricula->curso->nome;
    }

    /** Concordância com o sexo da ficha; sem sexo registado, as duas formas ("matriculado(a)"). */
    private function genero(Estudante $estudante, string $masculino, string $feminino): string
    {
        return match ($estudante->sexo) {
            'F' => $feminino,
            'M' => $masculino,
            default => $masculino === $feminino ? $masculino : $masculino.'(a)',
        };
    }

    /** 6.0 → "6"; 4.5 → "4,5"; 14.25 → "14,25" */
    private function numero(string $valor): string
    {
        $texto = rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $texto);
    }
}

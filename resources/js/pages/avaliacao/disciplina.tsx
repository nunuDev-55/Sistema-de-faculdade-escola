import { Bloco, BotaoIcone, CabecalhoFicha, Estado, LinhaVazia, type CorEstado } from '@/components/erp';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Componente {
    id: number | null;
    nome: string;
    peso: number | string;
    data: string | null;
}

interface Estudante {
    inscricao_id: number;
    numero: string;
    nome: string;
    em_atraso: boolean;
    notas: Record<string, string>;
    media: string | null;
}

interface LinhaPauta {
    nota: string;
    nota_final: number | null;
    situacao: string | null;
    situacao_rotulo: string | null;
}

interface Pauta {
    id: number;
    codigo: string | null;
    estado: 'rascunho' | 'submetida' | 'validada' | 'publicada';
    estado_rotulo: string;
    observacao: string | null;
    publicada_em: string | null;
    linhas: Record<string, LinhaPauta>;
}

interface Escala {
    maxima: number;
    aprovacao: number;
    admissao: number;
    dispensa: number;
    peso_exame: number;
}

interface Props {
    leccionacao: {
        id: number;
        disciplina: string;
        codigo: string;
        turma: string;
        turma_id: number;
        curso: string;
        periodo: string;
        ano_lectivo: string;
        docente: string | null;
    };
    componentes: Componente[];
    estudantes: Estudante[];
    pautas: { frequencia: Pauta | null; normal: Pauta | null; recorrencia: Pauta | null };
    escala: Escala;
    podeLancar: boolean;
    podeValidar: boolean;
}

const COR_PAUTA: Record<Pauta['estado'], CorEstado> = { rascunho: 'laranja', submetida: 'azul', validada: 'azul', publicada: 'verde' };
const COR_SITUACAO: Record<string, CorEstado> = {
    dispensado: 'verde',
    aprovado: 'verde',
    admitido: 'azul',
    excluido: 'vermelho',
    reprovado: 'vermelho',
};

/* ---------------------------------------------------------------- Contas (as mesmas do servidor: PautaService) */

const numero = (n: number) => (Math.round(n * 100) / 100).toLocaleString('pt-PT', { maximumFractionDigits: 2 });

/** "12,5" → 12.5; "F" → 0 (faltou); vazio ou inválido → null */
function ler(texto: string | undefined, maxima: number): number | 'invalida' | null {
    const t = (texto ?? '').trim();
    if (t === '') return null;
    if (['F', 'FALTOU'].includes(t.toUpperCase())) return 0;
    const n = Number(t.replace(',', '.'));
    return Number.isFinite(n) && n >= 0 && n <= maxima ? n : 'invalida';
}

function media(componentes: Componente[], notas: Record<string, string>, maxima: number): number | null {
    if (componentes.length === 0) return null;
    let soma = 0;
    for (const c of componentes) {
        const v = ler(notas[String(c.id)], maxima);
        if (v === null || v === 'invalida') return null;
        soma += (v * Number(c.peso)) / 100;
    }
    return Math.round(soma * 100) / 100;
}

function situacaoFrequencia(m: number, e: Escala): string {
    if (m < e.admissao) return 'excluido';
    return m >= e.dispensa ? 'dispensado' : 'admitido';
}

const ROTULO_SITUACAO: Record<string, string> = {
    excluido: 'Excluído',
    admitido: 'Admitido a exame',
    dispensado: 'Dispensado',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
};

/* ---------------------------------------------------------------- Acções de uma pauta */

function AccoesPauta({
    pauta,
    podeLancar,
    podeValidar,
    porGuardar,
}: {
    pauta: Pauta;
    podeLancar: boolean;
    podeValidar: boolean;
    porGuardar: boolean;
}) {
    const [devolver, setDevolver] = useState(false);
    const [motivo, setMotivo] = useState('');
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const opcoes = { preserveScroll: true };

    return (
        <div className="flex flex-col gap-2 border-t px-4 py-3">
            {devolver ? (
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        value={motivo}
                        onChange={(e) => setMotivo(e.target.value)}
                        placeholder="O que o docente tem de corrigir"
                        aria-label="Motivo da devolução"
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setDevolver(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(route('pautas.devolver', pauta.id), { motivo }, { ...opcoes, onSuccess: () => setDevolver(false) })
                            }
                            disabled={motivo.trim() === ''}
                        >
                            Devolver
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <Button asChild size="sm" variant="ghost">
                        <Link href={route('pautas.imprimir', pauta.id)}>
                            <FileText className="size-4" /> Pauta para imprimir
                        </Link>
                    </Button>
                    {pauta.estado === 'rascunho' && podeLancar && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={porGuardar}
                            title={porGuardar ? 'Guarde as notas primeiro' : undefined}
                            onClick={() => {
                                if (confirm('Submeter a pauta? Depois de submetida, as notas só mudam se a devolverem.')) {
                                    router.post(route('pautas.submeter', pauta.id), {}, opcoes);
                                }
                            }}
                        >
                            Submeter para validação
                        </Button>
                    )}
                    {podeValidar && ['submetida', 'validada'].includes(pauta.estado) && (
                        <Button size="sm" variant="outline" onClick={() => setDevolver(true)}>
                            Devolver ao docente
                        </Button>
                    )}
                    {podeValidar && pauta.estado === 'submetida' && (
                        <Button size="sm" onClick={() => router.post(route('pautas.validar', pauta.id), {}, opcoes)}>
                            Validar
                        </Button>
                    )}
                    {podeValidar && pauta.estado === 'validada' && (
                        <Button
                            size="sm"
                            onClick={() => {
                                if (
                                    confirm('Publicar a pauta? Os resultados passam a contar para os estudantes e a pauta deixa de se poder alterar.')
                                ) {
                                    router.post(route('pautas.publicar', pauta.id), {}, opcoes);
                                }
                            }}
                        >
                            Publicar
                        </Button>
                    )}
                </div>
            )}
            <InputError message={errors.pauta} />
        </div>
    );
}

function TituloPauta({ titulo, pauta }: { titulo: string; pauta: Pauta }) {
    return (
        <span className="flex flex-wrap items-center gap-3">
            {titulo}
            <Estado cor={COR_PAUTA[pauta.estado]}>{pauta.estado_rotulo}</Estado>
            {pauta.codigo && <span className="text-muted-foreground font-mono text-[12px] font-normal">{pauta.codigo}</span>}
        </span>
    );
}

/* ---------------------------------------------------------------- Plano de avaliação */

const MODELO: Componente[] = [
    { id: null, nome: '1.º teste', peso: 30, data: null },
    { id: null, nome: '2.º teste', peso: 30, data: null },
    { id: null, nome: '1.º trabalho', peso: 20, data: null },
    { id: null, nome: '2.º trabalho', peso: 20, data: null },
];

function PlanoAvaliacao({ leccionacaoId, componentes, editavel }: { leccionacaoId: number; componentes: Componente[]; editavel: boolean }) {
    const [lista, setLista] = useState<Componente[]>(componentes.length > 0 ? componentes : editavel ? MODELO : []);
    const [aEditar, setAEditar] = useState(componentes.length === 0 && editavel);
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const soma = lista.reduce((s, c) => s + (Number(String(c.peso).replace(',', '.')) || 0), 0);
    const mudar = (i: number, campo: keyof Componente, valor: string) => setLista(lista.map((c, n) => (n === i ? { ...c, [campo]: valor } : c)));
    const guardar = () =>
        router.put(
            route('avaliacao.plano', leccionacaoId),
            { componentes: lista as never },
            { preserveScroll: true, onSuccess: () => setAEditar(false) },
        );

    if (!aEditar) {
        return (
            <Bloco
                titulo="Plano de avaliação"
                accoes={
                    editavel && (
                        <Button size="sm" variant="ghost" onClick={() => setAEditar(true)}>
                            Alterar
                        </Button>
                    )
                }
            >
                {componentes.length === 0 ? (
                    <p className="text-muted-foreground px-4 py-6 text-center text-[13.5px]">O docente ainda não definiu o plano de avaliação.</p>
                ) : (
                    <ul className="flex flex-wrap gap-x-6 gap-y-2 px-4 py-3 text-[13.5px]">
                        {componentes.map((c) => (
                            <li key={c.id}>
                                <span className="font-medium">{c.nome}</span>{' '}
                                <span className="text-muted-foreground tabular-nums">{numero(Number(c.peso))} %</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Bloco>
        );
    }

    return (
        <Bloco
            titulo="Plano de avaliação"
            accoes={
                <Button size="sm" variant="ghost" onClick={() => setLista(MODELO)}>
                    Usar 2 testes + 2 trabalhos
                </Button>
            }
        >
            <table className="tabela" data-tabela-fixa="">
                <thead>
                    <tr>
                        <th>Componente</th>
                        <th className="w-28 text-right">Peso (%)</th>
                        <th className="hidden w-44 sm:table-cell">Data</th>
                        <th className="w-10" />
                    </tr>
                </thead>
                <tbody>
                    {lista.map((c, i) => (
                        <tr key={i}>
                            <td>
                                <Input value={c.nome} onChange={(e) => mudar(i, 'nome', e.target.value)} aria-label={`Nome do componente ${i + 1}`} />
                            </td>
                            <td>
                                <Input
                                    value={String(c.peso).replace('.', ',')}
                                    onChange={(e) => mudar(i, 'peso', e.target.value)}
                                    inputMode="decimal"
                                    className="text-right tabular-nums"
                                    aria-label={`Peso de ${c.nome}`}
                                />
                            </td>
                            <td className="hidden sm:table-cell">
                                <Input
                                    type="date"
                                    value={c.data ?? ''}
                                    onChange={(e) => mudar(i, 'data', e.target.value)}
                                    aria-label={`Data de ${c.nome}`}
                                />
                            </td>
                            <td>
                                <BotaoIcone
                                    rotulo={`Tirar ${c.nome}`}
                                    onClick={() => setLista(lista.filter((_, n) => n !== i))}
                                    disabled={lista.length === 1}
                                >
                                    <Trash2 className="size-4" />
                                </BotaoIcone>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-[13.5px]">
                    <span className={cn('font-semibold tabular-nums', Math.abs(soma - 100) > 0.001 && 'text-red-600 dark:text-red-400')}>
                        Soma: {numero(soma)} %
                    </span>
                    {Math.abs(soma - 100) > 0.001 && <span className="text-muted-foreground"> · tem de dar 100 %</span>}
                    <InputError message={errors.componentes} className="mt-1" />
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setLista([...lista, { id: null, nome: '', peso: 0, data: null }])}
                        disabled={lista.length >= 10}
                    >
                        <Plus /> Componente
                    </Button>
                    {componentes.length > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setLista(componentes);
                                setAEditar(false);
                            }}
                        >
                            Cancelar
                        </Button>
                    )}
                    <Button size="sm" onClick={guardar} disabled={Math.abs(soma - 100) > 0.001}>
                        Guardar o plano
                    </Button>
                </div>
            </div>
        </Bloco>
    );
}

/* ---------------------------------------------------------------- Folha de notas da frequência */

function Frequencia({ props }: { props: Props }) {
    const { leccionacao, componentes, estudantes, escala, podeLancar, podeValidar } = props;
    const pauta = props.pautas.frequencia;
    const editavel = podeLancar && pauta?.estado === 'rascunho';
    const inicial = useMemo(() => Object.fromEntries(estudantes.map((e) => [e.inscricao_id, e.notas])), [estudantes]);
    const [notas, setNotas] = useState<Record<number, Record<string, string>>>(inicial);
    const [aGuardar, setAGuardar] = useState(false);
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const porGuardar = JSON.stringify(notas) !== JSON.stringify(inicial);
    const doAno = estudantes.filter((e) => !e.em_atraso).length;

    const mudar = (inscricao: number, componente: number | null, valor: string) =>
        setNotas({ ...notas, [inscricao]: { ...notas[inscricao], [String(componente)]: valor } });
    const guardar = () => {
        setAGuardar(true);
        router.put(route('avaliacao.notas', leccionacao.id), { notas: notas as never }, { preserveScroll: true, onFinish: () => setAGuardar(false) });
    };

    if (!pauta) return null;
    const publicada = pauta.estado !== 'rascunho';

    return (
        <Bloco
            titulo={
                <TituloPauta
                    titulo={`Frequência (${estudantes.length} estudantes${doAno < estudantes.length ? `, ${estudantes.length - doAno} em atraso` : ''})`}
                    pauta={pauta}
                />
            }
        >
            {pauta.observacao && pauta.estado === 'rascunho' && (
                <p className="border-b bg-amber-50 px-4 py-2.5 text-[13px] text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Devolvida: {pauta.observacao}
                </p>
            )}
            {componentes.length === 0 ? (
                <p className="text-muted-foreground px-4 py-6 text-center text-[13.5px]">Defina o plano de avaliação para lançar as notas.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="tabela" data-tabela-fixa="">
                        <thead>
                            <tr>
                                <th>Estudante</th>
                                {componentes.map((c) => (
                                    <th key={c.id} className="text-center whitespace-nowrap">
                                        {c.nome}
                                        <div className="text-muted-foreground text-[11px] font-normal">{numero(Number(c.peso))} %</div>
                                    </th>
                                ))}
                                <th className="text-right">Média</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            {estudantes.length === 0 && (
                                <LinhaVazia colunas={componentes.length + 3} texto="Ainda não há estudantes desta turma nesta disciplina." />
                            )}
                            {estudantes.map((e) => {
                                const linha = pauta.linhas[String(e.inscricao_id)];
                                const m =
                                    publicada && linha
                                        ? linha.nota === ''
                                            ? null
                                            : Number(linha.nota.replace(',', '.'))
                                        : media(componentes, notas[e.inscricao_id] ?? {}, escala.maxima);
                                const situacao = publicada && linha?.situacao ? linha.situacao : m === null ? null : situacaoFrequencia(m, escala);
                                return (
                                    <tr key={e.inscricao_id}>
                                        <td className="min-w-48">
                                            <span className="font-medium">{e.nome}</span>
                                            <div className="text-muted-foreground font-mono text-[11.5px]">
                                                {e.numero}
                                                {e.em_atraso && ' · em atraso'}
                                            </div>
                                        </td>
                                        {componentes.map((c) => {
                                            const valor = notas[e.inscricao_id]?.[String(c.id)] ?? '';
                                            const invalida = ler(valor, escala.maxima) === 'invalida';
                                            return (
                                                <td key={c.id} className="text-center">
                                                    {editavel ? (
                                                        <Input
                                                            value={valor}
                                                            onChange={(ev) => mudar(e.inscricao_id, c.id, ev.target.value)}
                                                            inputMode="decimal"
                                                            className={cn('mx-auto w-20 text-center tabular-nums', invalida && 'border-red-500')}
                                                            aria-label={`${c.nome} de ${e.nome}`}
                                                            aria-invalid={invalida}
                                                        />
                                                    ) : (
                                                        <span className="tabular-nums">{valor || '—'}</span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                        <td className="text-right font-semibold tabular-nums">{m === null ? '—' : numero(m)}</td>
                                        <td>
                                            {situacao ? (
                                                <Estado cor={COR_SITUACAO[situacao] ?? 'cinza'}>{ROTULO_SITUACAO[situacao]}</Estado>
                                            ) : (
                                                <span className="text-muted-foreground">Por lançar</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            {editavel && componentes.length > 0 && estudantes.length > 0 && (
                <div className="flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-[12.5px]">
                        Escreva a nota (ex.: 12,5) ou F para quem faltou (conta zero). Abaixo de {numero(escala.admissao)} exclui; a partir de{' '}
                        {numero(escala.dispensa)} dispensa do exame.
                    </p>
                    <div className="flex flex-col items-end gap-1">
                        <Button size="sm" onClick={guardar} disabled={!porGuardar || aGuardar}>
                            Guardar as notas
                        </Button>
                        <InputError message={errors.notas} />
                    </div>
                </div>
            )}
            <AccoesPauta pauta={pauta} podeLancar={podeLancar} podeValidar={podeValidar} porGuardar={porGuardar} />
        </Bloco>
    );
}

/* ---------------------------------------------------------------- Exame normal e recorrência */

function Exame({ titulo, pauta, props }: { titulo: string; pauta: Pauta; props: Props }) {
    const { estudantes, escala, podeLancar, podeValidar } = props;
    const frequencia = props.pautas.frequencia;
    const editavel = podeLancar && pauta.estado === 'rascunho';
    const inicial = useMemo(() => Object.fromEntries(Object.entries(pauta.linhas).map(([id, l]) => [id, l.nota])), [pauta.linhas]);
    const [notas, setNotas] = useState<Record<string, string>>(inicial);
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const porGuardar = JSON.stringify(notas) !== JSON.stringify(inicial);
    const linhas = estudantes.filter((e) => pauta.linhas[String(e.inscricao_id)] !== undefined);

    return (
        <Bloco titulo={<TituloPauta titulo={`${titulo} (${linhas.length})`} pauta={pauta} />}>
            {pauta.observacao && pauta.estado === 'rascunho' && (
                <p className="border-b bg-amber-50 px-4 py-2.5 text-[13px] text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Devolvida: {pauta.observacao}
                </p>
            )}
            <table className="tabela" data-tabela-fixa="">
                <thead>
                    <tr>
                        <th>Estudante</th>
                        <th className="text-right">Frequência</th>
                        <th className="text-center">Exame</th>
                        <th className="text-right">Nota final</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    {linhas.map((e) => {
                        const id = String(e.inscricao_id);
                        const linha = pauta.linhas[id];
                        const mfTexto = frequencia?.linhas[id]?.nota ?? '';
                        const mf = mfTexto === '' ? null : Number(mfTexto.replace(',', '.'));
                        const exame = ler(notas[id], escala.maxima);
                        const faltou = (notas[id] ?? '').trim().toUpperCase().startsWith('F');
                        const final =
                            pauta.estado !== 'rascunho'
                                ? linha.nota_final
                                : exame === null || exame === 'invalida' || faltou || mf === null
                                  ? null
                                  : mf * (1 - escala.peso_exame / 100) + exame * (escala.peso_exame / 100);
                        const situacao =
                            pauta.estado !== 'rascunho'
                                ? linha.situacao
                                : faltou
                                  ? 'reprovado'
                                  : final === null
                                    ? null
                                    : final >= escala.aprovacao
                                      ? 'aprovado'
                                      : 'reprovado';
                        return (
                            <tr key={id}>
                                <td className="min-w-48">
                                    <span className="font-medium">{e.nome}</span>
                                    <div className="text-muted-foreground font-mono text-[11.5px]">{e.numero}</div>
                                </td>
                                <td className="text-right tabular-nums">{mf === null ? '—' : numero(mf)}</td>
                                <td className="text-center">
                                    {editavel ? (
                                        <Input
                                            value={notas[id] ?? ''}
                                            onChange={(ev) => setNotas({ ...notas, [id]: ev.target.value })}
                                            inputMode="decimal"
                                            className={cn('mx-auto w-20 text-center tabular-nums', exame === 'invalida' && 'border-red-500')}
                                            aria-label={`Exame de ${e.nome}`}
                                        />
                                    ) : (
                                        <span className="tabular-nums">{linha.nota || '—'}</span>
                                    )}
                                </td>
                                <td className="text-right font-semibold tabular-nums">{final === null ? '—' : numero(final)}</td>
                                <td>
                                    {situacao ? (
                                        <Estado cor={COR_SITUACAO[situacao] ?? 'cinza'}>{ROTULO_SITUACAO[situacao]}</Estado>
                                    ) : (
                                        <span className="text-muted-foreground">Por lançar</span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            {editavel && (
                <div className="flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-[12.5px]">
                        Nota final = {numero(100 - escala.peso_exame)} % da frequência + {numero(escala.peso_exame)} % do exame. Aprova com{' '}
                        {numero(escala.aprovacao)} ou mais.
                    </p>
                    <div className="flex flex-col items-end gap-1">
                        <Button
                            size="sm"
                            onClick={() => router.put(route('pautas.exame', pauta.id), { notas }, { preserveScroll: true })}
                            disabled={!porGuardar}
                        >
                            Guardar as notas
                        </Button>
                        <InputError message={errors.notas} />
                    </div>
                </div>
            )}
            <AccoesPauta pauta={pauta} podeLancar={podeLancar} podeValidar={podeValidar} porGuardar={porGuardar} />
        </Bloco>
    );
}

export default function AvaliacaoDisciplina(props: Props) {
    const { leccionacao, componentes, pautas, podeLancar } = props;
    const planoEditavel = podeLancar && pautas.frequencia?.estado === 'rascunho';

    return (
        <AppLayout>
            <Head title={`${leccionacao.disciplina} · ${leccionacao.turma}`} />
            <CabecalhoFicha
                voltar={podeLancar ? { href: route('docencia.minhas'), label: 'As minhas turmas' } : { href: route('pautas.index'), label: 'Pautas' }}
                referencia={leccionacao.disciplina}
                titulo={
                    <>
                        Turma {leccionacao.turma} · {leccionacao.curso}
                    </>
                }
                subtitulo={[
                    leccionacao.periodo,
                    `Ano lectivo ${leccionacao.ano_lectivo}`,
                    leccionacao.docente ? `Docente: ${leccionacao.docente}` : 'Sem docente atribuído',
                ].join(' · ')}
            />
            <div className="flex flex-col gap-5">
                <PlanoAvaliacao
                    key={componentes.map((c) => `${c.id}:${c.peso}`).join('|')}
                    leccionacaoId={leccionacao.id}
                    componentes={componentes}
                    editavel={planoEditavel}
                />
                <Frequencia key={`f${pautas.frequencia?.estado}${componentes.length}`} props={props} />
                {pautas.normal && <Exame key={`n${pautas.normal.estado}`} titulo="Exame normal" pauta={pautas.normal} props={props} />}
                {pautas.recorrencia && (
                    <Exame key={`r${pautas.recorrencia.estado}`} titulo="Exame de recorrência" pauta={pautas.recorrencia} props={props} />
                )}
            </div>
        </AppLayout>
    );
}

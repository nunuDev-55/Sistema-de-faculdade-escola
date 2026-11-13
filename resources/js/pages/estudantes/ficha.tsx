import { Bloco, BotaoIcone, BotaoLink, CabecalhoFicha, Campo, Campos, Estado, LinhaForm, LinhaVazia, Separadores } from '@/components/erp';
import { COR_ESTUDANTE, COR_MATRICULA, creditos } from '@/components/estudantes';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Estudante {
    id: number;
    numero: string;
    nome: string;
    sexo_rotulo: string | null;
    data_nascimento: string | null;
    documento_rotulo: string | null;
    documento_numero: string | null;
    nuit: string | null;
    nacionalidade: string | null;
    naturalidade: string | null;
    provincia: string | null;
    morada: string | null;
    telemovel: string | null;
    email: string | null;
    necessidades_especiais: string | null;
    categoria_rotulo: string;
    estado: string;
    estado_rotulo: string;
    nota_privada: string | null;
    registado_em: string;
}

interface ResumoInscricao {
    id: number;
    codigo: string;
    estado: string;
    disciplinas: number;
    creditos: number;
    em_atraso: number;
}

interface Matricula {
    id: number;
    numero: string;
    ano_lectivo: string;
    curso: string;
    curso_id: number;
    ano_curricular: number;
    regime: string | null;
    plano: string;
    plano_id: number;
    estado: string;
    estado_rotulo: string;
    motivo: string | null;
    data: string;
    periodos: { id: number; nome: string; inscricao: ResumoInscricao | null }[];
    inscricao_anual: ResumoInscricao | null;
}

interface Encarregado {
    id: number;
    nome: string;
    parentesco: string;
    principal: boolean;
    ve_financas: boolean;
    telemovel: string | null;
    email: string | null;
    profissao: string | null;
    outros_educandos: string[];
}

interface OpcoesMatricula {
    anos: Opcao[];
    cursos: (Opcao & { duracao: number; tem_plano: boolean })[];
    regimes: Opcao[];
    sugestao: { ano_lectivo_id: string; curso_id: string; ano_curricular: string; regime: string };
}

interface Props {
    estudante: Estudante;
    encarregados: Encarregado[];
    matriculas: Matricula[];
    opcoesMatricula: OpcoesMatricula;
    parentescos: Opcao[];
    superior: boolean;
    financas: ContaCorrente | null;
    metodos: Opcao[];
}

type Separador = 'percurso' | 'dados' | 'encarregados' | 'financas';

interface ContaCorrente {
    cobrancas: {
        id: number;
        numero: string;
        descricao: string;
        valor: string;
        pago: string;
        vencimento: string;
        estado: string;
        estado_rotulo: string;
        vencida: boolean;
    }[];
    pagamentos: { id: number; numero: string; data: string; valor: string; metodo: string; estado: string }[];
    divida: string;
    vencido: string;
    credito: string;
}

/* ---------------------------------------------------------------- Finanças */

function NovoPagamento({ estudanteId, metodos, fechar }: { estudanteId: number; metodos: Opcao[]; fechar: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        valor: '',
        data: new Date().toISOString().slice(0, 10),
        metodo: 'numerario',
        referencia: '',
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('pagamentos.store', estudanteId), { onSuccess: fechar });
    };
    return (
        <form onSubmit={guardar} className="border-b">
            <LinhaForm
                rotulo="Valor (MT)"
                htmlFor="pg_valor"
                obrigatorio
                ajuda="Abate as cobranças que vencem primeiro; o que sobrar fica como adiantamento."
            >
                <Input
                    id="pg_valor"
                    inputMode="decimal"
                    value={data.valor}
                    onChange={(e) => setData('valor', e.target.value)}
                    className="max-w-40 tabular-nums"
                />
                <InputError message={errors.valor} className="mt-1" />
            </LinhaForm>
            <LinhaForm rotulo="Data" htmlFor="pg_data" obrigatorio>
                <Input id="pg_data" type="date" value={data.data} onChange={(e) => setData('data', e.target.value)} className="max-w-48" />
                <InputError message={errors.data} className="mt-1" />
            </LinhaForm>
            <LinhaForm rotulo="Forma de pagamento" htmlFor="pg_metodo" obrigatorio>
                <CaixaEscolha id="pg_metodo" value={data.metodo} onChange={(e) => setData('metodo', e.target.value)} className="max-w-60">
                    {metodos.map((m) => (
                        <option key={m.valor} value={m.valor}>
                            {m.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
            </LinhaForm>
            <LinhaForm rotulo="Referência" htmlFor="pg_referencia" ajuda="Número do talão do banco ou da transacção M-Pesa / e-Mola.">
                <Input id="pg_referencia" value={data.referencia} onChange={(e) => setData('referencia', e.target.value)} className="max-w-64" />
            </LinhaForm>
            <div className="flex justify-end gap-2 px-5 py-3">
                <Button type="button" variant="outline" onClick={fechar}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={processing}>
                    Registar e emitir recibo
                </Button>
            </div>
        </form>
    );
}

function Financas({ estudanteId, conta, metodos }: { estudanteId: number; conta: ContaCorrente; metodos: Opcao[] }) {
    const pode = usePode();
    const [novo, setNovo] = useState(false);
    const mt = (v: string) => `${Number(v).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MT`;
    return (
        <div className="flex flex-col gap-5">
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    ['Em dívida', conta.divida, ''],
                    ['Vencido', conta.vencido, 'text-red-700 dark:text-red-400'],
                    ['Adiantamento', conta.credito, 'text-green-700 dark:text-green-400'],
                ].map(([rotulo, valor, cor]) => (
                    <div key={rotulo} className="painel px-4 py-3">
                        <div className="text-muted-foreground text-[12.5px]">{rotulo}</div>
                        <div className={`text-[19px] font-semibold tabular-nums ${cor}`}>{mt(valor)}</div>
                    </div>
                ))}
            </div>
            <Bloco
                titulo="Pagamentos"
                accoes={
                    pode('pagamentos.registar') &&
                    !novo && (
                        <Button size="sm" onClick={() => setNovo(true)}>
                            <Plus /> Registar pagamento
                        </Button>
                    )
                }
            >
                {novo && <NovoPagamento estudanteId={estudanteId} metodos={metodos} fechar={() => setNovo(false)} />}
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Recibo</th>
                            <th>Data</th>
                            <th>Forma</th>
                            <th className="text-right">Valor</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {conta.pagamentos.length === 0 && <LinhaVazia colunas={5} texto="Ainda não há pagamentos." />}
                        {conta.pagamentos.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link href={route('pagamentos.recibo', p.id)} className="font-mono text-[12.5px] hover:underline">
                                        {p.numero}
                                    </Link>
                                </td>
                                <td className="tabular-nums">{formatData(p.data)}</td>
                                <td>{p.metodo}</td>
                                <td className="text-right tabular-nums">{mt(p.valor)}</td>
                                <td>{p.estado === 'valido' ? <Estado cor="verde">Válido</Estado> : <Estado cor="cinza">Anulado</Estado>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
            <Bloco titulo="Cobranças">
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Cobrança</th>
                            <th>Descrição</th>
                            <th>Vencimento</th>
                            <th className="text-right">Valor</th>
                            <th className="text-right">Pago</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {conta.cobrancas.length === 0 && <LinhaVazia colunas={6} texto="Ainda não há cobranças." />}
                        {conta.cobrancas.map((c) => (
                            <tr key={c.id}>
                                <td className="font-mono text-[12.5px]">{c.numero}</td>
                                <td>{c.descricao}</td>
                                <td className="tabular-nums">{formatData(c.vencimento)}</td>
                                <td className="text-right tabular-nums">{mt(c.valor)}</td>
                                <td className="text-right tabular-nums">{mt(c.pago)}</td>
                                <td>
                                    {c.vencida ? (
                                        <Estado cor="vermelho">Vencida</Estado>
                                    ) : (
                                        <Estado
                                            cor={
                                                c.estado === 'paga'
                                                    ? 'verde'
                                                    : c.estado === 'parcial'
                                                      ? 'azul'
                                                      : c.estado === 'anulada'
                                                        ? 'cinza'
                                                        : 'laranja'
                                            }
                                        >
                                            {c.estado_rotulo}
                                        </Estado>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
        </div>
    );
}

/* ---------------------------------------------------------------- Nova matrícula */

function NovaMatricula({ estudanteId, opcoes, fechar }: { estudanteId: number; opcoes: OpcoesMatricula; fechar: () => void }) {
    const termos = useTermos();
    const { data, setData, post, processing, errors } = useForm(opcoes.sugestao);
    const curso = opcoes.cursos.find((c) => c.valor === data.curso_id);
    const anos = Array.from({ length: curso?.duracao ?? 1 }, (_, i) => String(i + 1));
    const erros = errors as Record<string, string | undefined>;

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('matriculas.store', estudanteId), { preserveScroll: true, onSuccess: fechar });
    };

    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo="Nova matrícula">
                {erros.estudante && <p className="border-b px-5 py-3 text-[13px] text-red-700 dark:text-red-300">{erros.estudante}</p>}
                <LinhaForm rotulo="Ano lectivo" htmlFor="ano_lectivo_id" obrigatorio>
                    <CaixaEscolha
                        id="ano_lectivo_id"
                        value={data.ano_lectivo_id}
                        onChange={(e) => setData('ano_lectivo_id', e.target.value)}
                        className="max-w-48"
                    >
                        <option value="">—</option>
                        {opcoes.anos.map((a) => (
                            <option key={a.valor} value={a.valor}>
                                {a.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                    {opcoes.anos.length === 0 && (
                        <p className="text-muted-foreground mt-1 text-xs">Crie primeiro o ano lectivo em Estrutura → Calendário.</p>
                    )}
                    <InputError message={errors.ano_lectivo_id} className="mt-1" />
                </LinhaForm>
                <LinhaForm
                    rotulo={termos.curso}
                    htmlFor="curso_id"
                    obrigatorio
                    ajuda="Fica no plano curricular em vigor; na renovação, mantém o plano em que entrou."
                >
                    <CaixaEscolha
                        id="curso_id"
                        value={data.curso_id}
                        onChange={(e) => {
                            setData('curso_id', e.target.value);
                            setData('ano_curricular', '1');
                        }}
                        className="max-w-96"
                    >
                        <option value="">—</option>
                        {opcoes.cursos.map((c) => (
                            <option key={c.valor} value={c.valor} disabled={!c.tem_plano}>
                                {c.rotulo}
                                {!c.tem_plano && ' (sem plano em vigor)'}
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.curso_id} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo={termos.ano_curricular} htmlFor="ano_curricular" obrigatorio>
                    <CaixaEscolha
                        id="ano_curricular"
                        value={data.ano_curricular}
                        onChange={(e) => setData('ano_curricular', e.target.value)}
                        className="max-w-32"
                    >
                        {anos.map((a) => (
                            <option key={a} value={a}>
                                {a}.º
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.ano_curricular} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Regime" htmlFor="regime">
                    <CaixaEscolha id="regime" value={data.regime} onChange={(e) => setData('regime', e.target.value)} className="max-w-48">
                        <option value="">—</option>
                        {opcoes.regimes.map((r) => (
                            <option key={r.valor} value={r.valor}>
                                {r.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.regime} className="mt-1" />
                </LinhaForm>
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Matricular
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

/* ---------------------------------------------------------------- Uma matrícula e as suas inscrições */

function MudarEstado({ matricula, fechar }: { matricula: Matricula; fechar: () => void }) {
    const accoes = matricula.estado === 'suspensa' ? ['reactivar', 'anular'] : ['suspender', 'anular'];
    const { data, setData, put, processing, errors } = useForm({ accao: accoes[0], motivo: '' });
    const erros = errors as Record<string, string | undefined>;
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (data.accao === 'anular' && !confirm(`Anular a matrícula ${matricula.numero}? As inscrições sem resultado também ficam anuladas.`)) return;
        put(route('matriculas.estado', matricula.id), { preserveScroll: true, onSuccess: fechar });
    };
    return (
        <form onSubmit={guardar} className="bg-muted/40 flex flex-col gap-2 border-b px-4 py-3 sm:flex-row sm:items-start">
            <CaixaEscolha value={data.accao} onChange={(e) => setData('accao', e.target.value)} className="sm:max-w-40" aria-label="Acção">
                {accoes.map((a) => (
                    <option key={a} value={a}>
                        {a.charAt(0).toUpperCase() + a.slice(1)}
                    </option>
                ))}
            </CaixaEscolha>
            {data.accao !== 'reactivar' && (
                <div className="flex-1">
                    <Input
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        placeholder="Motivo (fica no registo)"
                        aria-label="Motivo"
                    />
                    <InputError message={errors.motivo ?? erros.matricula} className="mt-1" />
                </div>
            )}
            <div className="flex gap-2">
                <Button type="button" size="sm" variant="outline" onClick={fechar}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" disabled={processing} variant={data.accao === 'anular' ? 'destructive' : 'default'}>
                    Confirmar
                </Button>
            </div>
        </form>
    );
}

function LinhaInscricao({
    nome,
    inscricao,
    matricula,
    periodoId,
}: {
    nome: string;
    inscricao: ResumoInscricao | null;
    matricula: Matricula;
    periodoId: number | null;
}) {
    const pode = usePode();
    const confirmada = inscricao !== null && inscricao.estado === 'confirmada';
    const podeInscrever = periodoId !== null && matricula.estado === 'activa' && pode('inscricoes.gerir');
    return (
        <tr>
            <td className="font-medium">{nome}</td>
            <td className="font-mono text-[12.5px]">{inscricao?.codigo ?? '—'}</td>
            <td className="text-right tabular-nums">
                {confirmada ? inscricao.disciplinas : '—'}
                {confirmada && inscricao.em_atraso > 0 && <span className="text-muted-foreground text-xs"> ({inscricao.em_atraso} em atraso)</span>}
            </td>
            <td className="text-right tabular-nums">{confirmada ? creditos(inscricao.creditos) : '—'}</td>
            <td>
                {inscricao === null ? (
                    <Estado cor="cinza">Por inscrever</Estado>
                ) : confirmada ? (
                    <Estado cor="verde">Inscrito</Estado>
                ) : (
                    <Estado cor="vermelho">Anulada</Estado>
                )}
            </td>
            <td>
                <div className="flex justify-end gap-1.5 whitespace-nowrap">
                    {confirmada && (
                        <Button asChild size="sm" variant="ghost">
                            <Link href={route('inscricoes.comprovativo', inscricao.id)}>
                                <FileText className="size-4" /> Comprovativo
                            </Link>
                        </Button>
                    )}
                    {podeInscrever && (
                        <Button asChild size="sm" variant={confirmada ? 'outline' : 'default'}>
                            <Link href={route('inscricoes.create', [matricula.id, periodoId])}>{confirmada ? 'Alterar' : 'Inscrever'}</Link>
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function BlocoMatricula({ matricula, superior }: { matricula: Matricula; superior: boolean }) {
    const pode = usePode();
    const termos = useTermos();
    const [mudar, setMudar] = useState(false);
    const podeMudar = pode('matriculas.estado') && ['activa', 'suspensa'].includes(matricula.estado);

    return (
        <Bloco
            titulo={
                <span>
                    {matricula.ano_lectivo} · {matricula.curso}
                </span>
            }
            accoes={
                <div className="flex items-center gap-3">
                    <Estado cor={COR_MATRICULA[matricula.estado] ?? 'cinza'}>{matricula.estado_rotulo}</Estado>
                    {podeMudar && !mudar && (
                        <Button size="sm" variant="ghost" onClick={() => setMudar(true)}>
                            Mudar estado
                        </Button>
                    )}
                </div>
            }
        >
            {mudar && <MudarEstado matricula={matricula} fechar={() => setMudar(false)} />}
            <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 border-b px-4 py-2.5 text-[13px]">
                <span className="text-foreground font-mono">{matricula.numero}</span>
                <span>
                    {matricula.ano_curricular}.º {termos.ano_curricular.toLowerCase()}
                </span>
                {matricula.regime && <span>{matricula.regime}</span>}
                <Link href={route('estrutura.planos.show', matricula.plano_id)} className="hover:text-foreground underline-offset-2 hover:underline">
                    Plano {matricula.plano}
                </Link>
                <span>desde {formatData(matricula.data)}</span>
                {matricula.motivo && <span className="text-foreground">Motivo: {matricula.motivo}</span>}
            </div>
            <table className="tabela">
                <thead>
                    <tr>
                        <th>{superior ? termos.periodo : 'Inscrição'}</th>
                        <th>Comprovativo</th>
                        <th className="text-right">Disciplinas</th>
                        <th className="text-right">Créditos</th>
                        <th>Estado</th>
                        <th className="w-px" />
                    </tr>
                </thead>
                <tbody>
                    {superior ? (
                        matricula.periodos.map((p) => (
                            <LinhaInscricao key={p.id} nome={p.nome} inscricao={p.inscricao} matricula={matricula} periodoId={p.id} />
                        ))
                    ) : (
                        <LinhaInscricao nome="Ano lectivo" inscricao={matricula.inscricao_anual} matricula={matricula} periodoId={null} />
                    )}
                </tbody>
            </table>
        </Bloco>
    );
}

/* ---------------------------------------------------------------- Encarregados */

function NovoEncarregado({ estudanteId, parentescos, fechar }: { estudanteId: number; parentescos: Opcao[]; fechar: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        nome: '',
        parentesco: 'pai',
        telemovel: '',
        email: '',
        profissao: '',
        principal: false,
        ve_financas: true,
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('estudantes.encarregados.store', estudanteId), { preserveScroll: true, onSuccess: fechar });
    };
    return (
        <form onSubmit={guardar} className="border-b">
            <LinhaForm rotulo="Nome" htmlFor="enc_nome" obrigatorio>
                <Input id="enc_nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} />
                <InputError message={errors.nome} className="mt-1" />
            </LinhaForm>
            <LinhaForm rotulo="Parentesco" htmlFor="enc_parentesco" obrigatorio>
                <CaixaEscolha
                    id="enc_parentesco"
                    value={data.parentesco}
                    onChange={(e) => setData('parentesco', e.target.value)}
                    className="max-w-56"
                >
                    {parentescos.map((p) => (
                        <option key={p.valor} value={p.valor}>
                            {p.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
            </LinhaForm>
            <LinhaForm
                rotulo="Telemóvel"
                htmlFor="enc_telemovel"
                obrigatorio
                ajuda="Se já for encarregado de outro estudante (irmão), fica ligado aos dois."
            >
                <Input
                    id="enc_telemovel"
                    type="tel"
                    inputMode="tel"
                    value={data.telemovel}
                    onChange={(e) => setData('telemovel', e.target.value)}
                    className="max-w-56"
                />
                <InputError message={errors.telemovel} className="mt-1" />
            </LinhaForm>
            <LinhaForm rotulo="E-mail" htmlFor="enc_email">
                <Input id="enc_email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="max-w-96" />
                <InputError message={errors.email} className="mt-1" />
            </LinhaForm>
            <LinhaForm rotulo="Profissão" htmlFor="enc_profissao">
                <Input id="enc_profissao" value={data.profissao} onChange={(e) => setData('profissao', e.target.value)} className="max-w-80" />
            </LinhaForm>
            <LinhaForm rotulo="Opções" htmlFor="enc_principal">
                <label className="flex items-center gap-2 pt-2 text-[13.5px]">
                    <input id="enc_principal" type="checkbox" checked={data.principal} onChange={(e) => setData('principal', e.target.checked)} />
                    Encarregado principal (recebe os avisos)
                </label>
                <label className="flex items-center gap-2 pt-2 text-[13.5px]">
                    <input type="checkbox" checked={data.ve_financas} onChange={(e) => setData('ve_financas', e.target.checked)} />
                    Vê as propinas e os pagamentos no portal
                </label>
            </LinhaForm>
            <div className="flex justify-end gap-2 px-5 py-3">
                <Button type="button" variant="outline" onClick={fechar}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={processing}>
                    Acrescentar
                </Button>
            </div>
        </form>
    );
}

/* ---------------------------------------------------------------- Ficha */

export default function FichaEstudante({ estudante, encarregados, matriculas, opcoesMatricula, parentescos, superior, financas, metodos }: Props) {
    const pode = usePode();
    const [separador, setSeparador] = useState<Separador>('percurso');
    const [novaMatricula, setNovaMatricula] = useState(false);
    const [novoEncarregado, setNovoEncarregado] = useState(false);
    const actual = matriculas.find((m) => m.estado !== 'anulada');

    const desligar = (e: Encarregado) => {
        if (!confirm(`${e.nome} deixa de ser encarregado de ${estudante.nome}?`)) return;
        router.delete(route('estudantes.encarregados.destroy', [estudante.id, e.id]), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={estudante.nome} />
            <CabecalhoFicha
                voltar={{ href: route('estudantes.index'), label: 'Estudantes' }}
                referencia={estudante.numero}
                titulo={estudante.nome}
                subtitulo={actual ? `${actual.curso} · ${actual.ano_curricular}.º · ${actual.ano_lectivo}` : 'Ainda sem matrícula'}
                estado={<Estado cor={COR_ESTUDANTE[estudante.estado] ?? 'cinza'}>{estudante.estado_rotulo}</Estado>}
            />

            <div className="mb-4 flex flex-wrap gap-2">
                {pode('matriculas.criar') && !novaMatricula && (
                    <Button
                        size="sm"
                        onClick={() => {
                            setSeparador('percurso');
                            setNovaMatricula(true);
                        }}
                    >
                        <Plus /> Nova matrícula
                    </Button>
                )}
                {pode('estudantes.gerir') && (
                    <BotaoLink href={route('estudantes.edit', estudante.id)} icone={Pencil} variante="outline">
                        Alterar dados
                    </BotaoLink>
                )}
            </div>

            <Separadores<Separador>
                itens={[
                    { valor: 'percurso', label: 'Matrículas e inscrições' },
                    { valor: 'dados', label: 'Dados pessoais' },
                    { valor: 'encarregados', label: `Encarregados (${encarregados.length})` },
                    ...(financas ? [{ valor: 'financas' as const, label: 'Finanças' }] : []),
                ]}
                activo={separador}
                mudar={setSeparador}
            />

            {separador === 'percurso' && (
                <div className="flex flex-col gap-5">
                    {novaMatricula && <NovaMatricula estudanteId={estudante.id} opcoes={opcoesMatricula} fechar={() => setNovaMatricula(false)} />}
                    {matriculas.length === 0 && !novaMatricula && (
                        <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">Ainda não tem matrículas.</div>
                    )}
                    {matriculas.map((m) => (
                        <BlocoMatricula key={m.id} matricula={m} superior={superior} />
                    ))}
                </div>
            )}

            {separador === 'dados' && (
                <div className="flex flex-col gap-5">
                    <Campos>
                        <Campo rotulo="Nome">{estudante.nome}</Campo>
                        <Campo rotulo="Sexo">{estudante.sexo_rotulo}</Campo>
                        <Campo rotulo="Data de nascimento">{formatData(estudante.data_nascimento)}</Campo>
                        <Campo rotulo={estudante.documento_rotulo ?? 'Documento'}>{estudante.documento_numero}</Campo>
                        <Campo rotulo="NUIT">{estudante.nuit}</Campo>
                        <Campo rotulo="Nacionalidade">{estudante.nacionalidade}</Campo>
                        <Campo rotulo="Naturalidade">{estudante.naturalidade}</Campo>
                        <Campo rotulo="Telemóvel">{estudante.telemovel}</Campo>
                        <Campo rotulo="E-mail">{estudante.email}</Campo>
                        <Campo rotulo="Província">{estudante.provincia}</Campo>
                        <Campo rotulo="Morada">{estudante.morada}</Campo>
                        <Campo rotulo="Categoria">{estudante.categoria_rotulo}</Campo>
                        <Campo rotulo="Necessidades especiais">{estudante.necessidades_especiais}</Campo>
                        <Campo rotulo="Registado em">{formatData(estudante.registado_em)}</Campo>
                    </Campos>
                    {estudante.nota_privada && (
                        <Bloco titulo="Nota interna">
                            <p className="px-5 py-3 text-[13.5px] whitespace-pre-line">{estudante.nota_privada}</p>
                        </Bloco>
                    )}
                </div>
            )}

            {separador === 'encarregados' && (
                <Bloco
                    titulo="Encarregados de educação"
                    accoes={
                        pode('estudantes.gerir') &&
                        !novoEncarregado && (
                            <Button size="sm" variant="outline" onClick={() => setNovoEncarregado(true)}>
                                <Plus /> Acrescentar
                            </Button>
                        )
                    }
                >
                    {novoEncarregado && (
                        <NovoEncarregado estudanteId={estudante.id} parentescos={parentescos} fechar={() => setNovoEncarregado(false)} />
                    )}
                    <table className="tabela">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Parentesco</th>
                                <th>Telemóvel</th>
                                <th>E-mail</th>
                                <th>Também de</th>
                                {pode('estudantes.gerir') && <th className="w-10" />}
                            </tr>
                        </thead>
                        <tbody>
                            {encarregados.length === 0 && <LinhaVazia colunas={6} texto="Ainda não tem encarregados." />}
                            {encarregados.map((e) => (
                                <tr key={e.id}>
                                    <td>
                                        <span className="font-medium">{e.nome}</span>
                                        {e.principal && <span className="text-muted-foreground text-xs"> · principal</span>}
                                        {!e.ve_financas && <span className="text-muted-foreground text-xs"> · não vê finanças</span>}
                                    </td>
                                    <td>{e.parentesco}</td>
                                    <td className="tabular-nums">{e.telemovel ?? '—'}</td>
                                    <td>{e.email ?? '—'}</td>
                                    <td>{e.outros_educandos.length > 0 ? e.outros_educandos.join(', ') : '—'}</td>
                                    {pode('estudantes.gerir') && (
                                        <td>
                                            <BotaoIcone rotulo={`Desligar ${e.nome}`} onClick={() => desligar(e)}>
                                                <Trash2 className="size-4" />
                                            </BotaoIcone>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Bloco>
            )}

            {separador === 'financas' && financas && <Financas estudanteId={estudante.id} conta={financas} metodos={metodos} />}
        </AppLayout>
    );
}

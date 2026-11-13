import { Estado, type CorEstado } from '@/components/erp';
import InputError from '@/components/input-error';
import { formatCreditos } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { formatData } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { type FormEvent, type ReactNode } from 'react';

interface Disciplina {
    codigo: string;
    nome: string;
    creditos: number;
    em_atraso: boolean;
    resultado: string | null;
    nota: number | null;
}

interface Props {
    instituicao: { nome: string; logotipo: string | null };
    estudante: { numero: string; nome: string; email: string | null; telemovel: string | null };
    matriculas: {
        numero: string;
        ano_lectivo: string;
        curso: string;
        ano_curricular: number;
        estado: string;
        inscricoes: { codigo: string; periodo: string; disciplinas: Disciplina[] }[];
    }[];
    financas: {
        divida: string;
        vencido: string;
        credito: string;
        cobrancas: { descricao: string; vencimento: string; falta: string; vencida: boolean }[];
        pagamentos: { numero: string; data: string; valor: string }[];
    };
    pedidos: {
        numero: string;
        tipo: string;
        finalidade: string | null;
        pedido_em: string;
        emolumento: string | null;
        situacao: string;
        situacao_rotulo: string;
        motivo_recusa: string | null;
        documento_numero: string | null;
    }[];
    tiposDocumento: Opcao[];
}

const COR_PEDIDO: Record<string, CorEstado> = {
    aguarda_pagamento: 'laranja',
    por_emitir: 'azul',
    emitido: 'verde',
    entregue: 'cinza',
    recusado: 'vermelho',
};

/** Pedir uma declaração, o histórico ou o certificado; o emolumento aparece em "Por pagar". */
function PedirDocumento({ tipos }: { tipos: Opcao[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ tipo: tipos[0]?.valor ?? '', finalidade: '' });
    const pedir = (e: FormEvent) => {
        e.preventDefault();
        post(route('portal.pedidos.store'), { preserveScroll: true, onSuccess: () => reset('finalidade') });
    };
    return (
        <form onSubmit={pedir} className="flex flex-col gap-2 border-b px-4 py-3 sm:flex-row sm:items-start">
            <div className="flex-1">
                <CaixaEscolha value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} aria-label="Documento">
                    {tipos.map((t) => (
                        <option key={t.valor} value={t.valor}>
                            {t.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <InputError message={errors.tipo} className="mt-1" />
            </div>
            <div className="flex-1">
                <Input
                    value={data.finalidade}
                    onChange={(e) => setData('finalidade', e.target.value)}
                    placeholder="Para quê? (opcional)"
                    aria-label="Finalidade"
                />
                <InputError message={errors.finalidade} className="mt-1" />
            </div>
            <Button type="submit" disabled={processing}>
                Pedir
            </Button>
        </form>
    );
}

const mt = (v: string) => `${Number(v).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MT`;
const nota = (n: number) => n.toLocaleString('pt-PT', { maximumFractionDigits: 2 });

function Caixa({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <section className="bg-background overflow-hidden rounded-xl border">
            <h2 className="border-b px-4 py-3 text-[14px] font-semibold">{titulo}</h2>
            {children}
        </section>
    );
}

/** O portal do estudante: matrículas, disciplinas e notas publicadas, propinas e pagamentos. */
export default function PainelPortal({ instituicao, estudante, matriculas, financas, pedidos, tiposDocumento }: Props) {
    const actual = matriculas[0];
    const { flash } = usePage<{ flash?: { sucesso?: string } }>().props;

    return (
        <div className="bg-muted min-h-screen pb-10">
            <Head title="Portal do estudante" />
            <header className="bg-background border-b">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
                    <div className="flex min-w-0 items-center gap-3">
                        {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-9 w-9 object-contain" />}
                        <div className="min-w-0">
                            <div className="truncate text-[14px] font-semibold">{instituicao.nome}</div>
                            <div className="text-muted-foreground text-[12px]">Portal do estudante</div>
                        </div>
                    </div>
                    <Button size="sm" variant="ghost" onClick={() => router.post(route('portal.sair'))}>
                        <LogOut /> Sair
                    </Button>
                </div>
            </header>

            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 pt-5">
                {flash?.sucesso && (
                    <p className="rounded-lg border border-green-300 bg-green-50 px-4 py-2.5 text-[13.5px] text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">
                        {flash.sucesso}
                    </p>
                )}
                <div>
                    <h1 className="text-[20px] font-semibold">{estudante.nome}</h1>
                    <p className="text-muted-foreground text-[13.5px]">
                        <span className="font-mono">{estudante.numero}</span>
                        {actual && ` · ${actual.curso} · ${actual.ano_curricular}.º ano · ${actual.ano_lectivo}`}
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Por pagar', financas.divida, ''],
                        ['Vencido', financas.vencido, 'text-red-700 dark:text-red-400'],
                        ['Adiantamento', financas.credito, 'text-green-700 dark:text-green-400'],
                    ].map(([rotulo, valor, cor]) => (
                        <div key={rotulo} className="bg-background rounded-xl border px-4 py-3">
                            <div className="text-muted-foreground text-[12.5px]">{rotulo}</div>
                            <div className={`text-[18px] font-semibold tabular-nums ${cor}`}>{mt(valor)}</div>
                        </div>
                    ))}
                </div>

                {matriculas.length === 0 && <p className="text-muted-foreground text-[13.5px]">Ainda não tem matrícula.</p>}
                {matriculas.map((m) =>
                    m.inscricoes.map((i) => (
                        <Caixa key={i.codigo} titulo={`${i.periodo} · ${m.ano_lectivo}`}>
                            <ul className="divide-y">
                                {i.disciplinas.map((d) => (
                                    <li key={d.codigo} className="flex items-center justify-between gap-3 px-4 py-2.5 text-[13.5px]">
                                        <div className="min-w-0">
                                            <div className="font-medium">{d.nome}</div>
                                            <div className="text-muted-foreground text-[12px]">
                                                {formatCreditos(d.creditos)} créditos{d.em_atraso && ' · em atraso'}
                                            </div>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            {d.resultado === null ? (
                                                <span className="text-muted-foreground">Por lançar</span>
                                            ) : (
                                                <>
                                                    {d.nota !== null && <div className="text-[16px] font-semibold tabular-nums">{nota(d.nota)}</div>}
                                                    <Estado cor={d.resultado === 'Aprovado' ? 'verde' : 'vermelho'}>{d.resultado}</Estado>
                                                </>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Caixa>
                    )),
                )}

                <Caixa titulo="Por pagar">
                    {financas.cobrancas.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-4 text-[13.5px]">Não tem nada por pagar.</p>
                    ) : (
                        <ul className="divide-y">
                            {financas.cobrancas.map((c, n) => (
                                <li key={n} className="flex items-center justify-between gap-3 px-4 py-2.5 text-[13.5px]">
                                    <div>
                                        <div>{c.descricao}</div>
                                        <div
                                            className={c.vencida ? 'text-[12px] text-red-700 dark:text-red-400' : 'text-muted-foreground text-[12px]'}
                                        >
                                            {c.vencida ? 'Venceu a ' : 'Vence a '}
                                            {formatData(c.vencimento)}
                                        </div>
                                    </div>
                                    <div className="font-semibold tabular-nums">{mt(c.falta)}</div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Caixa>

                <Caixa titulo="Documentos">
                    <PedirDocumento tipos={tiposDocumento} />
                    {pedidos.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-4 text-[13.5px]">
                            Peça aqui declarações, o histórico ou o certificado. Levanta-os na secretaria.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {pedidos.map((p) => (
                                <li key={p.numero} className="flex items-start justify-between gap-3 px-4 py-2.5 text-[13.5px]">
                                    <div className="min-w-0">
                                        <div className="font-medium">{p.tipo}</div>
                                        <div className="text-muted-foreground text-[12px]">
                                            <span className="font-mono">{p.numero}</span> · {formatData(p.pedido_em)}
                                            {p.emolumento && ` · ${mt(p.emolumento)}`}
                                        </div>
                                        {p.motivo_recusa && <div className="text-[12px] text-red-700 dark:text-red-400">{p.motivo_recusa}</div>}
                                    </div>
                                    <Estado cor={COR_PEDIDO[p.situacao] ?? 'cinza'} className="shrink-0">
                                        {p.situacao_rotulo}
                                    </Estado>
                                </li>
                            ))}
                        </ul>
                    )}
                </Caixa>

                {financas.pagamentos.length > 0 && (
                    <Caixa titulo="Últimos pagamentos">
                        <ul className="divide-y">
                            {financas.pagamentos.map((p) => (
                                <li key={p.numero} className="flex items-center justify-between gap-3 px-4 py-2.5 text-[13.5px]">
                                    <div>
                                        <span className="font-mono text-[12.5px]">{p.numero}</span>
                                        <span className="text-muted-foreground"> · {formatData(p.data)}</span>
                                    </div>
                                    <div className="tabular-nums">{mt(p.valor)}</div>
                                </li>
                            ))}
                        </ul>
                    </Caixa>
                )}
            </main>
        </div>
    );
}

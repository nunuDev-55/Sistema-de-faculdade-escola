import { Estado } from '@/components/erp';
import { formatCreditos } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { formatData } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { type ReactNode } from 'react';

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
export default function PainelPortal({ instituicao, estudante, matriculas, financas }: Props) {
    const actual = matriculas[0];

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

                <Caixa titulo="Propinas por pagar">
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

import { MensagemFlash } from '@/components/erp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import { formatData } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, Printer } from 'lucide-react';
import { useState } from 'react';

interface Props {
    instituicao: { nome: string; nuit: string | null; morada: string; logotipo: string | null };
    pagamento: {
        id: number;
        numero: string;
        data: string;
        valor: string;
        metodo: string;
        referencia: string | null;
        estado: string;
        motivo_anulacao: string | null;
        registado_por: string | null;
        adiantamento: string;
    };
    estudante: { id: number; numero: string; nome: string };
    linhas: { numero: string; descricao: string; valor: string }[];
}

const mt = (v: string) => `${Number(v).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MT`;

/** O recibo numa folha A4 (Imprimir → Guardar como PDF). Um recibo não se apaga: anula-se com o motivo. */
export default function Recibo({ instituicao, pagamento, estudante, linhas }: Props) {
    const pode = usePode();
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [anular, setAnular] = useState(false);
    const [motivo, setMotivo] = useState('');
    const anulado = pagamento.estado !== 'valido';

    return (
        <div className="bg-muted min-h-screen print:bg-white">
            <Head title={`Recibo ${pagamento.numero}`} />
            <style>{'@page { size: A4; margin: 14mm; }'}</style>
            <div className="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 py-4 print:hidden">
                <Link
                    href={route('estudantes.show', estudante.id)}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[13px]"
                >
                    <ChevronLeft className="size-3.5" /> {estudante.nome}
                </Link>
                <div className="flex gap-2">
                    {pode('pagamentos.anular') && !anulado && !anular && (
                        <Button size="sm" variant="outline" onClick={() => setAnular(true)}>
                            Anular o recibo
                        </Button>
                    )}
                    <Button size="sm" onClick={() => window.print()}>
                        <Printer /> Imprimir
                    </Button>
                </div>
                {anular && (
                    <div className="flex w-full flex-col gap-2 sm:flex-row">
                        <Input
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            placeholder="Porque se anula (fica no registo)"
                            aria-label="Motivo da anulação"
                        />
                        <Button variant="outline" onClick={() => setAnular(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={motivo.trim() === ''}
                            onClick={() => router.post(route('pagamentos.anular', pagamento.id), { motivo }, { onSuccess: () => setAnular(false) })}
                        >
                            Anular
                        </Button>
                    </div>
                )}
                {errors.motivo && <p className="w-full text-[13px] text-red-600">{errors.motivo}</p>}
            </div>

            <article className="mx-auto mb-10 max-w-[210mm] bg-white px-[14mm] py-[12mm] text-[12.5px] leading-relaxed text-neutral-900 shadow-sm print:m-0 print:max-w-none print:p-0 print:shadow-none">
                <header className="flex items-center gap-4 border-b border-neutral-300 pb-4">
                    {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-14 w-14 object-contain" />}
                    <div>
                        <div className="text-[15px] font-semibold">{instituicao.nome}</div>
                        <div className="text-[11.5px] text-neutral-600">
                            {[instituicao.nuit && `NUIT ${instituicao.nuit}`, instituicao.morada].filter(Boolean).join(' · ')}
                        </div>
                    </div>
                </header>
                <div className="mt-6 flex flex-wrap items-end justify-between gap-3">
                    <h1 className="text-[18px] font-semibold tracking-wide uppercase">Recibo</h1>
                    <div className="text-right">
                        <div className="text-[11px] tracking-wide text-neutral-500 uppercase">N.º</div>
                        <div className="font-mono text-[16px] font-semibold">{pagamento.numero}</div>
                    </div>
                </div>
                {anulado && (
                    <div className="mt-4 border border-red-400 px-3 py-2 text-center font-semibold text-red-700 uppercase">
                        Anulado{pagamento.motivo_anulacao ? `: ${pagamento.motivo_anulacao}` : ''}
                    </div>
                )}
                <dl className="mt-5 grid grid-cols-[9rem_1fr] gap-x-4 gap-y-1.5 border border-neutral-300 px-4 py-3">
                    <dt className="text-neutral-500">Recebemos de</dt>
                    <dd className="font-semibold">
                        {estudante.nome} <span className="font-mono font-normal">({estudante.numero})</span>
                    </dd>
                    <dt className="text-neutral-500">A quantia de</dt>
                    <dd className="font-semibold tabular-nums">{mt(pagamento.valor)}</dd>
                    <dt className="text-neutral-500">Data</dt>
                    <dd>{formatData(pagamento.data)}</dd>
                    <dt className="text-neutral-500">Forma</dt>
                    <dd>
                        {pagamento.metodo}
                        {pagamento.referencia && ` · ${pagamento.referencia}`}
                    </dd>
                </dl>
                <table className="mt-5 w-full border-collapse text-left">
                    <thead>
                        <tr className="border-b-2 border-neutral-800 text-[11px] tracking-wide text-neutral-600 uppercase">
                            <th className="py-1.5 pr-2 font-semibold">Cobrança</th>
                            <th className="py-1.5 pr-2 font-semibold">Descrição</th>
                            <th className="py-1.5 text-right font-semibold">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        {linhas.map((l) => (
                            <tr key={l.numero} className="border-b border-neutral-200">
                                <td className="py-1.5 pr-2 font-mono text-[11.5px]">{l.numero}</td>
                                <td className="py-1.5 pr-2">{l.descricao}</td>
                                <td className="py-1.5 text-right tabular-nums">{mt(l.valor)}</td>
                            </tr>
                        ))}
                        {Number(pagamento.adiantamento) > 0 && (
                            <tr className="border-b border-neutral-200">
                                <td className="py-1.5 pr-2" />
                                <td className="py-1.5 pr-2">Adiantamento (abate as próximas cobranças)</td>
                                <td className="py-1.5 text-right tabular-nums">{mt(pagamento.adiantamento)}</td>
                            </tr>
                        )}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-neutral-800 font-semibold">
                            <td className="py-1.5" colSpan={2}>
                                Total
                            </td>
                            <td className="py-1.5 text-right tabular-nums">{mt(pagamento.valor)}</td>
                        </tr>
                    </tfoot>
                </table>
                <div className="mt-14 grid grid-cols-2 gap-10 text-center text-[11px] text-neutral-600">
                    <div />
                    <div className="border-t border-neutral-400 pt-1.5">
                        A Tesouraria{pagamento.registado_por ? ` (${pagamento.registado_por})` : ''}
                    </div>
                </div>
                <footer className="mt-8 border-t border-neutral-200 pt-2 text-[10px] text-neutral-500">Documento processado por computador.</footer>
            </article>
            <MensagemFlash />
        </div>
    );
}

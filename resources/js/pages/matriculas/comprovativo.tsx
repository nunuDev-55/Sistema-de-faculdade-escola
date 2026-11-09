import { MensagemFlash } from '@/components/erp';
import { formatCreditos } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { formatDataHora } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Printer } from 'lucide-react';

interface Props {
    instituicao: { nome: string; nuit: string | null; morada: string; telefone: string | null; email: string | null; logotipo: string | null };
    inscricao: { id: number; codigo: string; estado: string; periodo: string; periodo_id: number | null; confirmada_em: string | null };
    matricula: { id: number; numero: string; curso: string; ano_curricular: number; regime: string | null; ano_lectivo: string };
    estudante: { id: number; numero: string; nome: string; documento: string | null };
    disciplinas: { codigo: string; nome: string; creditos: number; em_atraso: boolean; estado: string }[];
    emitidoPor: string;
    podeInscrever: boolean;
}

/** O comprovativo de inscrição numa folha A4, para imprimir ou guardar em PDF (Imprimir → Guardar como PDF). */
export default function Comprovativo({ instituicao, inscricao, matricula, estudante, disciplinas, emitidoPor, podeInscrever }: Props) {
    const total = disciplinas.reduce((s, d) => s + d.creditos, 0);
    const anulada = inscricao.estado !== 'confirmada';
    const contactos = [instituicao.nuit && `NUIT ${instituicao.nuit}`, instituicao.morada, instituicao.telefone, instituicao.email]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="bg-muted min-h-screen print:bg-white">
            <Head title={`Comprovativo ${inscricao.codigo}`} />
            <style>{'@page { size: A4; margin: 14mm; }'}</style>

            <div className="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 py-4 print:hidden">
                <Link
                    href={route('estudantes.show', estudante.id)}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[13px]"
                >
                    <ChevronLeft className="size-3.5" /> {estudante.nome}
                </Link>
                <div className="flex gap-2">
                    {podeInscrever && inscricao.periodo_id !== null && !anulada && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('inscricoes.create', [matricula.id, inscricao.periodo_id])}>Alterar a inscrição</Link>
                        </Button>
                    )}
                    <Button size="sm" onClick={() => window.print()}>
                        <Printer /> Imprimir
                    </Button>
                </div>
            </div>

            <article className="mx-auto mb-10 max-w-[210mm] bg-white px-[14mm] py-[12mm] text-[12.5px] leading-relaxed text-neutral-900 shadow-sm print:m-0 print:max-w-none print:p-0 print:shadow-none">
                <header className="flex items-center gap-4 border-b border-neutral-300 pb-4">
                    {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-16 w-16 object-contain" />}
                    <div className="min-w-0">
                        <div className="text-[16px] font-semibold">{instituicao.nome}</div>
                        {contactos && <div className="text-[11.5px] text-neutral-600">{contactos}</div>}
                    </div>
                </header>

                <div className="mt-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-[18px] font-semibold tracking-wide uppercase">Comprovativo de inscrição</h1>
                        <div className="text-neutral-600">
                            {inscricao.periodo} · Ano lectivo {matricula.ano_lectivo}
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-[11px] tracking-wide text-neutral-500 uppercase">N.º</div>
                        <div className="font-mono text-[17px] font-semibold">{inscricao.codigo}</div>
                    </div>
                </div>

                {anulada && (
                    <div className="mt-4 border border-red-400 px-3 py-2 text-center font-semibold text-red-700 uppercase">Inscrição anulada</div>
                )}

                <dl className="mt-5 grid grid-cols-[9rem_1fr] gap-x-4 gap-y-1.5 border border-neutral-300 px-4 py-3 sm:grid-cols-[9rem_1fr_7rem_1fr]">
                    <dt className="text-neutral-500">Estudante</dt>
                    <dd className="font-semibold sm:col-span-3">{estudante.nome}</dd>
                    <dt className="text-neutral-500">N.º de estudante</dt>
                    <dd className="font-mono">{estudante.numero}</dd>
                    <dt className="text-neutral-500">Documento</dt>
                    <dd>{estudante.documento ?? '—'}</dd>
                    <dt className="text-neutral-500">Curso</dt>
                    <dd className="sm:col-span-3">{matricula.curso}</dd>
                    <dt className="text-neutral-500">Ano</dt>
                    <dd>{matricula.ano_curricular}.º</dd>
                    <dt className="text-neutral-500">Regime</dt>
                    <dd>{matricula.regime ?? '—'}</dd>
                    <dt className="text-neutral-500">Matrícula</dt>
                    <dd className="font-mono">{matricula.numero}</dd>
                    <dt className="text-neutral-500">Confirmada em</dt>
                    <dd>{inscricao.confirmada_em ? formatDataHora(inscricao.confirmada_em) : '—'}</dd>
                </dl>

                <table className="mt-5 w-full border-collapse text-left">
                    <thead>
                        <tr className="border-b-2 border-neutral-800 text-[11.5px] tracking-wide text-neutral-600 uppercase">
                            <th className="py-1.5 pr-2 font-semibold">Código</th>
                            <th className="py-1.5 pr-2 font-semibold">Disciplina</th>
                            <th className="py-1.5 pr-2 font-semibold">Observação</th>
                            <th className="py-1.5 text-right font-semibold">Créditos</th>
                        </tr>
                    </thead>
                    <tbody>
                        {disciplinas.length === 0 && (
                            <tr>
                                <td colSpan={4} className="py-4 text-center text-neutral-500">
                                    Sem disciplinas inscritas.
                                </td>
                            </tr>
                        )}
                        {disciplinas.map((d) => (
                            <tr key={d.codigo} className="border-b border-neutral-200">
                                <td className="py-1.5 pr-2 font-mono text-[12px]">{d.codigo}</td>
                                <td className="py-1.5 pr-2">{d.nome}</td>
                                <td className="py-1.5 pr-2 text-neutral-600">{d.em_atraso ? 'Em atraso' : ''}</td>
                                <td className="py-1.5 text-right tabular-nums">{formatCreditos(d.creditos)}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-neutral-800 font-semibold">
                            <td className="py-1.5" colSpan={3}>
                                Total: {disciplinas.length} {disciplinas.length === 1 ? 'disciplina' : 'disciplinas'}
                            </td>
                            <td className="py-1.5 text-right tabular-nums">{formatCreditos(total)}</td>
                        </tr>
                    </tfoot>
                </table>

                <div className="mt-16 grid grid-cols-2 gap-10 text-center text-[11.5px] text-neutral-600">
                    <div className="border-t border-neutral-400 pt-1.5">O(A) estudante</div>
                    <div className="border-t border-neutral-400 pt-1.5">A Secretaria Académica</div>
                </div>

                <footer className="mt-10 border-t border-neutral-200 pt-2 text-[10.5px] text-neutral-500">
                    Documento processado por computador. Impresso por {emitidoPor} em {formatDataHora(new Date().toISOString())}.
                </footer>
            </article>
            <MensagemFlash />
        </div>
    );
}

import { MensagemFlash } from '@/components/erp';
import { Button } from '@/components/ui/button';
import { formatDataHora } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Printer } from 'lucide-react';

interface Props {
    instituicao: { nome: string; logotipo: string | null };
    pauta: {
        id: number;
        codigo: string | null;
        epoca: string;
        estado: string;
        estado_rotulo: string;
        publicada_em: string | null;
        disciplina: string;
        codigo_disciplina: string;
        turma: string;
        curso: string;
        periodo: string;
        docente: string | null;
        leccionacao_id: number;
    };
    linhas: { numero: string; nome: string; nota: number | 'F' | null; nota_final: number | null; situacao: string | null }[];
    final: boolean;
}

const nota = (n: number | 'F' | null) => (n === null ? '—' : n === 'F' ? 'Faltou' : n.toLocaleString('pt-PT', { maximumFractionDigits: 2 }));

/** A pauta numa folha A4, para afixar ou arquivar (Imprimir → Guardar como PDF). */
export default function PautaImpressa({ instituicao, pauta, linhas, final }: Props) {
    const publicada = pauta.estado === 'publicada';

    return (
        <div className="bg-muted min-h-screen print:bg-white">
            <Head title={`Pauta ${pauta.codigo ?? ''} ${pauta.disciplina}`} />
            <style>{'@page { size: A4; margin: 14mm; }'}</style>
            <div className="mx-auto flex max-w-[210mm] items-center justify-between gap-2 px-4 py-4 print:hidden">
                <Link
                    href={route('avaliacao.show', pauta.leccionacao_id)}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[13px]"
                >
                    <ChevronLeft className="size-3.5" /> {pauta.disciplina}
                </Link>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer /> Imprimir
                </Button>
            </div>

            <article className="mx-auto mb-10 max-w-[210mm] bg-white px-[14mm] py-[12mm] text-[12px] leading-relaxed text-neutral-900 shadow-sm print:m-0 print:max-w-none print:p-0 print:shadow-none">
                <header className="flex items-center gap-4 border-b border-neutral-300 pb-4">
                    {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-14 w-14 object-contain" />}
                    <div className="text-[15px] font-semibold">{instituicao.nome}</div>
                </header>

                <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-[17px] font-semibold tracking-wide uppercase">Pauta de {pauta.epoca.toLowerCase()}</h1>
                        <div className="text-neutral-700">
                            {pauta.codigo_disciplina} · {pauta.disciplina}
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-[11px] tracking-wide text-neutral-500 uppercase">N.º</div>
                        <div className="font-mono text-[15px] font-semibold">{pauta.codigo ?? '—'}</div>
                    </div>
                </div>

                {!publicada && (
                    <div className="mt-4 border border-amber-500 px-3 py-2 text-center font-semibold text-amber-800 uppercase">
                        Provisória: {pauta.estado_rotulo.toLowerCase()}
                    </div>
                )}

                <dl className="mt-4 grid grid-cols-[7rem_1fr] gap-x-4 gap-y-1 border border-neutral-300 px-4 py-3 sm:grid-cols-[7rem_1fr_6rem_1fr]">
                    <dt className="text-neutral-500">Curso</dt>
                    <dd className="sm:col-span-3">{pauta.curso}</dd>
                    <dt className="text-neutral-500">Turma</dt>
                    <dd>{pauta.turma}</dd>
                    <dt className="text-neutral-500">Período</dt>
                    <dd>{pauta.periodo}</dd>
                    <dt className="text-neutral-500">Docente</dt>
                    <dd>{pauta.docente ?? '—'}</dd>
                    <dt className="text-neutral-500">Publicada</dt>
                    <dd>{pauta.publicada_em ? formatDataHora(pauta.publicada_em) : '—'}</dd>
                </dl>

                <table className="mt-5 w-full border-collapse text-left">
                    <thead>
                        <tr className="border-b-2 border-neutral-800 text-[11px] tracking-wide text-neutral-600 uppercase">
                            <th className="py-1.5 pr-2 font-semibold">N.º</th>
                            <th className="py-1.5 pr-2 font-semibold">Nome</th>
                            <th className="py-1.5 pr-2 text-right font-semibold">{final ? 'Exame' : 'Média'}</th>
                            {final && <th className="py-1.5 pr-2 text-right font-semibold">Final</th>}
                            <th className="py-1.5 font-semibold">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        {linhas.length === 0 && (
                            <tr>
                                <td colSpan={final ? 5 : 4} className="py-4 text-center text-neutral-500">
                                    A pauta ainda não tem linhas: o docente tem de a submeter.
                                </td>
                            </tr>
                        )}
                        {linhas.map((l) => (
                            <tr key={l.numero} className="border-b border-neutral-200">
                                <td className="py-1 pr-2 font-mono text-[11.5px]">{l.numero}</td>
                                <td className="py-1 pr-2">{l.nome}</td>
                                <td className="py-1 pr-2 text-right tabular-nums">{nota(l.nota)}</td>
                                {final && <td className="py-1 pr-2 text-right font-semibold tabular-nums">{nota(l.nota_final)}</td>}
                                <td className="py-1">{l.situacao ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-14 grid grid-cols-2 gap-10 text-center text-[11px] text-neutral-600">
                    <div className="border-t border-neutral-400 pt-1.5">O(A) docente</div>
                    <div className="border-t border-neutral-400 pt-1.5">O(A) director(a) de curso</div>
                </div>
                <footer className="mt-8 border-t border-neutral-200 pt-2 text-[10px] text-neutral-500">Documento processado por computador.</footer>
            </article>
            <MensagemFlash />
        </div>
    );
}

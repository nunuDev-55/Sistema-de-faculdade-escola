import { MensagemFlash } from '@/components/erp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import { formatData } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, Printer, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

interface LinhaDisciplina {
    ano_lectivo: string;
    periodo: string;
    codigo: string;
    nome: string;
    creditos: string;
    nota: string | null;
}

/** O que ficou congelado na emissão (App\Services\DocumentoService). */
interface Conteudo {
    titulo: string;
    instituicao: { nome: string; nuit: string | null; morada: string };
    estudante: { nome: string; numero: string; documento: string | null; data_nascimento: string | null };
    curso: { nome: string; grau: string };
    local_data: string;
    texto: string[];
    disciplinas: LinhaDisciplina[];
    resumo: { disciplinas: number; creditos: string; media: string; media_final: string | null; grau: string | null } | null;
}

interface Props {
    documento: {
        id: number;
        numero: string;
        tipo: string;
        estado: string;
        motivo_anulacao: string | null;
        emitido_em: string;
        emitido_por: string | null;
        codigo: string;
        url_verificacao: string;
        qr: string;
        confere: boolean;
    };
    conteudo: Conteudo;
    logotipo: string | null;
    estudante: { id: number; nome: string };
}

const virgula = (v: string) => v.replace('.', ',');

/** O documento numa folha A4 (Imprimir → Guardar como PDF), com o código e o QR que levam à página de verificação. */
export default function Documento({ documento, conteudo, logotipo, estudante }: Props) {
    const pode = usePode();
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [anular, setAnular] = useState(false);
    const [motivo, setMotivo] = useState('');
    const anulado = documento.estado !== 'valido';
    const comNotas = conteudo.disciplinas.some((d) => d.nota !== null);

    return (
        <div className="bg-muted min-h-screen print:bg-white">
            <Head title={`${conteudo.titulo} ${documento.numero}`} />
            <style>{'@page { size: A4; margin: 14mm; }'}</style>
            <div className="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 py-4 print:hidden">
                <Link
                    href={route('estudantes.show', estudante.id)}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[13px]"
                >
                    <ChevronLeft className="size-3.5" /> {estudante.nome}
                </Link>
                <div className="flex gap-2">
                    {pode('documentos.emitir') && !anulado && !anular && (
                        <Button size="sm" variant="outline" onClick={() => setAnular(true)}>
                            Anular o documento
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
                            placeholder="Porque se anula (aparece na página de verificação)"
                            aria-label="Motivo da anulação"
                        />
                        <Button variant="outline" onClick={() => setAnular(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={motivo.trim() === ''}
                            onClick={() => router.post(route('documentos.anular', documento.id), { motivo }, { onSuccess: () => setAnular(false) })}
                        >
                            Anular
                        </Button>
                    </div>
                )}
                {errors.motivo && <p className="w-full text-[13px] text-red-600">{errors.motivo}</p>}
                {!documento.confere && (
                    <p className="flex w-full items-center gap-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-[13px] text-red-800">
                        <TriangleAlert className="size-4 shrink-0" /> O conteúdo guardado deste documento não confere com a assinatura: foi alterado
                        fora do sistema. Não o entregue; a verificação pública diz que não confere.
                    </p>
                )}
            </div>

            <article className="mx-auto mb-10 flex min-h-[297mm] max-w-[210mm] flex-col bg-white px-[16mm] py-[14mm] text-[12.5px] leading-relaxed text-neutral-900 shadow-sm print:m-0 print:min-h-0 print:max-w-none print:p-0 print:shadow-none">
                <header className="flex flex-col items-center gap-2 border-b border-neutral-300 pb-4 text-center">
                    {logotipo && <img src={logotipo} alt="" className="h-16 w-16 object-contain" />}
                    <div className="text-[16px] font-semibold">{conteudo.instituicao.nome}</div>
                    <div className="text-[11.5px] text-neutral-600">
                        {[conteudo.instituicao.morada, conteudo.instituicao.nuit && `NUIT ${conteudo.instituicao.nuit}`].filter(Boolean).join(' · ')}
                    </div>
                </header>

                <div className="mt-8 text-center">
                    <h1 className="text-[19px] font-semibold tracking-[0.12em] uppercase">{conteudo.titulo}</h1>
                    <div className="mt-1 font-mono text-[12px] text-neutral-600">N.º {documento.numero}</div>
                </div>
                {anulado && (
                    <div className="mt-5 border border-red-400 px-3 py-2 text-center font-semibold text-red-700 uppercase">
                        Anulado{documento.motivo_anulacao ? `: ${documento.motivo_anulacao}` : ''}
                    </div>
                )}

                <div className="mt-8 flex flex-col gap-3 text-justify text-[13.5px] leading-[1.75]">
                    {conteudo.texto.map((p, i) => (
                        <p key={i} className="indent-8">
                            {p}
                        </p>
                    ))}
                </div>

                {conteudo.disciplinas.length > 0 && (
                    <table className="mt-6 w-full border-collapse text-left text-[12px]">
                        <thead>
                            <tr className="border-b-2 border-neutral-800 text-[10.5px] tracking-wide text-neutral-600 uppercase">
                                <th className="py-1.5 pr-2 font-semibold">Ano</th>
                                <th className="py-1.5 pr-2 font-semibold">Período</th>
                                <th className="py-1.5 pr-2 font-semibold">Código</th>
                                <th className="py-1.5 pr-2 font-semibold">Disciplina</th>
                                <th className="py-1.5 pr-2 text-right font-semibold">Créditos</th>
                                {comNotas && <th className="py-1.5 text-right font-semibold">Nota</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {conteudo.disciplinas.map((d) => (
                                <tr key={`${d.ano_lectivo}-${d.codigo}`} className="border-b border-neutral-200">
                                    <td className="py-1 pr-2 tabular-nums">{d.ano_lectivo}</td>
                                    <td className="py-1 pr-2">{d.periodo}</td>
                                    <td className="py-1 pr-2 font-mono text-[11px]">{d.codigo}</td>
                                    <td className="py-1 pr-2">{d.nome}</td>
                                    <td className="py-1 pr-2 text-right tabular-nums">{d.creditos}</td>
                                    {comNotas && <td className="py-1 text-right font-semibold tabular-nums">{d.nota}</td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {conteudo.resumo && (
                    <div className="mt-3 flex flex-wrap justify-end gap-x-6 gap-y-1 text-[12px]">
                        <span>
                            Disciplinas aprovadas: <b>{conteudo.resumo.disciplinas}</b>
                        </span>
                        <span>
                            Créditos obtidos: <b>{conteudo.resumo.creditos}</b>
                        </span>
                        <span>
                            Média ponderada: <b>{virgula(conteudo.resumo.media)}</b>
                        </span>
                        {conteudo.resumo.media_final && (
                            <span>
                                Média final: <b>{conteudo.resumo.media_final} valores</b>
                                {conteudo.resumo.grau && ` (${conteudo.resumo.grau})`}
                            </span>
                        )}
                    </div>
                )}

                <p className="mt-10">{conteudo.local_data}.</p>
                <div className="mt-14 grid grid-cols-2 gap-10 text-center text-[11.5px] text-neutral-600">
                    <div />
                    <div className="border-t border-neutral-400 pt-1.5">A Direcção dos Registos Académicos</div>
                </div>

                <footer className="mt-auto flex items-end gap-4 border-t border-neutral-200 pt-4 text-[10.5px] text-neutral-600">
                    <div className="size-[26mm] shrink-0 [&_svg]:h-full [&_svg]:w-full" dangerouslySetInnerHTML={{ __html: documento.qr }} />
                    <div className="flex flex-col gap-0.5">
                        <div>
                            Verifique a autenticidade lendo o código QR ou em{' '}
                            <span className="break-all">{documento.url_verificacao.replace(/\/verificar\/.*$/, '/verificar')}</span> com o código:
                        </div>
                        <div className="font-mono text-[14px] font-semibold tracking-wider text-neutral-900">{documento.codigo}</div>
                        <div>
                            Documento n.º {documento.numero}, emitido a {formatData(documento.emitido_em)}
                            {documento.emitido_por ? ` por ${documento.emitido_por}` : ''}. Documento processado por computador.
                        </div>
                    </div>
                </footer>
            </article>
            <MensagemFlash />
        </div>
    );
}

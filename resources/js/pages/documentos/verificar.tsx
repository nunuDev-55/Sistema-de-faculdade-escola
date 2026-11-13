import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatData } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, CircleHelp, CircleX } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

type Resultado =
    | { estado: 'inexistente' }
    | { estado: 'nao_confere'; numero: string }
    | {
          estado: 'valido' | 'anulado';
          numero: string;
          tipo: string;
          titular: string;
          estudante_numero: string;
          curso: string | null;
          ano_lectivo: string | null;
          resumo: { disciplinas: number; creditos: string; media: string; media_final: string | null; grau: string | null } | null;
          emitido_em: string;
          motivo_anulacao: string | null;
          anulado_em: string | null;
      };

interface Props {
    instituicao: { nome: string; logotipo: string | null };
    codigo: string;
    resultado: Resultado | null;
}

function Linha({ rotulo, children }: { rotulo: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[8.5rem_1fr] gap-3 border-b py-2 text-[13.5px] last:border-0">
            <dt className="text-muted-foreground">{rotulo}</dt>
            <dd className="min-w-0 font-medium">{children}</dd>
        </div>
    );
}

/** Página pública: quem recebe um documento lê o QR ou escreve o código e confirma se é autêntico e válido. */
export default function Verificar({ instituicao, codigo: inicial, resultado }: Props) {
    const [codigo, setCodigo] = useState(inicial);
    const verificar = (e: FormEvent) => {
        e.preventDefault();
        if (codigo.trim() !== '') router.get(route('verificar', codigo.trim()));
    };

    return (
        <div className="bg-muted flex min-h-screen flex-col items-center px-4 py-10">
            <Head title="Verificar documento" />
            <div className="bg-background w-full max-w-lg rounded-xl border p-6 shadow-sm">
                <div className="mb-5 flex items-center gap-3">
                    {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-12 w-12 object-contain" />}
                    <div>
                        <div className="text-[15px] font-semibold">{instituicao.nome}</div>
                        <h1 className="text-muted-foreground text-[13.5px]">Verificação de documentos</h1>
                    </div>
                </div>
                <form onSubmit={verificar} className="flex gap-2">
                    <Input
                        value={codigo}
                        onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                        placeholder="ABCD-EFGH-JKLM"
                        className="font-mono tracking-wider"
                        aria-label="Código de verificação"
                        autoFocus={!resultado}
                    />
                    <Button type="submit">Verificar</Button>
                </form>
                <p className="text-muted-foreground mt-2 text-[12.5px]">O código está no rodapé do documento, ao lado do código QR.</p>

                {resultado?.estado === 'inexistente' && (
                    <div className="mt-5 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                        <CircleHelp className="mt-0.5 size-5 shrink-0" />
                        <div className="text-[13.5px]">
                            <div className="font-semibold">Não há nenhum documento com este código.</div>
                            Confira se o escreveu bem. Se o código estiver certo, o documento não foi emitido por esta instituição.
                        </div>
                    </div>
                )}
                {resultado?.estado === 'nao_confere' && (
                    <div className="mt-5 flex gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                        <CircleX className="mt-0.5 size-5 shrink-0" />
                        <div className="text-[13.5px]">
                            <div className="font-semibold">O documento {resultado.numero} não confere.</div>
                            Os dados registados foram alterados depois da emissão. Não aceite o documento e contacte a secretaria académica.
                        </div>
                    </div>
                )}
                {(resultado?.estado === 'valido' || resultado?.estado === 'anulado') && (
                    <div className="mt-5">
                        {resultado.estado === 'valido' ? (
                            <div className="flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-[14px] font-semibold text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">
                                <CircleCheck className="size-5" /> Documento autêntico e válido
                            </div>
                        ) : (
                            <div className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                                <div className="flex items-center gap-2 text-[14px] font-semibold">
                                    <CircleAlert className="size-5" /> Documento anulado
                                    {resultado.anulado_em && <span className="font-normal">a {formatData(resultado.anulado_em)}</span>}
                                </div>
                                {resultado.motivo_anulacao && <p className="mt-1 text-[13px]">{resultado.motivo_anulacao}</p>}
                            </div>
                        )}
                        <dl className="mt-3">
                            <Linha rotulo="Documento">
                                {resultado.tipo} <span className="text-muted-foreground font-mono text-[12.5px]">{resultado.numero}</span>
                            </Linha>
                            <Linha rotulo="Titular">
                                {resultado.titular}{' '}
                                <span className="text-muted-foreground font-mono text-[12.5px]">{resultado.estudante_numero}</span>
                            </Linha>
                            {resultado.curso && <Linha rotulo="Curso">{resultado.curso}</Linha>}
                            {resultado.ano_lectivo && <Linha rotulo="Ano lectivo">{resultado.ano_lectivo}</Linha>}
                            {resultado.resumo?.media_final && (
                                <Linha rotulo="Média final">
                                    {resultado.resumo.media_final} valores{resultado.resumo.grau ? ` (${resultado.resumo.grau})` : ''}
                                </Linha>
                            )}
                            {resultado.resumo && !resultado.resumo.media_final && (
                                <Linha rotulo="Aprovações">
                                    {resultado.resumo.disciplinas} disciplinas, {resultado.resumo.creditos} créditos
                                </Linha>
                            )}
                            <Linha rotulo="Emitido a">{formatData(resultado.emitido_em)}</Linha>
                        </dl>
                        <p className="text-muted-foreground mt-3 text-[12.5px]">Confira estes dados com os do papel: têm de ser iguais.</p>
                    </div>
                )}
            </div>
        </div>
    );
}

import { Bloco, Estado, LinhaVazia, Paginacao, TituloPagina, type Pagina } from '@/components/erp';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import AppLayout from '@/layouts/app-layout';
import { formatDataHora } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';

interface Linha {
    id: number;
    numero: string;
    tipo: string;
    estudante_id: number;
    estudante: string;
    estudante_numero: string;
    finalidade: string | null;
    emitido_em: string;
    emitido_por: string | null;
    estado: string;
}

interface Props {
    documentos: Pagina<Linha>;
    filtros: { tipo: string; estado: string; q: string };
    tipos: Opcao[];
}

/** Os documentos académicos emitidos. Emitem-se na ficha do estudante (separador Documentos). */
export default function Documentos({ documentos, filtros: iniciais, tipos }: Props) {
    const { filtros, mudar } = useFiltros(route('documentos.index'), iniciais);

    return (
        <AppLayout>
            <Head title="Documentos académicos" />
            <TituloPagina
                titulo="Documentos académicos"
                descricao="Declarações, históricos e certificados emitidos. Cada um tem número e um código QR que confirma a autenticidade."
            />
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <CaixaEscolha value={filtros.tipo} onChange={(e) => mudar('tipo', e.target.value)} className="sm:max-w-60" aria-label="Tipo">
                    <option value="">Todos os tipos</option>
                    {tipos.map((t) => (
                        <option key={t.valor} value={t.valor}>
                            {t.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.estado} onChange={(e) => mudar('estado', e.target.value)} className="sm:max-w-40" aria-label="Estado">
                    <option value="">Todos</option>
                    <option value="valido">Válidos</option>
                    <option value="anulado">Anulados</option>
                </CaixaEscolha>
                <div className="relative w-full sm:max-w-sm">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input
                        value={filtros.q}
                        onChange={(e) => mudar('q', e.target.value)}
                        placeholder="Número do documento, nome ou número do estudante"
                        className="pl-8"
                        aria-label="Pesquisar"
                    />
                </div>
            </div>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Estudante</th>
                            <th>Emitido</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {documentos.data.length === 0 && (
                            <LinhaVazia colunas={5} texto="Nenhum documento com estes filtros. Os documentos emitem-se na ficha do estudante." />
                        )}
                        {documentos.data.map((d) => (
                            <tr key={d.id}>
                                <td>
                                    <Link href={route('documentos.show', d.id)} className="font-mono text-[12.5px] hover:underline">
                                        {d.numero}
                                    </Link>
                                </td>
                                <td>
                                    {d.tipo}
                                    {d.finalidade && <div className="text-muted-foreground text-[12px]">{d.finalidade}</div>}
                                </td>
                                <td>
                                    <Link href={route('estudantes.show', d.estudante_id)} className="font-medium hover:underline">
                                        {d.estudante}
                                    </Link>
                                    <div className="text-muted-foreground font-mono text-[11.5px]">{d.estudante_numero}</div>
                                </td>
                                <td className="tabular-nums">
                                    {formatDataHora(d.emitido_em)}
                                    {d.emitido_por && <div className="text-muted-foreground text-[12px]">{d.emitido_por}</div>}
                                </td>
                                <td>{d.estado === 'valido' ? <Estado cor="verde">Válido</Estado> : <Estado cor="cinza">Anulado</Estado>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={documentos} />
            </Bloco>
        </AppLayout>
    );
}

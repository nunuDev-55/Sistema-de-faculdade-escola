import { Bloco, Estado, LinhaVazia, Paginacao, TituloPagina, type CorEstado, type Pagina } from '@/components/erp';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { useFiltros } from '@/hooks/use-filtros';
import AppLayout from '@/layouts/app-layout';
import { formatRelativo } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Linha {
    id: number;
    codigo: string | null;
    leccionacao_id: number;
    disciplina: string;
    turma: string;
    curso: string;
    periodo: string;
    docente: string | null;
    epoca: string;
    estado: 'rascunho' | 'submetida' | 'validada' | 'publicada';
    estado_rotulo: string;
    estudantes: number;
    actualizada: string;
}

interface Props {
    pautas: Pagina<Linha>;
    filtros: { estado: string; epoca: string };
    estados: Opcao[];
    epocas: Opcao[];
}

const COR: Record<Linha['estado'], CorEstado> = { rascunho: 'laranja', submetida: 'azul', validada: 'azul', publicada: 'verde' };

export default function Pautas({ pautas, filtros: iniciais, estados, epocas }: Props) {
    const { filtros, mudar } = useFiltros(route('pautas.index'), iniciais);

    return (
        <AppLayout>
            <Head title="Pautas" />
            <TituloPagina
                titulo="Pautas"
                contagem={pautas.total}
                descricao="O docente lança e submete; o director de curso valida e publica. Só as pautas publicadas contam para os estudantes."
            />
            <div className="mb-3 flex flex-col gap-2 sm:flex-row">
                <CaixaEscolha value={filtros.estado} onChange={(e) => mudar('estado', e.target.value)} className="sm:max-w-52" aria-label="Estado">
                    <option value="">Todos os estados</option>
                    {estados.map((e) => (
                        <option key={e.valor} value={e.valor}>
                            {e.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.epoca} onChange={(e) => mudar('epoca', e.target.value)} className="sm:max-w-52" aria-label="Época">
                    <option value="">Todas as épocas</option>
                    {epocas.map((e) => (
                        <option key={e.valor} value={e.valor}>
                            {e.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
            </div>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Turma</th>
                            <th>Época</th>
                            <th>Docente</th>
                            <th className="text-right">Estudantes</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pautas.data.length === 0 && <LinhaVazia colunas={6} texto="Nenhuma pauta com estes filtros." />}
                        {pautas.data.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link href={route('avaliacao.show', p.leccionacao_id)} className="font-medium hover:underline">
                                        {p.disciplina}
                                    </Link>
                                    <div className="text-muted-foreground text-[12px]">{p.periodo}</div>
                                </td>
                                <td>
                                    {p.turma}
                                    <div className="text-muted-foreground text-[12px]">{p.curso}</div>
                                </td>
                                <td>{p.epoca}</td>
                                <td>{p.docente ?? '—'}</td>
                                <td className="text-right tabular-nums">{p.estudantes}</td>
                                <td>
                                    <Estado cor={COR[p.estado]}>{p.estado_rotulo}</Estado>
                                    <div className="text-muted-foreground text-[12px]">
                                        {p.codigo ? `${p.codigo} · ` : ''}
                                        {formatRelativo(p.actualizada)}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={pautas} />
            </Bloco>
        </AppLayout>
    );
}

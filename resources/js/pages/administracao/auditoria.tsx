import { Bloco, LinhaVazia, Paginacao, TituloPagina, type Pagina } from '@/components/erp';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import AppLayout from '@/layouts/app-layout';
import { formatDataHora, formatRelativo } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface Registo {
    id: number;
    data: string | null;
    modulo: string | null;
    evento: string | null;
    descricao: string;
    utilizador: string;
    registo: string | null;
    alteracoes: { campo: string; antes: string; depois: string }[];
    detalhes: string | null;
}

interface Props {
    registos: Pagina<Registo>;
    filtros: { utilizador: string; modulo: string; de: string; ate: string };
    utilizadores: Opcao[];
    modulos: Opcao[];
}

export default function Auditoria({ registos, filtros: iniciais, utilizadores, modulos }: Props) {
    const { filtros, mudar } = useFiltros(route('auditoria.index'), iniciais);
    const filtrado = Object.values(filtros).some((v) => v !== '');

    return (
        <AppLayout>
            <Head title="Auditoria" />
            <TituloPagina
                titulo="Auditoria"
                contagem={registos.total}
                descricao="Quem fez o quê e quando: entradas no sistema, matrículas, inscrições, planos, configuração e contas. Nada se apaga daqui."
            />

            <div className="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-[14rem_14rem_10rem_10rem]">
                <CaixaEscolha value={filtros.utilizador} onChange={(e) => mudar('utilizador', e.target.value)} aria-label="Quem">
                    <option value="">Todas as pessoas</option>
                    {utilizadores.map((u) => (
                        <option key={u.valor} value={u.valor}>
                            {u.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.modulo} onChange={(e) => mudar('modulo', e.target.value)} aria-label="Módulo">
                    <option value="">Todos os módulos</option>
                    {modulos.map((m) => (
                        <option key={m.valor} value={m.valor}>
                            {m.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <Input type="date" value={filtros.de} onChange={(e) => mudar('de', e.target.value)} aria-label="Desde" title="Desde" />
                <Input type="date" value={filtros.ate} onChange={(e) => mudar('ate', e.target.value)} aria-label="Até" title="Até" />
            </div>

            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Quem</th>
                            <th>Módulo</th>
                            <th>O que aconteceu</th>
                        </tr>
                    </thead>
                    <tbody>
                        {registos.data.length === 0 && (
                            <LinhaVazia colunas={4} texto={filtrado ? 'Nenhum registo com estes filtros.' : 'Ainda não há registos.'} />
                        )}
                        {registos.data.map((r) => (
                            <tr key={r.id}>
                                <td className="whitespace-nowrap tabular-nums" title={r.data ? formatDataHora(r.data) : undefined}>
                                    {r.data ? formatDataHora(r.data) : '—'}
                                    {r.data && <div className="text-muted-foreground text-[12px]">{formatRelativo(r.data)}</div>}
                                </td>
                                <td>{r.utilizador}</td>
                                <td className="text-muted-foreground">{r.modulo}</td>
                                <td className="min-w-0">
                                    <span className="font-medium">{r.descricao}</span>
                                    {r.registo && <span className="text-muted-foreground"> · {r.registo}</span>}
                                    {r.detalhes && <div className="text-muted-foreground text-[12.5px]">{r.detalhes}</div>}
                                    {r.alteracoes.length > 0 && (
                                        <ul className="mt-1 flex flex-col gap-0.5 text-[12.5px]">
                                            {r.alteracoes.map((a, i) => (
                                                <li key={i} className="flex flex-wrap items-center gap-1.5">
                                                    <span className="text-muted-foreground">{a.campo}:</span>
                                                    <span className="text-muted-foreground line-through decoration-1">{a.antes}</span>
                                                    <ArrowRight className="text-muted-foreground size-3" aria-label="passou a" />
                                                    <span>{a.depois}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={registos} />
            </Bloco>
        </AppLayout>
    );
}

import { Bloco, BotaoLink, Estado, LinhaVazia, Paginacao, TituloPagina, type Pagina } from '@/components/erp';
import { COR_ESTUDANTE } from '@/components/estudantes';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';

interface Linha {
    id: number;
    numero: string;
    nome: string;
    curso: string | null;
    ano_curricular: number | null;
    ano_lectivo: string | null;
    telemovel: string | null;
    estado: string;
    estado_rotulo: string;
}

interface Props {
    estudantes: Pagina<Linha>;
    filtros: { q: string; estado: string; curso: string };
    estados: Opcao[];
    cursos: Opcao[];
}

export default function Estudantes({ estudantes, filtros: iniciais, estados, cursos }: Props) {
    const pode = usePode();
    const termos = useTermos();
    const { filtros, mudar } = useFiltros(route('estudantes.index'), iniciais);
    const filtrado = filtros.q !== '' || filtros.estado !== '' || filtros.curso !== '';

    return (
        <AppLayout>
            <Head title="Estudantes" />
            <TituloPagina
                titulo="Estudantes"
                contagem={estudantes.total}
                descricao="A ficha de cada estudante: dados, encarregados, matrículas e inscrições."
                accoes={
                    pode('estudantes.gerir') && (
                        <BotaoLink href={route('estudantes.create')} icone={Plus}>
                            Novo estudante
                        </BotaoLink>
                    )
                }
            />

            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:max-w-sm">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input
                        value={filtros.q}
                        onChange={(e) => mudar('q', e.target.value)}
                        placeholder="Nome, número, documento ou telemóvel"
                        className="pl-8"
                        aria-label="Pesquisar estudante"
                    />
                </div>
                <CaixaEscolha
                    value={filtros.curso}
                    onChange={(e) => mudar('curso', e.target.value)}
                    className="sm:max-w-72"
                    aria-label={termos.curso}
                >
                    <option value="">Todos os {termos.curso === 'Classe' ? 'níveis' : 'cursos'}</option>
                    {cursos.map((c) => (
                        <option key={c.valor} value={c.valor}>
                            {c.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.estado} onChange={(e) => mudar('estado', e.target.value)} className="sm:max-w-44" aria-label="Estado">
                    <option value="">Todos os estados</option>
                    {estados.map((e) => (
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
                            <th>Número</th>
                            <th>Nome</th>
                            <th>{termos.curso}</th>
                            <th className="text-right">{termos.ano_curricular}</th>
                            <th>Telemóvel</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {estudantes.data.length === 0 && (
                            <LinhaVazia
                                colunas={6}
                                texto={filtrado ? 'Nenhum estudante com estes filtros.' : 'Ainda não há estudantes registados.'}
                            />
                        )}
                        {estudantes.data.map((e) => (
                            <tr key={e.id}>
                                <td className="font-mono text-[12.5px]">{e.numero}</td>
                                <td>
                                    <Link href={route('estudantes.show', e.id)} className="font-medium hover:underline">
                                        {e.nome}
                                    </Link>
                                </td>
                                <td>
                                    {e.curso ?? <span className="text-muted-foreground">Sem matrícula</span>}
                                    {e.ano_lectivo && <span className="text-muted-foreground"> · {e.ano_lectivo}</span>}
                                </td>
                                <td className="text-right tabular-nums">{e.ano_curricular ? `${e.ano_curricular}.º` : '—'}</td>
                                <td className="tabular-nums">{e.telemovel ?? '—'}</td>
                                <td>
                                    <Estado cor={COR_ESTUDANTE[e.estado] ?? 'cinza'}>{e.estado_rotulo}</Estado>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={estudantes} />
            </Bloco>
        </AppLayout>
    );
}

import { Bloco, BotaoLink, Estado, LinhaVazia, Paginacao, TituloPagina, type Pagina } from '@/components/erp';
import { COR_MATRICULA } from '@/components/estudantes';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';

interface Linha {
    id: number;
    numero: string;
    estudante_id: number;
    estudante_numero: string;
    estudante: string;
    curso: string;
    ano_curricular: number;
    regime: string | null;
    estado: string;
    estado_rotulo: string;
    inscricoes: number;
    data: string;
}

interface Props {
    matriculas: Pagina<Linha>;
    filtros: { ano: string; curso: string; estado: string; q: string };
    contagens: Record<string, number>;
    anos: Opcao[];
    cursos: Opcao[];
    estados: Opcao[];
}

export default function Matriculas({ matriculas, filtros: iniciais, contagens, anos, cursos, estados }: Props) {
    const pode = usePode();
    const termos = useTermos();
    const { filtros, mudar } = useFiltros(route('matriculas.index'), iniciais);
    const total = Object.values(contagens).reduce((s, n) => s + Number(n), 0);

    const escolha = (campo: 'ano' | 'curso' | 'estado', rotulo: string, opcoes: Opcao[], todos: string | null, className: string) => (
        <CaixaEscolha value={filtros[campo]} onChange={(e) => mudar(campo, e.target.value)} className={className} aria-label={rotulo}>
            {todos !== null && <option value="">{todos}</option>}
            {opcoes.map((o) => (
                <option key={o.valor} value={o.valor}>
                    {o.rotulo}
                </option>
            ))}
        </CaixaEscolha>
    );

    return (
        <AppLayout>
            <Head title="Matrículas" />
            <TituloPagina
                titulo="Matrículas"
                contagem={total}
                descricao="Para matricular, abra a ficha do estudante. A inscrição nas disciplinas faz-se a seguir à matrícula."
                accoes={
                    pode('estudantes.gerir') && (
                        <BotaoLink href={route('estudantes.create')} icone={Plus} variante="outline">
                            Novo estudante
                        </BotaoLink>
                    )
                }
            />

            {anos.length === 0 ? (
                <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">
                    Ainda não há anos lectivos. Crie o primeiro em{' '}
                    <Link href={route('estrutura.calendario.index')} className="text-foreground underline">
                        Estrutura → Calendário
                    </Link>
                    .
                </div>
            ) : (
                <>
                    <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        {escolha('ano', 'Ano lectivo', anos, null, 'sm:max-w-36')}
                        {escolha('curso', termos.curso, cursos, `Todos os ${termos.curso === 'Classe' ? 'níveis' : 'cursos'}`, 'sm:max-w-72')}
                        {escolha('estado', 'Estado', estados, 'Todos os estados', 'sm:max-w-44')}
                        <div className="relative w-full sm:max-w-xs">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                            <Input
                                value={filtros.q}
                                onChange={(e) => mudar('q', e.target.value)}
                                placeholder="Nome ou número do estudante"
                                className="pl-8"
                                aria-label="Pesquisar"
                            />
                        </div>
                    </div>

                    {total > 0 && (
                        <div className="text-muted-foreground mb-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px]">
                            {estados
                                .filter((e) => contagens[e.valor])
                                .map((e) => (
                                    <Estado key={e.valor} cor={COR_MATRICULA[e.valor] ?? 'cinza'}>
                                        {e.rotulo}: <span className="text-foreground tabular-nums">{contagens[e.valor]}</span>
                                    </Estado>
                                ))}
                        </div>
                    )}

                    <Bloco>
                        <table className="tabela">
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Estudante</th>
                                    <th>{termos.curso}</th>
                                    <th className="text-right">{termos.ano_curricular}</th>
                                    <th>Regime</th>
                                    <th className="text-right">Inscrições</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {matriculas.data.length === 0 && <LinhaVazia colunas={8} texto="Nenhuma matrícula com estes filtros." />}
                                {matriculas.data.map((m) => (
                                    <tr key={m.id}>
                                        <td className="font-mono text-[12.5px]">{m.numero}</td>
                                        <td>
                                            <Link href={route('estudantes.show', m.estudante_id)} className="font-medium hover:underline">
                                                {m.estudante}
                                            </Link>
                                            <div className="text-muted-foreground font-mono text-[11.5px]">{m.estudante_numero}</div>
                                        </td>
                                        <td>{m.curso}</td>
                                        <td className="text-right tabular-nums">{m.ano_curricular}.º</td>
                                        <td>{m.regime ?? '—'}</td>
                                        <td className="text-right tabular-nums">{m.inscricoes}</td>
                                        <td className="tabular-nums">{formatData(m.data)}</td>
                                        <td>
                                            <Estado cor={COR_MATRICULA[m.estado] ?? 'cinza'}>{m.estado_rotulo}</Estado>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <Paginacao pagina={matriculas} />
                    </Bloco>
                </>
            )}
        </AppLayout>
    );
}

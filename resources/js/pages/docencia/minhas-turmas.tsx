import { Bloco, LinhaVazia, TituloPagina } from '@/components/erp';
import { NavDocencia } from '@/components/seccoes';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

interface Leccionacao {
    id: number;
    disciplina: string;
    codigo: string;
    turma: string;
    turma_id: number;
    curso: string;
    ano_lectivo: string;
    periodo: string;
    estudantes: number;
}

interface Props {
    docente: { id: number; nome: string } | null;
    leccionacoes: Leccionacao[];
}

export default function MinhasTurmas({ docente, leccionacoes }: Props) {
    const pode = usePode();
    const porPeriodo = leccionacoes.reduce<Record<string, Leccionacao[]>>((grupos, l) => {
        const chave = `${l.periodo} · ${l.ano_lectivo}`;
        (grupos[chave] ??= []).push(l);
        return grupos;
    }, {});

    return (
        <AppLayout>
            <Head title="As minhas turmas" />
            <TituloPagina
                titulo="As minhas turmas"
                descricao={docente ? `${docente.nome}: as disciplinas e turmas que lhe foram distribuídas.` : undefined}
            />
            <NavDocencia />
            {docente === null ? (
                <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">
                    A sua conta ainda não está ligada a uma ficha de docente. Peça à secretaria que a ligue em Docência → Docentes.
                </div>
            ) : leccionacoes.length === 0 ? (
                <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">
                    Ainda não lhe foi distribuída nenhuma disciplina.
                </div>
            ) : (
                <div className="flex flex-col gap-5">
                    {Object.entries(porPeriodo).map(([periodo, linhas]) => (
                        <Bloco key={periodo} titulo={periodo}>
                            <table className="tabela">
                                <thead>
                                    <tr>
                                        <th>Disciplina</th>
                                        <th>Turma</th>
                                        <th>Curso</th>
                                        <th className="text-right">Estudantes</th>
                                        <th className="w-px" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {linhas.length === 0 && <LinhaVazia colunas={5} />}
                                    {linhas.map((l) => (
                                        <tr key={l.id}>
                                            <td>
                                                <Link href={route('avaliacao.show', l.id)} className="font-medium hover:underline">
                                                    {l.disciplina}
                                                </Link>
                                                <div className="text-muted-foreground font-mono text-[11.5px]">{l.codigo}</div>
                                            </td>
                                            <td>
                                                {pode('turmas.ver') ? (
                                                    <Link href={route('turmas.show', l.turma_id)} className="hover:underline">
                                                        {l.turma}
                                                    </Link>
                                                ) : (
                                                    l.turma
                                                )}
                                            </td>
                                            <td>{l.curso}</td>
                                            <td className="text-right tabular-nums">{l.estudantes}</td>
                                            <td>
                                                <Link
                                                    href={route('avaliacao.show', l.id)}
                                                    className="text-[13px] whitespace-nowrap underline-offset-2 hover:underline"
                                                >
                                                    Notas e pauta
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Bloco>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

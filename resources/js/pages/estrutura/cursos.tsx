import { Bloco, Estado, LinhaVazia, TituloPagina } from '@/components/erp';
import { FormularioCurso, type Curso } from '@/components/formulario-curso';
import { NavEstrutura } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    cursos: Curso[];
    unidades: Opcao[];
    graus: Opcao[];
}

export default function Cursos({ cursos, unidades, graus }: Props) {
    const pode = usePode();
    const [novo, setNovo] = useState(false);

    return (
        <AppLayout>
            <Head title="Cursos" />
            <TituloPagina
                titulo="Estrutura académica"
                descricao="Cursos, disciplinas, planos curriculares e calendário."
                accoes={
                    pode('estrutura.gerir') &&
                    !novo && (
                        <Button size="sm" onClick={() => setNovo(true)}>
                            <Plus /> Novo curso
                        </Button>
                    )
                }
            />
            <NavEstrutura />

            {novo && (
                <div className="mb-5">
                    <FormularioCurso unidades={unidades} graus={graus} fechar={() => setNovo(false)} />
                </div>
            )}

            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Curso</th>
                            <th>Grau</th>
                            <th className="text-right">Duração</th>
                            <th>Unidade</th>
                            <th>Plano em vigor</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cursos.length === 0 && <LinhaVazia colunas={7} texto="Ainda não há cursos. Crie o primeiro com «Novo curso»." />}
                        {cursos.map((c) => (
                            <tr key={c.id}>
                                <td className="font-mono text-[12.5px]">{c.codigo}</td>
                                <td>
                                    <Link href={route('estrutura.cursos.show', c.id)} className="font-medium hover:underline">
                                        {c.nome}
                                    </Link>
                                </td>
                                <td>{c.grau_rotulo}</td>
                                <td className="text-right tabular-nums">{c.duracao_anos} anos</td>
                                <td>{c.unidade ?? '—'}</td>
                                <td>
                                    {c.plano_em_vigor ? (
                                        <Estado cor="verde">{c.plano_em_vigor}</Estado>
                                    ) : (
                                        <Estado cor="laranja">Sem plano aprovado</Estado>
                                    )}
                                </td>
                                <td>{c.activo ? <Estado cor="verde">Activo</Estado> : <Estado cor="cinza">Inactivo</Estado>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
        </AppLayout>
    );
}

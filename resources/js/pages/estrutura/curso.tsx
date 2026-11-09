import { Bloco, CabecalhoFicha, Campo, Campos, Estado, LinhaForm, LinhaVazia } from '@/components/erp';
import { FormularioCurso, type Curso } from '@/components/formulario-curso';
import InputError from '@/components/input-error';
import { NavEstrutura } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Plano {
    id: number;
    versao: string;
    estado: 'rascunho' | 'aprovado' | 'substituido';
    estado_rotulo: string;
    disciplinas: number;
    metodo: string;
    aprovado_em: string | null;
}

interface Props {
    curso: Curso;
    planos: Plano[];
    unidades: Opcao[];
    graus: Opcao[];
    metodos: Opcao[];
    sugestaoVersao: string;
}

const COR_PLANO = { rascunho: 'laranja', aprovado: 'verde', substituido: 'cinza' } as const;

export default function CursoFicha({ curso, planos, unidades, graus, metodos, sugestaoVersao }: Props) {
    const pode = usePode();
    const [editar, setEditar] = useState(false);
    const [novoPlano, setNovoPlano] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ versao: sugestaoVersao, metodo_avaliacao: metodos[0]?.valor ?? 'media_simples' });

    const criarPlano = (e: FormEvent) => {
        e.preventDefault();
        post(route('estrutura.planos.store', curso.id));
    };

    return (
        <AppLayout>
            <Head title={curso.nome} />
            <NavEstrutura />
            <CabecalhoFicha
                voltar={{ href: route('estrutura.cursos.index'), label: 'Cursos' }}
                referencia={curso.codigo}
                titulo={curso.nome}
                estado={curso.activo ? <Estado cor="verde">Activo</Estado> : <Estado cor="cinza">Inactivo</Estado>}
            />

            {editar ? (
                <div className="mb-5">
                    <FormularioCurso curso={curso} unidades={unidades} graus={graus} fechar={() => setEditar(false)} />
                </div>
            ) : (
                <div className="mb-5 flex flex-col gap-3">
                    <Campos>
                        <Campo rotulo="Grau">{curso.grau_rotulo}</Campo>
                        <Campo rotulo="Duração">{curso.duracao_anos} anos</Campo>
                        <Campo rotulo="Unidade orgânica">{curso.unidade}</Campo>
                        <Campo rotulo="Plano em vigor">{curso.plano_em_vigor}</Campo>
                    </Campos>
                    {pode('estrutura.gerir') && (
                        <div className="flex justify-end">
                            <Button size="sm" variant="outline" onClick={() => setEditar(true)}>
                                <Pencil /> Alterar o curso
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <Bloco
                titulo="Planos curriculares"
                accoes={
                    pode('estrutura.gerir') &&
                    !novoPlano && (
                        <Button size="sm" variant="outline" onClick={() => setNovoPlano(true)}>
                            <Plus /> Novo plano
                        </Button>
                    )
                }
            >
                {novoPlano && (
                    <form onSubmit={criarPlano} className="border-b">
                        <LinhaForm rotulo="Versão" htmlFor="versao" obrigatorio ajuda="Normalmente o ano em que entra em vigor.">
                            <Input id="versao" value={data.versao} onChange={(e) => setData('versao', e.target.value)} className="max-w-32" />
                            <InputError message={errors.versao} className="mt-1" />
                        </LinhaForm>
                        <LinhaForm rotulo="Método de avaliação" htmlFor="metodo_avaliacao" ajuda="Como se calcula a média do curso.">
                            <CaixaEscolha
                                id="metodo_avaliacao"
                                value={data.metodo_avaliacao}
                                onChange={(e) => setData('metodo_avaliacao', e.target.value)}
                                className="max-w-80"
                            >
                                {metodos.map((m) => (
                                    <option key={m.valor} value={m.valor}>
                                        {m.rotulo}
                                    </option>
                                ))}
                            </CaixaEscolha>
                        </LinhaForm>
                        <div className="flex justify-end gap-2 px-5 py-3">
                            <Button type="button" variant="outline" onClick={() => setNovoPlano(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Criar em rascunho
                            </Button>
                        </div>
                    </form>
                )}
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Versão</th>
                            <th>Estado</th>
                            <th className="text-right">Disciplinas</th>
                            <th>Método de avaliação</th>
                            <th>Aprovado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        {planos.length === 0 && <LinhaVazia colunas={5} texto="Este curso ainda não tem plano curricular." />}
                        {planos.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link href={route('estrutura.planos.show', p.id)} className="font-medium hover:underline">
                                        Plano {p.versao}
                                    </Link>
                                </td>
                                <td>
                                    <Estado cor={COR_PLANO[p.estado]}>{p.estado_rotulo}</Estado>
                                </td>
                                <td className="text-right tabular-nums">{p.disciplinas}</td>
                                <td>{p.metodo}</td>
                                <td>{p.aprovado_em ? formatData(p.aprovado_em) : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
        </AppLayout>
    );
}

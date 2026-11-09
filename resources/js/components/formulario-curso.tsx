import { Bloco, LinhaForm } from '@/components/erp';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { type Opcao } from '@/types';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

export interface Curso {
    id: number;
    codigo: string;
    nome: string;
    grau: string;
    grau_rotulo: string;
    duracao_anos: number;
    unidade: string | null;
    unidade_id: number | null;
    activo: boolean;
    plano_em_vigor: string | null;
}

/** Formulário do curso: o mesmo para criar (lista) e para alterar (ficha) */
export function FormularioCurso({ curso, unidades, graus, fechar }: { curso?: Curso; unidades: Opcao[]; graus: Opcao[]; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        codigo: curso?.codigo ?? '',
        nome: curso?.nome ?? '',
        grau: curso?.grau ?? 'licenciatura',
        duracao_anos: String(curso?.duracao_anos ?? 4),
        unidade_id: curso?.unidade_id ? String(curso.unidade_id) : '',
        activo: curso?.activo ?? true,
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (curso) put(route('estrutura.cursos.update', curso.id), opcoes);
        else post(route('estrutura.cursos.store'), opcoes);
    };
    return (
        <form onSubmit={guardar}>
            <Bloco titulo={curso ? 'Alterar o curso' : 'Novo curso'}>
                <LinhaForm rotulo="Código" htmlFor="codigo" obrigatorio ajuda="Curto e único, por exemplo LIG.">
                    <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} className="max-w-40 uppercase" />
                    <InputError message={errors.codigo} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio>
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} placeholder="Licenciatura em …" />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Grau" htmlFor="grau" obrigatorio>
                    <CaixaEscolha id="grau" value={data.grau} onChange={(e) => setData('grau', e.target.value)} className="max-w-72">
                        {graus.map((g) => (
                            <option key={g.valor} value={g.valor}>
                                {g.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <LinhaForm rotulo="Duração" htmlFor="duracao_anos" obrigatorio ajuda="Em anos (no ensino geral, o número de classes).">
                    <Input
                        id="duracao_anos"
                        type="number"
                        min={1}
                        max={12}
                        value={data.duracao_anos}
                        onChange={(e) => setData('duracao_anos', e.target.value)}
                        className="w-24"
                    />
                    <InputError message={errors.duracao_anos} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Unidade orgânica" htmlFor="unidade_id">
                    <CaixaEscolha
                        id="unidade_id"
                        value={data.unidade_id}
                        onChange={(e) => setData('unidade_id', e.target.value)}
                        className="max-w-96"
                    >
                        <option value="">—</option>
                        {unidades.map((u) => (
                            <option key={u.valor} value={u.valor}>
                                {u.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                {curso && (
                    <LinhaForm rotulo="Activo" htmlFor="activo" ajuda="Um curso inactivo não recebe matrículas novas.">
                        <input
                            id="activo"
                            type="checkbox"
                            checked={data.activo}
                            onChange={(e) => setData('activo', e.target.checked)}
                            className="mt-2.5"
                        />
                    </LinhaForm>
                )}
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {curso ? 'Guardar' : 'Criar curso'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

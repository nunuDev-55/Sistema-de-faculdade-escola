import { Bloco, BotaoIcone, Estado, LinhaForm, LinhaVazia, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavEstrutura } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Unidade {
    id: number;
    tipo: string;
    tipo_rotulo: string;
    codigo: string;
    nome: string;
    pai: string | null;
    unidade_pai_id: number | null;
    activo: boolean;
    cursos: number;
}

function Formulario({ unidade, unidades, tipos, fechar }: { unidade?: Unidade; unidades: Unidade[]; tipos: Opcao[]; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        tipo: unidade?.tipo ?? 'faculdade',
        codigo: unidade?.codigo ?? '',
        nome: unidade?.nome ?? '',
        unidade_pai_id: unidade?.unidade_pai_id ? String(unidade.unidade_pai_id) : '',
        activo: unidade?.activo ?? true,
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (unidade) put(route('estrutura.unidades.update', unidade.id), opcoes);
        else post(route('estrutura.unidades.store'), opcoes);
    };
    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={unidade ? `Alterar ${unidade.nome}` : 'Nova unidade orgânica'}>
                <LinhaForm rotulo="Tipo" htmlFor="tipo" obrigatorio>
                    <CaixaEscolha id="tipo" value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} className="max-w-60">
                        {tipos.map((t) => (
                            <option key={t.valor} value={t.valor}>
                                {t.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <LinhaForm rotulo="Código" htmlFor="codigo" obrigatorio>
                    <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} className="max-w-40 uppercase" />
                    <InputError message={errors.codigo} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio>
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} placeholder="Faculdade de …" />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Pertence a" htmlFor="unidade_pai_id" ajuda="Por exemplo, um departamento pertence a uma faculdade.">
                    <CaixaEscolha
                        id="unidade_pai_id"
                        value={data.unidade_pai_id}
                        onChange={(e) => setData('unidade_pai_id', e.target.value)}
                        className="max-w-96"
                    >
                        <option value="">— (topo da instituição)</option>
                        {unidades
                            .filter((u) => u.id !== unidade?.id)
                            .map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nome}
                                </option>
                            ))}
                    </CaixaEscolha>
                    <InputError message={errors.unidade_pai_id} className="mt-1" />
                </LinhaForm>
                {unidade && (
                    <LinhaForm rotulo="Activa" htmlFor="activo">
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
                        {unidade ? 'Guardar' : 'Criar unidade'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Unidades({ unidades, tipos }: { unidades: Unidade[]; tipos: Opcao[] }) {
    const pode = usePode();
    const [formulario, setFormulario] = useState<Unidade | 'nova' | null>(null);

    return (
        <AppLayout>
            <Head title="Unidades orgânicas" />
            <TituloPagina
                titulo="Estrutura académica"
                descricao="Faculdades, escolas e departamentos."
                accoes={
                    pode('estrutura.gerir') &&
                    formulario === null && (
                        <Button size="sm" onClick={() => setFormulario('nova')}>
                            <Plus /> Nova unidade
                        </Button>
                    )
                }
            />
            <NavEstrutura />
            {formulario !== null && (
                <Formulario
                    key={formulario === 'nova' ? 'nova' : formulario.id}
                    unidade={formulario === 'nova' ? undefined : formulario}
                    unidades={unidades}
                    tipos={tipos}
                    fechar={() => setFormulario(null)}
                />
            )}
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Unidade</th>
                            <th>Tipo</th>
                            <th>Pertence a</th>
                            <th className="text-right">Cursos</th>
                            <th>Estado</th>
                            {pode('estrutura.gerir') && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {unidades.length === 0 && <LinhaVazia colunas={7} texto="Ainda não há unidades orgânicas." />}
                        {unidades.map((u) => (
                            <tr key={u.id}>
                                <td className="font-mono text-[12.5px]">{u.codigo}</td>
                                <td>{u.nome}</td>
                                <td>{u.tipo_rotulo}</td>
                                <td>{u.pai ?? '—'}</td>
                                <td className="text-right tabular-nums">{u.cursos}</td>
                                <td>{u.activo ? <Estado cor="verde">Activa</Estado> : <Estado cor="cinza">Inactiva</Estado>}</td>
                                {pode('estrutura.gerir') && (
                                    <td>
                                        <BotaoIcone rotulo={`Alterar ${u.nome}`} onClick={() => setFormulario(u)}>
                                            <Pencil className="size-4" />
                                        </BotaoIcone>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
        </AppLayout>
    );
}

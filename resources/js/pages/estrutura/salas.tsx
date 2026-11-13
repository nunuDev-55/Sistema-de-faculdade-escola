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

interface Sala {
    id: number;
    codigo: string;
    nome: string | null;
    tipo: string;
    tipo_rotulo: string;
    capacidade: number;
    activo: boolean;
}

function Formulario({ sala, tipos, fechar }: { sala?: Sala; tipos: Opcao[]; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        codigo: sala?.codigo ?? '',
        nome: sala?.nome ?? '',
        tipo: sala?.tipo ?? 'aula',
        capacidade: String(sala?.capacidade ?? 40),
        activo: sala?.activo ?? true,
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (sala) put(route('estrutura.salas.update', sala.id), opcoes);
        else post(route('estrutura.salas.store'), opcoes);
    };
    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={sala ? `Alterar a sala ${sala.codigo}` : 'Nova sala'}>
                <LinhaForm rotulo="Código" htmlFor="codigo" obrigatorio ajuda="Como aparece no horário, por exemplo B2-104.">
                    <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} className="max-w-40 uppercase" />
                    <InputError message={errors.codigo} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Nome" htmlFor="nome">
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} placeholder="Laboratório de redes" />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Tipo" htmlFor="tipo" obrigatorio>
                    <CaixaEscolha id="tipo" value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} className="max-w-60">
                        {tipos.map((t) => (
                            <option key={t.valor} value={t.valor}>
                                {t.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <LinhaForm rotulo="Lotação" htmlFor="capacidade" obrigatorio ajuda="Lugares sentados. Serve para os horários e os exames.">
                    <Input
                        id="capacidade"
                        type="number"
                        min={1}
                        value={data.capacidade}
                        onChange={(e) => setData('capacidade', e.target.value)}
                        className="w-28 tabular-nums"
                    />
                    <InputError message={errors.capacidade} className="mt-1" />
                </LinhaForm>
                {sala && (
                    <LinhaForm rotulo="Em uso" htmlFor="activo" ajuda="Uma sala fora de uso não aparece nos horários novos.">
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
                        {sala ? 'Guardar' : 'Criar sala'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Salas({ salas, tipos }: { salas: Sala[]; tipos: Opcao[] }) {
    const pode = usePode();
    const [formulario, setFormulario] = useState<Sala | 'nova' | null>(null);
    const lugares = salas.filter((s) => s.activo).reduce((t, s) => t + s.capacidade, 0);

    return (
        <AppLayout>
            <Head title="Salas" />
            <TituloPagina
                titulo="Estrutura académica"
                descricao="Cursos, disciplinas, planos curriculares, calendário e salas."
                accoes={
                    pode('calendario.gerir') &&
                    formulario === null && (
                        <Button size="sm" onClick={() => setFormulario('nova')}>
                            <Plus /> Nova sala
                        </Button>
                    )
                }
            />
            <NavEstrutura />
            {formulario !== null && (
                <Formulario
                    key={formulario === 'nova' ? 'nova' : formulario.id}
                    sala={formulario === 'nova' ? undefined : formulario}
                    tipos={tipos}
                    fechar={() => setFormulario(null)}
                />
            )}
            <Bloco titulo={`${salas.length} salas · ${lugares.toLocaleString('pt-PT')} lugares em uso`}>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th className="text-right">Lotação</th>
                            <th>Estado</th>
                            {pode('calendario.gerir') && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {salas.length === 0 && <LinhaVazia colunas={6} texto="Ainda não há salas." />}
                        {salas.map((s) => (
                            <tr key={s.id}>
                                <td className="font-mono text-[12.5px]">{s.codigo}</td>
                                <td>{s.nome ?? '—'}</td>
                                <td>{s.tipo_rotulo}</td>
                                <td className="text-right tabular-nums">{s.capacidade}</td>
                                <td>{s.activo ? <Estado cor="verde">Em uso</Estado> : <Estado cor="cinza">Fora de uso</Estado>}</td>
                                {pode('calendario.gerir') && (
                                    <td>
                                        <BotaoIcone rotulo={`Alterar a sala ${s.codigo}`} onClick={() => setFormulario(s)}>
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

import { Bloco, BotaoIcone, Estado, LinhaForm, LinhaVazia, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavEstrutura, numeroCampo } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { formatNumero } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Disciplina {
    id: number;
    codigo: string;
    nome: string;
    creditos: number;
    horas_teoricas: number;
    horas_praticas: number;
    unidade: string | null;
    unidade_id: number | null;
    activo: boolean;
    planos: number;
}

function Formulario({ disciplina, unidades, fechar }: { disciplina?: Disciplina; unidades: Opcao[]; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        codigo: disciplina?.codigo ?? '',
        nome: disciplina?.nome ?? '',
        creditos: numeroCampo(disciplina?.creditos ?? 6),
        horas_teoricas: String(disciplina?.horas_teoricas ?? 30),
        horas_praticas: String(disciplina?.horas_praticas ?? 30),
        unidade_id: disciplina?.unidade_id ? String(disciplina.unidade_id) : '',
        activo: disciplina?.activo ?? true,
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (disciplina) put(route('estrutura.disciplinas.update', disciplina.id), opcoes);
        else post(route('estrutura.disciplinas.store'), opcoes);
    };
    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={disciplina ? `Alterar ${disciplina.codigo}` : 'Nova disciplina'}>
                <LinhaForm rotulo="Código" htmlFor="codigo" obrigatorio>
                    <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} className="max-w-40 uppercase" />
                    <InputError message={errors.codigo} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio>
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm
                    rotulo="Créditos"
                    htmlFor="creditos"
                    obrigatorio
                    ajuda="Pode ter meio crédito (4,5). No SNATCA, 1 crédito ≈ 25–30 horas de trabalho do estudante."
                >
                    <Input
                        id="creditos"
                        inputMode="decimal"
                        value={data.creditos}
                        onChange={(e) => setData('creditos', e.target.value)}
                        className="w-24 tabular-nums"
                    />
                    <InputError message={errors.creditos} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Horas (teóricas · práticas)" htmlFor="horas_teoricas">
                    <div className="flex gap-2">
                        <Input
                            id="horas_teoricas"
                            type="number"
                            min={0}
                            value={data.horas_teoricas}
                            onChange={(e) => setData('horas_teoricas', e.target.value)}
                            className="w-24"
                            aria-label="Horas teóricas"
                        />
                        <Input
                            id="horas_praticas"
                            type="number"
                            min={0}
                            value={data.horas_praticas}
                            onChange={(e) => setData('horas_praticas', e.target.value)}
                            className="w-24"
                            aria-label="Horas práticas"
                        />
                    </div>
                    <InputError message={errors.horas_teoricas ?? errors.horas_praticas} className="mt-1" />
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
                {disciplina && (
                    <LinhaForm rotulo="Activa" htmlFor="activo" ajuda="Uma disciplina inactiva não se acrescenta a planos novos.">
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
                        {disciplina ? 'Guardar' : 'Criar disciplina'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Disciplinas({ disciplinas, unidades }: { disciplinas: Disciplina[]; unidades: Opcao[] }) {
    const pode = usePode();
    const [formulario, setFormulario] = useState<Disciplina | 'nova' | null>(null);
    const [q, setQ] = useState('');
    const lista = disciplinas.filter((d) => `${d.codigo} ${d.nome}`.toLowerCase().includes(q.trim().toLowerCase()));

    return (
        <AppLayout>
            <Head title="Disciplinas" />
            <TituloPagina
                titulo="Estrutura académica"
                descricao="Cursos, disciplinas, planos curriculares e calendário."
                accoes={
                    pode('estrutura.gerir') &&
                    formulario === null && (
                        <Button size="sm" onClick={() => setFormulario('nova')}>
                            <Plus /> Nova disciplina
                        </Button>
                    )
                }
            />
            <NavEstrutura />
            {formulario !== null && (
                <Formulario
                    key={formulario === 'nova' ? 'nova' : formulario.id}
                    disciplina={formulario === 'nova' ? undefined : formulario}
                    unidades={unidades}
                    fechar={() => setFormulario(null)}
                />
            )}

            <div className="relative mb-3 max-w-sm">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                <Input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Pesquisar por código ou nome"
                    className="pl-8"
                    aria-label="Pesquisar disciplina"
                />
            </div>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Disciplina</th>
                            <th className="text-right">Créditos</th>
                            <th className="text-right">Horas T · P</th>
                            <th>Unidade</th>
                            <th className="text-right">Planos</th>
                            <th>Estado</th>
                            {pode('estrutura.gerir') && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {lista.length === 0 && (
                            <LinhaVazia
                                colunas={8}
                                texto={disciplinas.length === 0 ? 'Ainda não há disciplinas.' : 'Nenhuma disciplina com essa pesquisa.'}
                            />
                        )}
                        {lista.map((d) => (
                            <tr key={d.id}>
                                <td className="font-mono text-[12.5px]">{d.codigo}</td>
                                <td>{d.nome}</td>
                                <td className="text-right tabular-nums">{formatNumero(d.creditos)}</td>
                                <td className="text-right tabular-nums">
                                    {d.horas_teoricas} · {d.horas_praticas}
                                </td>
                                <td>{d.unidade ?? '—'}</td>
                                <td className="text-right tabular-nums">{d.planos}</td>
                                <td>{d.activo ? <Estado cor="verde">Activa</Estado> : <Estado cor="cinza">Inactiva</Estado>}</td>
                                {pode('estrutura.gerir') && (
                                    <td>
                                        <BotaoIcone rotulo={`Alterar ${d.nome}`} onClick={() => setFormulario(d)}>
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

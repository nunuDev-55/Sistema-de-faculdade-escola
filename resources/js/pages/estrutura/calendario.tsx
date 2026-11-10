import { Bloco, BotaoIcone, ErroFicha, Estado, LinhaForm, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavEstrutura } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarPlus, Pencil } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Periodo {
    id: number;
    nome: string;
    inicio: string;
    fim: string;
    inscricoes_inicio: string | null;
    inscricoes_fim: string | null;
    exames_inicio: string | null;
    exames_fim: string | null;
}

interface Ano {
    id: number;
    nome: string;
    inicio: string;
    fim: string;
    estado: string;
    periodos: Periodo[];
}

interface Props {
    anos: Ano[];
    tipoPeriodo: string;
    periodosPorAno: number;
    podeGerir: boolean;
    sugestao: { nome: string; inicio: string; fim: string };
}

const intervalo = (de: string | null, ate: string | null) => (de && ate ? `${formatData(de)} a ${formatData(ate)}` : '—');

function EditarPeriodo({ periodo, fechar }: { periodo: Periodo; fechar: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        inicio: periodo.inicio,
        fim: periodo.fim,
        inscricoes_inicio: periodo.inscricoes_inicio ?? '',
        inscricoes_fim: periodo.inscricoes_fim ?? '',
        exames_inicio: periodo.exames_inicio ?? '',
        exames_fim: periodo.exames_fim ?? '',
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        put(route('estrutura.calendario.periodos.update', periodo.id), { preserveScroll: true, onSuccess: fechar });
    };
    const par = (rotulo: string, a: 'inicio' | 'inscricoes_inicio' | 'exames_inicio', b: 'fim' | 'inscricoes_fim' | 'exames_fim') => (
        <LinhaForm rotulo={rotulo} htmlFor={`${a}-${periodo.id}`}>
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    id={`${a}-${periodo.id}`}
                    type="date"
                    value={data[a]}
                    onChange={(e) => setData(a, e.target.value)}
                    className="w-44"
                    aria-label={`${rotulo}: início`}
                />
                <span className="text-muted-foreground text-[13px]">a</span>
                <Input type="date" value={data[b]} onChange={(e) => setData(b, e.target.value)} className="w-44" aria-label={`${rotulo}: fim`} />
            </div>
            <InputError message={errors[a] ?? errors[b]} className="mt-1" />
        </LinhaForm>
    );
    return (
        <form onSubmit={guardar} className="border-t bg-[var(--linha-suave)]/40">
            {par('Aulas', 'inicio', 'fim')}
            {par('Inscrições', 'inscricoes_inicio', 'inscricoes_fim')}
            {par('Exames', 'exames_inicio', 'exames_fim')}
            <div className="flex justify-end gap-2 px-5 py-3">
                <Button type="button" variant="outline" size="sm" onClick={fechar}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    Guardar datas
                </Button>
            </div>
        </form>
    );
}

export default function Calendario({ anos, tipoPeriodo, periodosPorAno, podeGerir, sugestao }: Props) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [novo, setNovo] = useState(false);
    const [aEditar, setAEditar] = useState<number | null>(null);
    const form = useForm(sugestao);

    const criar = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('estrutura.calendario.store'), { preserveScroll: true, onSuccess: () => setNovo(false) });
    };

    return (
        <AppLayout>
            <Head title="Calendário" />
            <TituloPagina
                titulo="Estrutura académica"
                descricao={`Anos lectivos e os seus ${tipoPeriodo}s.`}
                accoes={
                    podeGerir &&
                    !novo && (
                        <Button size="sm" onClick={() => setNovo(true)}>
                            <CalendarPlus /> Novo ano lectivo
                        </Button>
                    )
                }
            />
            <NavEstrutura />
            <ErroFicha mensagem={errors.periodo} />

            {novo && (
                <form onSubmit={criar} className="mb-5">
                    <Bloco titulo="Novo ano lectivo">
                        <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio ajuda="Por exemplo 2027 ou 2027/2028.">
                            <Input id="nome" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} className="max-w-40" />
                            <InputError message={form.errors.nome} className="mt-1" />
                        </LinhaForm>
                        <LinhaForm
                            rotulo="Começa e acaba"
                            htmlFor="inicio"
                            obrigatorio
                            ajuda={`O sistema divide o ano em ${periodosPorAno} ${tipoPeriodo}s; depois acerta as datas de cada um.`}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <Input
                                    id="inicio"
                                    type="date"
                                    value={form.data.inicio}
                                    onChange={(e) => form.setData('inicio', e.target.value)}
                                    className="w-44"
                                    aria-label="Início"
                                />
                                <span className="text-muted-foreground text-[13px]">a</span>
                                <Input
                                    type="date"
                                    value={form.data.fim}
                                    onChange={(e) => form.setData('fim', e.target.value)}
                                    className="w-44"
                                    aria-label="Fim"
                                />
                            </div>
                            <InputError message={form.errors.inicio ?? form.errors.fim} className="mt-1" />
                        </LinhaForm>
                        <div className="flex justify-end gap-2 px-5 py-3">
                            <Button type="button" variant="outline" onClick={() => setNovo(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Criar com {periodosPorAno} {tipoPeriodo}s
                            </Button>
                        </div>
                    </Bloco>
                </form>
            )}

            <div className="flex flex-col gap-5">
                {anos.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-[13.5px]">Ainda não há anos lectivos.</p>
                )}
                {anos.map((a) => (
                    <Bloco
                        key={a.id}
                        titulo={`Ano lectivo ${a.nome}`}
                        accoes={
                            <span className="flex items-center gap-3 text-[12.5px]">
                                <span className="text-muted-foreground">
                                    {formatData(a.inicio)} a {formatData(a.fim)}
                                </span>
                                <Estado cor={a.estado === 'Aberto' ? 'verde' : a.estado === 'Fechado' ? 'cinza' : 'laranja'}>{a.estado}</Estado>
                            </span>
                        }
                    >
                        <table className="tabela">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Aulas</th>
                                    <th>Inscrições</th>
                                    <th>Exames</th>
                                    {podeGerir && <th className="w-10" />}
                                </tr>
                            </thead>
                            <tbody>
                                {a.periodos.map((p) => (
                                    <tr key={p.id}>
                                        <td className="font-medium whitespace-nowrap capitalize">{p.nome}</td>
                                        <td className="whitespace-nowrap">{intervalo(p.inicio, p.fim)}</td>
                                        <td className="whitespace-nowrap">{intervalo(p.inscricoes_inicio, p.inscricoes_fim)}</td>
                                        <td className="whitespace-nowrap">{intervalo(p.exames_inicio, p.exames_fim)}</td>
                                        {podeGerir && (
                                            <td>
                                                <BotaoIcone
                                                    rotulo={`Acertar as datas do ${p.nome}`}
                                                    onClick={() => setAEditar(aEditar === p.id ? null : p.id)}
                                                >
                                                    <Pencil className="size-4" />
                                                </BotaoIcone>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {a.periodos
                            .filter((p) => p.id === aEditar)
                            .map((p) => (
                                <EditarPeriodo key={p.id} periodo={p} fechar={() => setAEditar(null)} />
                            ))}
                    </Bloco>
                ))}
            </div>
        </AppLayout>
    );
}

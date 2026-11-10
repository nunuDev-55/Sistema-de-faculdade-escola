import { Bloco, BotaoIcone, CabecalhoFicha, ErroFicha, Estado } from '@/components/erp';
import { COR_SITUACAO, creditos } from '@/components/estudantes';
import InputError from '@/components/input-error';
import { formatCreditos } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, X } from 'lucide-react';
import { type FormEvent } from 'react';

interface Disciplina {
    disciplina_id: number;
    codigo: string;
    nome: string;
    creditos: number;
    ano: number;
    anual: boolean;
    obrigatoria: boolean;
    em_atraso: boolean;
    estado: 'disponivel' | 'bloqueada' | 'aprovada' | 'inscrita' | 'em_curso';
    estado_rotulo: string;
    motivo: string | null;
    inscricao_id: number | null;
}

interface Props {
    matricula: {
        id: number;
        numero: string;
        activa: boolean;
        estado_rotulo: string;
        curso: string;
        ano_curricular: number;
        plano: string;
        ano_lectivo: string;
    };
    estudante: { id: number; numero: string; nome: string };
    periodo: { id: number; nome: string; inscricoes_inicio: string | null; inscricoes_fim: string | null };
    disciplinas: Disciplina[];
    inscricao: { id: number; codigo: string; estado: string } | null;
    limite: number;
}

export default function Inscricao({ matricula, estudante, periodo, disciplinas, inscricao, limite }: Props) {
    const termos = useTermos();
    const { data, setData, post, processing, errors } = useForm<{ disciplinas: number[] }>({ disciplinas: [] });
    const erros = errors as Record<string, string | undefined>;

    const jaInscritos = disciplinas.filter((d) => d.estado === 'inscrita').reduce((s, d) => s + d.creditos, 0);
    const escolhidos = disciplinas.filter((d) => data.disciplinas.includes(d.disciplina_id)).reduce((s, d) => s + d.creditos, 0);
    const total = jaInscritos + escolhidos;
    const passa = total > limite + 0.001;
    const disponiveisDoAno = disciplinas.filter((d) => d.estado === 'disponivel' && !d.em_atraso).map((d) => d.disciplina_id);

    const alternar = (id: number) =>
        setData('disciplinas', data.disciplinas.includes(id) ? data.disciplinas.filter((x) => x !== id) : [...data.disciplinas, id]);

    const inscrever = (e: FormEvent) => {
        e.preventDefault();
        post(route('inscricoes.store', [matricula.id, periodo.id]));
    };

    const anular = (d: Disciplina) => {
        if (d.inscricao_id === null || !confirm(`Anular a inscrição em ${d.nome}?`)) return;
        router.delete(route('inscricoes.destroy', d.inscricao_id), { preserveScroll: true });
    };

    const grupos = [
        { titulo: `Do ${matricula.ano_curricular}.º ${termos.ano_curricular.toLowerCase()}`, linhas: disciplinas.filter((d) => !d.em_atraso) },
        { titulo: 'Em atraso (anos anteriores)', linhas: disciplinas.filter((d) => d.em_atraso) },
    ].filter((g) => g.linhas.length > 0);

    return (
        <AppLayout>
            <Head title={`Inscrição · ${estudante.nome}`} />
            <CabecalhoFicha
                voltar={{ href: route('estudantes.show', estudante.id), label: estudante.nome }}
                referencia={`Inscrição no ${periodo.nome}`}
                titulo={`${estudante.nome} · ${estudante.numero}`}
                subtitulo={`${matricula.curso} · ${matricula.ano_curricular}.º ano · ${matricula.ano_lectivo} · plano ${matricula.plano} · matrícula ${matricula.numero}`}
                estado={inscricao && inscricao.estado === 'confirmada' ? <Estado cor="verde">Inscrição {inscricao.codigo}</Estado> : undefined}
            />

            {!matricula.activa && <ErroFicha mensagem={`A matrícula está ${matricula.estado_rotulo.toLowerCase()}: não se pode inscrever.`} />}
            {erros.matricula && <ErroFicha mensagem={erros.matricula} />}

            <div className="text-muted-foreground mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px]">
                {periodo.inscricoes_inicio && periodo.inscricoes_fim && (
                    <span>
                        Prazo de inscrição: {formatData(periodo.inscricoes_inicio)} a {formatData(periodo.inscricoes_fim)}
                    </span>
                )}
                <span>
                    Limite: {creditos(limite)} por {termos.periodo.toLowerCase()}, com as disciplinas em atraso
                </span>
                {inscricao && inscricao.estado === 'confirmada' && (
                    <Link
                        href={route('inscricoes.comprovativo', inscricao.id)}
                        className="text-foreground inline-flex items-center gap-1 hover:underline"
                    >
                        <FileText className="size-4" /> Comprovativo {inscricao.codigo}
                    </Link>
                )}
            </div>

            <form onSubmit={inscrever} className="flex flex-col gap-5">
                {disciplinas.length === 0 && (
                    <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">
                        O plano {matricula.plano} não tem disciplinas para este {termos.periodo.toLowerCase()}.
                    </div>
                )}
                {grupos.map((g) => (
                    <Bloco key={g.titulo} titulo={g.titulo}>
                        {/* No telemóvel fica uma tabela compacta (caixa, disciplina com o código e a situação, créditos) */}
                        <table className="tabela" data-tabela-fixa="">
                            <thead>
                                <tr>
                                    <th className="w-10" />
                                    <th className="hidden sm:table-cell">Código</th>
                                    <th>Disciplina</th>
                                    <th className="hidden text-right sm:table-cell">Ano</th>
                                    <th className="text-right">Créditos</th>
                                    <th className="hidden sm:table-cell">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                {g.linhas.map((d) => {
                                    const escolhida = data.disciplinas.includes(d.disciplina_id);
                                    const podeEscolher = d.estado === 'disponivel' && matricula.activa;
                                    const situacao = (
                                        <div className="flex items-center gap-1">
                                            <Estado cor={COR_SITUACAO[d.estado] ?? 'cinza'}>
                                                {d.estado === 'disponivel' ? 'Pode inscrever-se' : d.estado_rotulo}
                                            </Estado>
                                            {d.estado === 'inscrita' && matricula.activa && d.inscricao_id !== null && (
                                                <BotaoIcone rotulo={`Anular a inscrição em ${d.nome}`} onClick={() => anular(d)}>
                                                    <X className="size-4" />
                                                </BotaoIcone>
                                            )}
                                        </div>
                                    );
                                    return (
                                        <tr
                                            key={d.disciplina_id}
                                            className={cn(podeEscolher && 'cursor-pointer', escolhida && 'bg-primary/5')}
                                            onClick={() => podeEscolher && alternar(d.disciplina_id)}
                                        >
                                            <td onClick={(e) => e.stopPropagation()}>
                                                {d.estado === 'inscrita' ? (
                                                    <input type="checkbox" checked disabled aria-label={`${d.nome}: já inscrito`} />
                                                ) : podeEscolher ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={escolhida}
                                                        onChange={() => alternar(d.disciplina_id)}
                                                        aria-label={`Escolher ${d.nome}`}
                                                    />
                                                ) : null}
                                            </td>
                                            <td className="hidden font-mono text-[12.5px] sm:table-cell">{d.codigo}</td>
                                            <td>
                                                <span className="font-medium">{d.nome}</span>
                                                {d.anual && <span className="text-muted-foreground text-xs"> · anual</span>}
                                                {!d.obrigatoria && <span className="text-muted-foreground text-xs"> · opcional</span>}
                                                <div className="text-muted-foreground font-mono text-[11.5px] sm:hidden">
                                                    {d.codigo} · {d.ano}.º ano
                                                </div>
                                                {d.motivo && <div className="text-muted-foreground text-xs">{d.motivo}</div>}
                                                <div className="mt-1 sm:hidden" onClick={(e) => e.stopPropagation()}>
                                                    {situacao}
                                                </div>
                                            </td>
                                            <td className="hidden text-right tabular-nums sm:table-cell">{d.ano}.º</td>
                                            <td className="text-right align-top tabular-nums sm:align-middle">{formatCreditos(d.creditos)}</td>
                                            <td className="hidden sm:table-cell" onClick={(e) => e.stopPropagation()}>
                                                {situacao}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </Bloco>
                ))}

                {matricula.activa && disciplinas.some((d) => d.estado === 'disponivel') && (
                    <div className="painel bg-background/95 sticky bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 px-4 py-3 shadow-lg backdrop-blur">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-[13.5px]">
                                <span className={cn('font-semibold tabular-nums', passa && 'text-red-600 dark:text-red-400')}>
                                    {formatCreditos(total)} de {formatCreditos(limite)} créditos
                                </span>
                                {jaInscritos > 0 && <span className="text-muted-foreground"> ({formatCreditos(jaInscritos)} já inscritos)</span>}
                                {passa && <div className="text-xs text-red-600 dark:text-red-400">Passa o limite: tire alguma disciplina.</div>}
                                <InputError message={errors.disciplinas} className="mt-1" />
                            </div>
                            <div className="flex gap-2">
                                {disponiveisDoAno.length > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setData('disciplinas', Array.from(new Set([...data.disciplinas, ...disponiveisDoAno])))}
                                    >
                                        Escolher as do {termos.periodo.toLowerCase()}
                                    </Button>
                                )}
                                <Button type="submit" disabled={processing || data.disciplinas.length === 0 || passa}>
                                    Inscrever{data.disciplinas.length > 0 && ` (${data.disciplinas.length})`}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}

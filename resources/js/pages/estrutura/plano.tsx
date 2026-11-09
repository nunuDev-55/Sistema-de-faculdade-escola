import { Bloco, BotaoIcone, CabecalhoFicha, ErroFicha, Estado, LinhaForm, LinhaVazia } from '@/components/erp';
import InputError from '@/components/input-error';
import { formatCreditos, NavEstrutura } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type Opcao, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, CopyPlus, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Linha {
    id: number;
    disciplina_id: number;
    codigo: string;
    nome: string;
    creditos: number;
    ano: number;
    periodo: number;
    obrigatoria: boolean;
}

interface Props {
    plano: {
        id: number;
        versao: string;
        estado: 'rascunho' | 'aprovado' | 'substituido';
        estado_rotulo: string;
        metodo: string;
        aprovado_em: string | null;
    };
    curso: { id: number; codigo: string; nome: string; duracao_anos: number };
    linhas: Linha[];
    precedencias: { id: number; disciplina: string; precedente: string }[];
    disponiveis: Opcao[];
    periodosPorAno: number;
    tipoPeriodo: string;
    pode: { editar: boolean; aprovar: boolean; novaVersao: boolean };
    sugestaoVersao: string;
}

const COR = { rascunho: 'laranja', aprovado: 'verde', substituido: 'cinza' } as const;
const somaCreditos = (ls: Linha[]) => ls.reduce((s, l) => s + l.creditos, 0);

export default function Plano({ plano, curso, linhas, precedencias, disponiveis, periodosPorAno, tipoPeriodo, pode, sugestaoVersao }: Props) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [confirmar, setConfirmar] = useState(false);
    const nomePeriodo = (p: number) => (p === 0 ? 'Anual' : `${p}.º ${tipoPeriodo}`);
    const anos = Array.from({ length: curso.duracao_anos }, (_, i) => i + 1);
    const periodos = [...Array.from({ length: periodosPorAno }, (_, i) => i + 1), 0];

    const disciplina = useForm({ disciplina_id: '', ano: '1', periodo: '1', obrigatoria: true });
    const precedencia = useForm({ disciplina_id: '', precedente_id: '' });
    const versao = useForm({ versao: sugestaoVersao });

    const acrescentar = (e: FormEvent) => {
        e.preventDefault();
        disciplina.post(route('estrutura.planos.disciplinas.store', plano.id), {
            preserveScroll: true,
            onSuccess: () => disciplina.setData('disciplina_id', ''),
        });
    };
    const acrescentarPrecedencia = (e: FormEvent) => {
        e.preventDefault();
        precedencia.post(route('estrutura.planos.precedencias.store', plano.id), { preserveScroll: true, onSuccess: () => precedencia.reset() });
    };
    const criarVersao = (e: FormEvent) => {
        e.preventDefault();
        versao.post(route('estrutura.planos.versao', plano.id));
    };

    return (
        <AppLayout>
            <Head title={`Plano ${plano.versao} · ${curso.codigo}`} />
            <NavEstrutura />
            <CabecalhoFicha
                voltar={{ href: route('estrutura.cursos.show', curso.id), label: curso.nome }}
                referencia={`Plano ${plano.versao}`}
                titulo={`${curso.codigo} · ${curso.nome}`}
                estado={<Estado cor={COR[plano.estado]}>{plano.estado_rotulo}</Estado>}
                subtitulo={`${plano.metodo} · ${linhas.length} disciplinas · ${formatCreditos(somaCreditos(linhas))} créditos${plano.aprovado_em ? ` · aprovado em ${formatData(plano.aprovado_em)}` : ''}`}
            />
            <ErroFicha mensagem={errors.plano} />

            {plano.estado === 'rascunho' && (
                <div className="mb-5 flex flex-col gap-3 rounded-lg border bg-[var(--marca-suave)] p-4 text-[13.5px] sm:flex-row sm:items-center sm:justify-between">
                    <p>Em rascunho: acrescente as disciplinas e as precedências. Depois de aprovado, o plano não se altera (só numa versão nova).</p>
                    {pode.aprovar &&
                        (confirmar ? (
                            <div className="flex shrink-0 items-center gap-2">
                                <span>Aprovar e pôr em vigor?</span>
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            route('estrutura.planos.aprovar', plano.id),
                                            {},
                                            { preserveScroll: true, onFinish: () => setConfirmar(false) },
                                        )
                                    }
                                >
                                    Aprovar
                                </Button>
                                <Button size="sm" variant="outline" onClick={() => setConfirmar(false)}>
                                    Cancelar
                                </Button>
                            </div>
                        ) : (
                            <Button size="sm" className="shrink-0" onClick={() => setConfirmar(true)}>
                                <CheckCircle2 /> Aprovar o plano
                            </Button>
                        ))}
                </div>
            )}

            {pode.novaVersao && (
                <form onSubmit={criarVersao} className="mb-5 flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="flex flex-col gap-1">
                        <label htmlFor="versao" className="text-[13px] font-medium">
                            Mudar o plano? Crie uma versão nova a partir desta
                        </label>
                        <Input id="versao" value={versao.data.versao} onChange={(e) => versao.setData('versao', e.target.value)} className="w-32" />
                        <InputError message={versao.errors.versao} />
                    </div>
                    <Button type="submit" variant="outline" disabled={versao.processing}>
                        <CopyPlus /> Criar versão {versao.data.versao}
                    </Button>
                </form>
            )}

            <div className="flex flex-col gap-5">
                {anos.map((ano) => {
                    const doAno = linhas.filter((l) => l.ano === ano);
                    const resumo = periodos
                        .map((p) => ({ p, ls: doAno.filter((l) => l.periodo === p) }))
                        .filter((x) => x.ls.length > 0)
                        .map((x) => `${nomePeriodo(x.p)}: ${formatCreditos(somaCreditos(x.ls))} cr.`)
                        .join(' · ');
                    return (
                        <Bloco
                            key={ano}
                            titulo={`${ano}.º ano`}
                            accoes={<span className="text-muted-foreground text-[12.5px]">{resumo || 'Sem disciplinas'}</span>}
                        >
                            <table className="tabela">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th>Código</th>
                                        <th>Disciplina</th>
                                        <th className="text-right">Créditos</th>
                                        <th>Tipo</th>
                                        {pode.editar && <th className="w-10" />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {doAno.length === 0 && <LinhaVazia colunas={pode.editar ? 6 : 5} texto="Sem disciplinas neste ano." />}
                                    {doAno.map((l) => (
                                        <tr key={l.id}>
                                            <td className="whitespace-nowrap">{nomePeriodo(l.periodo)}</td>
                                            <td className="font-mono text-[12.5px]">{l.codigo}</td>
                                            <td>{l.nome}</td>
                                            <td className="text-right tabular-nums">{formatCreditos(l.creditos)}</td>
                                            <td>{l.obrigatoria ? 'Obrigatória' : 'Opcional'}</td>
                                            {pode.editar && (
                                                <td>
                                                    <BotaoIcone
                                                        rotulo={`Tirar ${l.nome} do plano`}
                                                        onClick={() =>
                                                            router.delete(route('estrutura.planos.disciplinas.destroy', [plano.id, l.id]), {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </BotaoIcone>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Bloco>
                    );
                })}

                {pode.editar && (
                    <form onSubmit={acrescentar}>
                        <Bloco titulo="Acrescentar disciplina">
                            {disponiveis.length === 0 ? (
                                <p className="text-muted-foreground px-5 py-4 text-[13.5px]">
                                    Todas as disciplinas activas já estão no plano.{' '}
                                    <Link href={route('estrutura.disciplinas.index')} className="text-foreground underline">
                                        Criar disciplinas
                                    </Link>
                                </p>
                            ) : (
                                <>
                                    <LinhaForm rotulo="Disciplina" htmlFor="disciplina_id" obrigatorio>
                                        <CaixaEscolha
                                            id="disciplina_id"
                                            value={disciplina.data.disciplina_id}
                                            onChange={(e) => disciplina.setData('disciplina_id', e.target.value)}
                                        >
                                            <option value="">Escolha…</option>
                                            {disponiveis.map((d) => (
                                                <option key={d.valor} value={d.valor}>
                                                    {d.rotulo}
                                                </option>
                                            ))}
                                        </CaixaEscolha>
                                        <InputError message={disciplina.errors.disciplina_id} className="mt-1" />
                                    </LinhaForm>
                                    <LinhaForm rotulo="Quando" htmlFor="ano">
                                        <div className="flex flex-wrap gap-2">
                                            <CaixaEscolha
                                                id="ano"
                                                value={disciplina.data.ano}
                                                onChange={(e) => disciplina.setData('ano', e.target.value)}
                                                className="w-32"
                                                aria-label="Ano"
                                            >
                                                {anos.map((a) => (
                                                    <option key={a} value={a}>
                                                        {a}.º ano
                                                    </option>
                                                ))}
                                            </CaixaEscolha>
                                            <CaixaEscolha
                                                id="periodo"
                                                value={disciplina.data.periodo}
                                                onChange={(e) => disciplina.setData('periodo', e.target.value)}
                                                className="w-40"
                                                aria-label="Período"
                                            >
                                                {periodos.map((p) => (
                                                    <option key={p} value={p}>
                                                        {nomePeriodo(p)}
                                                    </option>
                                                ))}
                                            </CaixaEscolha>
                                        </div>
                                        <InputError message={disciplina.errors.ano ?? disciplina.errors.periodo} className="mt-1" />
                                    </LinhaForm>
                                    <LinhaForm rotulo="Obrigatória" htmlFor="obrigatoria">
                                        <input
                                            id="obrigatoria"
                                            type="checkbox"
                                            checked={disciplina.data.obrigatoria}
                                            onChange={(e) => disciplina.setData('obrigatoria', e.target.checked)}
                                            className="mt-2.5"
                                        />
                                    </LinhaForm>
                                    <div className="flex justify-end px-5 py-3">
                                        <Button type="submit" disabled={disciplina.processing}>
                                            <Plus /> Acrescentar
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Bloco>
                    </form>
                )}

                <Bloco titulo="Precedências">
                    <p className="text-muted-foreground px-4 pt-3 text-[13px]">
                        O estudante só se inscreve na disciplina depois de aprovar a precedente. A precedente tem de vir num período anterior.
                    </p>
                    <table className="tabela">
                        <thead>
                            <tr>
                                <th>Disciplina</th>
                                <th>Só depois de aprovar</th>
                                {pode.editar && <th className="w-10" />}
                            </tr>
                        </thead>
                        <tbody>
                            {precedencias.length === 0 && <LinhaVazia colunas={pode.editar ? 3 : 2} texto="Sem precedências." />}
                            {precedencias.map((p) => (
                                <tr key={p.id}>
                                    <td>{p.disciplina}</td>
                                    <td>{p.precedente}</td>
                                    {pode.editar && (
                                        <td>
                                            <BotaoIcone
                                                rotulo="Tirar a precedência"
                                                onClick={() =>
                                                    router.delete(route('estrutura.planos.precedencias.destroy', [plano.id, p.id]), {
                                                        preserveScroll: true,
                                                    })
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </BotaoIcone>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {pode.editar && linhas.length > 1 && (
                        <form onSubmit={acrescentarPrecedencia} className="flex flex-wrap items-end gap-3 border-t px-4 py-3">
                            <div className="flex min-w-48 flex-1 flex-col gap-1">
                                <label htmlFor="prec-disciplina" className="text-[12.5px] font-medium">
                                    Disciplina
                                </label>
                                <CaixaEscolha
                                    id="prec-disciplina"
                                    value={precedencia.data.disciplina_id}
                                    onChange={(e) => precedencia.setData('disciplina_id', e.target.value)}
                                >
                                    <option value="">Escolha…</option>
                                    {linhas.map((l) => (
                                        <option key={l.disciplina_id} value={l.disciplina_id}>
                                            {l.codigo} · {l.nome}
                                        </option>
                                    ))}
                                </CaixaEscolha>
                            </div>
                            <div className="flex min-w-48 flex-1 flex-col gap-1">
                                <label htmlFor="prec-precedente" className="text-[12.5px] font-medium">
                                    Só depois de aprovar
                                </label>
                                <CaixaEscolha
                                    id="prec-precedente"
                                    value={precedencia.data.precedente_id}
                                    onChange={(e) => precedencia.setData('precedente_id', e.target.value)}
                                >
                                    <option value="">Escolha…</option>
                                    {linhas.map((l) => (
                                        <option key={l.disciplina_id} value={l.disciplina_id}>
                                            {l.codigo} · {l.nome}
                                        </option>
                                    ))}
                                </CaixaEscolha>
                            </div>
                            <Button type="submit" variant="outline" disabled={precedencia.processing}>
                                <Plus /> Acrescentar
                            </Button>
                            <InputError message={precedencia.errors.precedente_id ?? precedencia.errors.disciplina_id} className="w-full" />
                        </form>
                    )}
                </Bloco>
            </div>
        </AppLayout>
    );
}

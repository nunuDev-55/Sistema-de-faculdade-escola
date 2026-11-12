import { Bloco, CabecalhoFicha, Campo, Campos, ErroFicha, LinhaForm, LinhaVazia } from '@/components/erp';
import InputError from '@/components/input-error';
import { formatCreditos, NavDocencia } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { usePode } from '@/hooks/use-pode';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { type Opcao, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Shuffle, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Turma {
    id: number;
    designacao: string;
    nome: string;
    curso: string;
    ano_curricular: number;
    ano_lectivo: string;
    periodo: string | null;
    regime: string | null;
    regime_rotulo: string | null;
    capacidade: number;
    director_id: number | null;
    director: string | null;
    ocupados: number;
    sem_turma: number;
}

interface Leccionacao {
    id: number;
    codigo: string;
    disciplina: string;
    creditos: number;
    docente_id: number | null;
    docente: string | null;
    inscritos: number;
}

interface EstudanteTurma {
    id: number;
    numero: string;
    nome: string;
    inscricao_periodo_id: number;
    matricula: string;
    ano_curricular: number;
    em_atraso: boolean;
    disciplinas: number;
}

interface Props {
    turma: Turma;
    leccionacoes: Leccionacao[];
    estudantes: EstudanteTurma[];
    outrasTurmas: Opcao[];
    docentes: Opcao[];
    disciplinas: Opcao[];
    regimes: Opcao[];
}

function AlterarTurma({ turma, docentes, regimes, fechar }: { turma: Turma; docentes: Opcao[]; regimes: Opcao[]; fechar: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        nome: turma.nome,
        regime: turma.regime ?? '',
        capacidade: String(turma.capacidade),
        director_id: turma.director_id ? String(turma.director_id) : '',
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        put(route('turmas.update', turma.id), { preserveScroll: true, onSuccess: fechar });
    };
    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={`Alterar a turma ${turma.designacao}`}>
                <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio>
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} className="w-24 uppercase" />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Regime" htmlFor="regime">
                    <CaixaEscolha id="regime" value={data.regime} onChange={(e) => setData('regime', e.target.value)} className="max-w-48">
                        <option value="">Todos</option>
                        {regimes.map((r) => (
                            <option key={r.valor} value={r.valor}>
                                {r.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.regime} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Lotação" htmlFor="capacidade" obrigatorio>
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
                <LinhaForm rotulo="Director de turma" htmlFor="director_id">
                    <CaixaEscolha
                        id="director_id"
                        value={data.director_id}
                        onChange={(e) => setData('director_id', e.target.value)}
                        className="max-w-80"
                    >
                        <option value="">—</option>
                        {docentes.map((d) => (
                            <option key={d.valor} value={d.valor}>
                                {d.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Guardar
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function TurmaFicha({ turma, leccionacoes, estudantes, outrasTurmas, docentes, disciplinas, regimes }: Props) {
    const pode = usePode();
    const termos = useTermos();
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [alterar, setAlterar] = useState(false);
    const [disciplina, setDisciplina] = useState('');
    const doAno = estudantes.filter((e) => !e.em_atraso);
    const emAtraso = estudantes.filter((e) => e.em_atraso);

    const atribuir = (l: Leccionacao, docente: string) =>
        router.put(route('leccionacoes.docente', l.id), { docente_id: docente || null }, { preserveScroll: true });
    const distribuir = () => router.post(route('turmas.distribuir', turma.id), {}, { preserveScroll: true });
    const mudar = (e: EstudanteTurma, destino: string) => {
        if (!destino) return;
        router.post(
            route('turmas.estudantes.mudar', turma.id),
            { inscricao_periodo_id: e.inscricao_periodo_id, turma_id: destino },
            { preserveScroll: true },
        );
    };
    const acrescentar = () => {
        if (!disciplina) return;
        router.post(
            route('turmas.disciplinas.store', turma.id),
            { disciplina_id: disciplina },
            { preserveScroll: true, onSuccess: () => setDisciplina('') },
        );
    };
    const apagar = () => {
        if (!confirm(`Apagar a turma ${turma.designacao}?`)) return;
        router.delete(route('turmas.destroy', turma.id));
    };

    return (
        <AppLayout>
            <Head title={`Turma ${turma.designacao}`} />
            <NavDocencia />
            <CabecalhoFicha
                voltar={{ href: route('turmas.index'), label: 'Turmas' }}
                referencia={`Turma ${turma.designacao}`}
                titulo={turma.curso}
                subtitulo={[turma.periodo, `Ano lectivo ${turma.ano_lectivo}`, turma.regime_rotulo].filter(Boolean).join(' · ')}
            />
            <ErroFicha mensagem={errors.turma ?? errors.turma_id ?? errors.docente_id ?? errors.disciplina_id} />

            {alterar ? (
                <AlterarTurma turma={turma} docentes={docentes} regimes={regimes} fechar={() => setAlterar(false)} />
            ) : (
                <div className="mb-5 flex flex-col gap-3">
                    <Campos>
                        <Campo rotulo="Estudantes">
                            {turma.ocupados} de {turma.capacidade}
                        </Campo>
                        <Campo rotulo="Director de turma">{turma.director}</Campo>
                        <Campo rotulo="Regime">{turma.regime_rotulo ?? 'Todos'}</Campo>
                        <Campo rotulo="Disciplinas">{leccionacoes.length}</Campo>
                    </Campos>
                    {pode('turmas.gerir') && (
                        <div className="flex flex-wrap justify-end gap-2">
                            {estudantes.length === 0 && (
                                <Button size="sm" variant="ghost" onClick={apagar} className="text-red-700 dark:text-red-400">
                                    <Trash2 /> Apagar a turma
                                </Button>
                            )}
                            <Button size="sm" variant="outline" onClick={() => setAlterar(true)}>
                                <Pencil /> Alterar a turma
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <div className="flex flex-col gap-5">
                <Bloco titulo="Disciplinas e docentes">
                    <table className="tabela">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>{termos.disciplina}</th>
                                <th className="text-right">Créditos</th>
                                <th>Docente</th>
                                <th className="text-right">Estudantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leccionacoes.length === 0 && <LinhaVazia colunas={5} texto="A turma ainda não tem disciplinas." />}
                            {leccionacoes.map((l) => (
                                <tr key={l.id}>
                                    <td className="font-mono text-[12.5px]">{l.codigo}</td>
                                    <td className="font-medium">{l.disciplina}</td>
                                    <td className="text-right tabular-nums">{formatCreditos(l.creditos)}</td>
                                    <td>
                                        {pode('distribuicao.gerir') ? (
                                            <CaixaEscolha
                                                value={l.docente_id ? String(l.docente_id) : ''}
                                                onChange={(e) => atribuir(l, e.target.value)}
                                                className="min-w-48"
                                                aria-label={`Docente de ${l.disciplina}`}
                                            >
                                                <option value="">— por atribuir —</option>
                                                {docentes.map((d) => (
                                                    <option key={d.valor} value={d.valor}>
                                                        {d.rotulo}
                                                    </option>
                                                ))}
                                            </CaixaEscolha>
                                        ) : (
                                            (l.docente ?? <span className="text-amber-700 dark:text-amber-400">Por atribuir</span>)
                                        )}
                                    </td>
                                    <td className="text-right tabular-nums">{l.inscritos}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {pode('distribuicao.gerir') && disciplinas.length > 0 && (
                        <div className="flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center">
                            <CaixaEscolha
                                value={disciplina}
                                onChange={(e) => setDisciplina(e.target.value)}
                                className="sm:max-w-96"
                                aria-label="Disciplina a acrescentar"
                            >
                                <option value="">Acrescentar outra disciplina (por exemplo, uma em atraso)…</option>
                                {disciplinas.map((d) => (
                                    <option key={d.valor} value={d.valor}>
                                        {d.rotulo}
                                    </option>
                                ))}
                            </CaixaEscolha>
                            <Button size="sm" variant="outline" onClick={acrescentar} disabled={!disciplina}>
                                Acrescentar
                            </Button>
                        </div>
                    )}
                </Bloco>

                <Bloco
                    titulo={`Estudantes (${doAno.length} de ${turma.capacidade})`}
                    accoes={
                        pode('turmas.gerir') &&
                        turma.sem_turma > 0 && (
                            <Button size="sm" onClick={distribuir}>
                                <Shuffle /> Distribuir {turma.sem_turma} sem turma
                            </Button>
                        )
                    }
                >
                    {turma.sem_turma > 0 && (
                        <p className="text-muted-foreground border-b px-4 py-2.5 text-[13px]">
                            {turma.sem_turma === 1 ? 'Há 1 estudante inscrito' : `Há ${turma.sem_turma} estudantes inscritos`} no{' '}
                            {turma.ano_curricular}.º ano deste curso ainda sem turma. A distribuição segue a ordem alfabética e a lotação de cada
                            turma; quem já tem turma não muda.
                        </p>
                    )}
                    <table className="tabela">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Nome</th>
                                <th>Matrícula</th>
                                <th className="text-right">Disciplinas</th>
                                {pode('turmas.gerir') && outrasTurmas.length > 0 && <th>Mudar para</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {doAno.length === 0 && <LinhaVazia colunas={5} texto="Ainda não há estudantes nesta turma." />}
                            {doAno.map((e) => (
                                <tr key={e.id}>
                                    <td className="font-mono text-[12.5px]">{e.numero}</td>
                                    <td>
                                        <Link href={route('estudantes.show', e.id)} className="font-medium hover:underline">
                                            {e.nome}
                                        </Link>
                                    </td>
                                    <td className="font-mono text-[12.5px]">{e.matricula}</td>
                                    <td className="text-right tabular-nums">{e.disciplinas}</td>
                                    {pode('turmas.gerir') && outrasTurmas.length > 0 && (
                                        <td>
                                            <CaixaEscolha
                                                value=""
                                                onChange={(ev) => mudar(e, ev.target.value)}
                                                className="min-w-40"
                                                aria-label={`Mudar ${e.nome} de turma`}
                                            >
                                                <option value="">—</option>
                                                {outrasTurmas.map((t) => (
                                                    <option key={t.valor} value={t.valor}>
                                                        {t.rotulo}
                                                    </option>
                                                ))}
                                            </CaixaEscolha>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Bloco>

                {emAtraso.length > 0 && (
                    <Bloco titulo={`De outros anos, em disciplinas em atraso (${emAtraso.length})`}>
                        <table className="tabela">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Nome</th>
                                    <th className="text-right">Ano</th>
                                    <th className="text-right">Disciplinas nesta turma</th>
                                </tr>
                            </thead>
                            <tbody>
                                {emAtraso.map((e) => (
                                    <tr key={e.id}>
                                        <td className="font-mono text-[12.5px]">{e.numero}</td>
                                        <td>
                                            <Link href={route('estudantes.show', e.id)} className="hover:underline">
                                                {e.nome}
                                            </Link>
                                        </td>
                                        <td className="text-right tabular-nums">{e.ano_curricular}.º</td>
                                        <td className="text-right tabular-nums">{e.disciplinas}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Bloco>
                )}
            </div>
        </AppLayout>
    );
}

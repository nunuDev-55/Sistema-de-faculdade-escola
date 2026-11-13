import { Bloco, Estado, LinhaForm, LinhaVazia, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavDocencia } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import { useTermos } from '@/hooks/use-termos';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type Opcao } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Turma {
    id: number;
    designacao: string;
    curso: string;
    ano_curricular: number;
    nome: string;
    regime: string | null;
    capacidade: number;
    estudantes: number;
    disciplinas: number;
    por_atribuir: number;
    director: string | null;
}

interface Props {
    turmas: Turma[];
    filtros: { ano: string; periodo: string; curso: string };
    anos: (Opcao & { periodos: Opcao[] })[];
    cursos: (Opcao & { duracao: number })[];
    regimes: Opcao[];
    superior: boolean;
}

function NovaTurma({ anos, cursos, regimes, superior, filtros, fechar }: Omit<Props, 'turmas'> & { fechar: () => void }) {
    const termos = useTermos();
    const { data, setData, post, processing, errors } = useForm({
        ano_lectivo_id: filtros.ano,
        periodo_id: filtros.periodo,
        curso_id: filtros.curso,
        ano_curricular: '1',
        nome: 'A',
        regime: '',
        capacidade: '40',
    });
    const ano = anos.find((a) => a.valor === data.ano_lectivo_id);
    const curso = cursos.find((c) => c.valor === data.curso_id);
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('turmas.store'));
    };

    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo="Nova turma">
                <LinhaForm rotulo="Ano lectivo" htmlFor="ano_lectivo_id" obrigatorio>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <CaixaEscolha
                            id="ano_lectivo_id"
                            value={data.ano_lectivo_id}
                            onChange={(e) => {
                                setData('ano_lectivo_id', e.target.value);
                                setData('periodo_id', anos.find((a) => a.valor === e.target.value)?.periodos[0]?.valor ?? '');
                            }}
                            className="sm:max-w-36"
                        >
                            {anos.map((a) => (
                                <option key={a.valor} value={a.valor}>
                                    {a.rotulo}
                                </option>
                            ))}
                        </CaixaEscolha>
                        {superior && (
                            <CaixaEscolha
                                id="periodo_id"
                                value={data.periodo_id}
                                onChange={(e) => setData('periodo_id', e.target.value)}
                                className="sm:max-w-44"
                                aria-label={termos.periodo}
                            >
                                {(ano?.periodos ?? []).map((p) => (
                                    <option key={p.valor} value={p.valor}>
                                        {p.rotulo}
                                    </option>
                                ))}
                            </CaixaEscolha>
                        )}
                    </div>
                    <InputError message={errors.ano_lectivo_id ?? errors.periodo_id} className="mt-1" />
                </LinhaForm>
                <LinhaForm
                    rotulo={termos.curso}
                    htmlFor="curso_id"
                    obrigatorio
                    ajuda="A turma nasce com as disciplinas do plano curricular para esse ano e período."
                >
                    <CaixaEscolha id="curso_id" value={data.curso_id} onChange={(e) => setData('curso_id', e.target.value)} className="max-w-96">
                        <option value="">—</option>
                        {cursos.map((c) => (
                            <option key={c.valor} value={c.valor}>
                                {c.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.curso_id} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo={`${termos.ano_curricular} e nome da turma`} htmlFor="ano_curricular" obrigatorio>
                    <div className="flex gap-2">
                        <CaixaEscolha
                            id="ano_curricular"
                            value={data.ano_curricular}
                            onChange={(e) => setData('ano_curricular', e.target.value)}
                            className="w-24"
                        >
                            {Array.from({ length: curso?.duracao ?? 1 }, (_, i) => String(i + 1)).map((a) => (
                                <option key={a} value={a}>
                                    {a}.º
                                </option>
                            ))}
                        </CaixaEscolha>
                        <Input
                            id="nome"
                            value={data.nome}
                            onChange={(e) => setData('nome', e.target.value)}
                            className="w-24 uppercase"
                            aria-label="Nome da turma"
                            placeholder="A"
                        />
                    </div>
                    <InputError message={errors.ano_curricular ?? errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Regime" htmlFor="regime" ajuda="Se escolher, só os estudantes desse regime entram nesta turma.">
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
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Criar turma
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Turmas(props: Props) {
    const { turmas, anos, cursos, superior } = props;
    const pode = usePode();
    const termos = useTermos();
    const [nova, setNova] = useState(false);
    const { filtros, mudar } = useFiltros(route('turmas.index'), props.filtros);
    const ano = anos.find((a) => a.valor === filtros.ano);
    const total = turmas.reduce((s, t) => s + t.estudantes, 0);

    return (
        <AppLayout>
            <Head title="Turmas" />
            <TituloPagina
                titulo="Docência"
                descricao="Turmas, distribuição de serviço e docentes."
                accoes={
                    pode('turmas.gerir') &&
                    !nova &&
                    anos.length > 0 && (
                        <Button size="sm" onClick={() => setNova(true)}>
                            <Plus /> Nova turma
                        </Button>
                    )
                }
            />
            <NavDocencia />
            {nova && <NovaTurma {...props} filtros={filtros} fechar={() => setNova(false)} />}

            {anos.length === 0 ? (
                <div className="painel text-muted-foreground px-5 py-10 text-center text-[13.5px]">
                    Ainda não há anos lectivos. Crie o primeiro em{' '}
                    <Link href={route('estrutura.calendario.index')} className="text-foreground underline">
                        Estrutura → Calendário
                    </Link>
                    .
                </div>
            ) : (
                <>
                    <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <CaixaEscolha
                            value={filtros.ano}
                            onChange={(e) => {
                                mudar('ano', e.target.value);
                                mudar('periodo', '');
                            }}
                            className="sm:max-w-36"
                            aria-label="Ano lectivo"
                        >
                            {anos.map((a) => (
                                <option key={a.valor} value={a.valor}>
                                    {a.rotulo}
                                </option>
                            ))}
                        </CaixaEscolha>
                        {superior && (
                            <CaixaEscolha
                                value={filtros.periodo}
                                onChange={(e) => mudar('periodo', e.target.value)}
                                className="sm:max-w-44"
                                aria-label={termos.periodo}
                            >
                                {(ano?.periodos ?? []).map((p) => (
                                    <option key={p.valor} value={p.valor}>
                                        {p.rotulo}
                                    </option>
                                ))}
                            </CaixaEscolha>
                        )}
                        <CaixaEscolha
                            value={filtros.curso}
                            onChange={(e) => mudar('curso', e.target.value)}
                            className="sm:max-w-72"
                            aria-label={termos.curso}
                        >
                            <option value="">Todos os {termos.curso === 'Classe' ? 'níveis' : 'cursos'}</option>
                            {cursos.map((c) => (
                                <option key={c.valor} value={c.valor}>
                                    {c.rotulo}
                                </option>
                            ))}
                        </CaixaEscolha>
                    </div>

                    <Bloco titulo={`${turmas.length} turmas · ${total.toLocaleString('pt-PT')} estudantes`}>
                        <table className="tabela">
                            <thead>
                                <tr>
                                    <th>Turma</th>
                                    <th>{termos.curso}</th>
                                    <th>Regime</th>
                                    <th className="text-right">Estudantes</th>
                                    <th>Disciplinas</th>
                                    <th>Director de turma</th>
                                </tr>
                            </thead>
                            <tbody>
                                {turmas.length === 0 && <LinhaVazia colunas={6} texto="Ainda não há turmas neste período." />}
                                {turmas.map((t) => (
                                    <tr key={t.id}>
                                        <td>
                                            <Link href={route('turmas.show', t.id)} className="font-medium hover:underline">
                                                {t.designacao}
                                            </Link>
                                        </td>
                                        <td>{t.curso}</td>
                                        <td>{t.regime ?? 'Todos'}</td>
                                        <td
                                            className={cn(
                                                'text-right tabular-nums',
                                                t.estudantes >= t.capacidade && 'text-amber-700 dark:text-amber-400',
                                            )}
                                        >
                                            {t.estudantes} de {t.capacidade}
                                        </td>
                                        <td>
                                            {t.por_atribuir > 0 ? (
                                                <Estado cor="laranja">
                                                    {t.disciplinas} · {t.por_atribuir} sem docente
                                                </Estado>
                                            ) : (
                                                <Estado cor="verde">{t.disciplinas} com docente</Estado>
                                            )}
                                        </td>
                                        <td>{t.director ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Bloco>
                </>
            )}
        </AppLayout>
    );
}

import { Bloco, BotaoIcone, Estado, LinhaForm, LinhaVazia, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavDocencia } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Docente {
    id: number;
    nome: string;
    numero: string | null;
    grau_academico: string | null;
    grau_rotulo: string | null;
    categoria: string | null;
    regime: string;
    regime_rotulo: string;
    unidade_id: number | null;
    unidade: string | null;
    telemovel: string | null;
    email: string | null;
    user_id: number | null;
    conta: string | null;
    activo: boolean;
    disciplinas: number;
}

interface Props {
    docentes: Docente[];
    q: string;
    graus: Opcao[];
    regimes: Opcao[];
    unidades: Opcao[];
    contas: (Opcao & { livre: boolean })[];
}

function Escolha({
    id,
    valor,
    mudar,
    opcoes,
    vazio = '—',
    className,
}: {
    id: string;
    valor: string;
    mudar: (v: string) => void;
    opcoes: Opcao[];
    vazio?: string | null;
    className?: string;
}) {
    return (
        <CaixaEscolha id={id} value={valor} onChange={(e) => mudar(e.target.value)} className={className}>
            {vazio !== null && <option value="">{vazio}</option>}
            {opcoes.map((o) => (
                <option key={o.valor} value={o.valor}>
                    {o.rotulo}
                </option>
            ))}
        </CaixaEscolha>
    );
}

function Formulario({
    docente,
    graus,
    regimes,
    unidades,
    contas,
    fechar,
}: Omit<Props, 'docentes' | 'q'> & { docente?: Docente; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        nome: docente?.nome ?? '',
        numero: docente?.numero ?? '',
        grau_academico: docente?.grau_academico ?? '',
        categoria: docente?.categoria ?? '',
        regime: docente?.regime ?? 'integral',
        unidade_id: docente?.unidade_id ? String(docente.unidade_id) : '',
        telemovel: docente?.telemovel ?? '',
        email: docente?.email ?? '',
        user_id: docente?.user_id ? String(docente.user_id) : '',
        activo: docente?.activo ?? true,
    });
    // A conta do próprio docente e as que ainda não estão ligadas a ninguém
    const contasLivres = contas.filter((c) => c.livre || c.valor === String(docente?.user_id ?? ''));
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (docente) put(route('docentes.update', docente.id), opcoes);
        else post(route('docentes.store'), opcoes);
    };

    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={docente ? `Alterar ${docente.nome}` : 'Novo docente'}>
                <LinhaForm rotulo="Nome" htmlFor="nome" obrigatorio>
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Número de funcionário" htmlFor="numero">
                    <Input id="numero" value={data.numero} onChange={(e) => setData('numero', e.target.value)} className="max-w-40" />
                    <InputError message={errors.numero} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Grau académico" htmlFor="grau_academico">
                    <Escolha
                        id="grau_academico"
                        valor={data.grau_academico}
                        mudar={(v) => setData('grau_academico', v)}
                        opcoes={graus}
                        className="max-w-48"
                    />
                </LinhaForm>
                <LinhaForm rotulo="Categoria" htmlFor="categoria" ajuda="Assistente, auxiliar, associado, catedrático…">
                    <Input id="categoria" value={data.categoria} onChange={(e) => setData('categoria', e.target.value)} className="max-w-64" />
                </LinhaForm>
                <LinhaForm rotulo="Regime" htmlFor="regime" obrigatorio>
                    <Escolha id="regime" valor={data.regime} mudar={(v) => setData('regime', v)} opcoes={regimes} vazio={null} className="max-w-48" />
                </LinhaForm>
                <LinhaForm rotulo="Unidade orgânica" htmlFor="unidade_id">
                    <Escolha id="unidade_id" valor={data.unidade_id} mudar={(v) => setData('unidade_id', v)} opcoes={unidades} className="max-w-96" />
                </LinhaForm>
                <LinhaForm rotulo="Contactos" htmlFor="telemovel">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="telemovel"
                            type="tel"
                            value={data.telemovel}
                            onChange={(e) => setData('telemovel', e.target.value)}
                            placeholder="Telemóvel"
                            className="sm:max-w-48"
                        />
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="E-mail"
                            aria-label="E-mail"
                            className="sm:max-w-80"
                        />
                    </div>
                    <InputError message={errors.telemovel ?? errors.email} className="mt-1" />
                </LinhaForm>
                <LinhaForm
                    rotulo="Conta de acesso"
                    htmlFor="user_id"
                    ajuda="A conta com que entra no sistema (perfil Docente). Sem ela, o docente não vê as suas turmas nem lança notas."
                >
                    <Escolha
                        id="user_id"
                        valor={data.user_id}
                        mudar={(v) => setData('user_id', v)}
                        opcoes={contasLivres}
                        vazio="— (sem conta)"
                        className="max-w-96"
                    />
                    <InputError message={errors.user_id} className="mt-1" />
                </LinhaForm>
                {docente && (
                    <LinhaForm rotulo="Activo" htmlFor="activo" ajuda="Um docente inactivo não recebe disciplinas novas.">
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
                        {docente ? 'Guardar' : 'Registar docente'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Docentes(props: Props) {
    const { docentes, q } = props;
    const pode = usePode();
    const [formulario, setFormulario] = useState<Docente | 'novo' | null>(null);
    const { filtros, mudar } = useFiltros(route('docentes.index'), { q });

    return (
        <AppLayout>
            <Head title="Docentes" />
            <TituloPagina
                titulo="Docência"
                descricao="Turmas, distribuição de serviço e docentes."
                accoes={
                    pode('docentes.gerir') &&
                    formulario === null && (
                        <Button size="sm" onClick={() => setFormulario('novo')}>
                            <Plus /> Novo docente
                        </Button>
                    )
                }
            />
            <NavDocencia />
            {formulario !== null && (
                <Formulario
                    key={formulario === 'novo' ? 'novo' : formulario.id}
                    docente={formulario === 'novo' ? undefined : formulario}
                    graus={props.graus}
                    regimes={props.regimes}
                    unidades={props.unidades}
                    contas={props.contas}
                    fechar={() => setFormulario(null)}
                />
            )}

            <div className="relative mb-3 max-w-sm">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                <Input
                    value={filtros.q}
                    onChange={(e) => mudar('q', e.target.value)}
                    placeholder="Nome, número ou e-mail"
                    className="pl-8"
                    aria-label="Pesquisar docente"
                />
            </div>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Grau · categoria</th>
                            <th>Regime</th>
                            <th>Unidade</th>
                            <th className="text-right">Disciplinas</th>
                            <th>Conta</th>
                            {pode('docentes.gerir') && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {docentes.length === 0 && (
                            <LinhaVazia colunas={7} texto={q ? 'Nenhum docente com essa pesquisa.' : 'Ainda não há docentes.'} />
                        )}
                        {docentes.map((d) => (
                            <tr key={d.id} className={d.activo ? undefined : 'opacity-60'}>
                                <td>
                                    <span className="font-medium">{d.nome}</span>
                                    {!d.activo && <span className="text-muted-foreground text-xs"> · inactivo</span>}
                                    <div className="text-muted-foreground text-[12.5px]">
                                        {[d.numero, d.telemovel].filter(Boolean).join(' · ') || '—'}
                                    </div>
                                </td>
                                <td>{[d.grau_rotulo, d.categoria].filter(Boolean).join(' · ') || '—'}</td>
                                <td>{d.regime_rotulo}</td>
                                <td>{d.unidade ?? '—'}</td>
                                <td className="text-right tabular-nums">{d.disciplinas}</td>
                                <td>{d.conta ? <Estado cor="verde">{d.conta}</Estado> : <Estado cor="laranja">Sem conta</Estado>}</td>
                                {pode('docentes.gerir') && (
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

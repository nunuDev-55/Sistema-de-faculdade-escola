import { Bloco, BotaoIcone, ErroFicha, Estado, LinhaForm, LinhaVazia, Paginacao, TituloPagina, type Pagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { formatRelativo } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Power, Search, UserPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Utilizador {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    active: boolean;
    perfil: string | null;
    last_login_at: string | null;
}

interface Props {
    utilizadores: Pagina<Utilizador>;
    filtros: { pesquisa: string; perfil: string; estado: string };
    perfis: string[];
    resumo: { total: number; activos: number };
}

function Formulario({ utilizador, perfis, fechar }: { utilizador?: Utilizador; perfis: string[]; fechar: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: utilizador?.name ?? '',
        email: utilizador?.email ?? '',
        phone: utilizador?.phone ?? '',
        perfil: utilizador?.perfil ?? '',
        password: '',
        password_confirmation: '',
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: fechar };
        if (utilizador) put(route('utilizadores.update', utilizador.id), opcoes);
        else post(route('utilizadores.store'), opcoes);
    };

    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo={utilizador ? `Alterar a conta de ${utilizador.name}` : 'Nova conta'}>
                <LinhaForm rotulo="Nome" htmlFor="name" obrigatorio>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="off" />
                    <InputError message={errors.name} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="E-mail" htmlFor="email" obrigatorio ajuda="É com ele que a pessoa entra no sistema.">
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="max-w-96"
                        autoComplete="off"
                    />
                    <InputError message={errors.email} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Telemóvel" htmlFor="phone">
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        className="max-w-56"
                    />
                    <InputError message={errors.phone} className="mt-1" />
                </LinhaForm>
                <LinhaForm rotulo="Perfil" htmlFor="perfil" obrigatorio ajuda="O perfil decide o que a pessoa vê e pode fazer (Perfis e permissões).">
                    <CaixaEscolha id="perfil" value={data.perfil} onChange={(e) => setData('perfil', e.target.value)} className="max-w-72">
                        <option value="">—</option>
                        {perfis.map((p) => (
                            <option key={p} value={p}>
                                {p}
                            </option>
                        ))}
                    </CaixaEscolha>
                    <InputError message={errors.perfil} className="mt-1" />
                </LinhaForm>
                <LinhaForm
                    rotulo="Senha"
                    htmlFor="password"
                    obrigatorio={!utilizador}
                    ajuda={utilizador ? 'Deixe em branco para manter a senha actual.' : 'Pelo menos 8 caracteres. A pessoa pode mudá-la depois.'}
                >
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="sm:max-w-56"
                            autoComplete="new-password"
                        />
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="sm:max-w-56"
                            placeholder="Repita a senha"
                            aria-label="Repita a senha"
                            autoComplete="new-password"
                        />
                    </div>
                    <InputError message={errors.password} className="mt-1" />
                </LinhaForm>
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {utilizador ? 'Guardar' : 'Criar conta'}
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Utilizadores({ utilizadores, filtros: iniciais, perfis, resumo }: Props) {
    const pode = usePode();
    const { auth, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [formulario, setFormulario] = useState<Utilizador | 'nova' | null>(null);
    const { filtros, mudar } = useFiltros(route('utilizadores.index'), iniciais);
    const filtrado = filtros.pesquisa !== '' || filtros.perfil !== '' || filtros.estado !== '';

    const alternar = (u: Utilizador) => {
        const pergunta = u.active
            ? `Desactivar a conta de ${u.name}? A pessoa deixa de entrar e as sessões abertas terminam.`
            : `Activar outra vez a conta de ${u.name}?`;
        if (!confirm(pergunta)) return;
        router.post(route('utilizadores.estado', u.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Utilizadores" />
            <TituloPagina
                titulo="Utilizadores"
                contagem={resumo.total}
                descricao={`Contas da equipa: quem entra no sistema e com que perfil. ${resumo.activos} activas.`}
                accoes={
                    pode('utilizadores.gerir') &&
                    formulario === null && (
                        <Button size="sm" onClick={() => setFormulario('nova')}>
                            <UserPlus /> Nova conta
                        </Button>
                    )
                }
            />
            <ErroFicha mensagem={errors.estado} />
            {formulario !== null && (
                <Formulario
                    key={formulario === 'nova' ? 'nova' : formulario.id}
                    utilizador={formulario === 'nova' ? undefined : formulario}
                    perfis={perfis}
                    fechar={() => setFormulario(null)}
                />
            )}

            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:max-w-sm">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input
                        value={filtros.pesquisa}
                        onChange={(e) => mudar('pesquisa', e.target.value)}
                        placeholder="Nome, e-mail ou telemóvel"
                        className="pl-8"
                        aria-label="Pesquisar utilizador"
                    />
                </div>
                <CaixaEscolha value={filtros.perfil} onChange={(e) => mudar('perfil', e.target.value)} className="sm:max-w-56" aria-label="Perfil">
                    <option value="">Todos os perfis</option>
                    {perfis.map((p) => (
                        <option key={p} value={p}>
                            {p}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.estado} onChange={(e) => mudar('estado', e.target.value)} className="sm:max-w-40" aria-label="Estado">
                    <option value="">Activas e inactivas</option>
                    <option value="activos">Activas</option>
                    <option value="inactivos">Inactivas</option>
                </CaixaEscolha>
            </div>

            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telemóvel</th>
                            <th>Perfil</th>
                            <th>Última entrada</th>
                            <th>Estado</th>
                            {(pode('utilizadores.gerir') || pode('utilizadores.desactivar')) && <th className="w-20" />}
                        </tr>
                    </thead>
                    <tbody>
                        {utilizadores.data.length === 0 && (
                            <LinhaVazia colunas={6} texto={filtrado ? 'Nenhuma conta com estes filtros.' : 'Ainda não há contas.'} />
                        )}
                        {utilizadores.data.map((u) => {
                            const eu = u.id === auth.user.id;
                            return (
                                <tr key={u.id}>
                                    <td>
                                        <span className="font-medium">{u.name}</span>
                                        {eu && <span className="text-muted-foreground text-xs"> · a sua conta</span>}
                                        <div className="text-muted-foreground text-[12.5px]">{u.email}</div>
                                    </td>
                                    <td className="tabular-nums">{u.phone ?? '—'}</td>
                                    <td>{u.perfil ?? '—'}</td>
                                    <td>
                                        {u.last_login_at ? (
                                            formatRelativo(u.last_login_at)
                                        ) : (
                                            <span className="text-muted-foreground">Nunca entrou</span>
                                        )}
                                    </td>
                                    <td>{u.active ? <Estado cor="verde">Activa</Estado> : <Estado cor="cinza">Inactiva</Estado>}</td>
                                    {(pode('utilizadores.gerir') || pode('utilizadores.desactivar')) && (
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                {pode('utilizadores.gerir') && (
                                                    <BotaoIcone rotulo={`Alterar a conta de ${u.name}`} onClick={() => setFormulario(u)}>
                                                        <Pencil className="size-4" />
                                                    </BotaoIcone>
                                                )}
                                                {pode('utilizadores.desactivar') && !eu && (
                                                    <BotaoIcone
                                                        rotulo={u.active ? `Desactivar ${u.name}` : `Activar ${u.name}`}
                                                        onClick={() => alternar(u)}
                                                        className={u.active ? 'hover:text-red-600' : 'hover:text-green-600'}
                                                    >
                                                        <Power className="size-4" />
                                                    </BotaoIcone>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <Paginacao pagina={utilizadores} />
            </Bloco>
        </AppLayout>
    );
}

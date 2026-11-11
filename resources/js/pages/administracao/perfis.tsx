import { Bloco, Estado, LinhaForm, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Perfil {
    id: number;
    nome: string;
    utilizadores: number;
    permissoes: string[];
    sistema: boolean;
    acessoTotal: boolean;
}

interface Modulo {
    chave: string;
    label: string;
    permissoes: { nome: string; label: string }[];
}

interface Props {
    perfis: Perfil[];
    catalogo: Modulo[];
}

/** A matriz de um perfil: um bloco por módulo, uma caixa por permissão. */
function Matriz({ perfil, catalogo }: { perfil: Perfil; catalogo: Modulo[] }) {
    const { data, setData, put, processing, errors, isDirty, reset } = useForm({ permissoes: perfil.permissoes });
    const erros = errors as Record<string, string | undefined>;
    const marcadas = new Set(data.permissoes);
    const total = catalogo.reduce((s, m) => s + m.permissoes.length, 0);

    const alternar = (nome: string) =>
        setData('permissoes', marcadas.has(nome) ? data.permissoes.filter((p) => p !== nome) : [...data.permissoes, nome]);
    const alternarModulo = (m: Modulo) => {
        const todas = m.permissoes.every((p) => marcadas.has(p.nome));
        const nomes = m.permissoes.map((p) => p.nome);
        setData('permissoes', todas ? data.permissoes.filter((p) => !nomes.includes(p)) : Array.from(new Set([...data.permissoes, ...nomes])));
    };
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        put(route('perfis.update', perfil.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={guardar} className="flex flex-col gap-4">
            {perfil.acessoTotal && (
                <p className="painel text-muted-foreground px-4 py-3 text-[13.5px]">
                    O Administrador tem sempre acesso a tudo, incluindo o que for acrescentado ao sistema no futuro. As permissões não se alteram.
                </p>
            )}
            {catalogo.map((m) => {
                const quantas = m.permissoes.filter((p) => perfil.acessoTotal || marcadas.has(p.nome)).length;
                return (
                    <Bloco
                        key={m.chave}
                        titulo={
                            <span>
                                {m.label}
                                <span className="text-muted-foreground ml-2 text-[12.5px] font-normal tabular-nums">
                                    {quantas} de {m.permissoes.length}
                                </span>
                            </span>
                        }
                        accoes={
                            !perfil.acessoTotal && (
                                <Button type="button" size="sm" variant="ghost" onClick={() => alternarModulo(m)}>
                                    {m.permissoes.every((p) => marcadas.has(p.nome)) ? 'Tirar todas' : 'Dar todas'}
                                </Button>
                            )
                        }
                    >
                        <ul className="divide-y divide-[var(--linha-suave)]">
                            {m.permissoes.map((p) => (
                                <li key={p.nome}>
                                    <label
                                        className={cn(
                                            'flex items-start gap-3 px-4 py-2.5 text-[13.5px]',
                                            !perfil.acessoTotal && 'hover:bg-muted/40 cursor-pointer',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            className="mt-0.5"
                                            checked={perfil.acessoTotal || marcadas.has(p.nome)}
                                            disabled={perfil.acessoTotal}
                                            onChange={() => alternar(p.nome)}
                                        />
                                        <span className="min-w-0 flex-1">
                                            {p.label}
                                            <span className="text-muted-foreground block font-mono text-[11.5px]">{p.nome}</span>
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </Bloco>
                );
            })}
            {!perfil.acessoTotal && (
                <div className="painel bg-background/95 sticky bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 flex flex-col gap-2 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                    <div className="text-[13.5px]">
                        <span className="font-semibold tabular-nums">
                            {data.permissoes.length} de {total} permissões
                        </span>
                        {isDirty && <span className="text-muted-foreground"> · alterações por guardar</span>}
                        <InputError
                            message={erros.permissoes ?? Object.entries(erros).find(([k]) => k.startsWith('permissoes.'))?.[1]}
                            className="mt-1"
                        />
                    </div>
                    <div className="flex gap-2">
                        {isDirty && (
                            <Button type="button" variant="outline" onClick={() => reset()}>
                                Desfazer
                            </Button>
                        )}
                        <Button type="submit" disabled={processing || !isDirty}>
                            Guardar permissões
                        </Button>
                    </div>
                </div>
            )}
        </form>
    );
}

function NovoPerfil({ fechar }: { fechar: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ nome: '' });
    const criar = (e: FormEvent) => {
        e.preventDefault();
        post(route('perfis.store'), { preserveScroll: true, onSuccess: fechar });
    };
    return (
        <form onSubmit={criar} className="mb-5">
            <Bloco titulo="Novo perfil">
                <LinhaForm rotulo="Nome do perfil" htmlFor="nome" obrigatorio ajuda="Por exemplo: Coordenador pedagógico, Biblioteca, Recepção.">
                    <Input id="nome" value={data.nome} onChange={(e) => setData('nome', e.target.value)} className="max-w-80" />
                    <InputError message={errors.nome} className="mt-1" />
                </LinhaForm>
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Criar perfil
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Perfis({ perfis, catalogo }: Props) {
    const [escolhido, setEscolhido] = useState<number>(perfis.find((p) => !p.acessoTotal)?.id ?? perfis[0]?.id ?? 0);
    const [novo, setNovo] = useState(false);
    const perfil = perfis.find((p) => p.id === escolhido) ?? perfis[0];

    const apagar = (p: Perfil) => {
        if (!confirm(`Apagar o perfil ${p.nome}?`)) return;
        router.delete(route('perfis.destroy', p.id), { preserveScroll: true, onSuccess: () => setEscolhido(perfis[0]?.id ?? 0) });
    };

    return (
        <AppLayout>
            <Head title="Perfis e permissões" />
            <TituloPagina
                titulo="Perfis e permissões"
                descricao="Cada pessoa da equipa tem um perfil; o perfil decide o que vê e o que pode fazer."
                accoes={
                    !novo && (
                        <Button size="sm" onClick={() => setNovo(true)}>
                            <Plus /> Novo perfil
                        </Button>
                    )
                }
            />
            {novo && <NovoPerfil fechar={() => setNovo(false)} />}

            <div className="grid gap-5 lg:grid-cols-[16rem_1fr] lg:items-start">
                <nav className="painel overflow-hidden lg:sticky lg:top-4" aria-label="Perfis">
                    <ul className="divide-y divide-[var(--linha-suave)]">
                        {perfis.map((p) => (
                            <li key={p.id}>
                                <button
                                    type="button"
                                    onClick={() => setEscolhido(p.id)}
                                    aria-current={p.id === perfil?.id ? 'true' : undefined}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-[13.5px] transition-colors',
                                        p.id === perfil?.id ? 'bg-muted font-medium' : 'hover:bg-muted/50',
                                    )}
                                >
                                    <span className="min-w-0">
                                        {p.nome}
                                        <span className="text-muted-foreground block text-[12px] font-normal">
                                            {p.utilizadores === 1 ? '1 pessoa' : `${p.utilizadores} pessoas`}
                                            {p.acessoTotal ? ' · acesso total' : ` · ${p.permissoes.length} permissões`}
                                        </span>
                                    </span>
                                    {p.sistema && <span className="text-muted-foreground shrink-0 text-[11px]">do sistema</span>}
                                </button>
                            </li>
                        ))}
                    </ul>
                </nav>

                {perfil && (
                    <section className="flex min-w-0 flex-col gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-3">
                                <h2 className="text-[17px] font-semibold">{perfil.nome}</h2>
                                {perfil.sistema ? <Estado cor="azul">Do sistema</Estado> : <Estado cor="cinza">Criado pela instituição</Estado>}
                            </div>
                            {!perfil.sistema && (
                                <Button size="sm" variant="ghost" onClick={() => apagar(perfil)} className="text-red-700 dark:text-red-400">
                                    <Trash2 /> Apagar o perfil
                                </Button>
                            )}
                        </div>
                        <Matriz key={perfil.id + ':' + perfil.permissoes.join(',')} perfil={perfil} catalogo={catalogo} />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

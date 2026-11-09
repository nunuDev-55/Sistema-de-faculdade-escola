import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowRight } from 'lucide-react';

interface Caixa {
    titulo: string;
    href: string;
    descricao: string;
}

interface Props {
    configuracaoEmFalta: string[];
    caixas: Caixa[];
}

export default function Dashboard({ configuracaoEmFalta, caixas }: Props) {
    const { auth, instituicao } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title="Início" />

            <div className="mb-6">
                <h1 className="text-[22px] font-semibold tracking-[-0.015em]">Bem-vindo, {auth.user.name.split(' ')[0]}</h1>
                <p className="text-muted-foreground text-[14px]">
                    {instituicao.nome}
                    {auth.perfil && ` · ${auth.perfil}`}
                </p>
            </div>

            {configuracaoEmFalta.length > 0 && (
                <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <div className="flex items-center gap-2 text-[14px] font-medium text-amber-900 dark:text-amber-200">
                        <AlertTriangle className="size-4" />
                        Falta configurar a instituição
                    </div>
                    <ul className="text-muted-foreground mt-2 list-inside list-disc text-[13px]">
                        {configuracaoEmFalta.map((aviso) => (
                            <li key={aviso}>{aviso}</li>
                        ))}
                    </ul>
                </div>
            )}

            {caixas.length === 0 ? (
                <p className="text-muted-foreground text-[14px]">O seu perfil ainda não tem módulos atribuídos. Contacte o administrador.</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {caixas.map((caixa) => (
                        <Link
                            key={caixa.href}
                            href={caixa.href}
                            className="group bg-card flex flex-col rounded-lg border p-4 transition-colors hover:border-[var(--marca)]"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-[15px] font-semibold">{caixa.titulo}</span>
                                <ArrowRight className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5" />
                            </div>
                            <p className="text-muted-foreground mt-1 text-[13px]">{caixa.descricao}</p>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

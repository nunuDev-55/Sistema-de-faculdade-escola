import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

/** Ecrãs de entrada (login, recuperar senha): limpos, com a instituição e o nome do sistema por cima do formulário. */
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { sistema, instituicao } = usePage<SharedData>().props;

    return (
        <div className="bg-muted flex min-h-svh flex-col items-center justify-center px-4 py-10">
            <div className="w-full max-w-[380px]">
                <div className="mb-8 flex flex-col items-center gap-5 text-center">
                    <Link href="/painel" className="flex items-center gap-2.5" aria-label={`${instituicao.nome} — ${sistema}`}>
                        <AppLogoIcon className="size-10" />
                        <span className="flex flex-col items-start leading-tight">
                            <span className="text-[16px] font-semibold">{instituicao.nome}</span>
                            <span className="text-muted-foreground text-[12.5px]">{sistema}</span>
                        </span>
                    </Link>
                    <div className="flex flex-col gap-1.5">
                        <h1 className="text-[22px] font-semibold tracking-[-0.015em]">{title}</h1>
                        {description && <p className="text-muted-foreground text-[14px]">{description}</p>}
                    </div>
                </div>
                <div className="bg-card rounded-lg border p-6">{children}</div>
            </div>
        </div>
    );
}

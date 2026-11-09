import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { MensagemFlash } from '@/components/erp';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { usePode } from '@/hooks/use-pode';
import { useTabelasEmCartoes } from '@/hooks/use-tabelas-em-cartoes';
import { itemActivo, NAVEGACAO } from '@/lib/navegacao';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Menu } from 'lucide-react';
import { type ReactNode } from 'react';

/**
 * Esqueleto de todas as páginas do sistema interno: menu lateral à esquerda (no telemóvel, numa gaveta),
 * barra fina no topo e o conteúdo. O menu está em lib/navegacao.ts; cada entrada só aparece a quem tem
 * a permissão (usePode) — a protecção verdadeira está sempre no backend.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    // No telemóvel, as listas (table.tabela) passam a cartões
    useTabelasEmCartoes();

    return (
        <div className="flex min-h-svh">
            <aside className="bg-sidebar sticky top-0 hidden h-svh w-60 shrink-0 flex-col border-r lg:flex print:hidden">
                <MenuLateral />
            </aside>
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="bg-background/95 sticky top-0 z-30 flex h-14 items-center gap-2 border-b px-3 backdrop-blur md:px-6 print:hidden">
                    <Sheet>
                        <SheetTrigger
                            className="hover:bg-accent flex size-9 items-center justify-center rounded-md lg:hidden"
                            aria-label="Abrir menu"
                        >
                            <Menu className="size-5" />
                        </SheetTrigger>
                        <SheetContent side="left" className="bg-sidebar flex w-72 flex-col p-0">
                            <SheetTitle className="sr-only">Menu</SheetTitle>
                            <MenuLateral />
                        </SheetContent>
                    </Sheet>
                    <div className="ml-auto flex items-center gap-1">
                        <AppearanceToggleDropdown />
                    </div>
                </header>
                <main className="mx-auto w-full max-w-[1400px] min-w-0 flex-1 px-4 py-6 md:px-8 md:py-7 print:max-w-none print:p-0">{children}</main>
                <MensagemFlash />
            </div>
        </div>
    );
}

function MenuLateral() {
    const { url, props } = usePage<SharedData>();
    const pode = usePode();
    const iniciais = useInitials();
    const caminho = url.split('?')[0];
    const activo = itemActivo(caminho);

    return (
        <>
            <Link href="/painel" className="flex h-14 shrink-0 items-center gap-2.5 px-4" aria-label={`${props.instituicao.nome} — Início`}>
                <AppLogoIcon className="size-7 shrink-0" />
                <span className="flex min-w-0 flex-col leading-tight">
                    <span className="truncate text-[14px] font-semibold">{props.instituicao.nome}</span>
                    <span className="text-muted-foreground text-[12px]">{props.sistema}</span>
                </span>
            </Link>

            <nav className="flex-1 overflow-y-auto px-2.5 pb-4" aria-label="Menu principal">
                {NAVEGACAO.map((grupo, g) => {
                    const itens = grupo.itens.filter((i) => pode(i.permissao));
                    if (itens.length === 0) return null;
                    return (
                        <div key={grupo.titulo ?? g} className={cn(g > 0 && 'mt-5')}>
                            {grupo.titulo && <p className="text-muted-foreground px-2 pb-1.5 text-[12px] font-medium">{grupo.titulo}</p>}
                            <ul className="flex flex-col gap-px">
                                {itens.map((item) => {
                                    const eActivo = activo?.href === item.href;
                                    return (
                                        <li key={item.href}>
                                            <Link
                                                href={item.href}
                                                className={cn(
                                                    'flex h-8 items-center gap-2.5 rounded-md px-2 text-[13.5px] transition-colors',
                                                    eActivo
                                                        ? 'bg-[var(--marca-suave)] font-medium text-[var(--marca-texto)]'
                                                        : 'text-sidebar-foreground hover:bg-sidebar-accent/70',
                                                )}
                                                aria-current={eActivo ? 'page' : undefined}
                                            >
                                                <item.icone
                                                    className={cn('size-4 shrink-0', eActivo ? 'text-[var(--marca-texto)]' : 'text-muted-foreground')}
                                                />
                                                {item.label}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    );
                })}
            </nav>

            <div className="shrink-0 border-t p-2.5">
                <DropdownMenu>
                    <DropdownMenuTrigger className="hover:bg-sidebar-accent flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left">
                        <span className="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-[12px] font-semibold">
                            {iniciais(props.auth.user.name)}
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col leading-tight">
                            <span className="truncate text-[13px] font-medium">{props.auth.user.name}</span>
                            <span className="text-muted-foreground truncate text-[12px]">{props.auth.user.email}</span>
                        </span>
                        <ChevronsUpDown className="text-muted-foreground size-3.5" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" side="top" className="w-60">
                        <UserMenuContent user={props.auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </>
    );
}

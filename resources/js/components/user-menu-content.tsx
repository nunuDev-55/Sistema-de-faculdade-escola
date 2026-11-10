import { DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { type SharedData, type User } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

/** O conteúdo do menu da conta (canto do menu lateral): quem está ligado, as definições e sair. */
export function UserMenuContent({ user }: { user: User }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex flex-col px-1 py-1.5 text-left">
                    <span className="truncate text-[13px] font-medium">{user.name}</span>
                    <span className="text-muted-foreground truncate text-[12px]">{user.email}</span>
                    {auth.perfil && <span className="text-muted-foreground mt-0.5 text-[11.5px]">{auth.perfil}</span>}
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link href="/definicoes/perfil" className="flex items-center gap-2">
                    <Settings className="size-4" />
                    Definições
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => router.post(route('logout'))} className="flex items-center gap-2">
                <LogOut className="size-4" />
                Sair do sistema
            </DropdownMenuItem>
        </>
    );
}

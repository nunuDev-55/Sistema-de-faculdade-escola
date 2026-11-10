import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { type HTMLAttributes } from 'react';

/**
 * O logótipo da instituição (Configuração) ou, enquanto não carregar nenhum, um ícone académico neutro
 * na cor da instituição.
 */
export default function AppLogoIcon({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
    const { instituicao } = usePage<SharedData>().props;

    if (instituicao.logotipo) {
        return (
            <span className={cn('inline-flex items-center justify-center', className)} {...props}>
                <img src={instituicao.logotipo} alt="" aria-hidden="true" draggable={false} className="size-full object-contain" />
            </span>
        );
    }

    return (
        <span
            className={cn('inline-flex items-center justify-center rounded-md bg-[var(--marca)] text-[var(--sobre-cor-empresa,#fff)]', className)}
            aria-hidden="true"
            {...props}
        >
            <GraduationCap className="size-[62%]" />
        </span>
    );
}

import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * Lista de escolha nativa do navegador (<select>), com o mesmo aspecto do Input.
 * Prefere-se à lista do Radix (select.tsx) nos formulários: funciona com o teclado e no telemóvel sem nada a mais.
 */
const CaixaEscolha = React.forwardRef<HTMLSelectElement, React.ComponentProps<'select'>>(({ className, ...props }, ref) => {
    return (
        <select
            className={cn(
                'border-input bg-card focus-visible:ring-ring h-9 w-full rounded-md border px-2 text-sm outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            ref={ref}
            {...props}
        />
    );
});

CaixaEscolha.displayName = 'CaixaEscolha';

export { CaixaEscolha };

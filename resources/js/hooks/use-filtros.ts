import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Filtros de uma lista paginada no servidor: cada mudança pede a lista outra vez (com uma pequena espera,
 * para a pesquisa não disparar a cada letra) e fica no endereço, para se poder partilhar ou voltar atrás.
 */
export function useFiltros<T extends Record<string, string>>(endereco: string, iniciais: T) {
    const [filtros, setFiltros] = useState<T>(iniciais);
    const primeira = useRef(true);

    useEffect(() => {
        if (primeira.current) {
            primeira.current = false;
            return;
        }
        const t = setTimeout(() => {
            const pedido = Object.fromEntries(Object.entries(filtros).filter(([, v]) => v !== ''));
            router.get(endereco, pedido, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
        return () => clearTimeout(t);
    }, [endereco, filtros]);

    const mudar = (campo: keyof T, valor: string) => setFiltros((f) => ({ ...f, [campo]: valor }));
    return { filtros, mudar };
}

import { useCallback, useState } from 'react';

const CHAVE = 'nserp.menu-recolhido';

function ler(): boolean {
    try {
        return localStorage.getItem(CHAVE) === '1';
    } catch {
        return false;
    }
}

/** Menu lateral recolhido (só os ícones), no computador. Fica lembrado neste navegador. */
export function useMenuRecolhido() {
    const [recolhido, setRecolhido] = useState(ler);
    const alternar = useCallback(
        () =>
            setRecolhido((r) => {
                try {
                    localStorage.setItem(CHAVE, r ? '0' : '1');
                } catch {
                    // Sem armazenamento (janela privada): vale só até mudar de página
                }
                return !r;
            }),
        [],
    );

    return { recolhido, alternar };
}

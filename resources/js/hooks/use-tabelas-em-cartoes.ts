import { useEffect } from 'react';

/**
 * Telemóvel: as listas (table.tabela) passam a cartões, uma linha por cartão (CSS em app.css, "Tabelas em cartões").
 * Aqui só se dá a cada célula o nome da sua coluna (data-label), lido do cabeçalho, e se marca a tabela com
 * data-cartoes; o CSS só actua nas tabelas marcadas. Uma tabela que deva ficar como está leva data-tabela-fixa.
 *
 * Volta a correr sempre que a página muda (MutationObserver), porque o React troca as linhas ao filtrar ou paginar.
 */
export function useTabelasEmCartoes() {
    useEffect(() => {
        let pedido = 0;
        const rotular = () => {
            pedido = 0;
            document.querySelectorAll<HTMLTableElement>('table.tabela:not([data-tabela-fixa])').forEach(rotularTabela);
        };
        const agendar = () => {
            if (!pedido) pedido = requestAnimationFrame(rotular);
        };

        rotular();
        // Só mudanças de conteúdo: os atributos que se põem aqui não voltam a disparar o observador
        const observador = new MutationObserver(agendar);
        observador.observe(document.body, { childList: true, subtree: true, characterData: true });
        return () => {
            observador.disconnect();
            if (pedido) cancelAnimationFrame(pedido);
        };
    }, []);
}

function rotularTabela(tabela: HTMLTableElement) {
    // Nomes das colunas: a última linha do cabeçalho que não seja a dos filtros
    const cabecalho = [...(tabela.tHead?.rows ?? [])].filter((r) => !r.classList.contains('linha-filtros')).pop();
    const nomes: string[] = [];
    for (const th of cabecalho?.cells ?? []) {
        const nome = (th.textContent ?? '').replace(/\s+/g, ' ').trim();
        for (let i = 0; i < th.colSpan; i++) nomes.push(i === 0 ? nome : '');
    }

    // Filtros: sem os nomes das colunas por cima, cada um leva a sua legenda ("Filtrar por cliente" → "Cliente")
    for (const linhaFiltros of [...(tabela.tHead?.rows ?? [])].filter((r) => r.classList.contains('linha-filtros'))) {
        let coluna = 0;
        for (const th of linhaFiltros.cells) {
            const rotulo =
                th
                    .querySelector('[aria-label]')
                    ?.getAttribute('aria-label')
                    ?.replace(/^Filtrar por /i, '') ??
                nomes[coluna] ??
                '';
            const legenda = rotulo ? rotulo.charAt(0).toUpperCase() + rotulo.slice(1) : '';
            if (legenda && th.dataset.label !== legenda) th.dataset.label = legenda;
            coluna += th.colSpan;
        }
    }

    const linhas = [...tabela.tBodies].flatMap((corpo) => [...corpo.rows]).concat(tabela.tFoot ? [...tabela.tFoot.rows] : []);
    for (const linha of linhas) {
        let coluna = 0;
        for (const celula of linha.cells) {
            // A 1.ª coluna é o título do cartão; as células que juntam colunas (colspan) não têm um nome só
            const nome = coluna > 0 && celula.colSpan === 1 ? (nomes[coluna] ?? '') : '';
            if (nome && celula.dataset.label !== nome) celula.dataset.label = nome;
            if (!nome && celula.dataset.label !== undefined) delete celula.dataset.label;

            const vazia = (celula.textContent ?? '').trim() === '' && !celula.querySelector('input, select, textarea, button, a, img, svg');
            if (vazia && celula.dataset.vazia === undefined) celula.dataset.vazia = '';
            if (!vazia && celula.dataset.vazia !== undefined) delete celula.dataset.vazia;

            coluna += celula.colSpan;
        }
    }

    if (tabela.dataset.cartoes === undefined) tabela.dataset.cartoes = '';
}

import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

// O nome do sistema e o da instituição vêm da primeira página (dados partilhados em HandleInertiaRequests)
const primeira = JSON.parse(document.getElementById('app')?.dataset.page ?? '{}') as {
    props?: { sistema?: string; instituicao?: { nome?: string } };
};
const sistema = primeira.props?.sistema ?? 'nsEDU';
const instituicao = primeira.props?.instituicao?.nome;

createInertiaApp({
    title: (title) => (instituicao && title.includes(instituicao) ? title : `${title} - ${sistema}`),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: 'var(--primary)',
        delay: 150,
    },
});

// This will set light / dark mode on load...
initializeTheme();

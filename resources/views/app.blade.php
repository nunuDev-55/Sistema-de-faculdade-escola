<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover: no iPhone, env(safe-area-inset-*) passa a valer --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Página base do sistema interno: o nome é o do produto (nsEDU); as cores, as da instituição --}}
        <title inertia>{{ config('nsedu.produto.nome') }}</title>

        @include('partials.cores')
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="manifest" href="/manifest.json">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('nsedu.produto.nome') }}">

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead

        {{-- Ecrã de carregamento enquanto o JavaScript da página ainda não chegou --}}
        <style>
            .lg-carregar { position: fixed; inset: 0; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px; background: #ffffff; }
            .lg-carregar .lg-nome { font-family: ui-sans-serif, system-ui, sans-serif; font-size: 20px; font-weight: 600; letter-spacing: -0.01em; color: var(--cor-empresa); }
            .lg-carregar .lg-barra { position: relative; width: 220px; height: 2px; overflow: hidden; border-radius: 2px; background: #e9eaf0; }
            .lg-carregar .lg-barra::after { content: ""; position: absolute; inset: 0; width: 35%; border-radius: inherit; background: var(--cor-empresa); animation: lg-deslizar 1.2s cubic-bezier(.4, 0, .2, 1) infinite; }
            #app:not(:empty) ~ .lg-carregar { display: none; }
            @media (prefers-color-scheme: dark) {
                .lg-carregar { background: #0d0d0f; }
                .lg-carregar .lg-barra { background: #24252b; }
            }
            @keyframes lg-deslizar { from { transform: translateX(-100%); } to { transform: translateX(290%); } }
            @media (prefers-reduced-motion: reduce) { .lg-carregar .lg-barra::after { animation: none; width: 100%; opacity: .5; } }
        </style>
    </head>
    <body class="font-sans antialiased">
        @inertia
        <div class="lg-carregar" aria-hidden="true">
            <span class="lg-nome">{{ app(\App\Support\Definicoes::class)->nomeCurto() }}</span>
            <div class="lg-barra"></div>
        </div>
    </body>
</html>

<?php

use App\Http\Middleware\CabecalhosSeguranca;
use App\Http\Middleware\GarantirUtilizadorActivo;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Um túnel (Cloudflare) ou o Nginx do servidor na própria máquina diz o IP e o esquema do visitante
        // (X-Forwarded-*). Só se acredita nesses cabeçalhos quando o pedido chega da própria máquina.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->web(append: [
            CabecalhosSeguranca::class,
            GarantirUtilizadorActivo::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

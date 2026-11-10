<!DOCTYPE html>
<html lang="pt">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        @php($empresa = \App\Support\Definicoes::paraPaginaDeErro())
        <title>@yield('titulo') · {{ $empresa['nome'] }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        {{-- Página de erro sem scripts nem ficheiros externos (funciona mesmo com o site em baixo) --}}
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: 24px;
                   background: #faf7f2; color: #241c1a; font: 15px/1.6 "Segoe UI", system-ui, -apple-system, sans-serif; }
            main { max-width: 460px; text-align: center; }
            img { height: 64px; width: auto; }
            .nome { margin: 0; color: #241c1a; font: 600 22px/1.2 Georgia, "Times New Roman", serif; }
            .codigo { margin: 28px 0 6px; color: {{ $empresa['cor'] }}; font-size: 12px; letter-spacing: .16em; text-transform: uppercase; font-weight: 600; }
            h1 { margin: 0; font: 400 34px/1.15 Georgia, "Times New Roman", serif; letter-spacing: -.01em; }
            p { color: #74685f; margin: 14px 0 0; }
            .accoes { margin-top: 28px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
            a { display: inline-block; border-radius: 999px; padding: 11px 22px; font-size: 14px; font-weight: 500; text-decoration: none; }
            .principal { background: #241c1a; color: #fff; }
            .secundario { border: 1px solid #ddd3c7; color: #241c1a; }
        </style>
    </head>
    <body>
        <main>
            @if ($empresa['logotipo'])
                <img src="{{ $empresa['logotipo'] }}" alt="{{ $empresa['nome'] }}">
            @else
                <p class="nome">{{ $empresa['nome'] }}</p>
            @endif
            <p class="codigo">@yield('codigo')</p>
            <h1>@yield('titulo')</h1>
            <p>@yield('mensagem')</p>
            <div class="accoes">
                <a class="principal" href="/">Página inicial</a>
                <a class="secundario" href="/painel">Ir para o painel</a>
            </div>
        </main>
    </body>
</html>

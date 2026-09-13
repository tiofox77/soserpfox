{{--
    A base das páginas de erro da casa.

    Até aqui as páginas de erro eram as do Laravel: um número grande, uma
    linha em inglês, fundo cinzento, sem ícone no separador e sem forma de
    voltar a lado nenhum. Quem apanhava um link partido saía do sistema sem
    perceber se tinha sido ele, se tinha sido o sistema, nem para onde ir.

    CSS embutido de propósito, como no 503: uma página de erro tem de aparecer
    bem exactamente quando o resto não está a aparecer. Sem ficheiros externos,
    sem CDN, sem Tailwind, sem base de dados — nada aqui depende de nada.

    Quem quiser acrescentar um código novo cria resources/views/errors/{codigo}
    e define as secções: `emoji`, `codigo`, `titulo`, `mensagem` e, se fizer
    sentido, `accao`.
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="@yield('robots', 'noindex, follow')">
    <title>@yield('titulo') · SOS ERP</title>
    @include('partials.favicon')
    <style>
        *{ box-sizing:border-box; margin:0; padding:0; }
        body{
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#0f172a 0%,#12283f 55%,#0e3a56 100%);
            color:#e2e8f0; padding:24px; line-height:1.6;
        }
        .cartao{
            max-width:540px; width:100%; text-align:center;
            background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10);
            border-radius:20px; padding:40px 32px; backdrop-filter:blur(8px);
        }
        .icone{
            width:76px; height:76px; margin:0 auto 22px; border-radius:20px;
            background:linear-gradient(135deg,#1270A7,#2b93cf);
            display:flex; align-items:center; justify-content:center; font-size:34px;
        }
        .codigo{
            display:inline-block; margin-bottom:14px; padding:3px 12px; border-radius:999px;
            background:rgba(239,120,17,.15); border:1px solid rgba(239,120,17,.35);
            color:#f0913f; font-size:12px; font-weight:700; letter-spacing:1.5px;
        }
        h1{ font-size:24px; font-weight:700; color:#fff; margin-bottom:12px; }
        p{ color:#94a3b8; font-size:15px; margin-bottom:14px; }
        .botoes{ margin-top:26px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .botao{
            display:inline-block; padding:11px 22px; border-radius:12px; font-size:14px;
            font-weight:600; text-decoration:none; transition:opacity .15s;
        }
        .botao:hover{ opacity:.88; text-decoration:none; }
        .principal{ background:linear-gradient(135deg,#EF7811,#f0913f); color:#fff; }
        .secundario{ background:rgba(255,255,255,.08); color:#cbd5e1; border:1px solid rgba(255,255,255,.14); }
        .nota{
            margin-top:26px; padding-top:20px; border-top:1px solid rgba(255,255,255,.10);
            font-size:13px; color:#64748b;
        }
        .marca{ margin-top:8px; font-weight:700; color:#2b93cf; letter-spacing:.5px; }
        a{ color:#5fb3e0; text-decoration:none; }
        a:hover{ text-decoration:underline; }
    </style>
</head>
<body>
    <div class="cartao">
        <div class="icone">@yield('emoji', '⚠️')</div>

        <div class="codigo">ERRO @yield('codigo')</div>

        <h1>@yield('titulo')</h1>

        @yield('mensagem')

        <div class="botoes">
            @section('accao')
                <a class="botao principal" href="{{ url('/') }}">Voltar ao início</a>
            @show
        </div>

        <div class="nota">
            Se isto continuar a acontecer, fale connosco por
            <a href="mailto:geral@soserp.vip">geral@soserp.vip</a>.
            <div class="marca">SOS ERP</div>
        </div>
    </div>
</body>
</html>

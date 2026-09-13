{{--
    A CASCA DO PWA — a mesma para os onze ecrãs. Ver App\Support\PaginaDoPwa.

    Tudo o que está aqui tem de existir SEM REDE: vem de /vendor ou do próprio
    pacote, e tudo está na lista de pré-guardados do service worker
    (resources/pwa/sw.js, que o PwaController completa com o pacote de hoje).
    Nada de CDN — a página cuja razão de existir é funcionar sem rede não pode
    depender de um servidor estranho.
--}}
@php
    $pacoteDoPwa = \App\Support\PacoteReact::doPwa();
    $publica = in_array($ecra, \App\Support\PaginaDoPwa::PUBLICAS, true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- O motor manda isto ao servidor em cada pedido (X-Sos-Version): é por
         aqui que se sabe que versão corre em cada aparelho. --}}
    <meta name="pwa-versao" content="{{ app(\App\Http\Controllers\PwaController::class)->buildVersion() }}">
    <title>{{ $titulo }} — SOS ERP</title>

    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="{{ pwa_theme_color() }}">

    {{-- O iOS não lê o manifesto. Sem estas, quem adiciona ao ecrã principal
         num iPhone fica com uma miniatura da página por ícone e a aplicação
         abre dentro do Safari, com a barra de endereço a comer o topo do POS. --}}
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('/pwa/icon-maskable-192.png') }}">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @include('partials.favicon')

    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    {{-- bcryptjs — confere o PIN de turno sem rede. --}}
    <script src="/js/vendor/bcrypt.min.js?v=1"></script>
    {{-- O PDF no próprio aparelho (talão e documento para o WhatsApp). --}}
    <script defer src="/vendor/js/html2canvas.min.js"></script>
    <script defer src="/vendor/js/jspdf.umd.min.js"></script>

    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .pwa-shell { padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* O movimento do PWA. Curto, e só onde diz alguma coisa: o que entra,
           o que sobe do fundo (a gaveta do carrinho), o que pede atenção.

           «backwards» E NUNCA «both»: uma animação que fica presa no fim
           mantém um contexto de empilhamento, e os filhos fixos (as folhas,
           o carrinho do telemóvel) passavam a ficar POR BAIXO do menu de baixo,
           com o z-index a valer só lá dentro. O fim de cada animação é o
           estilo natural, portanto não há nada a prender. */
        @keyframes pwa-roda { to { transform: rotate(360deg); } }
        @keyframes pwaEntra { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @keyframes pwaSobe { from { transform: translateY(100%); } to { transform: none; } }
        @keyframes pwaDesce { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: none; } }
        @keyframes pwaFundo { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pwaCresce { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }
        @keyframes pwaAbana { 0%, 100% { transform: none; } 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }
        @keyframes pwaPulsa { 0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, .45); } 50% { box-shadow: 0 0 0 10px rgba(249, 115, 22, 0); } }
        @keyframes pwaFlutua { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        .pwa-entra { animation: pwaEntra .28s ease-out backwards; }
        .pwa-aparece { animation: pwaFundo .25s ease-out backwards; }
        .pwa-sobe { animation: pwaSobe .26s cubic-bezier(.2, .8, .2, 1) backwards; }
        .pwa-desce { animation: pwaDesce .22s ease-out backwards; }
        .pwa-fundo { animation: pwaFundo .2s ease-out backwards; }
        .pwa-cresce { animation: pwaCresce .22s cubic-bezier(.2, .8, .2, 1) backwards; }
        .pwa-abana { animation: pwaAbana .4s ease-in-out; }
        .pwa-pulsa { animation: pwaPulsa 2s ease-in-out infinite; }
        .pwa-flutua { animation: pwaFlutua 3s ease-in-out infinite; }
        .pwa-toque { transition: transform .12s ease, box-shadow .2s ease, background-color .2s ease; }
        .pwa-toque:active { transform: scale(.97); }
        .pwa-cartao { transition: transform .2s ease, box-shadow .2s ease; }
        .pwa-cartao:hover { transform: translateY(-2px); box-shadow: 0 10px 24px -12px rgba(15, 23, 42, .25); }
        @media (prefers-reduced-motion: reduce) {
            .pwa-entra, .pwa-aparece, .pwa-sobe, .pwa-desce, .pwa-fundo, .pwa-cresce, .pwa-abana, .pwa-pulsa, .pwa-flutua { animation: none !important; }
            .pwa-cartao:hover, .pwa-toque:active { transform: none; }
        }
    </style>
</head>
<body class="{{ $publica ? 'bg-gradient-to-br from-blue-900 to-blue-700' : 'bg-slate-50' }} min-h-screen pwa-shell">

{{-- ECRÃ DE CARREGAMENTO, com estilos à mão e sem Tailwind nem React: é para
     aparecer exactamente quando eles ainda não chegaram. Sai quando o React
     monta (o pacote remove-o). --}}
<div id="pwa-a-carregar" role="status" aria-live="polite"
     style="position:fixed;inset:0;z-index:9998;background:linear-gradient(160deg,#1e3a8a,#312e81);
            display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;
            font-family:-apple-system,'Segoe UI',Roboto,sans-serif;color:#fff;text-align:center;padding:24px">
    <div style="width:64px;height:64px;border-radius:20px;background:rgba(255,255,255,.95);
                display:flex;align-items:center;justify-content:center">
        <img src="/pwa/icon-192x192.png" alt="SOS ERP" style="width:46px;height:46px;object-fit:contain" draggable="false">
    </div>
    <div style="width:34px;height:34px;border:3px solid rgba(255,255,255,.25);border-top-color:#fff;
                border-radius:50%;animation:pwa-roda .8s linear infinite"></div>
    <div>
        <p style="margin:0;font-weight:700;font-size:16px">{{ __('A preparar o ponto de venda') }}</p>
        <p id="pwa-a-carregar-nota" style="margin:6px 0 0;font-size:13px;opacity:.75">{{ __('Um momento…') }}</p>
    </div>
</div>

<div id="pwa-raiz" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>

{{-- O dicionário vai DENTRO da página, e não por um pedido à parte: sem rede
     esse pedido não se faz, e a aplicação voltava ao português a meio de um
     turno. Em português não vai nada — a chave já é a frase. --}}
@if(!empty($dicionario))
<script type="application/json" id="pwa-dicionario">@json($dicionario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>
@endif

{{-- Quem está a usar isto, e por conta de que empresa. A cópia de segurança
     carimba estes valores, e é por eles que a importação recusa um ficheiro
     de outra empresa. --}}
<script>
    window.SOS_TENANT_ID = @json($publica ? null : activeTenantId());
    window.SOS_USER_ID   = @json($publica ? null : auth()->id());
    window.SOS_USER_NAME = @json($publica ? null : auth()->user()?->name);
    window.__reactLingua = @json(app()->getLocale());

    // SE O PACOTE NÃO ARRANCAR, DIZÊ-LO. Um ecrã de carregamento que nunca sai
    // lê-se como aplicação encravada: quem está ao balcão não sabe se espera,
    // toca outra vez ou fecha. Aos quatro segundos explica-se; aos doze
    // oferece-se a saída.
    (function () {
        var nota = document.getElementById('pwa-a-carregar-nota');
        setTimeout(function () {
            if (nota && document.getElementById('pwa-a-carregar')) {
                nota.textContent = @json(__('A carregar pela primeira vez. Com internet é mais rápido.'), JSON_UNESCAPED_UNICODE);
            }
        }, 4000);
        setTimeout(function () {
            if (window.__pwaMontado || document.getElementById('pwa-aviso-arranque')) return;
            var ecra = document.getElementById('pwa-a-carregar');
            if (ecra) ecra.remove();
            var aviso = document.createElement('div');
            aviso.id = 'pwa-aviso-arranque';
            aviso.setAttribute('role', 'alert');
            aviso.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:9999;background:#b45309;color:#fff;'
                + 'padding:12px 16px;font-size:14px;line-height:1.45;font-family:-apple-system,Segoe UI,Roboto,sans-serif;'
                + 'box-shadow:0 2px 8px rgba(0,0,0,.2)';
            var texto = document.createElement('span');
            texto.textContent = @json(__('A aplicação não carregou por completo. Ligue-se à rede e recarregue — depois de carregar uma vez, passa a abrir sem internet.'), JSON_UNESCAPED_UNICODE);
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.textContent = @json(__('Recarregar'), JSON_UNESCAPED_UNICODE);
            botao.style.cssText = 'margin-left:12px;background:#fff;color:#b45309;border:0;border-radius:8px;padding:6px 14px;font-weight:700;cursor:pointer';
            botao.onclick = function () { window.location.reload(); };
            aviso.appendChild(texto);
            aviso.appendChild(botao);
            document.body.appendChild(aviso);
            console.warn('[PWA] O pacote não arrancou em 12s.');
        }, 12000);
    })();
</script>

{{-- O registo do service worker vai no pacote (casca/servicoOffline.ts), em
     TODAS as páginas, incluindo a entrada: quem chega primeiro à entrada
     também tem de ficar com o modo offline instalado. --}}
@if($pacoteDoPwa)
    <script type="module" src="{{ $pacoteDoPwa }}"></script>
@endif
</body>
</html>

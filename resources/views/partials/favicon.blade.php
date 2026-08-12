{{--
    Os ícones do site, num sítio só.

    Havia seis maneiras diferentes de os declarar espalhadas pelos layouts —
    umas com o .ico apenas, outras com os PNG por tamanho, outras a apontar
    para /pwa/icon-192.png, outras a nada. E mais de dez páginas com <head>
    próprio não declaravam ícone nenhum: os convites, o check-in do hotel, a
    empresa desactivada, a subscrição expirada, o 503, a página offline.
    Nessas, o browser ia buscar /favicon.ico à sorte ou mostrava a folha em
    branco.

    Quem precisar de ícones numa página nova inclui isto e acabou.
--}}
<link rel="icon" type="image/x-icon" sizes="any" href="{{ app_favicon() }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('brand/favicon-48x48.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('brand/favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#1270A7">

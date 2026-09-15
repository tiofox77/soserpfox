{{--
    UMA PÁGINA SOLTA — sem menu: a entrada no portal do cliente, o registo de
    uma conta, o assistente de 1.ª utilização. O ecrã React desenha o resto.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }} - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    @if(!empty($descricao))
    <meta name="description" content="{{ $descricao }}">
    @endif
    <meta name="robots" content="noindex, nofollow">
    @include('partials.favicon')
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">
    <style>
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        .icon-float { animation: float 3s ease-in-out infinite; }
    </style>
    @include('partials.react-animacoes')
    {{-- O registo conta visitas e conversões para a publicidade; o resto não. --}}
    @if($pixel ?? false)
    @include('partials.meta-pixel')
    @endif
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <x-ecra-react :nome="$ecra" :props="$props ?? []" class="min-h-screen" />

    @include('partials.react-pacote')
    @include('partials.consentimento')
</body>
</html>

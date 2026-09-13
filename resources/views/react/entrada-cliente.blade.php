{{--
    A ENTRADA NO PORTAL DO CLIENTE — uma página sem menu, com o ecrã React no meio.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }} - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    <meta name="description" content="{{ __('Acesse sua área exclusiva do cliente para visualizar faturas, eventos e documentos.') }}">
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
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <x-ecra-react :nome="$ecra" :props="$props ?? []" class="min-h-screen" />

    @include('partials.react-pacote')
</body>
</html>

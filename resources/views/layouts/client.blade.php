<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta Tags -->
    <title>@yield('title', __('Portal do Cliente')) - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    <meta name="description" content="{{ __('Portal exclusivo para clientes. Visualize faturas, eventos, documentos e acompanhe o status dos seus serviços.') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="{{ app_name() ?? config('app.name') }}">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', __('Portal do Cliente')) - {{ app_name() ?? config('app.name') }}">
    <meta property="og:site_name" content="{{ app_name() ?? config('app.name') }}">
    @if(app_logo())
    <meta property="og:image" content="{{ app_logo() }}">
    @endif

    @include('partials.favicon')

    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">

    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .icon-float { animation: float 3s ease-in-out infinite; }
        .card-hover { transition: transform .3s cubic-bezier(.4, 0, .2, 1), box-shadow .3s cubic-bezier(.4, 0, .2, 1); }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0, 0, 0, .1); }
    </style>

    @include('partials.react-animacoes')
</head>
<body class="flex min-h-screen flex-col bg-gray-100">
    {{-- A BARRA DO TOPO DO PORTAL — a peça `cliente/topo`, em React. Os menus
         (utilizador e telemóvel) eram um <script> em linha no fim da página.
         A altura fica reservada enquanto o JavaScript chega. --}}
    @php
        // O MENU SÓ TEM AS ÁREAS QUE A EMPRESA DEU A ESTE CLIENTE
        // (App\Support\PortalDoCliente) — um cliente da oficina não vê «Eventos».
        $clienteDoPortal = auth('client')->user();
        $areasDoPortal = $clienteDoPortal ? \App\Support\PortalDoCliente::doCliente($clienteDoPortal) : [];
        $veFacturasNoPortal = $clienteDoPortal && \App\Support\PortalDoCliente::veFacturas($clienteDoPortal);
        $ligacoesDoPortal = collect([
            ['route' => 'client.dashboard', 'icon' => 'fa-house',        'label' => __('Início'), 've' => true],
            ['route' => 'client.workshop',  'icon' => 'fa-car',          'label' => __('Oficina'), 've' => in_array('oficina', $areasDoPortal, true)],
            ['route' => 'client.statement', 'icon' => 'fa-chart-line',   'label' => __('Extrato'), 've' => $veFacturasNoPortal],
            ['route' => 'client.events',    'icon' => 'fa-calendar-days', 'label' => __('Eventos'), 've' => in_array('eventos', $areasDoPortal, true)],
            ['route' => 'client.invoices',  'icon' => 'fa-file-invoice', 'label' => __('Faturas'), 've' => $veFacturasNoPortal],
            ['route' => 'client.proformas', 'icon' => 'fa-file-lines',   'label' => __('Proformas'), 've' => in_array('facturacao', $areasDoPortal, true)],
        ])->filter(fn ($l) => $l['ve'])->values()->map(fn ($l) => [
            'url' => route($l['route']),
            'icone' => $l['icon'],
            'rotulo' => $l['label'],
            'activo' => request()->routeIs($l['route']),
        ])->all();
    @endphp

    <x-ecra-react nome="cliente/topo" :esqueleto="false" class="relative z-10 block min-h-[4rem] bg-white shadow-md" :props="[
        'inicio' => route('client.dashboard'),
        'logo' => app_logo(),
        'nome' => app_name(),
        'ligacoes' => $ligacoesDoPortal,
        'perfil' => route('client.profile'),
        'sair' => route('client.logout'),
        'csrf' => csrf_token(),
    ]" />

    <main class="flex-1 py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @yield('content')
        </div>
    </main>

    <footer class="mt-auto border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Todos os direitos reservados.') }}</p>
        </div>
    </footer>

    {{-- Avisos de canto e recados da sessão, barra de progresso e service
         worker. Sem keep-alive: a sessão do cliente é de outro guarda. --}}
    <x-ecra-react nome="casca/sistema" :esqueleto="false" :props="[
        'login' => route('client.login'),
        'manterViva' => null,
        'recados' => \App\Support\RecadosDaSessao::lista(),
    ]" />

    @include('partials.react-pacote')
</body>
</html>

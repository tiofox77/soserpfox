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
        [data-menu-painel][hidden] { display: none !important; }
    </style>

    @include('partials.react-animacoes')
</head>
<body class="flex min-h-screen flex-col bg-gray-100">
    {{-- O PORTAL SEM LIVEWIRE. Os menus (utilizador e telemóvel) abrem com o
         pequeno script do fim desta página: eram Alpine, que vinha com o Livewire. --}}
    @php
        $ligacoesDoPortal = [
            ['route' => 'client.dashboard', 'icon' => 'fa-house',        'label' => __('Início')],
            ['route' => 'client.statement', 'icon' => 'fa-chart-line',   'label' => __('Extrato')],
            ['route' => 'client.events',    'icon' => 'fa-calendar-days', 'label' => __('Eventos')],
            ['route' => 'client.invoices',  'icon' => 'fa-file-invoice', 'label' => __('Faturas')],
            ['route' => 'client.proformas', 'icon' => 'fa-file-lines',   'label' => __('Proformas')],
        ];
    @endphp

    <nav class="relative bg-white shadow-md">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <a href="{{ route('client.dashboard') }}" class="flex items-center">
                    @if(app_logo())
                        <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="mr-3 h-12 w-auto object-contain">
                    @else
                        <span class="mr-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600">
                            <i class="fas fa-users text-white"></i>
                        </span>
                    @endif
                    <span class="text-xl font-bold text-gray-900">{{ __('Portal do Cliente') }}</span>
                </a>

                <div class="hidden items-center space-x-1 md:flex">
                    @foreach($ligacoesDoPortal as $l)
                        <a href="{{ route($l['route']) }}"
                           @if(request()->routeIs($l['route'])) aria-current="page" @endif
                           class="rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($l['route']) ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50 hover:text-blue-600' }}">
                            <i class="fas {{ $l['icon'] }} mr-1"></i>{{ $l['label'] }}
                        </a>
                    @endforeach

                    <div class="relative ml-2" data-menu>
                        <button type="button" data-menu-botao aria-expanded="false" class="flex items-center text-gray-700 hover:text-blue-600">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100"><i class="fas fa-user text-blue-600"></i></span>
                            <i class="fas fa-chevron-down ml-2 text-sm"></i>
                            <span class="sr-only">{{ __('Menu do utilizador') }}</span>
                        </button>
                        <div data-menu-painel hidden class="animate-scale-in absolute right-0 z-20 mt-2 w-48 rounded-md bg-white py-1 shadow-lg">
                            <a href="{{ route('client.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-circle-user mr-2"></i>{{ __('Meu Perfil') }}
                            </a>
                            <form method="POST" action="{{ route('client.logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-right-from-bracket mr-2"></i>{{ __('Sair') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="flex items-center md:hidden" data-menu>
                    <button type="button" data-menu-botao aria-expanded="false" class="p-2 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-bars text-xl"></i>
                        <span class="sr-only">{{ __('Menu') }}</span>
                    </button>
                    <div data-menu-painel hidden class="animate-fade-in absolute left-0 right-0 top-16 z-20 border-t border-gray-100 bg-white py-2 shadow-lg">
                        @foreach($ligacoesDoPortal as $l)
                            <a href="{{ route($l['route']) }}"
                               class="block px-6 py-3 text-sm font-medium {{ request()->routeIs($l['route']) ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                                <i class="fas {{ $l['icon'] }} mr-2 w-5"></i>{{ $l['label'] }}
                            </a>
                        @endforeach
                        <a href="{{ route('client.profile') }}" class="block border-t border-gray-100 px-6 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-circle-user mr-2 w-5"></i>{{ __('Meu Perfil') }}
                        </a>
                        <form method="POST" action="{{ route('client.logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-6 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50">
                                <i class="fas fa-right-from-bracket mr-2 w-5"></i>{{ __('Sair') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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

    <script>
        // Os dois menus do topo: abrem no botão, fecham ao clicar fora ou com Escape.
        (function () {
            var menus = document.querySelectorAll('[data-menu]');
            function fechar(excepto) {
                menus.forEach(function (m) {
                    if (m === excepto) return;
                    m.querySelector('[data-menu-painel]').hidden = true;
                    m.querySelector('[data-menu-botao]').setAttribute('aria-expanded', 'false');
                });
            }
            menus.forEach(function (m) {
                var botao = m.querySelector('[data-menu-botao]');
                var painel = m.querySelector('[data-menu-painel]');
                botao.addEventListener('click', function (e) {
                    e.stopPropagation();
                    fechar(m);
                    painel.hidden = !painel.hidden;
                    botao.setAttribute('aria-expanded', painel.hidden ? 'false' : 'true');
                });
            });
            document.addEventListener('click', function (e) {
                if (!e.target.closest('[data-menu]')) fechar(null);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') fechar(null);
            });
        })();
    </script>

    @include('partials.react-pacote')
    @include('partials.pwa-register')
</body>
</html>

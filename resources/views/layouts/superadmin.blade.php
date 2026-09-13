<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(request()->cookie('casca_aberta') === '0') data-casca="fechada" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ app_name() }} - Super Admin</title>
    @include('partials.favicon')

    <!-- PWA -->
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">

    <!-- Tailwind CSS CDN -->
    <script src="/vendor/js/tailwind.js"></script>

    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    <style>
        /* Modern 2025 Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            }
            50% {
                box-shadow: 0 0 40px rgba(59, 130, 246, 0.8);
            }
        }

        @keyframes shine {
            0% {
                left: -100%;
            }
            100% {
                left: 200%;
            }
        }

        /* A barra lateral — as mesmas animações da aplicação. */
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInStagger {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes logoEntry {
            0% { opacity: 0; transform: scale(0.5) rotate(-10deg); }
            60% { transform: scale(1.1) rotate(5deg); }
            100% { opacity: 1; transform: scale(1) rotate(0deg); }
        }
        aside.first-load { animation: slideInLeft 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        aside.first-load .logo-container { animation: logoEntry 0.8s cubic-bezier(0.34, 1.56, 0.64, 1); }
        aside.first-load nav a { opacity: 0; animation: fadeInStagger 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        aside.first-load nav a:nth-child(1) { animation-delay: 0.1s; }
        aside.first-load nav a:nth-child(2) { animation-delay: 0.15s; }
        aside.first-load nav a:nth-child(3) { animation-delay: 0.2s; }
        aside.first-load nav a:nth-child(4) { animation-delay: 0.25s; }
        aside.first-load nav a:nth-child(n+5) { animation-delay: 0.3s; }
        aside nav a { position: relative; overflow: hidden; }
        aside nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        aside nav a:hover::before { left: 100%; }

        .card-hover {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card-hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
            pointer-events: none;
            z-index: 1;
        }

        .card-hover:hover::before {
            left: 200%;
        }

        .card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-3d {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
        }

        .card-3d:hover {
            transform: translateY(-10px) rotateX(5deg) scale(1.03);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .card-zoom {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }

        .card-zoom:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .card-glow {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .card-glow::after {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6, #ec4899, #f59e0b);
            border-radius: inherit;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
            filter: blur(10px);
        }

        .card-glow:hover::after {
            opacity: 0.7;
        }

        .card-bounce:hover {
            animation: bounce 0.6s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            25% { transform: translateY(-10px); }
            50% { transform: translateY(-5px); }
            75% { transform: translateY(-7px); }
        }

        .card-rotate {
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .card-rotate:hover {
            transform: rotate(-2deg) scale(1.05);
        }

        .stagger-animation > * {
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
        .stagger-animation > *:nth-child(5) { animation-delay: 0.5s; }
        .stagger-animation > *:nth-child(6) { animation-delay: 0.6s; }

        .icon-float {
            transition: transform 0.3s ease;
        }

        .card-hover:hover .icon-float {
            transform: translateY(-5px) scale(1.1);
        }

        .gradient-shift {
            background-size: 200% 200%;
            transition: background-position 0.5s ease;
        }

        .gradient-shift:hover {
            background-position: right center;
        }
    </style>
    @include('partials.react-animacoes')
</head>
<body class="bg-gray-50">
    {{--
        O PAINEL DA PLATAFORMA — a mesma casca da aplicação, em React.

        Era uma barra lateral em Blade com Alpine, jQuery e toastr. O menu sai
        agora do MenuDaCasca::daPlataforma (as áreas do dono da plataforma) e é
        desenhado pelo mesmo ecrã `casca`: encolher, telemóvel, o menu do
        utilizador — e as ligações «Perfil» e «Configurações», que apontavam
        para «#», passaram a levar a algum lado.
    --}}
    <div class="flex h-screen overflow-hidden">
        <x-ecra-react nome="casca" :esqueleto="false" class="casca-lugar flex flex-none" :props="[
            'menu' => \App\Support\MenuDaCasca::daPlataforma(auth()->user(), request()),
            'logo' => app_logo(),
            'nome' => app_name(),
            'csrf' => csrf_token(),
        ]" />

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-3 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center gap-3">
                        <x-ecra-react nome="casca/alternar" :esqueleto="false" class="flex flex-none items-center min-w-[2.5rem] lg:min-w-[1.5rem]" />
                        <div>
                            <h1 class="text-lg sm:text-2xl font-bold text-gray-900">@yield('title', 'Dashboard')</h1>
                            <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">@yield('subtitle', __('Área de administração'))</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 sm:space-x-4">
                        <x-ecra-react nome="casca/lingua" :esqueleto="false" class="min-w-[3.5rem]" :props="['actual' => app()->getLocale()]" />
                        <x-ecra-react nome="casca/notificacoes" :esqueleto="false" />
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-6">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
    </div>

    {{-- Avisos de canto (e os recados da sessão, que eram toastr), sessão
         expirada, barra de progresso e service worker. --}}
    <x-ecra-react nome="casca/sistema" :esqueleto="false" :props="[
        'login' => route('login'),
        'manterViva' => url('/keep-alive'),
        'recados' => \App\Support\RecadosDaSessao::lista(),
    ]" />

    {{-- OS ECRÃS EM REACT. O painel da plataforma tem layout próprio e precisa
         do mesmo pacote que o resto da aplicação. --}}
    @include('partials.react-pacote')
</body>
</html>

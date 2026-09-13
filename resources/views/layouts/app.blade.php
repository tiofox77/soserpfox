<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(request()->cookie('casca_aberta') === '0') data-casca="fechada" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta Tags -->
    <title>{{ $title ?? config('app.name', 'SOS ERP') }} | Sistema de Gestão Empresarial</title>
    <meta name="description" content="SOS ERP - Sistema completo de gestão empresarial. Gerencie eventos, inventário, CRM, faturação e muito mais. Solução profissional para empresas em Angola.">
    <meta name="keywords" content="ERP, gestão empresarial, sistema de gestão, Angola, eventos, inventário, CRM, faturação, contabilidade">
    <meta name="author" content="SOS ERP">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ config('app.name', 'SOS ERP') }} - Sistema de Gestão Empresarial">
    <meta property="og:description" content="Sistema completo de gestão empresarial. Gerencie eventos, inventário, CRM, faturação e muito mais.">
    <meta property="og:image" content="{{ app_logo() ?? asset('images/logo.png') }}">
    <meta property="og:site_name" content="{{ config('app.name', 'SOS ERP') }}">
    <meta property="og:locale" content="pt_AO">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="{{ config('app.name', 'SOS ERP') }} - Sistema de Gestão Empresarial">
    <meta name="twitter:description" content="Sistema completo de gestão empresarial. Gerencie eventos, inventário, CRM, faturação e muito mais.">
    <meta name="twitter:image" content="{{ app_logo() ?? asset('images/logo.png') }}">
    
    <!-- Favicon -->
    @include('partials.favicon')
    @include('partials.meta-pixel')
    
    <!-- PWA -->
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ function_exists('app_name') ? app_name() : config('app.name', 'SOS ERP') }}">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Prevenir FOUC: Força tamanhos de imagem antes de qualquer script -->
    <style>
        /* Crítico: carrega ANTES de qualquer framework.
           SÓ os logótipos da moldura (barra lateral e topo). Valia para TODAS
           as imagens de /storage/ e apanhava as fotos dos artigos no POS: 4rem
           de altura e encolhidas, tortas dentro do cartão. */
        aside img[src*="/storage/"], header img[src*="/storage/"], .sidebar img[src*="/storage/"] {
            max-height: 4rem !important;
            max-width: 200px !important;
            object-fit: contain !important;
        }
        aside img, .sidebar img {
            max-height: 4rem !important;
            max-width: 200px !important;
            height: auto !important;
            width: auto !important;
        }
    </style>

    <!-- Tailwind CSS CDN -->
    <script src="/vendor/js/tailwind.js"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">
    
    <style>
        /* Prevenir FOUC nos logótipos da moldura (ver o bloco crítico acima) */
        aside img[src*="/storage/"], header img[src*="/storage/"] {
            max-height: 4rem !important;
            max-width: 100% !important;
        }
        
        /* Logo sempre com tamanho controlado */
        aside img {
            max-height: 4rem !important;
            max-width: 200px !important;
            object-fit: contain !important;
        }
        
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

        /* As animações dos ecrãs React (modais, entrada em cascata, movimento
           reduzido) vivem em partials/react-animacoes — incluído no <head>. */
        
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
        
        /* Animação Sidebar - Slide In from Left */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Animação para itens de menu - Fade In sequencial */
        @keyframes fadeInStagger {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Animação do Logo - Bounce suave */
        @keyframes logoEntry {
            0% {
                opacity: 0;
                transform: scale(0.5) rotate(-10deg);
            }
            60% {
                transform: scale(1.1) rotate(5deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }
        
        /* Sidebar animations - only on first load, not on SPA navigation */
        aside.first-load {
            animation: slideInLeft 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        aside.first-load .logo-container {
            animation: logoEntry 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        aside.first-load nav a {
            opacity: 0;
            animation: fadeInStagger 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        
        aside.first-load nav a:nth-child(1) { animation-delay: 0.1s; }
        aside.first-load nav a:nth-child(2) { animation-delay: 0.15s; }
        aside.first-load nav a:nth-child(3) { animation-delay: 0.2s; }
        aside.first-load nav a:nth-child(4) { animation-delay: 0.25s; }
        aside.first-load nav a:nth-child(5) { animation-delay: 0.3s; }
        aside.first-load nav a:nth-child(6) { animation-delay: 0.35s; }
        aside.first-load nav a:nth-child(7) { animation-delay: 0.4s; }
        aside.first-load nav a:nth-child(8) { animation-delay: 0.45s; }
        aside.first-load nav a:nth-child(9) { animation-delay: 0.5s; }
        aside.first-load nav a:nth-child(10) { animation-delay: 0.55s; }
        aside.first-load nav a:nth-child(n+11) { animation-delay: 0.6s; }
        
        /* Animação do conteúdo principal - Fade In */
        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        main.first-load {
            animation: fadeInContent 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.3s backwards;
        }
        
        /* Efeito de brilho no hover dos itens de menu */
        aside nav a {
            position: relative;
            overflow: hidden;
        }
        
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
        
        aside nav a:hover::before {
            left: 100%;
        }
        
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
        
        main.first-load .stagger-animation > * {
            animation: fadeInUp 0.6s ease-out backwards;
        }
        
        main.first-load .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        main.first-load .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        main.first-load .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        main.first-load .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
        main.first-load .stagger-animation > *:nth-child(5) { animation-delay: 0.5s; }
        main.first-load .stagger-animation > *:nth-child(6) { animation-delay: 0.6s; }
        
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
        
        /* Fox Easter Egg Animations */
        @keyframes foxFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-8px);
            }
        }
        
        @keyframes foxWiggle {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(-5deg);
            }
            75% {
                transform: rotate(5deg);
            }
        }
        
        .fox-paw {
            animation: foxWiggle 2s ease-in-out infinite;
            display: inline-block;
        }
    </style>
    @include('partials.react-animacoes')
</head>
<body class="bg-gray-50">
    {{-- Banner de licença (só na build offline; no-op na cloud). --}}
    @includeWhen(!empty($licencaEstado), 'partials.licenca-banner')

    @auth
        @php
            // O menu decide-se uma vez por pedido — o Blade e o ecrã em React desenham-no daqui.
            $menuDaCasca = \App\Support\MenuDaCasca::montar(auth()->user(), request());
        @endphp
        @php
            // A entrada do conteúdo em cascata só na primeira página da sessão:
            // cada ligação abre uma página nova, e a cascata a cada clique cansa.
            $primeiraVista = ! session('casca_vista');
            if ($primeiraVista) session()->put('casca_vista', true);
        @endphp
        <!-- Layout with Sidebar -->
        <div class="flex h-screen overflow-hidden">
            {{--
                A BARRA LATERAL — o ecrã `casca`, em React.

                O menu sai do MenuDaCasca (quem vê o quê, por que ordem); o ecrã
                sabe a forma: abrir e fechar grupos, encolher, o menu do
                utilizador, o véu no telemóvel. Até o JavaScript chegar, o lugar
                fica com a largura e a cor da barra (ver `.casca-lugar`), para a
                página não saltar para o lado quando ela aparece.
            --}}
            <x-ecra-react nome="casca" :esqueleto="false" class="casca-lugar flex flex-none" :props="[
                'menu' => $menuDaCasca,
                'logo' => app_logo(),
                'nome' => app_name(),
                'csrf' => csrf_token(),
            ]" />

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Bar -->
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="flex items-center justify-between px-3 sm:px-6 py-3 sm:py-4">
                        <div class="flex items-center gap-3">
                            {{-- Abrir e fechar a barra lateral — fala com ela por evento. --}}
                            <x-ecra-react nome="casca/alternar" :esqueleto="false" class="flex flex-none items-center min-w-[2.5rem] lg:min-w-[1.5rem]" />
                            <div>
                                <h1 class="text-lg sm:text-2xl font-bold text-gray-900 truncate max-w-[200px] sm:max-w-none">@yield('page-title', __('Dashboard'))</h1>
                                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">@yield('page-subtitle', __('Bem-vindo ao sistema'))</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 sm:space-x-4">
                            <!-- Tenant Switcher (Sempre mostra empresa ativa) -->
                            @if(auth()->check() && !auth()->user()->isSuperAdmin())
                                <div class="hidden sm:block">
                                    <x-ecra-react nome="casca/empresa" :esqueleto="false" />
                                </div>
                            @endif
                            
                            <!-- Subscription Timer -->
                            @if(auth()->check())
                                <div class="hidden md:block">
                                    <x-ecra-react nome="casca/subscricao" :esqueleto="false" />
                                </div>
                            @endif
                            
                            <!-- Easter Egg: Fox Paw in Header (FOX Friendly Only) -->
                            @if($menuDaCasca['fox'] ?? false)
                                <div class="group relative ml-3 hidden sm:block cursor-pointer" title="{{ __('FOX Friendly activo!') }}">
                                    <span class="fox-paw text-xl">🐾</span>
                                    <div class="pointer-events-none absolute top-full right-0 mt-2 px-3 py-2 bg-orange-500 text-white text-xs rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 -translate-y-1 group-hover:opacity-100 group-hover:translate-y-0 transition duration-200">
                                        <div class="font-bold">🦊 FOX Power!</div>
                                        <div class="absolute bottom-full right-4 mb-[-4px]">
                                            <div class="border-4 border-transparent border-b-orange-500"></div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Língua do ecrã. Um GET com ?lang= chega ao
                                 DefinirLingua, que guarda no perfil e no
                                 cookie — sem componente, sem estado. --}}
                            <x-ecra-react nome="casca/lingua" :esqueleto="false" class="min-w-[3.5rem]" :props="['actual' => app()->getLocale()]" />

                            <!-- Notificações -->
                            @if(auth()->check())
                                <x-ecra-react nome="casca/notificacoes" :esqueleto="false" />
                            @endif
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main id="app-main" class="{{ $primeiraVista ? 'first-load ' : '' }}flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-4 lg:p-6">
                    {{-- Avisos do dono da plataforma (manutenções, mudanças de
                         preço, obrigações novas da AGT). Vive aqui porque tem
                         de aparecer em qualquer página; sem mensagens no ar,
                         custa uma leitura de cache. --}}
                    <x-ecra-react nome="casca/mensagens" :esqueleto="false" />

                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <!-- Guest Layout (No Sidebar) -->
        <div class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    @endauth

    @auth
        {{-- O que corria em <script> soltos: avisos de canto, sessão expirada,
             barra de progresso, PDF do ecrã e service worker. --}}
        <x-ecra-react nome="casca/sistema" :esqueleto="false" :props="[
            'login' => route('login'),
            'manterViva' => url('/keep-alive'),
            // Na página inicial o `status`/`success` já aparece no próprio ecrã.
            'recados' => \App\Support\RecadosDaSessao::lista(request()->routeIs('home') ? ['status', 'success'] : []),
        ]" />

        @unless(auth()->user()->isSuperAdmin())
            <x-ecra-react nome="casca/suporte" :esqueleto="false" :props="[
                'tickets' => route('support.tickets'),
                'melhorias' => route('support.features'),
            ]" />
        @endunless
    @endauth

    {{-- OS ECRÃS EM REACT. O bloco vive num partial porque o painel da
         plataforma tem layout próprio e precisa do mesmo. --}}
    @include('partials.react-pacote')

    @stack('scripts')
</body>
</html>

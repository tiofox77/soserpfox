<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        /* Crítico: carrega ANTES de qualquer framework */
        img[src*="/storage/"] {
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
    
    <!-- Livewire Styles -->
    @livewireStyles
    
    <style>
        [x-cloak] { display: none !important; }

        /* Disable transitions/animations during SPA navigation morph */
        body.is-navigating *:not(.spa-progress) {
            transition-duration: 0s !important;
            animation-duration: 0s !important;
            animation-delay: 0s !important;
        }

        /* SPA Navigation Progress Bar */
        .spa-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
            z-index: 99999;
            transition: width 0.3s ease;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.7);
        }
        .spa-progress.done {
            transition: width 0.1s ease, opacity 0.4s ease 0.1s;
            opacity: 0;
        }
        
        /* Prevenir FOUC (Flash of Unstyled Content) em imagens */
        img[src*="/storage/"] {
            max-height: 4rem !important;
            max-width: 100% !important;
        }
        
        /* Logo sempre com tamanho controlado */
        aside img {
            max-height: 4rem !important;
            max-width: 200px !important;
            object-fit: contain !important;
        }
        
        /* Toastr Custom Styles - Barra colorida apenas em cima */
        #toast-container > div {
            background-color: #ffffff !important;
            color: #1f2937 !important;
            border-top: 4px solid #3b82f6 !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        }
        
        .toast-success {
            border-top-color: #3b82f6 !important;
        }
        
        .toast-success .toast-message {
            color: #1f2937 !important;
        }
        
        .toast-success:before {
            color: #3b82f6 !important;
        }
        
        .toast-error {
            border-top-color: #ef4444 !important;
        }
        
        .toast-error .toast-message {
            color: #1f2937 !important;
        }
        
        .toast-error:before {
            color: #ef4444 !important;
        }
        
        .toast-warning {
            border-top-color: #f59e0b !important;
        }
        
        .toast-warning .toast-message {
            color: #1f2937 !important;
        }
        
        .toast-warning:before {
            color: #f59e0b !important;
        }
        
        .toast-info {
            border-top-color: #3b82f6 !important;
        }
        
        .toast-info .toast-message {
            color: #1f2937 !important;
        }
        
        .toast-info:before {
            color: #3b82f6 !important;
        }
        
        #toast-container > div:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2) !important;
        }
        
        .toast-close-button {
            color: #6b7280 !important;
        }
        
        .toast-progress {
            opacity: 0.3 !important;
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

        /* Modal — Fade In simples (backdrop) */
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .animate-fade-in { animation: modalFadeIn .18s ease-out both; }

        /* Modal — Scale In (conteúdo) */
        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(.94) translateY(8px); }
            to   { opacity: 1; transform: scale(1)   translateY(0);   }
        }
        .animate-scale-in { animation: modalScaleIn .22s cubic-bezier(.2,.9,.3,1.2) both; }

        /* Botão — pressão táctil + ripple suave */
        .btn-press { transition: transform .12s ease, box-shadow .15s ease, filter .15s ease; }
        .btn-press:hover { filter: brightness(1.04); }
        .btn-press:active { transform: scale(.96); }

        /* wire:loading global — leve fade na própria zona de loading */
        [wire\:loading].animate-pop { animation: modalScaleIn .18s ease-out both; }
        
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
</head>
<body class="bg-gray-50">
    {{-- Banner de licença (só na build offline; no-op na cloud). --}}
    @includeWhen(!empty($licencaEstado), 'partials.licenca-banner')

    @auth
        @php
            // O menu decide-se uma vez por pedido — o Blade e o ecrã em React desenham-no daqui.
            $menuDaCasca = \App\Support\MenuDaCasca::montar(auth()->user(), request());
        @endphp
        <!-- Layout with Sidebar -->
        <div x-data="{
            sidebarOpen: window.innerWidth >= 1024,
            isMobile: window.innerWidth < 768,
            isTablet: window.innerWidth >= 768 && window.innerWidth < 1024,
            firstLoad: true,
            init() {
                this.handleResize();
                window.addEventListener('resize', () => this.handleResize());
                setTimeout(() => { this.firstLoad = false; }, 1200);
            },
            handleResize() {
                this.isMobile = window.innerWidth < 768;
                this.isTablet = window.innerWidth >= 768 && window.innerWidth < 1024;
                if (this.isMobile) {
                    this.sidebarOpen = false;
                } else if (this.isTablet) {
                    this.sidebarOpen = false;
                }
            },
            closeMobileSidebar() {
                if (this.isMobile) this.sidebarOpen = false;
            }
        }" class="flex h-screen overflow-hidden">
            <!-- Mobile Backdrop -->
            <div x-show="sidebarOpen && isMobile"
                 x-transition:enter="transition-opacity ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="sidebarOpen = false"
                 class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 lg:hidden"
                 x-cloak></div>

            <!-- Sidebar -->
            @if(session('casca_react'))
            {{-- A CASCA EM REACT, em ensaio: a barra lateral inteira sai do
                 ecra `casca`, com o mesmo MenuDaCasca. Liga-se por sessão em
                 /casca/novo-ecra e desliga-se em /casca/ecra-de-sempre. A
                 barra do topo fica em Blade — tem componentes Livewire. --}}
            <x-ecra-react nome="casca" class="flex flex-none" :props="[
                'menu' => $menuDaCasca,
                'logo' => app_logo(),
                'nome' => app_name(),
                'csrf' => csrf_token(),
                'voltar' => route('casca.livewire'),
            ]" />
            @else
            <aside id="app-sidebar" :class="{
                    'first-load': firstLoad,
                    'w-64': sidebarOpen,
                    'w-20': !sidebarOpen && !isMobile,
                    'w-0 -translate-x-full': !sidebarOpen && isMobile,
                    'w-64 translate-x-0': sidebarOpen && isMobile,
                    'fixed inset-y-0 left-0 z-50': isMobile
                }" class="bg-gradient-to-b from-blue-900 to-blue-800 text-white transition-[width,transform] duration-300 flex flex-col shadow-2xl overflow-hidden">
                <!-- Logo -->
                <div class="flex items-center justify-between p-4 border-b border-blue-700 logo-container">
                    <div class="flex items-center justify-center" :class="sidebarOpen ? 'w-full' : ''">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" 
                                 alt="{{ app_name() }}" 
                                 style="max-height: 4rem; max-width: 200px;"
                                 class="w-auto object-contain transition-[height,width] duration-300"
                                 :class="sidebarOpen ? 'h-16' : 'h-12 w-12'">
                        @else
                            <div :class="sidebarOpen ? 'w-12 h-12' : 'w-10 h-10'" class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center shadow-lg transition-[width,height] duration-300">
                                <i class="fas fa-crown text-white transition-[font-size] duration-300" :class="sidebarOpen ? 'text-2xl' : 'text-xl'"></i>
                            </div>
                        @endif
                    </div>
                    <!-- Close button on mobile -->
                    <button @click="sidebarOpen = false" x-show="isMobile" class="text-blue-300 hover:text-white transition ml-2 p-1">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <!-- Menu -->
                <nav id="sidebar-menu" class="flex-1 overflow-y-auto py-4">
                    {{-- O menu vem do MenuDaCasca — o mesmo que o ecrã em React desenha.
                         Uma ligação nova entra lá, não aqui. --}}
                    @include('partials.casca.menu', ['menu' => $menuDaCasca])
                </nav>

                <!-- Easter Egg: FOX Friendly -->
                @php
                    $tenant = auth()->user()->activeTenant();
                    $subscription = $tenant ? $tenant->activeSubscription : null;
                    $plan = $subscription ? $subscription->plan : null;
                    $isFoxFriendly = $plan && str_contains(strtolower($plan->slug), 'fox');
                @endphp
                
                @if($isFoxFriendly)
                <div class="border-t border-blue-700 px-4 py-3">
                    <div x-data="{ foxHover: false }" 
                         @mouseenter="foxHover = true" 
                         @mouseleave="foxHover = false"
                         class="relative cursor-help">
                        <div class="flex items-center justify-center">
                            <div class="text-3xl transition-transform duration-300" 
                                 :class="foxHover ? 'scale-125' : 'scale-100'"
                                 style="animation: foxFloat 3s ease-in-out infinite;">
                                🦊
                            </div>
                        </div>
                        <div x-show="foxHover && sidebarOpen" 
                             x-transition
                             class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs rounded-lg shadow-lg whitespace-nowrap">
                            <div class="font-bold">🦊 FOX Friendly Active!</div>
                            <div class="text-xs opacity-90">3 meses grátis • Todos os módulos</div>
                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 -mt-1">
                                <div class="border-4 border-transparent border-t-red-500"></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Suporte Menu -->
                <div class="mt-auto border-t border-blue-700 pt-4">
                    <a href="{{ route('support.tickets') }}" 
                       class="flex items-center px-4 py-3 {{ request()->routeIs('support.*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition group">
                        <i class="fas fa-life-ring text-2xl text-purple-400"></i>
                        <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">{{ __('Suporte') }}</span>
                        <span x-show="sidebarOpen" class="ml-auto text-xs bg-purple-500 px-2 py-1 rounded-full">{{ __('Novo') }}</span>
                    </a>
                </div>

                <!-- User Menu -->
                <div class="border-t border-blue-700 p-4">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center w-full text-left hover:bg-blue-700/50 rounded-lg p-2 transition">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-lg">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div x-show="sidebarOpen" class="ml-3 flex-1">
                                <p class="text-sm font-medium">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-blue-300">{{ auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Utilizador' }}</p>
                            </div>
                            <i x-show="sidebarOpen" class="fas fa-chevron-up text-sm" :class="open ? '' : 'rotate-180'"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute bottom-full left-0 mb-2 w-full bg-white rounded-lg shadow-xl py-2">
                            <a href="{{ route('my-account') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2 text-blue-600"></i> Minha Conta
                            </a>
                            {{-- Os dados da empresa — NIF, morada e regime fiscal —
                                 são de quem a gere. O link deixou de aparecer a
                                 quem a página recusa. --}}
                            @can('settings.view')
                            <a href="{{ route('company.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-building mr-2 text-indigo-600"></i> Dados da Empresa
                            </a>
                            @endcan
                            @if(auth()->user()->canManageAccount())
                                <a href="{{ route('my-account') }}?tab=companies" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-building mr-2 text-purple-600"></i> Minhas Empresas
                                </a>
                                <a href="{{ route('my-account') }}?tab=plan" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-crown mr-2 text-yellow-600"></i> Meu Plano
                                </a>
                            @endif
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="{{ route('changelog') }}" class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <span><i class="fas fa-rocket mr-2 text-indigo-600"></i> Atualizações</span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">v{{ config('changelog.current', '1.0') }}</span>
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Sair
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>
            @endif

            <!-- Add wire:navigate to all internal sidebar links (runs before Livewire boots) -->
            <script>
                (function() {
                    function addWireNavigate() {
                        document.querySelectorAll('#sidebar-menu a[href], aside a[href]').forEach(function(link) {
                            if (!link.href || !link.href.startsWith(window.location.origin)) return;
                            if (link.closest('form')) return;
                            if (link.getAttribute('href') === '#') return;
                            if (link.hasAttribute('wire:navigate')) return;
                            link.setAttribute('wire:navigate', '');
                        });
                    }
                    addWireNavigate();
                    document.addEventListener('livewire:navigated', addWireNavigate);
                })();
            </script>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden" :class="{ 'ml-0': isMobile }">
                <!-- Top Bar -->
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="flex items-center justify-between px-3 sm:px-6 py-3 sm:py-4">
                        <div class="flex items-center gap-3">
                            <!-- Hamburger Button (Mobile/Tablet) -->
                            <button @click="sidebarOpen = !sidebarOpen; window.dispatchEvent(new CustomEvent('casca:alternar'))" class="lg:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100 transition">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            <!-- Desktop sidebar toggle -->
                            <button @click="sidebarOpen = !sidebarOpen; window.dispatchEvent(new CustomEvent('casca:alternar'))" class="hidden lg:block text-gray-400 hover:text-gray-600 p-1 rounded transition">
                                <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                            </button>
                            <div>
                                <h1 class="text-lg sm:text-2xl font-bold text-gray-900 truncate max-w-[200px] sm:max-w-none">@yield('page-title', __('Dashboard'))</h1>
                                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">@yield('page-subtitle', __('Bem-vindo ao sistema'))</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 sm:space-x-4">
                            <!-- Tenant Switcher (Sempre mostra empresa ativa) -->
                            @if(auth()->check() && !auth()->user()->isSuperAdmin())
                                <div class="hidden sm:block">
                                    <livewire:tenant-switcher />
                                </div>
                            @endif
                            
                            <!-- Subscription Timer -->
                            @if(auth()->check())
                                <div class="hidden md:block">
                                    <livewire:subscription-timer />
                                </div>
                            @endif
                            
                            <!-- Easter Egg: Fox Paw in Header (FOX Friendly Only) -->
                            @if($isFoxFriendly ?? false)
                                <div class="ml-3 hidden sm:block" 
                                     x-data="{ showFoxMessage: false }"
                                     @mouseenter="showFoxMessage = true"
                                     @mouseleave="showFoxMessage = false"
                                     title="{{ __('FOX Friendly activo!') }}">
                                    <div class="relative cursor-pointer">
                                        <span class="fox-paw text-xl">🐾</span>
                                        <div x-show="showFoxMessage"
                                             x-transition
                                             class="absolute top-full right-0 mt-2 px-3 py-2 bg-orange-500 text-white text-xs rounded-lg shadow-xl whitespace-nowrap z-50">
                                            <div class="font-bold">🦊 FOX Power!</div>
                                            <div class="absolute bottom-full right-4 mb-[-4px]">
                                                <div class="border-4 border-transparent border-b-orange-500"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Língua do ecrã. Um GET com ?lang= chega ao
                                 DefinirLingua, que guarda no perfil e no
                                 cookie — sem componente, sem estado. --}}
                            <div class="relative" x-data="{ aberto: false }" @click.outside="aberto = false">
                                <button @click="aberto = !aberto"
                                        class="flex items-center gap-1.5 px-2.5 py-2 text-sm font-bold text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition uppercase"
                                        title="Língua / Language / Langue">
                                    <i class="fas fa-globe text-gray-400"></i>{{ app()->getLocale() }}
                                </button>
                                <div x-show="aberto" x-transition x-cloak
                                     class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-50 min-w-[10rem]">
                                    @foreach(['pt' => 'Português', 'en' => 'English', 'fr' => 'Français'] as $sigla => $nome)
                                        <a href="{{ request()->fullUrlWithQuery(['lang' => $sigla]) }}"
                                           class="flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 transition {{ app()->getLocale() === $sigla ? 'font-bold text-blue-700' : 'text-gray-700' }}">
                                            {{ $nome }}
                                            @if(app()->getLocale() === $sigla)
                                                <i class="fas fa-check text-blue-600 text-xs"></i>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Notificações -->
                            @if(auth()->check())
                                <livewire:notifications />
                            @endif
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main id="app-main" :class="{ 'first-load': firstLoad }" class="flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-4 lg:p-6">
                    {{-- Avisos do dono da plataforma (manutenções, mudanças de
                         preço, obrigações novas da AGT). Vive aqui porque tem
                         de aparecer em qualquer página; sem mensagens no ar,
                         custa uma leitura de cache. --}}
                    @livewire('mensagens-da-plataforma')

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

    {{-- O dicionário para o JavaScript dos ecrãs (POS, modais de impressão).
         Em português não emite nada — as chaves são o texto português. --}}
    @include('partials.js-traducoes')

    <!-- Livewire Scripts (já inclui Alpine.js V3) -->
    @livewireScripts
    
    <!-- Toastr CDN -->
    <link rel="stylesheet" href="/vendor/css/toastr.min.css">
    <script src="/vendor/js/jquery.min.js"></script>
    <script src="/vendor/js/toastr.min.js"></script>

    {{-- Máscara de dinheiro (1.234,56) nos inputs de preço/valores. Delegada,
         sobrevive aos re-render do Livewire. --}}
    <script src="{{ asset('js/mascara-dinheiro.js') }}?v=2" defer></script>

    <script>
        // Configuração do Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        // Configuração global do Livewire - Listeners para notificações
        document.addEventListener('livewire:init', () => {
            // ── Recuperação de sessão expirada — sem freeze, sem perder a venda ──
            // Problema: quando a sessão morre por inatividade, um pedido Livewire volta
            // como 419/401 (ver bootstrap/app.php). O comportamento antigo (reload cego)
            // perdia a venda em curso no POS. Agora: numa página POS mostra-se um overlay
            // claro (o carrinho fica guardado no cliente e é restaurado após novo login);
            // noutras páginas faz-se apenas um reload suave.
            window.__sessionDeadShown = false;
            window.sosSessionDead = function (reason) {
                if (window.__sessionDeadShown) return;
                window.__sessionDeadShown = true;
                var overlay = document.getElementById('sos-session-expired');
                if (window.__isPOS && overlay) {
                    overlay.style.display = 'flex';           // bloqueia o POS com mensagem + botão login
                } else {
                    try { toastr.warning('A sessão expirou — a recarregar…', '', { timeOut: 1500 }); } catch (_) {}
                    setTimeout(function () { window.location.reload(); }, 900);
                }
            };

            // Injetar o overlay (inerte até window.__isPOS o mostrar via sosSessionDead)
            (function () {
                if (document.getElementById('sos-session-expired')) return;
                var o = document.createElement('div');
                o.id = 'sos-session-expired';
                o.style.cssText = 'display:none;position:fixed;inset:0;z-index:100000;background:rgba(17,24,39,.88);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:20px;';
                o.innerHTML =
                    '<div style="background:#fff;border-radius:16px;padding:32px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.45);font-family:inherit;">'
                    + '<div style="font-size:44px;line-height:1;margin-bottom:12px;">&#128274;</div>'
                    {{-- As entidades HTML (&atilde;) sairam de cena: o @json
                         entrega uma cadeia JavaScript ja escapada, portanto os
                         acentos podem vir inteiros — e em frances havia
                         demasiados para os escrever assim um a um. --}}
                    + '<h2 style="font-size:20px;font-weight:800;margin:0 0 8px;color:#111827;">' + @json(__('Sessão expirada')) + '</h2>'
                    + '<p style="color:#4b5563;margin:0 0 20px;font-size:14px;line-height:1.5;">' + @json(__('Por inatividade, a sua sessão terminou. <strong>O carrinho foi guardado</strong> e será restaurado assim que iniciar sessão novamente.')) + '</p>'
                    + '<a href="{{ route('login') }}" style="display:inline-block;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;padding:12px 26px;border-radius:10px;font-weight:700;text-decoration:none;">' + @json(__('Iniciar sessão')) + '</a>'
                    + '</div>';
                document.body.appendChild(o);
            })();

            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    // 419 = CSRF/página expirada · 401 = sessão terminada (bootstrap/app.php)
                    if (status === 419 || status === 401) {
                        preventDefault();                 // impede o Livewire de interpretar a resposta (evita o freeze)
                        window.sosSessionDead('livewire-' + status);
                        return;
                    }

                    // 409 = separador aberto de antes de um deploy: o snapshot do
                    // componente já não bate certo com o código (ver bootstrap/app.php).
                    // A sessão está boa — recarrega-se a página no mesmo sítio, em vez
                    // de mostrar um 500 que não diz nada nem dá saída.
                    if (status === 409) {
                        preventDefault();

                        if (!window.__sosRecarregando) {
                            window.__sosRecarregando = true;
                            window.location.reload();
                        }
                    }
                });
            });

            // ── Keep-alive robusto (5 min) + ping ao voltar a ficar visível ──
            // Mantém o cookie de sessão vivo com o separador aberto e reage a sleep/lock
            // (o timer é estrangulado em background; o visibilitychange apanha o "acordar").
            // Mantém o guard document.hidden para não sobrecarregar as sessões-BD.
            window.__lastPing = 0;
            window.sosKeepAlive = function () {
                var now = (window.performance && performance.now) ? performance.now() : 0;
                if (now && (now - window.__lastPing) < 30000) return;   // no máx. 1 ping / 30s
                window.__lastPing = now;
                fetch('{{ url('/keep-alive') }}', {
                    method: 'GET', credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store', redirect: 'manual'
                }).then(function (res) {
                    // redirect:'manual' → 302 (sessão morta) vem como type 'opaqueredirect' (status 0)
                    if (res.type === 'opaqueredirect' || res.status === 401 || res.status === 419) {
                        window.sosSessionDead('keepalive');
                    }
                }).catch(function () {});
            };
            setInterval(function () { if (!document.hidden) window.sosKeepAlive(); }, 5 * 60 * 1000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) window.sosKeepAlive(); });

            // Listener para notificações de sucesso
            Livewire.on('success', (event) => {
                toastr.success(event.message || event[0].message || 'Operação realizada com sucesso!');
            });
            
            // Listener para notificações de erro
            Livewire.on('error', (event) => {
                toastr.error(event.message || event[0].message || 'Ocorreu um erro!');
            });
            
            // Listener para notificações de aviso
            Livewire.on('warning', (event) => {
                toastr.warning(event.message || event[0].message || 'Atenção!');
            });
            
            // Listener para notificações de informação
            Livewire.on('info', (event) => {
                toastr.info(event.message || event[0].message || 'Informação!');
            });
            
            // ── Auto-focus no primeiro campo com erro de validação ───────────
            // Disparar via $this->dispatch('focus-first-error', field: 'name')
            // a partir de qualquer componente Livewire após captar uma
            // ValidationException. Faz scroll suave + foco no input correspondente.
            Livewire.on('focus-first-error', (event) => {
                const data = event[0] || event;
                const field = data.field || data;
                if (!field || typeof field !== 'string') return;

                // Pequeno delay para o DOM atualizar (mensagens de erro renderizadas)
                setTimeout(() => {
                    // Procura inputs com wire:model="<field>" ou variantes (.live, .blur, etc.)
                    const selectors = [
                        `[wire\\:model="${field}"]`,
                        `[wire\\:model\\.live="${field}"]`,
                        `[wire\\:model\\.blur="${field}"]`,
                        `[wire\\:model\\.lazy="${field}"]`,
                        `[wire\\:model\\.defer="${field}"]`,
                        `[name="${field}"]`,
                        `#${field}`,
                    ];
                    let el = null;
                    for (const sel of selectors) {
                        try { el = document.querySelector(sel); } catch (e) { el = null; }
                        if (el) break;
                    }
                    if (!el) return;
                    try {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.focus({ preventScroll: true });
                        // Realce visual breve
                        el.classList.add('ring-2', 'ring-red-500');
                        setTimeout(() => el.classList.remove('ring-2', 'ring-red-500'), 2000);
                    } catch (e) {}
                }, 80);
            });

            // Listener único para notificações (evita duplicação) - Mantido para compatibilidade
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Notificação';
                
                // Prevenir duplicação
                toastr.remove();
                
                if (type === 'success') {
                    toastr.success(message);
                } else if (type === 'error') {
                    toastr.error(message);
                } else if (type === 'warning') {
                    toastr.warning(message);
                } else {
                    toastr.info(message);
                }
            });
        });
    </script>
    
    <!-- Sidebar Scroll Memory -->
    <script>
        (function() {
            const scrollKey = 'sidebar-scroll-position';

            function initSidebarScroll() {
                const sidebarMenu = document.getElementById('sidebar-menu');
                if (!sidebarMenu) return;

                // Restaurar posição do scroll
                const saved = localStorage.getItem(scrollKey);
                if (saved !== null) {
                    sidebarMenu.scrollTop = parseInt(saved, 10);
                }

                // Salvar posição do scroll (debounced)
                let scrollTimeout;
                sidebarMenu.addEventListener('scroll', function() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        localStorage.setItem(scrollKey, sidebarMenu.scrollTop);
                    }, 100);
                });
            }

            // On initial page load
            document.addEventListener('DOMContentLoaded', function() {
                initSidebarScroll();
            });

            // After SPA navigation: restore sidebar scroll
            document.addEventListener('livewire:navigated', function() {
                initSidebarScroll();
            });
        })();
    </script>
    
    <!-- SPA Navigation Progress Bar -->
    <div id="spa-progress" class="spa-progress" style="width: 0%;"></div>
    
    <!-- SPA Navigation (wire:navigate) - Progress Bar & Transition Control -->
    <script>
        (function() {
            const bar = document.getElementById('spa-progress');
            let progressInterval;
            
            // Show progress bar + disable transitions on navigation start
            document.addEventListener('livewire:navigate:start', () => {
                // Disable all transitions during morph to prevent visual glitches
                document.body.classList.add('is-navigating');
                
                // Save sidebar scroll position
                const sidebarMenu = document.getElementById('sidebar-menu');
                if (sidebarMenu) {
                    localStorage.setItem('sidebar-scroll-position', sidebarMenu.scrollTop);
                }
                
                // Progress bar
                bar.classList.remove('done');
                bar.style.width = '0%';
                let w = 0;
                clearInterval(progressInterval);
                progressInterval = setInterval(() => {
                    w += (95 - w) * 0.1;
                    bar.style.width = w + '%';
                    if (w >= 94) clearInterval(progressInterval);
                }, 80);
            });
            
            // Complete progress bar + re-enable transitions after morph
            document.addEventListener('livewire:navigated', () => {
                clearInterval(progressInterval);
                bar.style.width = '100%';
                bar.classList.add('done');
                setTimeout(() => { bar.style.width = '0%'; bar.classList.remove('done'); }, 500);
                
                // Re-enable transitions after morph completes (next frame)
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        document.body.classList.remove('is-navigating');
                    });
                });
            });

            // Close mobile sidebar on SPA navigation
            document.addEventListener('livewire:navigate:start', () => {
                if (window.innerWidth < 768) {
                    const wrapper = document.querySelector('[x-data]');
                    if (wrapper && wrapper._x_dataStack) {
                        wrapper._x_dataStack[0].sidebarOpen = false;
                    }
                }
            });
        })();
    </script>
    
    {{--
        O PDF feito no browser, a partir da própria pré-visualização.

        São 8 KB. As bibliotecas pesadas (html2canvas e jsPDF, 560 KB) só
        descem quando alguém carrega mesmo num botão de PDF — não pesam nas
        páginas de quem nunca gera nenhum.
    --}}
    <script src="/js/pdf-do-documento.js?v={{ filemtime(public_path('js/pdf-do-documento.js')) }}" defer></script>

    {{-- Os gráficos do painel de facturação. Fora do Blade porque um
         script em linha não volta a correr numa navegação do Livewire, e
         quem chegasse ao painel pela barra lateral via tudo em branco. --}}
    <script src="/js/painel-facturacao.js?v={{ filemtime(public_path('js/painel-facturacao.js')) }}" defer></script>

    {{-- OS ECRÃS EM REACT.

         O nome vem do manifesto e leva hash — e SEM `?v=` por cima. Os pedaços
         importam a entrada por caminho relativo e sem query; com um nome fixo
         mais query, o browser via dois módulos e carregava o React duas vezes.
         Ver App\Support\PacoteReact.

         Null quando ainda não foi construído, que é um estado normal numa
         instalação acabada de clonar: os ecrãs React não montam e o resto da
         aplicação abre na mesma. --}}
    @php($pacoteReact = \App\Support\PacoteReact::caminho())
    @if($pacoteReact)
        {{-- A língua dos ecrãs em React. O dicionário só se anuncia a quem
             precisa dele: em português a chave já é a frase. --}}
        <script>
            window.__reactLingua = @json(app()->getLocale());
            @if(\App\Support\DicionarioDoReact::precisa())
            window.__reactDicionarioUrl = @json(route('react.traducoes', ['marca' => \App\Support\DicionarioDoReact::marca()]));
            @endif
        </script>
        <script type="module" src="{{ $pacoteReact }}" defer></script>
    @endif

    <!-- Custom Scripts Stack -->
    @stack('scripts')
    
    <!-- PWA Service Worker Registration + Auto-Update -->
    @include('partials.pwa-register')
    
    <!-- Componente para enviar email de boas-vindas após redirect -->
    @livewire('send-welcome-email')
    
    <!-- Botão Flutuante de Suporte -->
    @if(!auth()->user()->isSuperAdmin())
        @include('components.support-button')
    @endif
</body>
</html>

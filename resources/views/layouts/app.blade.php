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
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    <link rel="apple-touch-icon" href="{{ app_favicon() }}">
    @endif
    
    <!-- PWA -->
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ function_exists('app_name') ? app_name() : config('app.name', 'SOS ERP') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ url('/pwa/icon-192.png') }}">
    
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
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
    @auth
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
                    <div class="px-3 mb-2">
                        <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Menu Principal</p>
                    </div>
                    
                    <a href="{{ route('home') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('home') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                        <i class="fas fa-home w-6 text-blue-300"></i>
                        <span x-show="sidebarOpen" class="ml-3">Início</span>
                    </a>
                    
                    {{-- Utilizadores - Collapsible Menu (Apenas Super Admin) --}}
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('Super Admin'))
                    <div x-data="{ usersOpen: {{ request()->routeIs('users.*') ? 'true' : 'false' }} }">
                        <button @click="usersOpen = !usersOpen" 
                                class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                            <div class="flex items-center">
                                <i class="fas fa-users w-6 text-purple-300"></i>
                                <span x-show="sidebarOpen" class="ml-3">Utilizadores</span>
                            </div>
                            <i x-show="sidebarOpen" 
                               :class="usersOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                               class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                        </button>
                        
                        <div x-show="usersOpen" 
                             x-collapse
                             class="bg-blue-900/30">
                            <a href="{{ route('users.index') }}" 
                               class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('users.index') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                <i class="fas fa-user-friends w-5 text-purple-400 text-sm"></i>
                                <span x-show="sidebarOpen" class="ml-3 text-sm">Gestão de Utilizadores</span>
                            </a>
                            
                            <a href="{{ route('users.roles-permissions') }}" 
                               class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('users.roles-permissions') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                <i class="fas fa-shield-alt w-5 text-purple-400 text-sm"></i>
                                <span x-show="sidebarOpen" class="ml-3 text-sm">Roles & Permissões</span>
                            </a>
                        </div>
                    </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('invoicing'))
                        <!-- Invoicing Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ invoicingOpen: {{ request()->routeIs('invoicing.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="invoicingOpen = !invoicingOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar w-6 text-yellow-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Facturação</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="invoicingOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="invoicingOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                @can('invoicing.dashboard.view')
                                <a href="{{ route('invoicing.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.dashboard') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">📊 Dashboard</span>
                                </a>
                                @endcan
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                @can('invoicing.pos.access')
                                <a href="{{ route('invoicing.pos') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.pos') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cash-register w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">🛒 POS - Ponto de Venda</span>
                                </a>
                                @endcan

                                @can('invoicing.pos.access')
                                <a href="{{ route('invoicing.offline.pos') }}"
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.offline.*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-mobile-screen-button w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">📱 POS Offline (PWA)</span>
                                </a>
                                @endcan

                                <a href="{{ route('invoicing.pos.shifts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.pos.shifts') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-user-clock w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">⏰ Turnos de Caixa</span>
                                </a>
                                
                                <a href="{{ route('invoicing.pos.shift-history') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.pos.shift-history') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-history w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">📋 Histórico de Turnos</span>
                                </a>
                                
                                @can('invoicing.pos.reports')
                                <a href="{{ route('invoicing.pos.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.pos.reports') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">📊 Relatórios POS</span>
                                </a>
                                @endcan
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                @can('invoicing.clients.view')
                                <a href="{{ route('invoicing.clients') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.clients*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Clientes</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.suppliers.view')
                                <a href="{{ route('invoicing.suppliers') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.suppliers*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-truck w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Fornecedores</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.products.view')
                                <a href="{{ route('invoicing.products') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.products*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-box w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Produtos</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.categories.view')
                                <a href="{{ route('invoicing.categories') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.categories*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-folder w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Categorias</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.brands.view')
                                <a href="{{ route('invoicing.brands') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.brands*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tag w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Marcas</span>
                                </a>
                                @endcan
                                
                                <!-- Documentos Submenu -->
                                <div x-data="{ documentsOpen: {{ request()->routeIs('invoicing.sales.*') || request()->routeIs('invoicing.purchases.*') || request()->routeIs('invoicing.receipts.*') || request()->routeIs('invoicing.credit-notes.*') || request()->routeIs('invoicing.debit-notes.*') || request()->routeIs('invoicing.advances.*') ? 'true' : 'false' }} }" class="border-l-2 border-blue-700/30 ml-8">
                                    <button @click="documentsOpen = !documentsOpen" 
                                            class="w-full flex items-center justify-between pr-4 py-2.5 hover:bg-blue-700/30 transition group">
                                        <div class="flex items-center">
                                            <i class="fas fa-file-alt w-5 text-purple-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">Documentos</span>
                                        </div>
                                        <i x-show="sidebarOpen" 
                                           :class="documentsOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                           class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                                    </button>
                                    
                                    <div x-show="documentsOpen" 
                                         x-collapse
                                         class="bg-blue-900/20">
                                        
                                        @can('invoicing.sales.proformas.view')
                                        <a href="{{ route('invoicing.sales.proformas') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.proformas*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice-dollar w-5 text-purple-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Proformas Venda</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.sales.invoices.view')
                                        @php $__tipoFatura = request()->query('type'); @endphp
                                        <a href="{{ route('invoicing.sales.invoices') }}"
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.invoices*') && $__tipoFatura !== 'FR' ? 'bg-blue-700 border-l-4 border-indigo-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice w-5 text-indigo-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas Venda</span>
                                        </a>

                                        {{-- Fatura-Recibo (FR): mesma sequência do POS, paga no acto --}}
                                        <a href="{{ route('invoicing.sales.invoices', ['type' => 'FR']) }}"
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.invoices*') && $__tipoFatura === 'FR' ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-receipt w-5 text-emerald-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas-Recibo</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.purchases.proformas.view')
                                        <a href="{{ route('invoicing.purchases.proformas') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.purchases.proformas*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice w-5 text-orange-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Proformas Compra</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.purchases.invoices.view')
                                        <a href="{{ route('invoicing.purchases.invoices') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.purchases.invoices*') ? 'bg-blue-700 border-l-4 border-red-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice-dollar w-5 text-red-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas Compra</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.imports.view')
                                        <a href="{{ route('invoicing.imports.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.imports*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-ship w-5 text-cyan-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Importações</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.receipts.view')
                                        <a href="{{ route('invoicing.receipts.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.receipts*') ? 'bg-blue-700 border-l-4 border-blue-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-receipt w-5 text-blue-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Recibos</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.credit-notes.view')
                                        <a href="{{ route('invoicing.credit-notes.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.credit-notes*') ? 'bg-blue-700 border-l-4 border-red-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-minus w-5 text-red-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Crédito</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.debit-notes.view')
                                        <a href="{{ route('invoicing.debit-notes.index') }}"
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.debit-notes*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-plus w-5 text-green-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Débito</span>
                                        </a>
                                        @endcan

                                        @canany(['invoicing.transport-guides.view', 'invoicing.debit-notes.view'])
                                        <a href="{{ route('invoicing.transport-guides') }}"
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.transport-guides*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-truck w-5 text-orange-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Guias Transporte</span>
                                        </a>
                                        @endcanany
                                        
                                        @can('invoicing.advances.view')
                                        <a href="{{ route('invoicing.advances.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.advances*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-coins w-5 text-yellow-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Adiantamentos</span>
                                        </a>
                                        @endcan
                                        
                                    </div>
                                </div>
                                
                                @can('invoicing.warehouses.view')
                                <a href="{{ route('invoicing.warehouses') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.warehouses*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-warehouse w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Armazéns</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.stock.view')
                                <a href="{{ route('invoicing.stock') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.stock') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-boxes w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Gestão Stock</span>
                                </a>
                                @endcan
                                
                                <a href="{{ route('invoicing.product-batches') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.product-batches') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-check w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Lotes e Validades</span>
                                </a>
                                
                                <a href="{{ route('invoicing.expiry-report') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.expiry-report') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-red-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">📊 Relatório Validade</span>
                                </a>
                                
                                @can('invoicing.warehouse-transfer.view')
                                <a href="{{ route('invoicing.warehouse-transfer') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.warehouse-transfer') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transfer. Armazéns</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.inter-company-transfer.view')
                                <a href="{{ route('invoicing.inter-company-transfer') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.inter-company-transfer') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-building-circle-arrow-right w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transfer. Inter-Empresa</span>
                                </a>
                                @endcan
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                {{-- Hub de Relatórios — submenu em árvore (oculto para Caixa/Vendedor sem permissão) --}}
                                @can('invoicing.reports.view')
                                <div x-data="{ reportsOpen: {{ request()->routeIs('invoicing.reports.*') || request()->routeIs('invoicing.expiry-report') ? 'true' : 'false' }} }" class="border-l-2 border-blue-700/30 ml-8">
                                    <button @click="reportsOpen = !reportsOpen" 
                                            class="w-full flex items-center justify-between pr-4 py-2.5 hover:bg-blue-700/30 transition group">
                                        <div class="flex items-center">
                                            <i class="fas fa-chart-column w-5 text-emerald-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">📈 Relatórios</span>
                                        </div>
                                        <i x-show="sidebarOpen" 
                                           :class="reportsOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                           class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                                    </button>
                                    
                                    <div x-show="reportsOpen" x-collapse class="bg-blue-900/20">
                                        <a href="{{ route('invoicing.reports.hub') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.reports.hub') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-table-cells w-5 text-yellow-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs font-semibold">Painel de Relatórios</span>
                                        </a>

                                        @php
                                            $reportGroups = [
                                                'Rentabilidade' => [
                                                    ['invoicing.reports.profit-loss', 'Lucros e Perdas (DRE)', 'fa-chart-line'],
                                                    ['invoicing.reports.margin', 'Análise de Margem', 'fa-percentage'],
                                                    ['invoicing.reports.product-performance', 'Desempenho de Produtos', 'fa-chart-pie'],
                                                    ['invoicing.reports.comparative', 'Comparativo', 'fa-balance-scale'],
                                                ],
                                                'Vendas' => [
                                                    ['invoicing.reports.sales', 'Mapa de Vendas', 'fa-file-invoice'],
                                                    ['invoicing.reports.top-clients', 'Top Clientes', 'fa-crown'],
                                                    ['invoicing.reports.top-products', 'Top Produtos', 'fa-star'],
                                                    ['invoicing.reports.sales-by-user', 'Vendas por Vendedor', 'fa-user-tie'],
                                                ],
                                                'Compras' => [
                                                    ['invoicing.reports.purchases', 'Mapa de Compras', 'fa-shopping-cart'],
                                                    ['invoicing.reports.top-suppliers', 'Top Fornecedores', 'fa-truck'],
                                                    ['invoicing.reports.best-supplier', 'Melhor Fornecedor', 'fa-medal'],
                                                ],
                                                'Contas Correntes' => [
                                                    ['invoicing.reports.accounts-receivable', 'Contas a Receber', 'fa-hand-holding-usd'],
                                                    ['invoicing.reports.accounts-payable', 'Contas a Pagar', 'fa-money-bill-wave'],
                                                    ['invoicing.reports.payment-methods', 'Recebimentos por Meio', 'fa-money-check-alt'],
                                                    ['invoicing.reports.aging-clients', 'Aging de Clientes', 'fa-clock'],
                                                    ['invoicing.reports.account-statement', 'Extracto de Conta Corrente', 'fa-file-invoice-dollar'],
                                                ],
                                                'Fiscal & SAFT' => [
                                                    ['invoicing.reports.vat', 'Mapa de IVA', 'fa-percent'],
                                                    ['invoicing.reports.documents', 'Mapa de Documentos', 'fa-file-alt'],
                                                ],
                                                'Produtos & Serviços' => [
                                                    ['invoicing.reports.price-list', 'Tabela de Preços e Lucro', 'fa-tags'],
                                                    ['invoicing.reports.services', 'Mapa de Serviços', 'fa-concierge-bell'],
                                                    ['invoicing.expiry-report', 'Validade de Produtos', 'fa-calendar-check'],
                                                ],
                                                // Esta lista é MANTIDA À MÃO e vive separada do hub. Um
                                                // relatório novo tem de entrar nos dois sítios, senão só
                                                // aparece no painel e ninguém o encontra pelo menu — foi o
                                                // que aconteceu ao extracto e aos ajustes de stock.
                                                'Stock & Controlo' => [
                                                    ['invoicing.reports.stock-adjustments', 'Ajustes de Stock', 'fa-sliders'],
                                                ],
                                            ];
                                        @endphp

                                        @foreach($reportGroups as $groupName => $groupReports)
                                            <div x-show="sidebarOpen" class="px-4 pt-3 pb-1 text-[10px] uppercase tracking-wider text-blue-300/70 font-bold">{{ $groupName }}</div>
                                            @foreach($groupReports as $r)
                                                <a href="{{ route($r[0]) }}" 
                                                   class="flex items-center pl-6 pr-4 py-2 {{ request()->routeIs($r[0]) ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                                    <i class="fas {{ $r[2] }} w-5 text-emerald-300 text-xs"></i>
                                                    <span x-show="sidebarOpen" class="ml-3 text-xs">{{ $r[1] }}</span>
                                                </a>
                                            @endforeach
                                        @endforeach
                                    </div>
                                </div>
                                @endcan
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                @can('invoicing.taxes.view')
                                <a href="{{ route('invoicing.taxes') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.taxes') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-percent w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Impostos (IVA)</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.series.view')
                                <a href="{{ route('invoicing.series') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.series') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-hashtag w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Séries de Documentos</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.saft.view')
                                <a href="{{ route('invoicing.saft-generator') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.saft-generator') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-code w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Gerador SAFT-AO</span>
                                </a>
                                @endcan
                                
                                @can('invoicing.settings.view')
                                <a href="{{ route('invoicing.settings') }}"
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.settings') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cogs w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span>
                                </a>

                                {{-- Auditoria: quem fez o quê. Mesma permissão
                                     das configurações — quem pode ver a
                                     configuração fiscal pode ver quem lhe mexeu. --}}
                                <a href="{{ route('invoicing.audit') }}"
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.audit') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clipboard-list w-5 text-slate-300 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Auditoria</span>
                                </a>
                                @endcan

                                @can('invoicing.agt.view')
                                <a href="{{ route('invoicing.agt-settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.agt-settings') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-signature w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">AGT Angola</span>
                                </a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('invoicing'))
                        <!-- Tesouraria Module - Collapsible Menu (integrado com Faturação) -->
                        <div class="mt-6" x-data="{ treasuryOpen: {{ request()->routeIs('treasury.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="treasuryOpen = !treasuryOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-coins w-6 text-green-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Tesouraria</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="treasuryOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="treasuryOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                @can('treasury.reports.view')
                                <a href="{{ route('treasury.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.reports*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-invoice-dollar w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                @endcan
                                
                                @can('treasury.accounts.view')
                                <a href="{{ route('treasury.accounts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.accounts*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-wallet w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Contas Bancárias</span>
                                </a>
                                @endcan
                                
                                @can('treasury.transactions.view')
                                <a href="{{ route('treasury.transactions') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.transactions*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transações</span>
                                </a>
                                @endcan
                                
                                @can('treasury.transfers.view')
                                <a href="{{ route('treasury.transfers') }}"
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.transfers*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-right-left w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transferências</span>
                                </a>
                                @endcan
                                
                                @can('treasury.payment-methods.view')
                                <a href="{{ route('treasury.payment-methods') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.payment-methods*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-money-bill-wave w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Métodos de Pagamento</span>
                                </a>
                                @endcan
                                
                                @can('treasury.banks.view')
                                <a href="{{ route('treasury.banks') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.banks*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-university w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Bancos</span>
                                </a>
                                @endcan
                                
                                @can('treasury.cash-registers.view')
                                <a href="{{ route('treasury.cash-registers') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.cash-registers*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cash-register w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Caixas</span>
                                </a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('eventos'))
                        <!-- Events Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ eventsOpen: {{ request()->routeIs('events.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="eventsOpen = !eventsOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt w-6 text-pink-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Eventos</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="eventsOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="eventsOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('events.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.dashboard') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-pie w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('events.calendar') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.calendar') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-alt w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Calendário</span>
                                </a>
                                
                                <a href="{{ route('events.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.reports') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-bar w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <a href="{{ route('events.equipment.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.equipment.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tools w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Equipamentos</span>
                                </a>
                                
                                <a href="{{ route('events.venues.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.venues.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-map-marker-alt w-5 text-red-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Locais</span>
                                </a>
                                
                                <a href="{{ route('events.types.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.types.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tags w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Tipos de Eventos</span>
                                </a>
                                
                                <a href="{{ route('events.technicians.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.technicians.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-user-tie w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Técnicos</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('rh'))
                        <!-- HR Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ hrOpen: {{ request()->routeIs('hr.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="hrOpen = !hrOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-user-tie w-6 text-cyan-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Recursos Humanos</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="hrOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="hrOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('hr.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.dashboard') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>

                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('hr.employees.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.employees*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Funcionários</span>
                                </a>
                                
                                <a href="{{ route('hr.departments.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.departments*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-building w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Departamentos</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('hr.attendance.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.attendance*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clock w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Presenças</span>
                                </a>
                                
                                <a href="{{ route('hr.vacations.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.vacations*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-umbrella-beach w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Férias</span>
                                </a>
                                
                                <a href="{{ route('hr.leaves') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.leaves*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-times w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Licenças e Faltas</span>
                                </a>
                                
                                <a href="{{ route('hr.overtime') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.overtime') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-business-time w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Horas Extras</span>
                                </a>

                                <a href="{{ route('hr.overtime-night-shift') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.overtime-night-shift') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-moon w-5 text-indigo-300 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Turno Noturno</span>
                                </a>

                                <a href="{{ route('hr.salary-discounts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.salary-discounts') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-percentage w-5 text-red-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Descontos Salariais</span>
                                </a>

                                @php
                                    $usesShifts = false;
                                    try {
                                        $usesShifts = \App\Models\HR\HRSetting::getValue('uses_shifts', '0') == '1';
                                    } catch (\Exception $e) {
                                        // Silenciosamente falhar se não conseguir recuperar a configuração
                                    }
                                @endphp
                                @if($usesShifts)
                                <a href="{{ route('hr.shifts.index') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.shifts*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clock w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Turnos</span>
                                </a>
                                @endif
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('hr.payroll') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.payroll*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-money-check-alt w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Folha de Pagamento</span>
                                </a>
                                
                                <a href="{{ route('hr.advances') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.advances*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-hand-holding-usd w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Adiantamentos</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>

                                <a href="{{ route('hr.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.reports*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-pie w-5 text-violet-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <a href="{{ route('hr.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.settings*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cogs w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações RH</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('contabilidade'))
                        <!-- Accounting Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ accountingOpen: {{ request()->routeIs('accounting.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="accountingOpen = !accountingOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-chart-line w-6 text-emerald-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Contabilidade</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="accountingOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="accountingOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('accounting.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.dashboard') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-pie w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('accounting.accounts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.accounts*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-sitemap w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Plano de Contas</span>
                                </a>
                                
                                <a href="{{ route('accounting.journals') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.journals*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-book w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Diários</span>
                                </a>
                                
                                <a href="{{ route('accounting.document-types') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.document-types*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-alt w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Tipos de Documentos</span>
                                </a>
                                
                                <a href="{{ route('accounting.moves') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.moves*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-invoice w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Lançamentos</span>
                                </a>
                                
                                <a href="{{ route('accounting.periods') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.periods*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-alt w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Períodos</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('accounting.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.reports*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-bar w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('accounting.reconciliation') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.reconciliation*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Reconciliação</span>
                                </a>
                                
                                <a href="{{ route('accounting.fixed-assets') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.fixed-assets*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-building w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Imobilizado</span>
                                </a>
                                
                                <a href="{{ route('accounting.currencies') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.currencies*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-coins w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Moedas</span>
                                </a>
                                
                                <a href="{{ route('accounting.cost-centers') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.cost-centers*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-sitemap w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Centros Custo</span>
                                </a>
                                
                                <a href="{{ route('accounting.analytics') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.analytics*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-pie w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Analítica</span>
                                </a>
                                
                                <a href="{{ route('accounting.budgets') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.budgets*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calculator w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Orçamentos</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('accounting.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('accounting.settings*') ? 'bg-blue-700 border-l-4 border-emerald-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cog w-5 text-gray-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('oficina'))
                        <!-- Oficina Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ oficinaOpen: {{ request()->routeIs('workshop.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="oficinaOpen = !oficinaOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-wrench w-6 text-orange-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Gestão de Oficina</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="oficinaOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="oficinaOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('workshop.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.dashboard') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('workshop.vehicles') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.vehicles*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-car w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Veículos</span>
                                </a>
                                
                                <a href="{{ route('workshop.mechanics') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.mechanics*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-user-cog w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Mecânicos</span>
                                </a>
                                
                                <a href="{{ route('workshop.services') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.services*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tools w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Serviços</span>
                                </a>
                                
                                <a href="{{ route('workshop.parts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.parts*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-boxes w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Peças</span>
                                </a>
                                
                                <a href="{{ route('workshop.work-orders') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.work-orders*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clipboard-list w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Ordens de Serviço</span>
                                </a>
                                
                                <a href="{{ route('workshop.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('workshop.reports*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-bar w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('hotel'))
                        <!-- Hotel Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ hotelOpen: {{ request()->routeIs('hotel.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="hotelOpen = !hotelOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-hotel w-6 text-purple-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Gestão de Hotel</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="hotelOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="hotelOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('hotel.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.dashboard') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('hotel.reservations') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.reservations*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-check w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Reservas</span>
                                </a>
                                
                                <a href="{{ route('hotel.walk-in') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.walk-in*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-walking w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Walk-in</span>
                                </a>
                                
                                <a href="{{ route('hotel.checkout') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.checkout*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-sign-out-alt w-5 text-red-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Check-out</span>
                                </a>
                                
                                <a href="{{ route('hotel.calendar') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.calendar*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-alt w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Calendário</span>
                                </a>
                                
                                <a href="{{ route('hotel.housekeeping') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.housekeeping*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-broom w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Housekeeping</span>
                                </a>
                                
                                <a href="{{ route('hotel.maintenance') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.maintenance*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tools w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Manutenção</span>
                                </a>
                                
                                <a href="{{ route('hotel.staff') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.staff*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Funcionarios</span>
                                </a>
                                
                                <a href="{{ route('hotel.rooms') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.rooms*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-door-open w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Quartos</span>
                                </a>
                                
                                <a href="{{ route('hotel.room-types') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.room-types*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-bed w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Tipos de Quarto</span>
                                </a>
                                
                                <a href="{{ route('hotel.guests') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.guests*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Hóspedes</span>
                                </a>
                                
                                <a href="{{ route('hotel.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.reports*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-bar w-5 text-amber-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <a href="{{ route('hotel.rates') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.rates*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tags w-5 text-amber-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Tarifas</span>
                                </a>
                                
                                <a href="{{ route('hotel.packages') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.packages*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-gift w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Pacotes</span>
                                </a>
                                
                                <a href="{{ route('hotel.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hotel.settings*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cog w-5 text-gray-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('salon'))
                        <!-- Salon Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ salonOpen: {{ request()->routeIs('salon.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="salonOpen = !salonOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-spa w-6 text-pink-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Salão de Beleza</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="salonOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="salonOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('salon.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.dashboard') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('salon.appointments') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.appointments*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-calendar-check w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Agendamentos</span>
                                </a>
                                
                                <a href="{{ route('salon.clients') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.clients*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Clientes</span>
                                </a>
                                
                                <a href="{{ route('salon.services') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.services') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cut w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Serviços</span>
                                </a>
                                
                                <a href="{{ route('salon.services.categories') }}" 
                                   class="flex items-center pl-10 pr-4 py-2 {{ request()->routeIs('salon.services.categories') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-folder w-4 text-pink-300 text-xs"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-xs text-blue-200">Categorias</span>
                                </a>
                                
                                <a href="{{ route('salon.professionals') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.professionals*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-user-tie w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Profissionais</span>
                                </a>
                                
                                <a href="{{ route('salon.products') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.products*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-boxes w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Produtos</span>
                                </a>
                                
                                <a href="{{ route('salon.pos') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.pos*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cash-register w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">POS / Faturar</span>
                                </a>
                                
                                <a href="{{ route('salon.reports.time') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.reports.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-bar w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <div class="border-t border-blue-700/50 my-2"></div>
                                
                                <a href="{{ route('salon.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('salon.settings') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cog w-5 text-gray-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('restaurant'))
                        <div class="mt-6" x-data="{ restaurantOpen: {{ request()->routeIs('restaurant.*') ? 'true' : 'false' }} }">
                            <button @click="restaurantOpen = !restaurantOpen"
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-utensils w-6 text-orange-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Restaurante</span>
                                </div>
                                <i x-show="sidebarOpen" :class="restaurantOpen ? 'fa-chevron-down' : 'fa-chevron-right'"
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            <div x-show="restaurantOpen" x-collapse class="bg-blue-900/30">
                                @can('restaurant.dashboard.view')
                                    <a href="{{ route('restaurant.dashboard') }}"
                                       class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('restaurant.dashboard') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                        <i class="fas fa-chart-line w-5 text-orange-300 text-sm"></i>
                                        <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                    </a>
                                @endcan
                                @can('restaurant.floor.view')
                                    <a href="{{ route('restaurant.floor') }}"
                                       class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('restaurant.floor') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                        <i class="fas fa-chair w-5 text-emerald-300 text-sm"></i>
                                        <span x-show="sidebarOpen" class="ml-3 text-sm">Sala e Mesas</span>
                                    </a>
                                @endcan
                                @can('restaurant.orders.view')
                                    <a href="{{ route('restaurant.orders') }}"
                                       class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('restaurant.orders') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                        <i class="fas fa-receipt w-5 text-violet-300 text-sm"></i>
                                        <span x-show="sidebarOpen" class="ml-3 text-sm">Comandas</span>
                                    </a>
                                @endcan
                                @can('restaurant.kitchen.view')
                                    <a href="{{ route('restaurant.kitchen') }}"
                                       class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('restaurant.kitchen') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                        <i class="fas fa-fire-burner w-5 text-red-300 text-sm"></i>
                                        <span x-show="sidebarOpen" class="ml-3 text-sm">Cozinha / KDS</span>
                                    </a>
                                @endcan
                                @can('restaurant.reservations.view')
                                    <a href="{{ route('restaurant.reservations') }}" class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('restaurant.reservations') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                        <i class="fas fa-calendar-check w-5 text-cyan-300 text-sm"></i><span x-show="sidebarOpen" class="ml-3 text-sm">Reservas</span>
                                    </a>
                                @endcan
                                @can('restaurant.recipes.view')
                                    <a href="{{route('restaurant.recipes')}}" class="flex items-center pl-8 pr-4 py-2.5 {{request()->routeIs('restaurant.recipes')?'bg-blue-700 border-l-4 border-orange-400':'hover:bg-blue-700/50'}} transition"><i class="fas fa-book-open w-5 text-lime-300 text-sm"></i><span x-show="sidebarOpen" class="ml-3 text-sm">Fichas Técnicas</span></a>
                                @endcan
                                @can('restaurant.stock.view')
                                    <a href="{{route('restaurant.stock')}}" class="flex items-center pl-8 pr-4 py-2.5 {{request()->routeIs('restaurant.stock')?'bg-blue-700 border-l-4 border-orange-400':'hover:bg-blue-700/50'}} transition"><i class="fas fa-boxes-stacked w-5 text-emerald-300 text-sm"></i><span x-show="sidebarOpen" class="ml-3 text-sm">Stock e Desperdícios</span></a>
                                @endcan
                                @can('restaurant.reports.view')
                                    <a href="{{route('restaurant.reports')}}" class="flex items-center pl-8 pr-4 py-2.5 {{request()->routeIs('restaurant.reports')?'bg-blue-700 border-l-4 border-orange-400':'hover:bg-blue-700/50'}} transition"><i class="fas fa-chart-column w-5 text-indigo-300 text-sm"></i><span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span></a>
                                @endcan
                                @can('restaurant.settings.view')
                                    <a href="{{route('restaurant.settings')}}" class="flex items-center pl-8 pr-4 py-2.5 {{request()->routeIs('restaurant.settings')?'bg-blue-700 border-l-4 border-orange-400':'hover:bg-blue-700/50'}} transition"><i class="fas fa-gear w-5 text-slate-300 text-sm"></i><span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span></a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('notifications'))
                        <!-- Notifications Module -->
                        <div class="mt-6">
                            <a href="{{ route('notifications.settings') }}" 
                               class="flex items-center px-4 py-3 {{ request()->routeIs('notifications.*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                <i class="ri-notification-3-line text-2xl text-yellow-400"></i>
                                <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Notificações</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('crm'))
                        <!-- CRM Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ crmOpen: {{ request()->routeIs('crm.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="crmOpen = !crmOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-user-check w-6 text-teal-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">CRM</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="crmOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="crmOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('crm.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('crm.dashboard') ? 'bg-blue-700 border-l-4 border-teal-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('crm.leads') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('crm.leads*') ? 'bg-blue-700 border-l-4 border-teal-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-user-plus w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Leads</span>
                                </a>
                                
                                <a href="{{ route('crm.oportunidades') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('crm.oportunidades*') ? 'bg-blue-700 border-l-4 border-teal-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-bullseye w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Oportunidades</span>
                                </a>
                                
                                <a href="{{ route('crm.funil-vendas') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('crm.funil-vendas*') ? 'bg-blue-700 border-l-4 border-teal-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-filter w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Funil de Vendas</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('inventario'))
                        <!-- Inventário Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ inventarioOpen: {{ request()->routeIs('inventario.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="inventarioOpen = !inventarioOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-boxes w-6 text-amber-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Inventário</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="inventarioOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="inventarioOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('inventario.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('inventario.dashboard') ? 'bg-blue-700 border-l-4 border-amber-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('inventario.armazens') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('inventario.armazens*') ? 'bg-blue-700 border-l-4 border-amber-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-warehouse w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Armazéns</span>
                                </a>
                                
                                <a href="{{ route('inventario.movimentos') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('inventario.movimentos*') ? 'bg-blue-700 border-l-4 border-amber-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Movimentos</span>
                                </a>
                                
                                <a href="{{ route('inventario.contagem') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('inventario.contagem*') ? 'bg-blue-700 border-l-4 border-amber-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clipboard-check w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Contagem de Stock</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('compras'))
                        <!-- Compras Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ comprasOpen: {{ request()->routeIs('compras.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="comprasOpen = !comprasOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-shopping-cart w-6 text-lime-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Compras</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="comprasOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="comprasOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('compras.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('compras.dashboard') ? 'bg-blue-700 border-l-4 border-lime-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('compras.fornecedores') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('compras.fornecedores*') ? 'bg-blue-700 border-l-4 border-lime-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-truck w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Fornecedores</span>
                                </a>
                                
                                <a href="{{ route('compras.requisicoes') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('compras.requisicoes*') ? 'bg-blue-700 border-l-4 border-lime-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-alt w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Requisições</span>
                                </a>
                                
                                <a href="{{ route('compras.encomendas') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('compras.encomendas*') ? 'bg-blue-700 border-l-4 border-lime-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clipboard-list w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Encomendas</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->canAccessModuleMenu('projetos'))
                        <!-- Projetos Module - Collapsible Menu -->
                        <div class="mt-6" x-data="{ projetosOpen: {{ request()->routeIs('projetos.*') ? 'true' : 'false' }} }">
                            <!-- Header do Módulo -->
                            <button @click="projetosOpen = !projetosOpen" 
                                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
                                <div class="flex items-center">
                                    <i class="fas fa-project-diagram w-6 text-violet-400"></i>
                                    <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Projetos</span>
                                </div>
                                <i x-show="sidebarOpen" 
                                   :class="projetosOpen ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                   class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                            </button>
                            
                            <!-- Submenu Items -->
                            <div x-show="projetosOpen" 
                                 x-collapse
                                 class="bg-blue-900/30">
                                
                                <a href="{{ route('projetos.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('projetos.dashboard') ? 'bg-blue-700 border-l-4 border-violet-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('projetos.lista') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('projetos.lista*') ? 'bg-blue-700 border-l-4 border-violet-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-briefcase w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Projetos</span>
                                </a>
                                
                                <a href="{{ route('projetos.tarefas') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('projetos.tarefas*') ? 'bg-blue-700 border-l-4 border-violet-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tasks w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Tarefas</span>
                                </a>
                                
                                <a href="{{ route('projetos.timesheet') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('projetos.timesheet*') ? 'bg-blue-700 border-l-4 border-violet-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-clock w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Timesheet</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin())
                        <div class="px-3 mt-6 mb-2">
                            <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Super Admin</p>
                        </div>
                        
                        <a href="{{ route('superadmin.dashboard') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.dashboard') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-chart-line w-6 text-yellow-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Dashboard</span>
                        </a>
                        
                        <a href="{{ route('superadmin.tenants') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.tenants') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-building w-6 text-green-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Tenants</span>
                        </a>
                        
                        <a href="{{ route('superadmin.modules') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.modules') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-puzzle-piece w-6 text-purple-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Módulos</span>
                        </a>
                        
                        <a href="{{ route('superadmin.plans') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.plans') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-tags w-6 text-pink-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Planos</span>
                        </a>
                        
                        <a href="{{ route('superadmin.billing') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.billing') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-file-invoice-dollar w-6 text-emerald-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Billing</span>
                        </a>
                        
                        <div class="px-3 mt-6 mb-2">
                            <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Sistema</p>
                        </div>
                        
                        <a href="{{ route('superadmin.system-updates') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.system-updates') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-cloud-download-alt w-6 text-cyan-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Atualizações do Sistema</span>
                        </a>
                        
                        <a href="{{ route('superadmin.script-runner') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.script-runner') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-code w-6 text-green-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Executar Scripts</span>
                        </a>
                        
                        <div class="px-3 mt-6 mb-2">
                            <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Configurações</p>
                        </div>
                        
                        <a href="{{ route('superadmin.system-settings') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.system-settings') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-cog w-6 text-purple-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Configurações do Sistema</span>
                        </a>
                        
                        <a href="{{ route('superadmin.saft') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.saft') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-key w-6 text-orange-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">SAFT Configurações</span>
                        </a>
                        
                        <a href="{{ route('superadmin.system-optimization') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.system-optimization') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-tachometer-alt w-6 text-cyan-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">Optimização & OPcache</span>
                        </a>
                    @endif
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
                            <div class="text-xs opacity-90">6 meses grátis • Todos os módulos</div>
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
                        <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Suporte</span>
                        <span x-show="sidebarOpen" class="ml-auto text-xs bg-purple-500 px-2 py-1 rounded-full">Novo</span>
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
                            <a href="{{ route('company.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-building mr-2 text-indigo-600"></i> Dados da Empresa
                            </a>
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
                            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100 transition">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            <!-- Desktop sidebar toggle -->
                            <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:block text-gray-400 hover:text-gray-600 p-1 rounded transition">
                                <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                            </button>
                            <div>
                                <h1 class="text-lg sm:text-2xl font-bold text-gray-900 truncate max-w-[200px] sm:max-w-none">@yield('page-title', 'Dashboard')</h1>
                                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">@yield('page-subtitle', 'Bem-vindo ao sistema')</p>
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
                                     title="FOX Friendly Active!">
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
                            
                            <!-- Notificações -->
                            @if(auth()->check())
                                <livewire:notifications />
                            @endif
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main id="app-main" :class="{ 'first-load': firstLoad }" class="flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-4 lg:p-6">
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

    <!-- Livewire Scripts (já inclui Alpine.js V3) -->
    @livewireScripts
    
    <!-- Toastr CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
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
                    + '<h2 style="font-size:20px;font-weight:800;margin:0 0 8px;color:#111827;">Sess&atilde;o expirada</h2>'
                    + '<p style="color:#4b5563;margin:0 0 20px;font-size:14px;line-height:1.5;">Por inatividade, a sua sess&atilde;o terminou. <strong>O carrinho foi guardado</strong> e ser&aacute; restaurado assim que iniciar sess&atilde;o novamente.</p>'
                    + '<a href="{{ route('login') }}" style="display:inline-block;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;padding:12px 26px;border-radius:10px;font-weight:700;text-decoration:none;">Iniciar sess&atilde;o</a>'
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

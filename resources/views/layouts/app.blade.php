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
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    <link rel="apple-touch-icon" sizes="192x192" href="/pwa/icon-192x192.png">
    
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
        
        /* Aplicar animação à sidebar */
        aside {
            animation: slideInLeft 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        /* Animação do logo */
        aside .logo-container {
            animation: logoEntry 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Animação sequencial dos itens de menu */
        aside nav a {
            opacity: 0;
            animation: fadeInStagger 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        
        aside nav a:nth-child(1) { animation-delay: 0.1s; }
        aside nav a:nth-child(2) { animation-delay: 0.15s; }
        aside nav a:nth-child(3) { animation-delay: 0.2s; }
        aside nav a:nth-child(4) { animation-delay: 0.25s; }
        aside nav a:nth-child(5) { animation-delay: 0.3s; }
        aside nav a:nth-child(6) { animation-delay: 0.35s; }
        aside nav a:nth-child(7) { animation-delay: 0.4s; }
        aside nav a:nth-child(8) { animation-delay: 0.45s; }
        aside nav a:nth-child(9) { animation-delay: 0.5s; }
        aside nav a:nth-child(10) { animation-delay: 0.55s; }
        aside nav a:nth-child(n+11) { animation-delay: 0.6s; }
        
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
        
        main {
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            transition: all 0.3s ease;
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
            transition: all 0.4s ease;
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
        <div x-data="{ sidebarOpen: true }" class="flex h-screen overflow-hidden">
            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-gradient-to-b from-blue-900 to-blue-800 text-white transition-all duration-300 flex flex-col shadow-2xl">
                <!-- Logo -->
                <div class="flex items-center justify-between p-4 border-b border-blue-700 logo-container">
                    <div class="flex items-center justify-center" :class="sidebarOpen ? 'w-full' : ''">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" 
                                 alt="{{ app_name() }}" 
                                 style="max-height: 4rem; max-width: 200px;"
                                 class="w-auto object-contain transition-all duration-300"
                                 :class="sidebarOpen ? 'h-16' : 'h-12 w-12'">
                        @else
                            <div :class="sidebarOpen ? 'w-12 h-12' : 'w-10 h-10'" class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center shadow-lg transition-all duration-300">
                                <i class="fas fa-crown text-white transition-all duration-300" :class="sidebarOpen ? 'text-2xl' : 'text-xl'"></i>
                            </div>
                        @endif
                    </div>
                    <button @click="sidebarOpen = !sidebarOpen" class="text-blue-300 hover:text-white transition ml-2">
                        <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('invoicing'))
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
                                        <a href="{{ route('invoicing.sales.invoices') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.invoices*') ? 'bg-blue-700 border-l-4 border-indigo-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice w-5 text-indigo-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas Venda</span>
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
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.credit-notes*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-minus w-5 text-green-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Crédito</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.debit-notes.view')
                                        <a href="{{ route('invoicing.debit-notes.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.debit-notes*') ? 'bg-blue-700 border-l-4 border-red-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-plus w-5 text-red-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Débito</span>
                                        </a>
                                        @endcan
                                        
                                        @can('invoicing.advances.view')
                                        <a href="{{ route('invoicing.advances.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.advances*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-coins w-5 text-yellow-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Adiantamentos</span>
                                        </a>
                                        @endcan
                                        
                                        @if(auth()->user()->email === 'carlosfox1782@gmail.com')
                                        <div class="my-2 border-t border-blue-700/50"></div>
                                        
                                        {{-- Gerador de Documentos AGT (Teste) - Apenas para desenvolvedor --}}
                                        <a href="{{ route('invoicing.agt-documents') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.agt-documents') ? 'bg-gradient-to-r from-yellow-600 to-orange-600 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-flask w-5 text-yellow-400 text-sm animate-pulse"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs font-bold">🧪 Gerador AGT</span>
                                        </a>
                                        @endif
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('invoicing'))
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
                                <a href="{{ route('treasury.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.dashboard*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-yellow-400 text-sm"></i>
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('eventos'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('rh'))
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
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.overtime*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-business-time w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Horas Extras</span>
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
                                
                                <a href="{{ route('hr.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('hr.settings*') ? 'bg-blue-700 border-l-4 border-cyan-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cogs w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações RH</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('contabilidade'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('oficina'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('hotel'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('salon'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('notifications'))
                        <!-- Notifications Module -->
                        <div class="mt-6">
                            <a href="{{ route('notifications.settings') }}" 
                               class="flex items-center px-4 py-3 {{ request()->routeIs('notifications.*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                <i class="ri-notification-3-line text-2xl text-yellow-400"></i>
                                <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">Notificações</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('crm'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('inventario'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('compras'))
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

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasActiveModule('projetos'))
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
                            <a href="{{ route('my-account') }}?tab=companies" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-building mr-2 text-purple-600"></i> Minhas Empresas
                            </a>
                            <a href="{{ route('my-account') }}?tab=plan" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-crown mr-2 text-yellow-600"></i> Meu Plano
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

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Bar -->
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="flex items-center justify-between px-6 py-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                            <p class="text-sm text-gray-600">@yield('page-subtitle', 'Bem-vindo ao sistema')</p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Tenant Switcher (Sempre mostra empresa ativa) -->
                            @if(auth()->check() && !auth()->user()->isSuperAdmin())
                                <livewire:tenant-switcher />
                            @endif
                            
                            <!-- Subscription Timer -->
                            @if(auth()->check())
                                <livewire:subscription-timer />
                            @endif
                            
                            <!-- Easter Egg: Fox Paw in Header (FOX Friendly Only) -->
                            @if($isFoxFriendly ?? false)
                                <div class="ml-3" 
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
                            <button class="text-gray-600 hover:text-gray-900">
                                <i class="fas fa-search text-xl"></i>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
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
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarMenu = document.getElementById('sidebar-menu');
            const scrollKey = 'sidebar-scroll-position';
            
            if (!sidebarMenu) return;
            
            // Restaurar posição do scroll ao carregar
            const savedScrollPosition = localStorage.getItem(scrollKey);
            if (savedScrollPosition !== null) {
                sidebarMenu.scrollTop = parseInt(savedScrollPosition, 10);
            }
            
            // Salvar posição do scroll quando rolar
            let scrollTimeout;
            sidebarMenu.addEventListener('scroll', function() {
                // Debounce para não salvar a cada pixel
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    localStorage.setItem(scrollKey, sidebarMenu.scrollTop);
                }, 100);
            });
            
            // Também salvar quando clicar em qualquer link da sidebar
            const sidebarLinks = sidebarMenu.querySelectorAll('a, button');
            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    localStorage.setItem(scrollKey, sidebarMenu.scrollTop);
                });
            });
        });
    </script>
    
    <!-- Custom Scripts Stack -->
    @stack('scripts')
    
    <!-- PWA Service Worker Registration -->
    <script>
        // Registar último acesso online
        localStorage.setItem('soserp-last-online', Date.now().toString());
        
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    .then((reg) => {
                        console.log('[PWA] Service Worker registado com sucesso. Scope:', reg.scope);
                        
                        // Verificar atualizações a cada 60 min
                        setInterval(() => reg.update(), 60 * 60 * 1000);
                        
                        // Notificar quando nova versão disponível
                        reg.addEventListener('updatefound', () => {
                            const newWorker = reg.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'activated' && navigator.serviceWorker.controller) {
                                    if (typeof toastr !== 'undefined') {
                                        toastr.info(
                                            'Nova versão disponível. <a href="javascript:location.reload()" style="color:#fff;font-weight:bold;text-decoration:underline">Atualizar agora</a>',
                                            'Atualização SOS ERP',
                                            { timeOut: 0, extendedTimeOut: 0, closeButton: true, allowHtml: true }
                                        );
                                    }
                                }
                            });
                        });
                    })
                    .catch((err) => console.warn('[PWA] Falha ao registar SW:', err));
            });
        }

        // Indicador de status offline/online
        window.addEventListener('offline', () => {
            document.body.classList.add('app-offline');
            if (typeof toastr !== 'undefined') {
                toastr.warning('Sem conexão à internet. Algumas funcionalidades podem estar limitadas.', 'Offline', { timeOut: 5000 });
            }
        });
        window.addEventListener('online', () => {
            document.body.classList.remove('app-offline');
            localStorage.setItem('soserp-last-online', Date.now().toString());
            if (typeof toastr !== 'undefined') {
                toastr.success('Conexão restaurada!', 'Online', { timeOut: 3000 });
            }
        });
    </script>
    
    <!-- Componente para enviar email de boas-vindas após redirect -->
    @livewire('send-welcome-email')
    
    <!-- Botão Flutuante de Suporte -->
    @if(!auth()->user()->isSuperAdmin())
        @include('components.support-button')
    @endif
</body>
</html>

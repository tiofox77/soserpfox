<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Livewire Styles -->
    @livewireStyles
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Toastr Custom Styles */
        .toast-success { background-color: #10b981 !important; }
        .toast-error { background-color: #ef4444 !important; }
        .toast-warning { background-color: #f59e0b !important; }
        .toast-info { background-color: #3b82f6 !important; }
        
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
    </style>
</head>
<body class="bg-gray-50">
    @auth
        <!-- Layout with Sidebar -->
        <div x-data="{ sidebarOpen: true }" class="flex h-screen overflow-hidden">
            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-gradient-to-b from-blue-900 to-blue-800 text-white transition-all duration-300 flex flex-col shadow-2xl">
                <!-- Logo -->
                <div class="flex items-center justify-between p-4 border-b border-blue-700">
                    <div x-show="sidebarOpen" class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center shadow-lg">
                            <i class="fas fa-crown text-white text-xl"></i>
                        </div>
                        <div>
                            <span class="text-lg font-bold">{{ config('app.name') }}</span>
                            <p class="text-xs text-blue-300">Sistema ERP</p>
                        </div>
                    </div>
                    <button @click="sidebarOpen = !sidebarOpen" class="text-blue-300 hover:text-white transition">
                        <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                    </button>
                </div>

                <!-- Menu -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <div class="px-3 mb-2">
                        <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Menu Principal</p>
                    </div>
                    
                    <a href="{{ route('home') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('home') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                        <i class="fas fa-home w-6 text-blue-300"></i>
                        <span x-show="sidebarOpen" class="ml-3">Início</span>
                    </a>
                    
                    <a href="{{ route('users.index') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('users.*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                        <i class="fas fa-users w-6 text-purple-300"></i>
                        <span x-show="sidebarOpen" class="ml-3">Utilizadores</span>
                    </a>

                    @if(!auth()->user()->isSuperAdmin() && auth()->user()->hasActiveModule('invoicing'))
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
                                
                                <a href="{{ route('invoicing.pos') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.pos') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cash-register w-5 text-emerald-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">🛒 POS - Ponto de Venda</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('invoicing.clients') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.clients*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-users w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Clientes</span>
                                </a>
                                
                                <a href="{{ route('invoicing.suppliers') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.suppliers*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-truck w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Fornecedores</span>
                                </a>
                                
                                <a href="{{ route('invoicing.products') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.products*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-box w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Produtos</span>
                                </a>
                                
                                <a href="{{ route('invoicing.categories') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.categories*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-folder w-5 text-cyan-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Categorias</span>
                                </a>
                                
                                <a href="{{ route('invoicing.brands') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.brands*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-tag w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Marcas</span>
                                </a>
                                
                                <a href="{{ route('invoicing.invoices') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.invoices*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-invoice w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Faturas</span>
                                </a>
                                
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
                                        
                                        <a href="{{ route('invoicing.sales.proformas') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.proformas*') ? 'bg-blue-700 border-l-4 border-purple-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice-dollar w-5 text-purple-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Proformas Venda</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.sales.invoices') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.sales.invoices*') ? 'bg-blue-700 border-l-4 border-indigo-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice w-5 text-indigo-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas Venda</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.purchases.proformas') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.purchases.proformas*') ? 'bg-blue-700 border-l-4 border-orange-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice w-5 text-orange-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Proformas Compra</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.purchases.invoices') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.purchases.invoices*') ? 'bg-blue-700 border-l-4 border-red-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-invoice-dollar w-5 text-red-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Faturas Compra</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.receipts.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.receipts*') ? 'bg-blue-700 border-l-4 border-blue-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-receipt w-5 text-blue-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Recibos</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.credit-notes.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.credit-notes*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-minus w-5 text-green-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Crédito</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.debit-notes.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.debit-notes*') ? 'bg-blue-700 border-l-4 border-red-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-file-circle-plus w-5 text-red-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Notas Débito</span>
                                        </a>
                                        
                                        <a href="{{ route('invoicing.advances.index') }}" 
                                           class="flex items-center pl-4 pr-4 py-2.5 {{ request()->routeIs('invoicing.advances*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                            <i class="fas fa-coins w-5 text-yellow-400 text-sm"></i>
                                            <span x-show="sidebarOpen" class="ml-3 text-xs">Adiantamentos</span>
                                        </a>
                                    </div>
                                </div>
                                
                                <a href="{{ route('invoicing.warehouses') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.warehouses*') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-warehouse w-5 text-indigo-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Armazéns</span>
                                </a>
                                
                                <a href="{{ route('invoicing.stock') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.stock') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-boxes w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Gestão Stock</span>
                                </a>
                                
                                <a href="{{ route('invoicing.warehouse-transfer') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.warehouse-transfer') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transfer. Armazéns</span>
                                </a>
                                
                                <a href="{{ route('invoicing.inter-company-transfer') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.inter-company-transfer') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-building-circle-arrow-right w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transfer. Inter-Empresa</span>
                                </a>
                                
                                <div class="my-2 border-t border-blue-700/50"></div>
                                
                                <a href="{{ route('invoicing.taxes') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.taxes') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-percent w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Impostos (IVA)</span>
                                </a>
                                
                                <a href="{{ route('invoicing.series') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.series') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-hashtag w-5 text-pink-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Séries de Documentos</span>
                                </a>
                                
                                <a href="{{ route('invoicing.saft-generator') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.saft-generator') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-code w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Gerador SAFT-AO</span>
                                </a>
                                
                                <a href="{{ route('invoicing.settings') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('invoicing.settings') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cogs w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Configurações</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(!auth()->user()->isSuperAdmin() && auth()->user()->hasActiveModule('invoicing'))
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
                                
                                <a href="{{ route('treasury.dashboard') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.dashboard*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-chart-line w-5 text-yellow-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Dashboard</span>
                                </a>
                                
                                <a href="{{ route('treasury.reports') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.reports*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-file-invoice-dollar w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Relatórios</span>
                                </a>
                                
                                <a href="{{ route('treasury.payment-methods') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.payment-methods*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-money-bill-wave w-5 text-green-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Métodos de Pagamento</span>
                                </a>
                                
                                <a href="{{ route('treasury.banks') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.banks*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-university w-5 text-blue-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Bancos</span>
                                </a>
                                
                                <a href="{{ route('treasury.accounts') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.accounts*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-wallet w-5 text-purple-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Contas Bancárias</span>
                                </a>
                                
                                <a href="{{ route('treasury.cash-registers') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.cash-registers*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-cash-register w-5 text-orange-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Caixas</span>
                                </a>
                                
                                <a href="{{ route('treasury.transactions') }}" 
                                   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('treasury.transactions*') ? 'bg-blue-700 border-l-4 border-green-400' : 'hover:bg-blue-700/50' }} transition">
                                    <i class="fas fa-exchange-alt w-5 text-teal-400 text-sm"></i>
                                    <span x-show="sidebarOpen" class="ml-3 text-sm">Transações</span>
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
                            <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">Configurações</p>
                        </div>
                        
                        <a href="{{ route('superadmin.saft') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('superadmin.saft') ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                            <i class="fas fa-key w-6 text-orange-400"></i>
                            <span x-show="sidebarOpen" class="ml-3">SAFT Configurações</span>
                        </a>
                    @endif
                </nav>

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
                            <!-- Subscription Timer -->
                            @if(auth()->check())
                                <livewire:subscription-timer />
                            @endif
                            
                            <!-- Tenant Switcher (se usuário tem mais de 1 empresa) -->
                            @if(auth()->check() && canSwitchTenants())
                                <livewire:tenant-switcher />
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

        // Configuração global do Livewire
        document.addEventListener('livewire:init', () => {
            Livewire.on('success', (event) => {
                toastr.success(event.message || event[0].message || 'Operação realizada com sucesso!');
            });
            
            Livewire.on('error', (event) => {
                toastr.error(event.message || event[0].message || 'Ocorreu um erro!');
            });
            
            Livewire.on('warning', (event) => {
                toastr.warning(event.message || event[0].message || 'Atenção!');
            });
            
            Livewire.on('info', (event) => {
                toastr.info(event.message || event[0].message || 'Informação!');
            });
            
            // Listener para notificações genéricas (com tipo dinâmico)
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Notificação';
                
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
</body>
</html>

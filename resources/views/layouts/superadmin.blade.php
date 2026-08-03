<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ app_name() }} - Super Admin</title>
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    @endif
    
    <!-- PWA -->
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    @livewireStyles
    
    <style>
        [x-cloak] { display: none !important; }
        
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
</head>
<body class="bg-gray-50">
    <div x-data="{ sidebarOpen: true }" class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-gradient-to-b from-blue-900 to-blue-800 text-white transition-all duration-300 flex flex-col shadow-2xl">
            <!-- Logo -->
            <div class="flex items-center justify-between p-4 border-b border-blue-700">
                <div class="flex items-center justify-center" :class="sidebarOpen ? 'w-full' : ''">
                    @if(app_logo())
                        <img src="{{ app_logo() }}" alt="{{ app_name() }}" :class="sidebarOpen ? 'h-16 w-auto max-w-[200px]' : 'h-12 w-12 object-contain'">
                    @else
                        <div :class="sidebarOpen ? 'w-12 h-12' : 'w-10 h-10'" class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center shadow-lg">
                            <i class="fas fa-crown text-white" :class="sidebarOpen ? 'text-2xl' : 'text-xl'"></i>
                        </div>
                    @endif
                </div>
                <button @click="sidebarOpen = !sidebarOpen" class="text-blue-300 hover:text-white transition ml-2">
                    <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                </button>
            </div>

            <!-- Menu -->
            @php
                // Estrutura unica do menu (data-driven). Reorganizado por areas logicas.
                // Cada seccao: titulo => lista de itens [route, icon, color, label].
                $navSections = [
                    '' => [
                        ['route' => 'home', 'icon' => 'fas fa-home', 'color' => 'text-yellow-400', 'label' => 'Início'],
                    ],
                    'Principal' => [
                        ['route' => 'superadmin.dashboard', 'icon' => 'fas fa-chart-line', 'color' => 'text-yellow-400', 'label' => 'Dashboard'],
                        ['route' => 'superadmin.analytics', 'icon' => 'fas fa-fire', 'color' => 'text-orange-400', 'label' => 'Analytics & Leads'],
                    ],
                    'Comercial' => [
                        ['route' => 'superadmin.tenants', 'icon' => 'fas fa-building', 'color' => 'text-green-400', 'label' => 'Empresas / Tenants'],
                        ['route' => 'superadmin.plans', 'icon' => 'fas fa-tags', 'color' => 'text-pink-400', 'label' => 'Planos'],
                        ['route' => 'superadmin.modules', 'icon' => 'fas fa-puzzle-piece', 'color' => 'text-purple-400', 'label' => 'Módulos'],
                        ['route' => 'superadmin.billing', 'icon' => 'fas fa-file-invoice-dollar', 'color' => 'text-emerald-400', 'label' => 'Faturação / Billing'],
                    ],
                    'Comunicação' => [
                        ['route' => 'superadmin.contact-messages', 'icon' => 'fas fa-comments', 'color' => 'text-cyan-400', 'label' => 'Mensagens de Contacto'],
                        ['route' => 'superadmin.email-templates', 'icon' => 'fas fa-envelope', 'color' => 'text-blue-400', 'label' => 'Email Templates'],
                        ['route' => 'superadmin.smtp-settings', 'icon' => 'fas fa-server', 'color' => 'text-emerald-400', 'label' => 'SMTP'],
                        ['route' => 'superadmin.email-logs', 'icon' => 'fas fa-history', 'color' => 'text-yellow-400', 'label' => 'Email Logs'],
                        ['route' => 'superadmin.sms-settings', 'icon' => 'fas fa-sms', 'color' => 'text-green-400', 'label' => 'SMS'],
                        ['route' => 'superadmin.whatsapp-notifications', 'icon' => 'fab fa-whatsapp', 'color' => 'text-green-400', 'label' => 'WhatsApp'],
                    ],
                    'Sistema' => [
                        ['route' => 'superadmin.system-updates', 'icon' => 'fas fa-cloud-download-alt', 'color' => 'text-cyan-400', 'label' => 'Atualizações'],
                        ['route' => 'superadmin.system-commands', 'icon' => 'fas fa-terminal', 'color' => 'text-green-400', 'label' => 'Comandos & Seeders'],
                        ['route' => 'superadmin.script-runner', 'icon' => 'fas fa-code', 'color' => 'text-amber-400', 'label' => 'Script Runner'],
                        ['route' => 'superadmin.system-optimization', 'icon' => 'fas fa-rocket', 'color' => 'text-yellow-400', 'label' => 'Otimização'],
                    ],
                    'Configuração' => [
                        ['route' => 'superadmin.system-settings', 'icon' => 'fas fa-cog', 'color' => 'text-purple-400', 'label' => 'Gerais'],
                        ['route' => 'superadmin.software-settings', 'icon' => 'fas fa-shield-alt', 'color' => 'text-red-400', 'label' => 'Software'],
                        ['route' => 'superadmin.saft', 'icon' => 'fas fa-key', 'color' => 'text-orange-400', 'label' => 'SAFT-AO'],
                    ],
                ];
            @endphp
            <nav class="flex-1 overflow-y-auto py-4">
                @foreach($navSections as $section => $items)
                    @if($section !== '')
                        <div class="border-t border-blue-700/50 my-3"></div>
                        <div class="px-4 mb-1">
                            <p x-show="sidebarOpen" class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">{{ $section }}</p>
                        </div>
                    @endif

                    @foreach($items as $item)
                        @php $active = request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                           class="flex items-center px-4 py-2.5 {{ $active ? 'bg-blue-700/80 border-l-4 border-yellow-400' : 'hover:bg-blue-700/40' }} transition">
                            <i class="{{ $item['icon'] }} w-5 {{ $item['color'] }}"></i>
                            <span x-show="sidebarOpen" class="ml-3 text-sm">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <!-- User Menu -->
            <div class="border-t border-blue-700 p-4">
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center w-full text-left hover:bg-blue-700/50 rounded-lg p-2 transition">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div x-show="sidebarOpen" class="ml-3 flex-1">
                            <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-blue-300">Super Admin</p>
                        </div>
                        <i x-show="sidebarOpen" class="fas fa-chevron-up text-sm" :class="open ? '' : 'rotate-180'"></i>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute bottom-full left-0 mb-2 w-full bg-white rounded-lg shadow-xl py-2">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user-circle mr-2 text-blue-600"></i> Perfil
                        </a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-2 text-gray-600"></i> Configurações
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
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">@yield('title', 'Dashboard')</h1>
                        <p class="text-sm text-gray-600">@yield('subtitle', 'Área de administração')</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        @if(auth()->check())
                            <livewire:notifications />
                        @endif
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
    </div>

    @livewireScripts
    
    <!-- jQuery (required for Toastr) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        // Configuração do Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Configuração global do Livewire
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
        });

        // Mostrar flash messages
        @if(session()->has('message'))
            toastr.success('{{ session('message') }}');
        @endif

        @if(session()->has('error'))
            toastr.error('{{ session('error') }}');
        @endif

        @if(session()->has('warning'))
            toastr.warning('{{ session('warning') }}');
        @endif

        @if(session()->has('info'))
            toastr.info('{{ session('info') }}');
        @endif
    </script>
    
    <!-- PWA Service Worker -->
    @include('partials.pwa-register')
    <script>
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <title>{{ $title ?? 'Portal do Cliente' }} - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    <meta name="description" content="Portal exclusivo para clientes. Visualize faturas, eventos, documentos e acompanhe o status dos seus serviços.">
    <meta name="keywords" content="portal cliente, área cliente, minhas faturas, meus eventos, {{ app_name() ?? config('app.name') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="{{ app_name() ?? config('app.name') }}">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? 'Portal do Cliente' }} - {{ app_name() ?? config('app.name') }}">
    <meta property="og:description" content="Portal exclusivo para clientes">
    <meta property="og:site_name" content="{{ app_name() ?? config('app.name') }}">
    @if(app_logo())
    <meta property="og:image" content="{{ app_logo() }}">
    @endif
    
    <!-- Favicon -->
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    <link rel="apple-touch-icon" href="{{ app_favicon() }}">
    @else
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    @endif
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.3); }
            50% { box-shadow: 0 0 30px rgba(59, 130, 246, 0.6); }
        }
        
        .icon-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card-3d {
            perspective: 1000px;
            transform-style: preserve-3d;
        }
        
        .card-3d:hover {
            transform: translateY(-8px) rotateX(2deg) rotateY(2deg);
        }
        
        .card-zoom:hover {
            transform: scale(1.05);
        }
        
        .card-glow:hover {
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        .stagger-animation > * {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
        
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
        
        /* Smooth Transitions */
        * {
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
    
    @livewireStyles
</head>
<body class="bg-gray-100">
    {{-- Navbar --}}
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                {{-- Logo --}}
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="h-12 w-auto object-contain mr-3">
                        @else
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-users text-white"></i>
                            </div>
                        @endif
                        <span class="text-xl font-bold text-gray-900">Portal do Cliente</span>
                    </div>
                </div>

                {{-- Menu --}}
                <div class="flex items-center space-x-2">
                    @if(Route::has('client.dashboard'))
                        <a href="{{ route('client.dashboard') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-home mr-1"></i>Início
                        </a>
                    @endif
                    @if(Route::has('client.statement'))
                        <a href="{{ route('client.statement') }}" class="text-gray-700 hover:text-amber-600 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-chart-line mr-1"></i>Extrato
                        </a>
                    @endif
                    @if(Route::has('client.events'))
                        <a href="{{ route('client.events') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-calendar-alt mr-1"></i>Eventos
                        </a>
                    @endif
                    @if(Route::has('client.invoices'))
                        <a href="{{ route('client.invoices') }}" class="text-gray-700 hover:text-green-600 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-file-invoice mr-1"></i>Faturas
                        </a>
                    @endif
                    @if(Route::has('client.proformas'))
                        <a href="{{ route('client.proformas') }}" class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-file-alt mr-1"></i>Proformas
                        </a>
                    @endif
                    
                    {{-- User Menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center text-gray-700 hover:text-blue-600 focus:outline-none">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <i class="fas fa-chevron-down ml-2 text-sm"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                            @if(Route::has('client.profile'))
                                <a href="{{ route('client.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i>Meu Perfil
                                </a>
                            @endif
                            <button wire:click="logout" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Sair
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Content --}}
    <main class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </main>

    {{-- Footer --}}
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
            </p>
        </div>
    </footer>

    @livewireScripts
    
    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- PWA Service Worker -->
    <script>
        localStorage.setItem('soserp-last-online', Date.now().toString());
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        }
        window.addEventListener('offline', () => document.body.classList.add('app-offline'));
        window.addEventListener('online', () => { document.body.classList.remove('app-offline'); location.reload(); });
    </script>
</body>
</html>

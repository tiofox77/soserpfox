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
    
    <script>
        // A barra lateral lembra-se de estar encolhida: o lugar reservado para
        // ela tem de o saber ANTES de o React a desenhar.
        try { if (localStorage.getItem('casca:aberta') === '0') document.documentElement.dataset.casca = 'fechada'; } catch (e) {}
    </script>
    <style>
        [x-cloak] { display: none !important; }

        /* O LUGAR DA BARRA LATERAL enquanto o React não a desenha. */
        @media (min-width: 768px) {
            .casca-lugar:not(:has(aside)) { width: 5rem; background: linear-gradient(to bottom, #1e3a8a, #1e40af); }
        }
        @media (min-width: 1024px) {
            html:not([data-casca="fechada"]) .casca-lugar:not(:has(aside)) { width: 16rem; }
        }

        /* A barra de progresso de quem muda de página (ver o fim do layout). */
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
        <!-- Layout with Sidebar -->
        <div x-data="{
            sidebarOpen: window.innerWidth >= 1024,
            isMobile: window.innerWidth < 768,
            isTablet: window.innerWidth >= 768 && window.innerWidth < 1024,
            {{-- A entrada em cascata da barra lateral só na primeira página da
                 sessão do separador: sem a navegação do Livewire, cada página
                 é um carregamento novo, e a cascata a cada clique cansa. --}}
            firstLoad: (() => { try { const ja = sessionStorage.getItem('casca-vista'); sessionStorage.setItem('casca-vista', '1'); return !ja; } catch (e) { return true; } })(),
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
        }" @casca:estado.window="sidebarOpen = $event.detail.aberta" class="flex h-screen overflow-hidden">
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
                                <x-ecra-react nome="casca/notificacoes" :esqueleto="false" />
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

    {{-- O dicionário para o JavaScript dos ecrãs (POS, modais de impressão).
         Em português não emite nada — as chaves são o texto português. --}}
    @include('partials.js-traducoes')

    @include('partials.alpine')
    
    <!-- Toastr CDN -->
    <link rel="stylesheet" href="/vendor/css/toastr.min.css">
    <script src="/vendor/js/jquery.min.js"></script>
    <script src="/vendor/js/toastr.min.js"></script>

    {{-- Máscara de dinheiro (1.234,56) nos inputs de preço/valores. Delegada:
         apanha também os campos que um ecrã desenha depois de a página abrir. --}}
    <script src="{{ asset('js/mascara-dinheiro.js') }}?v=2" defer></script>

    <script>
        // Configuração do Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        (function () {
            // ── Recuperação de sessão expirada — sem freeze, sem perder a venda ──
            // Quando a sessão morre por inatividade, o keep-alive dá por isso. O
            // comportamento antigo (reload cego) perdia a venda em curso no POS.
            // Numa página POS mostra-se um overlay claro (o carrinho fica guardado
            // no cliente e é restaurado após novo login); nas outras faz-se só um
            // reload suave.
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

        })();
    </script>
    
    
    {{--
        A BARRA DE PROGRESSO DE QUEM MUDA DE PÁGINA.

        Era da navegação do Livewire. Agora cada ligação é uma página nova, e a
        barra corre do clique até o browser trocar de página — quem está numa
        rede fraca vê que o clique foi ouvido, em vez de carregar outra vez.
    --}}
    <div id="spa-progress" class="spa-progress" style="width: 0%;"></div>
    <script>
        (function () {
            const bar = document.getElementById('spa-progress');
            let relogio;

            const comecar = () => {
                bar.classList.remove('done');
                let w = 0;
                clearInterval(relogio);
                relogio = setInterval(() => {
                    w += (95 - w) * 0.08;
                    bar.style.width = w + '%';
                    if (w >= 94) clearInterval(relogio);
                }, 80);
            };

            document.addEventListener('click', (e) => {
                const a = e.target.closest && e.target.closest('a[href]');
                if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (a.target && a.target !== '_self') return;
                if (a.hasAttribute('download') || a.origin !== location.origin) return;
                if (a.getAttribute('href').startsWith('#') || (a.pathname === location.pathname && a.search === location.search && a.hash)) return;
                comecar();
            });

            // Voltar atrás pela cache do browser: a barra não pode ficar a meio.
            window.addEventListener('pageshow', () => {
                clearInterval(relogio);
                bar.style.width = '0%';
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

    {{-- Os gráficos do painel de facturação, num ficheiro que o browser guarda. --}}
    <script src="/js/painel-facturacao.js?v={{ filemtime(public_path('js/painel-facturacao.js')) }}" defer></script>

    {{-- OS ECRÃS EM REACT. O bloco vive num partial porque o painel da
         plataforma tem layout próprio e precisa do mesmo. --}}
    @include('partials.react-pacote')

    <!-- Custom Scripts Stack -->
    @stack('scripts')
    
    <!-- PWA Service Worker Registration + Auto-Update -->
    @include('partials.pwa-register')
    
    <!-- Botão Flutuante de Suporte -->
    @if(!auth()->user()->isSuperAdmin())
        @include('components.support-button')
    @endif
</body>
</html>

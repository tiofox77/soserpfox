@props(['title' => 'Módulo', 'description' => '', 'ctaText' => null, 'whatsapp' => null, 'gradientFrom' => '#2563eb', 'gradientTo' => '#7c3aed', 'tituloSeo' => null, 'canonical' => null, 'dadosEstruturados' => null])
@php
    $tituloDaPagina = $tituloSeo ? $tituloSeo . ' | SOSERP' : $title . ' — ' . (function_exists('app_name') ? app_name() : 'SOSERP');
    $imagemDePartilha = asset('brand/soserp-og-1200x630.png');
@endphp
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tituloDaPagina }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    @if($canonical)
    <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SOSERP">
    <meta property="og:locale" content="pt_AO">
    <meta property="og:title" content="{{ $tituloDaPagina }}">
    <meta property="og:description" content="{{ $description }}">
    @if($canonical)
    <meta property="og:url" content="{{ $canonical }}">
    @endif
    <meta property="og:image" content="{{ $imagemDePartilha }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $imagemDePartilha }}">
    {{ $dadosEstruturados }}
    @include('partials.favicon')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    {{-- v=2: o recolector passou a medir o tempo em página e a registar
         pesquisas. Sem subir a versão, os browsers serviam o ficheiro antigo
         da cache e nada disso chegava cá. --}}
    <script src="{{ asset('js/sos-tracker.js') }}?v=3" data-versao="{{ config('privacidade.versao') }}" defer></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .feature-card { transition: all 0.3s; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15); }
    </style>
    {{-- No fim do <head>: era aqui que o Tailwind em runtime injectava o CSS, e a cascata depende disso. --}}
    @include('partials.css-publico')
</head>
<body class="bg-white">

    {{-- Navigation (igual à landing) --}}
    <nav class="bg-white shadow-sm fixed w-full top-0 z-50 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center" style="padding: 10px 0;">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/" class="flex items-center">
                            @if(function_exists('app_logo') && app_logo())
                                <img src="{{ app_logo() }}" alt="{{ function_exists('app_name') ? app_name() : 'SOSERP' }}" style="height: 80px; max-height: 80px;" class="w-auto object-contain">
                            @else
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                    <i class="fas fa-chart-line text-white text-2xl"></i>
                                </div>
                                <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ function_exists('app_name') ? app_name() : 'SOSERP' }}</span>
                            @endif
                        </a>
                    </div>
                    <div class="hidden lg:ml-8 lg:flex lg:space-x-1 xl:space-x-3">
                        <a href="/#recursos" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Recursos</a>
                        <a href="/#certificacao" class="text-green-700 hover:text-green-600 px-2 py-2 text-sm font-medium transition inline-flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-shield-alt text-xs"></i> Certificação AGT
                        </a>
                        <a href="/modulos" class="text-blue-600 px-2 py-2 text-sm font-bold transition whitespace-nowrap">Módulos</a>
                        <a href="/#planos" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Planos</a>
                        <a href="/#roadmap" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Roadmap</a>
                        <a href="/#contacto" class="text-gray-700 hover:text-blue-600 px-2 py-2 text-sm font-medium transition whitespace-nowrap">Contacto</a>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('client.login') }}" class="hidden xl:inline-flex items-center text-purple-700 hover:text-purple-800 text-sm font-medium transition px-2 py-2 whitespace-nowrap" title="Portal do Cliente">
                        <i class="fas fa-users mr-1"></i>Área Cliente
                    </a>
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center text-gray-700 hover:text-blue-600 text-sm font-medium transition px-2 py-2 whitespace-nowrap">
                        <i class="fas fa-sign-in-alt mr-1"></i>Entrar
                    </a>
                    <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl text-sm font-semibold hover:shadow-lg transition px-4 py-2.5 whitespace-nowrap inline-flex items-center">
                        <i class="fas fa-rocket mr-2"></i>Começar Grátis
                    </a>
                    <button onclick="document.getElementById('mod-mobile-menu').classList.toggle('hidden')" class="lg:hidden text-gray-700 hover:text-blue-600 ml-1 p-2">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div id="mod-mobile-menu" class="hidden lg:hidden border-t border-gray-200 py-3">
                <div class="flex flex-col gap-1 text-sm font-medium">
                    <a href="/#recursos" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Recursos</a>
                    <a href="/#certificacao" class="text-green-700 hover:bg-green-50 px-3 py-2 rounded-lg"><i class="fas fa-shield-alt text-xs mr-1"></i>Certificação AGT</a>
                    <a href="/modulos" class="text-blue-600 font-bold hover:bg-blue-50 px-3 py-2 rounded-lg">Módulos</a>
                    <a href="/#planos" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Planos</a>
                    <a href="/#roadmap" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Roadmap</a>
                    <a href="/#contacto" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg">Contacto</a>
                    <a href="{{ route('client.login') }}" class="text-purple-700 hover:bg-purple-50 px-3 py-2 rounded-lg"><i class="fas fa-users mr-1"></i>Área Cliente</a>
                    <a href="{{ route('login') }}" class="text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 rounded-lg"><i class="fas fa-sign-in-alt mr-1"></i>Entrar</a>
                </div>
            </div>
        </div>
    </nav>

    {{-- Sub-nav de módulos (debaixo da nav principal) --}}
    <div class="fixed top-[100px] w-full bg-gradient-to-r from-blue-50 to-purple-50 border-b border-gray-200 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-1 overflow-x-auto py-2 text-xs font-medium scrollbar-hide">
                <a href="/modulos" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-blue-600 whitespace-nowrap transition">
                    <i class="fas fa-th-large mr-1"></i>Todos
                </a>
                <a href="/modulos/vendas" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-orange-600 whitespace-nowrap transition">📊 Vendas</a>
                <a href="/modulos/rh" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-purple-600 whitespace-nowrap transition">👥 RH</a>
                <a href="/modulos/hotel" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-cyan-600 whitespace-nowrap transition">🏨 Hotel</a>
                <a href="/modulos/salao" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-pink-600 whitespace-nowrap transition">💇 Salão</a>
                <a href="/modulos/oficina" class="px-3 py-1.5 rounded-full text-gray-600 hover:bg-white hover:text-orange-700 whitespace-nowrap transition">🔧 Oficina</a>
            </div>
        </div>
    </div>

    {{-- Conteúdo da página com offset para a nav fixa --}}
    <main style="padding-top: 144px;">
        {{ $slot }}
    </main>

    {{-- CTA Final --}}
    <section class="py-16" style="background: linear-gradient(135deg, {{ $gradientFrom }}, {{ $gradientTo }});">
        <div class="max-w-4xl mx-auto px-4 text-center text-white">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Pronto para começar?</h2>
            <p class="text-lg opacity-90 mb-8">{{ $ctaText ?? 'Experimenta grátis durante 14 dias. Sem cartão de crédito.' }}</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-gray-900 px-8 py-3 rounded-xl font-bold hover:bg-gray-100 shadow-lg">
                    <i class="fas fa-rocket mr-2"></i>Começar Grátis
                </a>
                @if($whatsapp)
                <a href="https://wa.me/{{ $whatsapp }}" target="_blank" class="bg-white/20 border-2 border-white text-white px-8 py-3 rounded-xl font-bold hover:bg-white/30">
                    <i class="fab fa-whatsapp mr-2"></i>Falar com Comercial
                </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Footer (igual à landing) --}}
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8 mb-8">
                <div>
                    <div class="flex items-center mb-4">
                        @if(function_exists('app_logo') && app_logo())
                            <img src="{{ app_logo() }}" alt="{{ function_exists('app_name') ? app_name() : 'SOSERP' }}" style="max-height: 3rem;" class="h-12 w-auto object-contain">
                        @else
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-white">{{ function_exists('app_name') ? app_name() : 'SOSERP' }}</span>
                        @endif
                    </div>
                    <p class="text-sm">Sistema de Gestão Empresarial completo e moderno para empresas angolanas.</p>
                </div>

                <div>
                    <h3 class="text-white font-bold mb-4">Produto</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#recursos" class="hover:text-white">Recursos</a></li>
                        <li><a href="/modulos" class="hover:text-white">Módulos</a></li>
                        <li><a href="/modulos/vendas" class="hover:text-white">— Vendas</a></li>
                        <li><a href="/modulos/rh" class="hover:text-white">— RH</a></li>
                        <li><a href="/modulos/hotel" class="hover:text-white">— Hotel</a></li>
                        <li><a href="/modulos/salao" class="hover:text-white">— Salão</a></li>
                        <li><a href="/modulos/oficina" class="hover:text-white">— Oficina</a></li>
                        <li><a href="/#planos" class="hover:text-white">Planos</a></li>
                        <li><a href="/#roadmap" class="hover:text-white">Roadmap</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-white font-bold mb-4">Empresa</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#sobre" class="hover:text-white">Sobre</a></li>
                        <li><a href="/#contacto" class="hover:text-white">Contacto</a></li>
                        <li><a href="/#contacto" class="hover:text-white">Suporte</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-white font-bold mb-4">Legal</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('legal.termos') }}" class="hover:text-white">Termos de Uso</a></li>
                        <li><a href="{{ route('legal.privacidade') }}" class="hover:text-white">Privacidade</a></li>
                        <li><a href="{{ route('legal.cookies') }}" class="hover:text-white">Cookies</a></li>
                        <li><a href="#" data-abrir-consentimento class="hover:text-white"><i class="fas fa-sliders mr-1"></i>Preferências de cookies</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-white font-bold mb-4">Contacto</h3>
                    <ul class="space-y-3 text-sm">
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-envelope mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <a href="mailto:suporte@soserp.vip" class="hover:text-white block">suporte@soserp.vip</a>
                                    <a href="mailto:comercial@soserp.vip" class="hover:text-white block">comercial@soserp.vip</a>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-phone mt-1 mr-2 text-blue-400"></i>
                                <a href="tel:+244939729902" class="hover:text-white">+244 939 729 902</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <p>Luanda, Angola</p>
                                    <p>Talatona, Rua Principal</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} Softec Angola. SOSERP. Todos os direitos reservados.</p>
                <p class="mt-2">
                    Desenvolvido por
                    <a href="https://softecangola.net" target="_blank" class="text-blue-400 hover:text-blue-300 font-semibold">
                        Softec Angola
                    </a>
                </p>
            </div>
        </div>
    </footer>

    @include('partials.consentimento')
</body>
</html>

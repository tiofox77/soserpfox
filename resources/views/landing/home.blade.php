<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>SOSERP - Sistema de Gestão Empresarial Multi-Tenant em Angola</title>
    <meta name="description" content="Sistema completo de gestão empresarial em Angola. Gerencie eventos, inventário, CRM, faturação, RH e contabilidade. Solução profissional multi-tenant.">
    <meta name="keywords" content="ERP Angola, sistema gestão empresarial, gestão eventos, CRM, faturação, inventário, contabilidade Angola">
    <meta name="author" content="SOSERP">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="language" content="Portuguese">
    <meta name="geo.region" content="AO">
    <meta name="geo.placename" content="Angola">
    
    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://soserp.vip">
    <meta property="og:title" content="SOS ERP - Sistema de Gestão Empresarial">
    <meta property="og:description" content="Plataforma completa de gestão empresarial em Angola. Gestão de Eventos, Inventário, CRM, Faturação, RH e Contabilidade. Multi-tenant, Seguro e Profissional. Teste grátis por 14 dias!">
    <meta property="og:site_name" content="SOS ERP">
    <meta property="og:locale" content="pt_AO">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SOSERP - Sistema de Gestão Empresarial">
    <meta name="twitter:description" content="Sistema completo de gestão empresarial em Angola.">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="https://soserp.vip">
    
    <!-- Preconnect para Performance -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "SoftwareApplication",
      "name": "SOSERP",
      "description": "Sistema de Gestão Empresarial Multi-Tenant para empresas em Angola",
      "url": "https://soserp.vip",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "offers": {
        "@@type": "Offer",
        "price": "0",
        "priceCurrency": "AOA",
        "availability": "https://schema.org/InStock",
        "eligibleRegion": {
          "@type": "Place",
          "name": "Angola"
        }
      },
      "aggregateRating": {
        "@@type": "AggregateRating",
        "ratingValue": "4.8",
        "reviewCount": "150"
      },
      "creator": {
        "@@type": "Organization",
        "name": "SOSERP",
        "url": "https://soserp.vip"
      }
    }
    </script>
    
    <!-- Prevenir FOUC: Força tamanhos de imagem antes de qualquer script -->
    <style>
        /* Crítico: carrega ANTES de qualquer framework */
        img[src*="/storage/"] {
            max-height: 80px !important;
            object-fit: contain !important;
        }
        nav img {
            max-height: 80px !important;
            height: 80px !important;
            width: auto !important;
        }
        footer img {
            max-height: 3rem !important;
            height: 3rem !important;
            width: auto !important;
        }
    </style>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-white">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-sm fixed w-full top-0 z-50 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center" style="padding: 10px 0;">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="height: 80px; max-height: 80px;" class="w-auto object-contain">
                        @else
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ app_name() }}</span>
                        @endif
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="#recursos" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Recursos</a>
                        <a href="#planos" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Planos</a>
                        <a href="#roadmap" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Roadmap</a>
                        <a href="#contacto" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Contacto</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('login') }}" class="text-gray-700 hover:text-blue-600 text-sm font-medium transition" style="padding: 10px;">
                        <i class="fas fa-sign-in-alt mr-2"></i>Entrar
                    </a>
                    <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl text-sm font-semibold hover:shadow-lg transition" style="padding: 10px 20px;">
                        <i class="fas fa-rocket mr-2"></i>Começar Grátis
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pb-20 bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50" style="padding-top: 120px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h1 class="text-5xl md:text-6xl font-bold text-gray-900 leading-tight mb-6">
                        Gestão Empresarial
                        <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Completa</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8">
                        Sistema completo de gestão empresarial multi-empresa com faturação, inventário, RH e muito mais. 
                        Tudo que sua empresa precisa em um só lugar.
                    </p>
                    <div class="flex space-x-4">
                        <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:shadow-xl transition">
                            <i class="fas fa-rocket mr-2"></i>Começar Agora
                        </a>
                        <a href="#planos" class="bg-white text-gray-900 px-8 py-4 rounded-xl text-lg font-semibold border-2 border-gray-200 hover:border-blue-600 transition">
                            <i class="fas fa-crown mr-2"></i>Ver Planos
                        </a>
                    </div>
                    <div class="mt-8 flex items-center space-x-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            14 dias grátis
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Sem cartão de crédito
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Cancele quando quiser
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-3xl opacity-10 blur-3xl"></div>
                    <div class="relative bg-white rounded-3xl shadow-2xl p-4">
                        <!-- SVG Dashboard Preview -->
                        <svg viewBox="0 0 800 500" class="rounded-xl shadow-lg w-full h-auto" xmlns="http://www.w3.org/2000/svg">
                            <!-- Background -->
                            <rect width="800" height="500" fill="#F9FAFB"/>
                            
                            <!-- Sidebar -->
                            <rect width="200" height="500" fill="url(#sidebarGradient)"/>
                            <defs>
                                <linearGradient id="sidebarGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#1E3A8A;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#1E40AF;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            
                            <!-- Logo Sidebar -->
                            <circle cx="100" cy="30" r="15" fill="#FBBF24"/>
                            <text x="100" y="65" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="middle" font-weight="bold">SOSERP</text>
                            
                            <!-- Menu Items -->
                            <rect x="15" y="100" width="170" height="35" rx="8" fill="#2563EB" opacity="0.3"/>
                            <circle cx="35" cy="117.5" r="8" fill="#60A5FA"/>
                            <text x="55" y="122" font-family="Arial, sans-serif" font-size="11" fill="white">Dashboard</text>
                            
                            <circle cx="35" cy="157.5" r="8" fill="#A78BFA"/>
                            <text x="55" y="162" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Faturação</text>
                            
                            <circle cx="35" cy="197.5" r="8" fill="#34D399"/>
                            <text x="55" y="202" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Tesouraria</text>
                            
                            <circle cx="35" cy="237.5" r="8" fill="#F59E0B"/>
                            <text x="55" y="242" font-family="Arial, sans-serif" font-size="11" fill="#93C5FD">Clientes</text>
                            
                            <!-- Header -->
                            <rect x="200" y="0" width="600" height="60" fill="white"/>
                            <line x1="200" y1="60" x2="800" y2="60" stroke="#E5E7EB" stroke-width="1"/>
                            <text x="220" y="35" font-family="Arial, sans-serif" font-size="18" fill="#111827" font-weight="bold">Início</text>
                            
                            <!-- User Icon -->
                            <circle cx="760" cy="30" r="15" fill="#3B82F6"/>
                            <text x="760" y="35" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="middle" font-weight="bold">CF</text>
                            
                            <!-- Main Content Area -->
                            <rect x="200" y="60" width="600" height="440" fill="#F9FAFB"/>
                            
                            <!-- Stats Cards -->
                            <rect x="220" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <defs>
                                <filter id="shadow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feDropShadow dx="0" dy="2" stdDeviation="4" flood-opacity="0.1"/>
                                </filter>
                            </defs>
                            <circle cx="245" cy="105" r="12" fill="#DBEAFE"/>
                            <path d="M 245 100 L 245 110 M 240 105 L 250 105" stroke="#3B82F6" stroke-width="2" stroke-linecap="round"/>
                            <text x="265" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Receitas Hoje</text>
                            <text x="245" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">45.850</text>
                            <text x="320" y="140" font-family="Arial, sans-serif" font-size="12" fill="#10B981">+12%</text>
                            <text x="245" y="158" font-family="Arial, sans-serif" font-size="10" fill="#9CA3AF">Kz</text>
                            
                            <rect x="410" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <circle cx="435" cy="105" r="12" fill="#D1FAE5"/>
                            <path d="M 430 105 L 435 110 L 442 100" stroke="#10B981" stroke-width="2" fill="none" stroke-linecap="round"/>
                            <text x="455" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Faturas Hoje</text>
                            <text x="435" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">23</text>
                            <text x="470" y="140" font-family="Arial, sans-serif" font-size="12" fill="#10B981">+8%</text>
                            
                            <rect x="600" y="80" width="170" height="90" rx="12" fill="white" filter="url(#shadow)"/>
                            <circle cx="625" cy="105" r="12" fill="#FEF3C7"/>
                            <rect x="620" y="100" width="10" height="10" fill="none" stroke="#F59E0B" stroke-width="2"/>
                            <text x="645" y="108" font-family="Arial, sans-serif" font-size="11" fill="#6B7280">Clientes</text>
                            <text x="625" y="140" font-family="Arial, sans-serif" font-size="20" fill="#111827" font-weight="bold">187</text>
                            
                            <!-- Chart Area -->
                            <rect x="220" y="190" width="360" height="280" rx="12" fill="white" filter="url(#shadow)"/>
                            <text x="240" y="215" font-family="Arial, sans-serif" font-size="13" fill="#111827" font-weight="bold">Vendas dos Últimos 7 Dias</text>
                            
                            <!-- Simple Bar Chart -->
                            <rect x="250" y="390" width="30" height="50" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="290" y="370" width="30" height="70" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="330" y="350" width="30" height="90" rx="4" fill="#8B5CF6" opacity="0.8"/>
                            <rect x="370" y="330" width="30" height="110" rx="4" fill="#8B5CF6"/>
                            <rect x="410" y="360" width="30" height="80" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="450" y="380" width="30" height="60" rx="4" fill="#3B82F6" opacity="0.7"/>
                            <rect x="490" y="370" width="30" height="70" rx="4" fill="#3B82F6" opacity="0.7"/>
                            
                            <!-- Chart Labels -->
                            <text x="260" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Seg</text>
                            <text x="300" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Ter</text>
                            <text x="340" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Qua</text>
                            <text x="380" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Qui</text>
                            <text x="420" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Sex</text>
                            <text x="460" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Sáb</text>
                            <text x="500" y="460" font-family="Arial, sans-serif" font-size="9" fill="#9CA3AF">Dom</text>
                            
                            <!-- Recent Invoices Table -->
                            <rect x="600" y="190" width="170" height="280" rx="12" fill="white" filter="url(#shadow)"/>
                            <text x="620" y="215" font-family="Arial, sans-serif" font-size="12" fill="#111827" font-weight="bold">Últimas Faturas</text>
                            
                            <!-- Table Rows -->
                            <rect x="615" y="230" width="140" height="35" rx="6" fill="#F3F4F6"/>
                            <text x="625" y="245" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/001</text>
                            <text x="625" y="258" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">12.500 Kz</text>
                            
                            <rect x="615" y="275" width="140" height="35" rx="6" fill="#F9FAFB"/>
                            <text x="625" y="290" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/002</text>
                            <text x="625" y="303" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">8.750 Kz</text>
                            
                            <rect x="615" y="320" width="140" height="35" rx="6" fill="#F3F4F6"/>
                            <text x="625" y="335" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/003</text>
                            <text x="625" y="348" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">15.200 Kz</text>
                            
                            <rect x="615" y="365" width="140" height="35" rx="6" fill="#F9FAFB"/>
                            <text x="625" y="380" font-family="Arial, sans-serif" font-size="9" fill="#6B7280">FT 2025/004</text>
                            <text x="625" y="393" font-family="Arial, sans-serif" font-size="10" fill="#111827" font-weight="bold">22.100 Kz</text>
                            
                            <!-- Status Badges -->
                            <circle cx="740" cy="248" r="3" fill="#10B981"/>
                            <circle cx="740" cy="293" r="3" fill="#10B981"/>
                            <circle cx="740" cy="338" r="3" fill="#F59E0B"/>
                            <circle cx="740" cy="383" r="3" fill="#EF4444"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-12 bg-white border-y border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-600 mb-2">500+</div>
                    <div class="text-gray-600">Empresas Ativas</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-600 mb-2">99.9%</div>
                    <div class="text-gray-600">Uptime</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-pink-600 mb-2">24/7</div>
                    <div class="text-gray-600">Suporte</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-green-600 mb-2">100%</div>
                    <div class="text-gray-600">Satisfação</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="recursos" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Recursos Poderosos</h2>
                <p class="text-xl text-gray-600">Tudo que você precisa para gerenciar seu negócio com eficiência</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-blue-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-file-invoice text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Faturação Completa</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Emita faturas, recibos e orçamentos profissionais em segundos. Controle total das suas vendas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Faturas personalizadas</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Controle de pagamentos</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Relatórios detalhados</li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-purple-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-building text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Multi-Empresa</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Gerencie múltiplas empresas em uma única conta. Troque entre elas com um clique.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Dados isolados</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Troca rápida</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Gestão centralizada</li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-pink-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-pink-400 to-pink-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-users-cog text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Gestão de Utilizadores</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Controle total de permissões e acessos. Cada utilizador com suas funções específicas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Permissões multi-nível</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Acesso seguro</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Auditoria completa</li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-green-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-cubes text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Inventário Inteligente</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Controle de stock em tempo real. Nunca mais fique sem produtos em stock.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Alertas de stock</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Códigos de barras</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Múltiplos armazéns</li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-yellow-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-pie text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Relatórios & Analytics</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Dashboards intuitivos e relatórios detalhados para tomadas de decisão estratégicas.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Gráficos em tempo real</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Exportação PDF/Excel</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>KPIs personalizados</li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="group bg-white rounded-3xl shadow-lg p-8 hover:shadow-2xl transition-all duration-300 border-l-4 border-red-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-red-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Segurança Avançada</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Seus dados protegidos com criptografia de ponta. Backups automáticos diários.
                    </p>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>SSL/TLS</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>Backup automático</li>
                        <li class="flex items-center"><div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-3"><i class="fas fa-check text-green-600 text-xs"></i></div>2FA disponível</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Events Module Highlight Section -->
    <section class="py-20 bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 relative overflow-hidden">
        <!-- Background Animation -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-10 left-10 w-72 h-72 bg-purple-500 rounded-full filter blur-3xl animate-pulse"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-pink-500 rounded-full filter blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-bold rounded-full mb-6 shadow-2xl animate-bounce">
                    <i class="fas fa-star mr-3 text-yellow-300"></i>
                    MÓDULO DESTAQUE
                    <i class="fas fa-star ml-3 text-yellow-300"></i>
                </div>
                <h2 class="text-5xl md:text-6xl font-extrabold text-white mb-6">
                    <i class="fas fa-calendar-star mr-4"></i>Gestão de Eventos
                </h2>
                <p class="text-2xl text-purple-200 max-w-3xl mx-auto leading-relaxed">
                    Organize eventos profissionais com controle total de equipamentos, equipes, orçamentos e muito mais!
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Left Side - Image/Icon -->
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500 to-pink-500 rounded-3xl blur-2xl opacity-50 animate-pulse"></div>
                    <div class="relative bg-white/10 backdrop-blur-lg rounded-3xl p-12 border border-white/20 shadow-2xl">
                        <div class="text-center">
                            <div class="w-48 h-48 mx-auto bg-gradient-to-br from-purple-500 via-pink-500 to-orange-500 rounded-full flex items-center justify-center mb-8 shadow-2xl animate-pulse">
                                <i class="fas fa-calendar-check text-white text-8xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-4">Tudo em Um Só Lugar</h3>
                            <p class="text-purple-200 text-lg">
                                Gerencie desde pequenas reuniões até grandes eventos corporativos com total profissionalismo
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Features -->
                <div class="space-y-6">
                    <!-- Feature 1 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-calendar-alt text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📅 Calendário Inteligente</h4>
                                <p class="text-purple-200">Visualize todos os eventos em um calendário interativo. Filtros avançados, arrastar e soltar, visão mensal/semanal/diária.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-boxes text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📦 Gestão de Equipamentos</h4>
                                <p class="text-purple-200">Controle completo de equipamentos: som, luz, palco, decoração. Rastreamento com QR Code, kits pré-definidos, histórico de uso.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-users text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">👥 Gestão de Equipes</h4>
                                <p class="text-purple-200">Aloque técnicos, fotógrafos, segurança e equipe. Controle de disponibilidade, turnos e remuneração por evento.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📍 Locais & Venues</h4>
                                <p class="text-purple-200">Cadastre salões, espaços e locais. Capacidade, disponibilidade, galeria de fotos, contatos e muito mais.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 5 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-red-500 to-rose-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">💰 Orçamentos & Faturação</h4>
                                <p class="text-purple-200">Crie orçamentos detalhados, controle custos vs. receita, emita faturas automaticamente e acompanhe pagamentos.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 6 -->
                    <div class="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 hover:scale-105 cursor-pointer">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-500 rounded-xl flex items-center justify-center mr-5 shadow-lg">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold text-white mb-2">📊 Relatórios Avançados</h4>
                                <p class="text-purple-200">Dashboards com KPIs, relatórios de lucratividade, análise de equipamentos mais usados, performance da equipe.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="text-center mt-16">
                <a href="/register" class="inline-flex items-center px-10 py-5 bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-white text-xl font-bold rounded-full shadow-2xl hover:shadow-pink-500/50 hover:scale-110 transition-all duration-300">
                    <i class="fas fa-rocket mr-3"></i>
                    Experimentar Gestão de Eventos Grátis
                    <i class="fas fa-arrow-right ml-3"></i>
                </a>
                <p class="text-purple-200 mt-4 text-sm">✨ 14 dias grátis • Sem cartão de crédito • Cancele quando quiser</p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="planos" class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-500 rounded-full filter blur-3xl"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4 animate-pulse">
                    <i class="fas fa-crown mr-2"></i>PRICING
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Planos Para Todos os Tamanhos</h2>
                <p class="text-xl text-gray-600">Escolha o plano ideal para o seu negócio e comece hoje mesmo!</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($plans as $plan)
                    @php
                        $isFox = str_contains(strtolower($plan->slug), 'fox');
                        $isStarter = str_contains(strtolower($plan->slug), 'starter');
                        $isProfessional = str_contains(strtolower($plan->slug), 'professional');
                        $isEnterprise = str_contains(strtolower($plan->slug), 'enterprise');
                        
                        // Define icon and colors
                        if ($isFox) {
                            $icon = '🦊';
                            $gradient = 'from-orange-500 via-red-500 to-pink-500';
                            $borderColor = 'border-orange-500';
                            $iconBg = 'bg-gradient-to-br from-orange-400 to-red-500';
                            $badgeColor = 'bg-orange-500';
                        } elseif ($isStarter) {
                            $icon = 'fa-rocket';
                            $gradient = 'from-green-500 to-emerald-600';
                            $borderColor = 'border-green-500';
                            $iconBg = 'bg-gradient-to-br from-green-400 to-emerald-500';
                            $badgeColor = 'bg-green-500';
                        } elseif ($isProfessional) {
                            $icon = 'fa-briefcase';
                            $gradient = 'from-blue-500 to-indigo-600';
                            $borderColor = 'border-blue-500';
                            $iconBg = 'bg-gradient-to-br from-blue-400 to-indigo-500';
                            $badgeColor = 'bg-blue-500';
                        } else {
                            $icon = 'fa-crown';
                            $gradient = 'from-purple-500 to-pink-600';
                            $borderColor = 'border-purple-500';
                            $iconBg = 'bg-gradient-to-br from-purple-400 to-pink-500';
                            $badgeColor = 'bg-purple-500';
                        }
                    @endphp
                    
                    <div class="group relative bg-white rounded-3xl shadow-xl p-8 hover:shadow-2xl transition-all duration-300 border-2 {{ $plan->is_featured ? $borderColor : 'border-gray-200' }}" 
                         style="animation: fadeInUp 0.6s ease-out {{ $loop->index * 0.1 }}s both;"
                         x-data="{ showDetails: false }">
                        
                        <!-- Badge Top -->
                        @if($plan->is_featured)
                            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                                <div class="bg-gradient-to-r {{ $gradient }} text-white text-xs font-bold px-4 py-2 rounded-full shadow-lg animate-bounce">
                                    <i class="fas fa-star mr-1"></i>{{ $isFox ? 'PROMO ESPECIAL!' : 'POPULAR' }}
                                </div>
                            </div>
                        @endif
                        
                        <!-- Icon -->
                        <div class="mb-6 relative">
                            <div class="w-16 h-16 {{ $iconBg }} rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-all duration-300 mx-auto">
                                @if($isFox)
                                    <span class="text-3xl">{{ $icon }}</span>
                                @else
                                    <i class="fas {{ $icon }} text-white text-2xl"></i>
                                @endif
                            </div>
                            @if($isFox)
                                <div class="absolute -top-2 -right-2 text-2xl animate-pulse">✨</div>
                            @endif
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 text-center">{{ $plan->name }}</h3>
                        <p class="text-gray-600 text-sm mb-6 text-center min-h-[40px]">{{ Str::limit($plan->description, 60) }}</p>
                        
                        <!-- Price -->
                        <div class="mb-6 text-center">
                            @if($plan->price_monthly == 0)
                            <div>
                                <span class="text-5xl font-bold bg-gradient-to-r {{ $gradient }} bg-clip-text text-transparent">GRÁTIS</span>
                                <p class="text-sm text-gray-500 mt-2">{{ $plan->trial_days }} dias</p>
                            </div>
                            @else
                            <div>
                                <span class="text-5xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0) }}</span>
                                <span class="text-gray-600 text-lg"> Kz</span>
                                <p class="text-sm text-gray-500 mt-1">/mês</p>
                            </div>
                            @endif
                        </div>

                        <!-- CTA Button -->
                        <a href="{{ route('register') }}" class="block w-full text-center bg-gradient-to-r {{ $gradient }} text-white px-6 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 mb-6">
                            <span class="inline-flex items-center">
                            @if($isFox)
                                🦊 Começar GRÁTIS
                            @else
                                <i class="fas fa-rocket mr-2"></i>Começar Agora
                            @endif
                            </span>
                        </a>

                        <!-- Features List -->
                        <div class="border-t border-gray-200 pt-6">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Recursos Incluídos</p>
                            <ul class="space-y-3 text-sm">
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-users text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->max_users >= 999 ? '999+' : $plan->max_users }}</strong> Utilizadores</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-building text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->max_companies >= 999 ? '50+' : $plan->max_companies }}</strong> Empresas</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-database text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ number_format($plan->max_storage_mb / 1000, 0) }}GB</strong> Storage</span>
                                </li>
                                <li class="flex items-start text-gray-700">
                                    <div class="flex-shrink-0 w-6 h-6 {{ $badgeColor }} rounded-full flex items-center justify-center mr-3 mt-0.5">
                                        <i class="fas fa-gift text-white text-xs"></i>
                                    </div>
                                    <span><strong>{{ $plan->trial_days }}</strong> dias grátis</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Modules List -->
                        <div class="border-t border-gray-200 pt-6 mt-6" x-show="!showDetails">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Módulos Incluídos</p>
                            <ul class="space-y-2 text-sm">
                                @if($plan->included_modules && is_array($plan->included_modules))
                                    @php
                                        $modulesLimit = $isFox ? 5 : 3;
                                        $moduleNames = [
                                            'invoicing' => '📄 Faturação',
                                            'treasury' => '💰 Tesouraria',
                                            'rh' => '👥 Recursos Humanos',
                                            'contabilidade' => '📊 Contabilidade',
                                            'oficina' => '🔧 Gestão de Oficina',
                                            'crm' => '🤝 CRM',
                                            'inventario' => '📦 Inventário',
                                            'compras' => '🛒 Compras',
                                            'projetos' => '📋 Projetos'
                                        ];
                                    @endphp
                                    @foreach(array_slice($plan->included_modules, 0, $modulesLimit) as $module)
                                        <li class="flex items-center text-gray-600">
                                            <i class="fas fa-check text-{{ $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple')) }}-500 mr-2"></i>
                                            <span>{{ $moduleNames[$module] ?? ucfirst($module) }}</span>
                                        </li>
                                    @endforeach
                                    @if(count($plan->included_modules) > $modulesLimit)
                                        <li class="text-gray-500 text-xs italic">
                                            + {{ count($plan->included_modules) - $modulesLimit }} módulos adicionais
                                        </li>
                                    @endif
                                @else
                                    <li class="text-gray-500 text-xs">Módulos básicos incluídos</li>
                                @endif
                            </ul>
                        </div>
                        
                        <!-- Expanded Details -->
                        <div class="border-t border-gray-200 pt-6 mt-6" x-show="showDetails" x-collapse>
                            <p class="text-xs font-bold text-gray-500 uppercase mb-4">Todos os Módulos</p>
                            <ul class="space-y-2 text-sm max-h-64 overflow-y-auto pr-2">
                                @if($plan->included_modules && is_array($plan->included_modules))
                                    @php
                                        $moduleNames = [
                                            'invoicing' => '📄 Faturação',
                                            'treasury' => '💰 Tesouraria',
                                            'rh' => '👥 Recursos Humanos',
                                            'contabilidade' => '📊 Contabilidade',
                                            'oficina' => '🔧 Gestão de Oficina',
                                            'crm' => '🤝 CRM',
                                            'inventario' => '📦 Inventário',
                                            'compras' => '🛒 Compras',
                                            'projetos' => '📋 Projetos'
                                        ];
                                    @endphp
                                    @foreach($plan->included_modules as $module)
                                        <li class="flex items-center text-gray-600">
                                            <i class="fas fa-check text-{{ $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple')) }}-500 mr-2"></i>
                                            <span>{{ $moduleNames[$module] ?? ucfirst($module) }}</span>
                                        </li>
                                    @endforeach
                                @endif
                                
                                @if($plan->features && is_array($plan->features))
                                    <li class="pt-4 mt-4 border-t border-gray-200">
                                        <p class="text-xs font-bold text-gray-500 uppercase mb-3">Features Adicionais</p>
                                    </li>
                                    @foreach($plan->features as $feature)
                                        <li class="flex items-start text-gray-600">
                                            <i class="fas fa-star text-yellow-500 mr-2 mt-1"></i>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                        </div>
                        
                        <!-- Ver Mais Button -->
                        @php
                            $buttonColor = $isFox ? 'orange' : ($isStarter ? 'green' : ($isProfessional ? 'blue' : 'purple'));
                        @endphp
                        <button @click="showDetails = !showDetails" class="w-full mt-6 px-4 py-2 border-2 border-{{ $buttonColor }}-500 text-{{ $buttonColor }}-600 rounded-lg font-semibold hover:bg-{{ $buttonColor }}-50 transition text-sm">
                            <span x-show="!showDetails">
                                <i class="fas fa-chevron-down mr-2"></i>Ver Todos os Detalhes
                            </span>
                            <span x-show="showDetails">
                                <i class="fas fa-chevron-up mr-2"></i>Ver Menos
                            </span>
                        </button>
                    </div>
                @endforeach
            </div>
            
            <!-- Trust Badge -->
            <div class="mt-16 text-center">
                <p class="text-sm text-gray-600 mb-4">✅ Sem compromisso • ✅ Cancele quando quiser • ✅ Suporte incluído</p>
                <div class="flex justify-center items-center space-x-8 text-gray-400">
                    <div class="flex items-center">
                        <i class="fas fa-lock text-2xl mr-2"></i>
                        <span class="text-sm">Pagamento Seguro</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-2xl mr-2"></i>
                        <span class="text-sm">Dados Protegidos</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-headset text-2xl mr-2"></i>
                        <span class="text-sm">Suporte 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Roadmap Section -->
    <section id="roadmap" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4">
                    <i class="fas fa-road mr-2"></i>v6.0.0
                </span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Roadmap do Projeto
                </h2>
                <p class="text-xl text-gray-600">
                    Acompanhe o desenvolvimento e as próximas funcionalidades
                </p>
            </div>

            <!-- Timeline -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                <!-- Concluído -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-green-500">
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Concluído
                            </h3>
                            <span class="px-3 py-1 bg-green-500 text-white rounded-full text-sm font-bold">100%</span>
                        </div>
                        <p class="text-gray-600">Funcionalidades implementadas</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Multi-Tenancy</p>
                                <p class="text-xs text-gray-600">Sistema multi-empresa</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Faturação AGT</p>
                                <p class="text-xs text-gray-600">Conforme normas angolanas</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Tesouraria</p>
                                <p class="text-xs text-gray-600">Gestão de caixa e bancos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">POS</p>
                                <p class="text-xs text-gray-600">Ponto de venda moderno</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">SAFT-AO</p>
                                <p class="text-xs text-gray-600">Gerador completo</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Clientes & Produtos</p>
                                <p class="text-xs text-gray-600">Gestão completa</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Sistema de Atualizações</p>
                                <p class="text-xs text-gray-600">Inteligente com seeders únicos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Séries de Faturação</p>
                                <p class="text-xs text-gray-600">Numeração automática AGT</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Integrações Automáticas</p>
                                <p class="text-xs text-gray-600">POS → Faturação → Treasury</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Notas de Crédito/Débito</p>
                                <p class="text-xs text-gray-600">Gestão completa</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Relatórios Financeiros</p>
                                <p class="text-xs text-gray-600">DRE, Fluxo de Caixa, A Receber/Pagar</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão de Usuários</p>
                                <p class="text-xs text-gray-600">Roles & Permissions com Spatie</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Planos & Assinaturas</p>
                                <p class="text-xs text-gray-600">Sistema de billing completo</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Validação de Tenants</p>
                                <p class="text-xs text-gray-600">Controle de acesso por status</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Módulo de Eventos</p>
                                <p class="text-xs text-gray-600">Calendário + Gestão de Equipamentos</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">SETS de Equipamentos</p>
                                <p class="text-xs text-gray-600">Conjuntos reutilizáveis</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">QR Code Equipamentos</p>
                                <p class="text-xs text-gray-600">Rastreamento completo</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Em Desenvolvimento -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-yellow-500">
                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-spinner fa-pulse text-yellow-500 mr-3"></i>
                                Em Desenvolvimento
                            </h3>
                            <span class="px-3 py-1 bg-yellow-500 text-white rounded-full text-sm font-bold">50%</span>
                        </div>
                        <p class="text-gray-600">Funcionalidades em construção</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Integração Eventos-Faturação</p>
                                <p class="text-xs text-gray-600">Orçamentos de equipamentos</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 30%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Integrações Bancárias</p>
                                <p class="text-xs text-gray-600">Multicaixa, BAI, BFA</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 40%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Gestão de Compras</p>
                                <p class="text-xs text-gray-600">Fornecedores e ordens</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 30%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Módulos Dinâmicos</p>
                                <p class="text-xs text-gray-600">Ativação por plano</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 60%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Planejado -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-t-4 border-blue-500">
                    <div class="bg-gradient-to-br from-blue-50 to-cyan-50 p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-lightbulb text-blue-500 mr-3"></i>
                                Planejado
                            </h3>
                            <span class="px-3 py-1 bg-blue-500 text-white rounded-full text-sm font-bold">Q1 2025</span>
                        </div>
                        <p class="text-gray-600">Próximas funcionalidades</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Recursos Humanos</p>
                                <p class="text-xs text-gray-600">Folha de pagamento</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">CRM</p>
                                <p class="text-xs text-gray-600">Gestão de leads</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Gestão de Projetos</p>
                                <p class="text-xs text-gray-600">Tarefas e timesheets</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">E-commerce</p>
                                <p class="text-xs text-gray-600">Loja online integrada</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Mobile App</p>
                                <p class="text-xs text-gray-600">Android & iOS</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">API REST</p>
                                <p class="text-xs text-gray-600">Integrações externas</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">BI & Dashboards</p>
                                <p class="text-xs text-gray-600">Business Intelligence</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-check-circle text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">13</p>
                    <p class="text-sm opacity-90">Concluído</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-spinner text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">4</p>
                    <p class="text-sm opacity-90">Em Desenvolvimento</p>
                </div>
                <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-lightbulb text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">8</p>
                    <p class="text-sm opacity-90">Planejado</p>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-chart-line text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">75%</p>
                    <p class="text-sm opacity-90">Progresso Total</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-white mb-6">Pronto para Transformar Seu Negócio?</h2>
            <p class="text-xl text-blue-100 mb-8">
                Junte-se a centenas de empresas que já confiam no SOSERP para gerenciar suas operações diárias.
            </p>
            <a href="{{ route('register') }}" class="bg-white text-purple-600 px-8 py-4 rounded-xl text-lg font-semibold hover:shadow-2xl transition inline-block">
                <i class="fas fa-rocket mr-2"></i>Começar Gratuitamente
            </a>
            <p class="text-blue-100 text-sm mt-4">
                Sem cartão de crédito • 14 dias grátis • Cancele quando quiser
            </p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contacto" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
                <!-- Contact Info -->
                <div>
                    <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4">
                        <i class="fas fa-envelope mr-2"></i>CONTACTO
                    </span>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Entre em Contacto</h2>
                    <p class="text-xl text-gray-600 mb-8">
                        Tem dúvidas? Nossa equipa está pronta para ajudar você a começar hoje mesmo!
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-envelope text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Email</h3>
                                <p class="text-gray-600">suporte@soserp.ao</p>
                                <p class="text-gray-600">comercial@soserp.ao</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-100 to-green-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-phone text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Telefone</h3>
                                <p class="text-gray-600">+244 923 000 000</p>
                                <p class="text-gray-600">+244 934 000 000</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Endereço</h3>
                                <p class="text-gray-600">Luanda, Angola</p>
                                <p class="text-gray-600">Talatona, Rua Principal</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-100 to-orange-200 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-clock text-orange-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">Horário de Atendimento</h3>
                                <p class="text-gray-600">Segunda - Sexta: 8h - 18h</p>
                                <p class="text-gray-600">Sábado: 9h - 13h</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-3xl p-8 shadow-xl">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Envie uma Mensagem</h3>
                    
                    <form id="contactForm" class="space-y-6" x-data="{ submitting: false, success: false, error: '' }" @submit.prevent="submitForm">
                        <div x-show="success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl" x-transition>
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>Mensagem enviada com sucesso! Entraremos em contato em breve.</span>
                        </div>
                        
                        <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl" x-transition>
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span x-text="error"></span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>Nome Completo *
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="Seu nome completo">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email *
                            </label>
                            <input type="email" name="email" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="seu@email.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone mr-2"></i>Telefone
                            </label>
                            <input type="tel" name="phone"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="+244 923 000 000">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-briefcase mr-2"></i>Empresa
                            </label>
                            <input type="text" name="company"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                   placeholder="Nome da sua empresa">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment-alt mr-2"></i>Mensagem *
                            </label>
                            <textarea name="message" required rows="4"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
                                      placeholder="Como podemos ajudar você?"></textarea>
                        </div>
                        
                        <button type="submit" 
                                :disabled="submitting"
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-xl font-bold hover:shadow-2xl transition-all duration-300 disabled:opacity-50">
                            <span x-show="!submitting">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar Mensagem
                            </span>
                            <span x-show="submitting">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                            </span>
                        </button>
                        
                        <p class="text-xs text-gray-500 text-center">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Seus dados estão seguros. Não compartilhamos informações com terceiros.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8 mb-8">
                <div>
                    <div class="flex items-center mb-4">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="max-height: 3rem;" class="h-12 w-auto object-contain">
                        @else
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-white">{{ app_name() }}</span>
                        @endif
                    </div>
                    <p class="text-sm">Sistema de Gestão Empresarial completo e moderno para empresas angolanas.</p>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Produto</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#recursos" class="hover:text-white">Recursos</a></li>
                        <li><a href="#planos" class="hover:text-white">Planos</a></li>
                        <li><a href="#roadmap" class="hover:text-white">Roadmap</a></li>
                        <li><a href="https://docs.soserp.ao" target="_blank" class="hover:text-white">Documentação</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Empresa</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#sobre" class="hover:text-white">Sobre</a></li>
                        <li><a href="#contacto" class="hover:text-white">Contacto</a></li>
                        <li><a href="#contacto" class="hover:text-white">Suporte</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-white font-bold mb-4">Legal</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white">Termos de Uso</a></li>
                        <li><a href="#" class="hover:text-white">Privacidade</a></li>
                        <li><a href="#" class="hover:text-white">Cookies</a></li>
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
                                <a href="tel:+244939779902" class="hover:text-white">+244 939 779 902</a>
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
                        <li>
                            <div class="flex items-start">
                                <i class="fas fa-clock mt-1 mr-2 text-blue-400"></i>
                                <div>
                                    <p>Segunda - Sexta: 8h - 18h</p>
                                    <p>Sábado: 9h - 13h</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 text-center text-sm">
                <p>&copy; 2025 SOSERP. Todos os direitos reservados.</p>
                <p class="mt-2">
                    Desenvolvido por 
                    <a href="https://softecangola.net" target="_blank" class="text-blue-400 hover:text-blue-300 font-semibold">
                        Softec Angola
                    </a>
                </p>
            </div>
        </div>
    </footer>

    <script>
        function submitForm() {
            const form = document.getElementById('contactForm');
            const formData = new FormData(form);
            
            this.submitting = true;
            this.success = false;
            this.error = '';
            
            fetch('{{ route("contact.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.submitting = false;
                
                if (data.success) {
                    this.success = true;
                    form.reset();
                    
                    // Esconder mensagem de sucesso após 5 segundos
                    setTimeout(() => {
                        this.success = false;
                    }, 5000);
                } else {
                    this.error = data.message || 'Erro ao enviar mensagem. Por favor, tente novamente.';
                    
                    // Se houver erros de validação
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        this.error = Array.isArray(firstError) ? firstError[0] : firstError;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.submitting = false;
                this.error = 'Erro ao enviar mensagem. Por favor, tente novamente.';
            });
        }
    </script>

</body>
</html>

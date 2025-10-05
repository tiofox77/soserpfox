<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ app_name() }} - Sistema de Gestão Empresarial</title>
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    @endif
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
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="height: 80px;" class="w-auto">
                        @else
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ app_name() }}</span>
                        @endif
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="#features" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Recursos</a>
                        <a href="#pricing" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Planos</a>
                        <a href="#roadmap" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Roadmap</a>
                        <a href="#contact" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition">Contacto</a>
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
                        <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Simplificada</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8">
                        Sistema completo de gestão empresarial multi-empresa com faturação, inventário, RH e muito mais. 
                        Tudo que sua empresa precisa em um só lugar.
                    </p>
                    <div class="flex space-x-4">
                        <a href="{{ route('register') }}" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:shadow-xl transition">
                            <i class="fas fa-rocket mr-2"></i>Começar Agora
                        </a>
                        <a href="#pricing" class="bg-white text-gray-900 px-8 py-4 rounded-xl text-lg font-semibold border-2 border-gray-200 hover:border-blue-600 transition">
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
                    <div class="relative bg-white rounded-3xl shadow-2xl p-8">
                        <img src="https://via.placeholder.com/600x400/667eea/ffffff?text=Dashboard+Preview" alt="Dashboard" class="rounded-xl">
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
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Recursos Poderosos</h2>
                <p class="text-xl text-gray-600">Tudo que você precisa para gerenciar seu negócio com eficiência</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-blue-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-blue-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-file-invoice-dollar text-blue-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Faturação Completa</h3>
                    <p class="text-gray-600 mb-4">
                        Emita faturas, recibos e orçamentos profissionais em segundos. Controle total das suas vendas.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Faturas personalizadas</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Controle de pagamentos</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Relatórios detalhados</li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-purple-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-purple-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-building text-purple-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Multi-Empresa</h3>
                    <p class="text-gray-600 mb-4">
                        Gerencie múltiplas empresas em uma única conta. Troque entre elas com um clique.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Dados isolados</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Troca rápida</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Gestão centralizada</li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-pink-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-pink-100 to-pink-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-users text-pink-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Gestão de Utilizadores</h3>
                    <p class="text-gray-600 mb-4">
                        Controle total de permissões e acessos. Cada utilizador com suas funções específicas.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Permissões multi-nível</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Acesso seguro</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Auditoria completa</li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-green-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-boxes text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Inventário Inteligente</h3>
                    <p class="text-gray-600 mb-4">
                        Controle de stock em tempo real. Nunca mais fique sem produtos em stock.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Alertas de stock</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Códigos de barras</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Múltiplos armazéns</li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-yellow-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-yellow-100 to-yellow-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-yellow-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Relatórios & Analytics</h3>
                    <p class="text-gray-600 mb-4">
                        Dashboards intuitivos e relatórios detalhados para tomadas de decisão estratégicas.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Gráficos em tempo real</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Exportação PDF/Excel</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>KPIs personalizados</li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition border-t-4 border-red-500">
                    <div class="w-16 h-16 bg-gradient-to-br from-red-100 to-red-200 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-shield-alt text-red-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Segurança Avançada</h3>
                    <p class="text-gray-600 mb-4">
                        Seus dados protegidos com criptografia de ponta. Backups automáticos diários.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>SSL/TLS</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Backup automático</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>2FA disponível</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Planos Para Todos os Tamanhos</h2>
                <p class="text-xl text-gray-600">Escolha o plano ideal para o seu negócio</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                @foreach($plans as $plan)
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition border-2 {{ $plan->is_featured ? 'border-purple-500 scale-105' : 'border-gray-200' }}">
                        @if($plan->is_featured)
                            <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white text-xs font-bold px-3 py-1 rounded-full inline-block mb-4">
                                <i class="fas fa-star mr-1"></i>POPULAR
                            </div>
                        @endif
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h3>
                        <p class="text-gray-600 text-sm mb-6">{{ $plan->description }}</p>
                        
                        <div class="mb-6">
                            <span class="text-4xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 2) }}</span>
                            <span class="text-gray-600"> Kz/mês</span>
                        </div>

                        <a href="{{ route('register') }}" class="block w-full text-center {{ $plan->is_featured ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white' : 'bg-gray-100 text-gray-900' }} px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition mb-6">
                            Começar Agora
                        </a>

                        <ul class="space-y-3 text-sm">
                            <li class="flex items-center text-gray-700">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                {{ $plan->max_users }} Utilizadores
                            </li>
                            <li class="flex items-center text-gray-700">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                {{ $plan->max_companies >= 999 ? 'Ilimitadas' : $plan->max_companies }} Empresas
                            </li>
                            <li class="flex items-center text-gray-700">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                {{ number_format($plan->max_storage_mb / 1000, 1) }}GB Storage
                            </li>
                            <li class="flex items-center text-gray-700">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                {{ $plan->trial_days }} dias grátis
                            </li>
                            @if($plan->features)
                                @foreach($plan->features as $feature)
                                    <li class="flex items-center text-gray-700">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Roadmap Section -->
    <section id="roadmap" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-bold rounded-full mb-4">
                    <i class="fas fa-road mr-2"></i>v5.0.0
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
                                <p class="text-xs text-gray-600">GitHub integration</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <p class="font-bold text-sm text-gray-900">Configurações</p>
                                <p class="text-xs text-gray-600">Logo, SEO, Aparência</p>
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
                            <span class="px-3 py-1 bg-yellow-500 text-white rounded-full text-sm font-bold">60%</span>
                        </div>
                        <p class="text-gray-600">Funcionalidades em construção</p>
                    </div>
                    <div class="p-6 space-y-3 max-h-[500px] overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Inventário Avançado</p>
                                <p class="text-xs text-gray-600">Armazéns e transferências</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 70%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-code text-yellow-600 mt-1"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-900">Relatórios Financeiros</p>
                                <p class="text-xs text-gray-600">DRE, Balanço, Fluxo</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 50%"></div>
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
                                <p class="font-bold text-sm text-gray-900">Dashboard Analytics</p>
                                <p class="text-xs text-gray-600">Gráficos e KPIs</p>
                                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 80%"></div>
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
                    <p class="text-4xl font-bold mb-1">9</p>
                    <p class="text-sm opacity-90">Concluído</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-spinner text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">5</p>
                    <p class="text-sm opacity-90">Em Desenvolvimento</p>
                </div>
                <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-lightbulb text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">8</p>
                    <p class="text-sm opacity-90">Planejado</p>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-6 text-white text-center">
                    <i class="fas fa-chart-line text-5xl mb-3 opacity-50"></i>
                    <p class="text-4xl font-bold mb-1">41%</p>
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

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center mb-4">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="h-12 w-auto">
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
                        <li><a href="#features" class="hover:text-white">Recursos</a></li>
                        <li><a href="#pricing" class="hover:text-white">Planos</a></li>
                        <li><a href="#" class="hover:text-white">Documentação</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white font-bold mb-4">Empresa</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#about" class="hover:text-white">Sobre</a></li>
                        <li><a href="#contact" class="hover:text-white">Contacto</a></li>
                        <li><a href="#" class="hover:text-white">Suporte</a></li>
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
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} SOSERP. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

</body>
</html>

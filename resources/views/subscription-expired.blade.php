<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expirada - SOS ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-6xl w-full">
        {{-- Card Principal --}}
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            {{-- Header com Gradiente --}}
            <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 p-8 text-center text-white">
                <div class="w-24 h-24 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-5xl"></i>
                </div>
                <h1 class="text-3xl font-bold mb-2">🔒 Subscription Expirada</h1>
                <p class="text-blue-100 text-lg">Seu plano de acesso ao SOS ERP expirou</p>
            </div>

            {{-- Conteúdo --}}
            <div class="p-8">
                {{-- Info da Empresa --}}
                @if(auth()->check() && auth()->user()->activeTenant())
                    <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-2xl p-6 mb-6 border-2 border-blue-200">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-building text-blue-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-bold text-gray-800 text-lg">{{ auth()->user()->activeTenant()->name }}</h3>
                                <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Detalhes da Expiração --}}
                @if(session('subscription'))
                    <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 mb-6">
                        <div class="flex items-start">
                            <i class="fas fa-calendar-times text-red-600 text-2xl mr-4 mt-1"></i>
                            <div class="flex-1">
                                <h4 class="font-bold text-red-800 mb-2">Seu plano expirou em:</h4>
                                <p class="text-2xl font-bold text-red-600 mb-3">
                                    {{ session('subscription')->current_period_end?->format('d/m/Y H:i') ?? 'Data não disponível' }}
                                </p>
                                @if(session('subscription')->plan)
                                    <p class="text-sm text-gray-600">
                                        <strong>Plano anterior:</strong> {{ session('subscription')->plan->name }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Mensagem Principal --}}
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                        O que aconteceu?
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Seu período de subscription ao <strong>SOS ERP</strong> chegou ao fim. 
                        Para continuar acessando todos os módulos e funcionalidades do sistema, 
                        é necessário renovar seu plano.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Não se preocupe! Seus dados estão seguros e serão preservados. 
                        Ao renovar, você terá acesso imediato a tudo novamente.
                    </p>
                </div>

                {{-- Módulos Bloqueados --}}
                <div class="bg-gradient-to-r from-orange-50 to-red-50 border-2 border-orange-200 rounded-2xl p-6 mb-8">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-lock text-orange-600 mr-3"></i>
                        Módulos Bloqueados
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @php
                            $modules = [
                                ['name' => 'Faturação', 'icon' => 'fa-file-invoice'],
                                ['name' => 'RH', 'icon' => 'fa-users'],
                                ['name' => 'Contabilidade', 'icon' => 'fa-calculator'],
                                ['name' => 'Oficina', 'icon' => 'fa-wrench'],
                                ['name' => 'CRM', 'icon' => 'fa-handshake'],
                                ['name' => 'Inventário', 'icon' => 'fa-boxes'],
                                ['name' => 'Compras', 'icon' => 'fa-shopping-cart'],
                                ['name' => 'Projetos', 'icon' => 'fa-project-diagram'],
                                ['name' => 'Eventos', 'icon' => 'fa-calendar-alt'],
                            ];
                        @endphp
                        @foreach($modules as $module)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas {{ $module['icon'] }} text-orange-500 mr-2"></i>
                                <span>{{ $module['name'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Benefícios de Renovar --}}
                <div class="mb-8">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        Ao renovar você terá:
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span class="text-gray-600"><strong>Acesso imediato</strong> a todos os módulos do sistema</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span class="text-gray-600"><strong>Dados preservados</strong> - nada foi perdido!</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span class="text-gray-600"><strong>Suporte técnico</strong> prioritário</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span class="text-gray-600"><strong>Atualizações</strong> constantes e novas funcionalidades</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span class="text-gray-600"><strong>Conformidade fiscal</strong> AGT Angola garantida</span>
                        </li>
                    </ul>
                </div>

                {{-- Botões de Ação --}}
                <div class="space-y-3">
                    <button onclick="document.getElementById('plans-section').scrollIntoView({behavior: 'smooth'})" 
                       class="block w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white text-center py-4 px-6 rounded-xl font-bold text-lg shadow-lg transition-all transform hover:scale-105 cursor-pointer">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        Ver Planos e Renovar
                    </button>
                    
                    <button onclick="window.location.href='{{ route('my-account') }}?tab=plan'" 
                       class="block w-full bg-white hover:bg-gray-50 text-blue-700 text-center py-3 px-6 rounded-xl font-semibold border-2 border-blue-300 transition-all cursor-pointer">
                        <i class="fas fa-crown mr-2"></i>
                        Gerenciar Plano Atual
                    </button>
                    
                    <a href="mailto:suporte@soserp.com?subject=Renovação%20de%20Subscription%20-%20{{ auth()->user()->activeTenant()?->name ?? 'Cliente' }}" 
                       class="block w-full bg-white hover:bg-gray-50 text-gray-700 text-center py-3 px-6 rounded-xl font-semibold border-2 border-gray-300 transition-all">
                        <i class="fas fa-headset mr-2"></i>
                        Falar com Suporte
                    </a>
                    
                    <form action="{{ route('logout') }}" method="POST" class="w-full">
                        @csrf
                        <button type="submit" 
                                class="w-full bg-white hover:bg-gray-50 text-gray-500 text-center py-3 px-6 rounded-xl font-semibold border-2 border-gray-200 transition-all">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Sair do Sistema
                        </button>
                    </form>
                </div>

                {{-- Contato --}}
                <div class="mt-8 pt-6 border-t-2 border-gray-100 text-center text-sm text-gray-500">
                    <p class="mb-2">
                        <i class="fas fa-phone mr-2"></i>
                        <strong>Telefone:</strong> +244 923 456 789
                    </p>
                    <p>
                        <i class="fas fa-envelope mr-2"></i>
                        <strong>Email:</strong> suporte@soserp.com
                    </p>
                </div>
            </div>
        </div>

        {{-- Seção de Planos --}}
        <div id="plans-section" class="mt-8">
            <div class="bg-white rounded-3xl shadow-2xl p-8">
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-rocket text-blue-600 mr-2"></i>
                        Escolha seu Plano
                    </h2>
                    <p class="text-gray-600">Selecione o melhor plano para o seu negócio</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @php
                        $plans = \App\Models\Plan::where('is_active', true)->orderBy('order')->get();
                    @endphp
                    
                    @foreach($plans as $plan)
                        <div class="bg-white rounded-2xl shadow-lg border-2 {{ $plan->is_featured ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200' }} p-6 relative hover:shadow-2xl transition-all transform hover:scale-105">
                            @if($plan->is_featured)
                                <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                                    <span class="inline-flex items-center px-4 py-1.5 bg-gradient-to-r from-yellow-400 to-orange-500 text-white text-xs font-bold rounded-full shadow-lg">
                                        <i class="fas fa-fire mr-1"></i>Popular
                                    </span>
                                </div>
                            @endif

                            {{-- Header --}}
                            <div class="text-center mb-4 {{ $plan->is_featured ? 'mt-2' : '' }}">
                                <div class="w-14 h-14 bg-gradient-to-br from-blue-100 to-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-{{ $plan->is_featured ? 'crown' : 'box' }} text-2xl text-blue-600"></i>
                                </div>
                                <h4 class="text-xl font-bold text-gray-900 mb-1">{{ $plan->name }}</h4>
                                <div class="flex items-baseline justify-center space-x-1 mb-2">
                                    <span class="text-3xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0) }}</span>
                                    <span class="text-sm text-gray-600">Kz/mês</span>
                                </div>
                                @if($plan->price_yearly > 0)
                                    <p class="text-xs text-green-600">
                                        <i class="fas fa-tag mr-1"></i>Economize no anual
                                    </p>
                                @endif
                            </div>

                            {{-- Limites Compactos --}}
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                                    <span class="text-xs text-gray-600 flex items-center">
                                        <i class="fas fa-users mr-2 text-blue-500"></i>Utilizadores
                                    </span>
                                    <span class="font-bold text-gray-900">{{ $plan->max_users }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                                    <span class="text-xs text-gray-600 flex items-center">
                                        <i class="fas fa-database mr-2 text-purple-500"></i>Storage
                                    </span>
                                    <span class="font-bold text-gray-900">{{ number_format($plan->max_storage_mb / 1000, 1) }}GB</span>
                                </div>
                            </div>

                            {{-- Features Resumidas (primeiras 3) --}}
                            @if($plan->features && count($plan->features) > 0)
                                <div class="space-y-1.5 mb-4 border-t border-gray-100 pt-4">
                                    @foreach(array_slice($plan->features, 0, 3) as $feature)
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check text-green-500 text-xs mt-0.5 flex-shrink-0"></i>
                                            <span class="text-xs text-gray-700">{{ $feature }}</span>
                                        </div>
                                    @endforeach
                                    @if(count($plan->features) > 3)
                                        <p class="text-xs text-blue-600 font-medium pt-1">
                                            + {{ count($plan->features) - 3 }} recursos adicionais
                                        </p>
                                    @endif
                                </div>
                            @endif

                            {{-- Action Button --}}
                            <a href="{{ route('my-account') }}?tab=plan&select={{ $plan->id }}" class="block w-full px-5 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-xl font-bold transition shadow-lg text-center text-sm">
                                <i class="fas fa-shopping-cart mr-2"></i>Selecionar
                            </a>
                        </div>
                    @endforeach
                </div>

                {{-- Info Box --}}
                <div class="mt-8 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-5">
                    <div class="flex items-center justify-center space-x-8 text-center flex-wrap">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-bolt text-blue-600 text-lg"></i>
                            <span class="text-sm font-semibold text-gray-700">Acesso Imediato</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-alt text-green-600 text-lg"></i>
                            <span class="text-sm font-semibold text-gray-700">Dados Preservados</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-headset text-purple-600 text-lg"></i>
                            <span class="text-sm font-semibold text-gray-700">Suporte Prioritário</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-check-circle text-blue-600 text-lg"></i>
                            <span class="text-sm font-semibold text-gray-700">AGT Angola</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-6 text-gray-500 text-sm">
            <p>&copy; {{ date('Y') }} SOS ERP - Sistema de Gestão Empresarial</p>
        </div>
    </div>
</body>
</html>

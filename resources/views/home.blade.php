@extends('layouts.app')

@section('title', 'Início')
@section('page-subtitle', 'Bem-vindo ao SOS ERP')

@section('content')
<div class="max-w-7xl mx-auto">
    @if (session('status'))
        <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-xl shadow-lg animate-fadeInUp">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <span class="font-medium">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <!-- Debug Panel -->
    @if(config('app.debug'))
    <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-xl">
        <div class="flex items-start">
            <i class="fas fa-bug text-yellow-600 text-2xl mr-3 mt-1"></i>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-yellow-800 mb-3">🐛 Debug Info - Verificação de Acesso</h3>
                <div class="text-sm text-yellow-700 space-y-2 font-mono bg-yellow-100 p-4 rounded">
                    @foreach($debug as $key => $value)
                        <div class="flex">
                            <strong class="w-48 text-yellow-900">{{ $key }}:</strong>
                            <span class="flex-1 {{ is_array($value) ? 'text-blue-700' : 'text-gray-800' }}">
                                {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Alert: Sem Empresa -->
    @if($needsCompany)
    <div class="mb-6 bg-gradient-to-r from-red-500 to-orange-500 rounded-2xl shadow-xl p-6 text-white animate-pulse">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-3xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-xl font-bold mb-2">
                    <i class="fas fa-building mr-2"></i>Nenhuma Empresa Configurada
                </h3>
                <p class="text-red-100 mb-4">
                    Você ainda não tem nenhuma empresa cadastrada. Para começar a usar o sistema, é necessário criar sua primeira empresa.
                </p>
                <div class="flex space-x-3">
                    <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 bg-white text-red-600 rounded-xl font-semibold hover:bg-red-50 transition shadow-lg">
                        <i class="fas fa-plus-circle mr-2"></i>Criar Empresa Agora
                    </a>
                    <a href="{{ route('my-account') }}?tab=companies" class="inline-flex items-center px-6 py-3 bg-white/20 backdrop-blur-sm text-white rounded-xl font-semibold hover:bg-white/30 transition">
                        <i class="fas fa-cog mr-2"></i>Gerenciar Conta
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Alert: Pagamento Pendente -->
    @if($hasPendingOrder)
    <div class="mb-6 bg-gradient-to-r from-orange-500 to-yellow-500 rounded-2xl shadow-xl p-6 text-white" id="pendingOrderAlert">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-clock text-3xl animate-pulse"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-xl font-bold mb-2">
                    <i class="fas fa-hourglass-half mr-2"></i>Pagamento em Análise
                </h3>
                <p class="text-orange-100 mb-2">
                    Seu pagamento foi recebido e está sendo verificado por nossa equipe. Você receberá acesso total assim que for aprovado (geralmente em até 24h).
                </p>
                <p class="text-orange-200 text-sm mb-4">
                    <i class="fas fa-sync-alt mr-1"></i>Esta página será atualizada automaticamente quando seu pedido for aprovado.
                </p>
                <div class="flex space-x-3">
                    <a href="{{ route('my-account') }}?tab=plan" class="inline-flex items-center px-6 py-3 bg-white text-orange-600 rounded-xl font-semibold hover:bg-orange-50 transition shadow-lg">
                        <i class="fas fa-file-invoice mr-2"></i>Ver Status do Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh a cada 15 segundos para verificar se pedido foi aprovado
        let checkCount = 0;
        const maxChecks = 240; // 1 hora (240 * 15s)
        
        const checkInterval = setInterval(() => {
            checkCount++;
            
            // Verificar status via fetch
            fetch('{{ route('home') }}', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Se não tem mais o alerta de pedido pendente, recarrega
                if (!html.includes('pendingOrderAlert')) {
                    console.log('✅ Pedido aprovado! Recarregando página...');
                    window.location.reload();
                }
            })
            .catch(error => {
                console.log('Erro ao verificar status:', error);
            });
            
            // Parar após 1 hora
            if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
                console.log('Verificação automática encerrada após 1 hora');
            }
        }, 15000); // 15 segundos
        
        console.log('🔄 Verificação automática de aprovação ativada (a cada 15s)');
    </script>
    @endif

    <!-- Alert: Sem Plano Ativo -->
    @if($needsSubscription && !$hasPendingOrder)
    <div class="mb-6 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl shadow-xl p-6 text-white">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-crown text-3xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-xl font-bold mb-2">
                    <i class="fas fa-star mr-2"></i>Nenhum Plano Ativo
                </h3>
                <p class="text-yellow-100 mb-4">
                    Sua empresa <strong>{{ $activeTenant->name ?? 'atual' }}</strong> não possui um plano ativo. Escolha um plano para desbloquear todos os recursos do sistema.
                </p>
                <div class="flex space-x-3">
                    <a href="{{ route('landing.home') }}#pricing" class="inline-flex items-center px-6 py-3 bg-white text-yellow-600 rounded-xl font-semibold hover:bg-yellow-50 transition shadow-lg">
                        <i class="fas fa-shopping-cart mr-2"></i>Ver Planos
                    </a>
                    <a href="{{ route('my-account') }}?tab=plan" class="inline-flex items-center px-6 py-3 bg-white/20 backdrop-blur-sm text-white rounded-xl font-semibold hover:bg-white/30 transition">
                        <i class="fas fa-info-circle mr-2"></i>Meu Plano Atual
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Alert: Trial Ativo -->
    @if($hasCompany && !$needsSubscription && $subscriptionStatus === 'trial')
    <div class="mb-6 bg-gradient-to-r from-blue-500 to-purple-500 rounded-2xl shadow-xl p-6 text-white">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-clock text-3xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-xl font-bold mb-2">
                    <i class="fas fa-gift mr-2"></i>Período de Teste Ativo
                </h3>
                <p class="text-blue-100 mb-4">
                    Você está no período de teste de <strong>{{ $activeSubscription->plan->trial_days }} dias</strong> do plano <strong>{{ $activeSubscription->plan->name }}</strong>.
                    @if($activeSubscription->trial_ends_at)
                        Expira em: <strong>{{ $activeSubscription->trial_ends_at->format('d/m/Y') }}</strong>
                        ({{ $activeSubscription->trial_ends_at->diffForHumans() }})
                    @endif
                </p>
                <a href="{{ route('my-account') }}?tab=plan" class="inline-flex items-center px-6 py-3 bg-white text-blue-600 rounded-xl font-semibold hover:bg-blue-50 transition shadow-lg">
                    <i class="fas fa-arrow-up mr-2"></i>Fazer Upgrade
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Welcome Card with Modern Design -->
    <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-purple-600 to-pink-500 rounded-3xl shadow-2xl p-8 mb-8 card-hover gradient-shift">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>
        
        <div class="relative flex items-center justify-between">
            <div class="flex-1">
                <div class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full mb-4">
                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                    <span class="text-white text-sm font-medium">Online</span>
                </div>
                <h2 class="text-4xl font-bold mb-3 text-white">Olá, {{ auth()->user()->name }}! 👋</h2>
                <p class="text-blue-100 text-lg">{{ \Carbon\Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
            </div>
            <div class="hidden md:flex items-center justify-center">
                <div class="relative">
                    <div class="w-24 h-24 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center icon-float transform rotate-6">
                        <i class="fas fa-user text-5xl text-white"></i>
                    </div>
                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 rounded-full border-4 border-white shadow-lg flex items-center justify-center">
                        <i class="fas fa-check text-white text-xs"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Cards with Animations -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 stagger-animation">
        @if(auth()->user()->isSuperAdmin())
            <a href="{{ route('superadmin.dashboard') }}" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-3d border border-yellow-200">
                <div class="p-6 bg-gradient-to-br from-yellow-50 to-orange-50 border-b border-yellow-100 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float card-bounce">
                            <i class="fas fa-crown text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-yellow-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Super Admin</h3>
                    <p class="text-sm text-gray-600">Gerir todo o sistema</p>
                </div>
            </a>

            <a href="{{ route('superadmin.tenants') }}" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-3d border border-green-200">
                <div class="p-6 bg-gradient-to-br from-green-50 to-emerald-50 border-b border-green-100 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float card-bounce">
                            <i class="fas fa-building text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-green-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Tenants</h3>
                    <p class="text-sm text-gray-600">Gerir organizações</p>
                </div>
            </a>

            <a href="{{ route('superadmin.billing') }}" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-3d border border-blue-200">
                <div class="p-6 bg-gradient-to-br from-blue-50 to-cyan-50 border-b border-blue-100 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float card-bounce">
                            <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-blue-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Billing</h3>
                    <p class="text-sm text-gray-600">Faturas e pagamentos</p>
                </div>
            </a>
        @else
            <!-- Tenant Dashboard Cards -->
            <a href="#" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-zoom border border-green-200">
                <div class="p-6 bg-gradient-to-br from-green-50 to-emerald-50 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                            <i class="fas fa-file-invoice text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-green-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Faturação</h3>
                    <p class="text-sm text-gray-600">Clientes, Faturas e Pagamentos</p>
                </div>
            </a>

            <a href="#" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-glow border border-purple-200">
                <div class="p-6 bg-gradient-to-br from-purple-50 to-pink-50 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                            <i class="fas fa-boxes text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-purple-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Produtos/Serviços</h3>
                    <p class="text-sm text-gray-600">Catálogo e Preços</p>
                </div>
            </a>

            <a href="#" class="group bg-white rounded-2xl shadow-lg overflow-hidden card-hover card-rotate border border-blue-200">
                <div class="p-6 bg-gradient-to-br from-blue-50 to-cyan-50 gradient-shift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                            <i class="fas fa-users text-white text-2xl"></i>
                        </div>
                        <i class="fas fa-arrow-right text-blue-400 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Utilizadores</h3>
                    <p class="text-sm text-gray-600">Gerir equipa</p>
                </div>
            </a>
        @endif
    </div>

    @if(!auth()->user()->isSuperAdmin())
    <!-- Tenant Info & Statistics with Modern Design -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 stagger-animation">
        <!-- Tenant Info Card -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6 card-hover border border-purple-100">
            <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center mr-3 icon-float">
                    <i class="fas fa-building text-white"></i>
                </div>
                Informações da Empresa
            </h3>
            @if(auth()->user()->tenant)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl border border-purple-100">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-briefcase text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-purple-600 font-semibold mb-1">Empresa</p>
                            <p class="text-sm font-bold text-gray-900">{{ auth()->user()->tenant->name }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl border border-blue-100">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-id-card text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600 font-semibold mb-1">NIF</p>
                            <p class="text-sm font-bold text-gray-900">{{ auth()->user()->tenant->nif ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-100">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-envelope text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-green-600 font-semibold mb-1">Email</p>
                            <p class="text-sm font-bold text-gray-900">{{ auth()->user()->tenant->email }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gradient-to-br from-orange-50 to-red-50 rounded-xl border border-orange-100">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-phone text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-orange-600 font-semibold mb-1">Telefone</p>
                            <p class="text-sm font-bold text-gray-900">{{ auth()->user()->tenant->phone ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Subscription Card with Modern Design -->
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-purple-600 to-pink-500 rounded-2xl shadow-xl p-6 text-white card-glow">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>
            <div class="relative">
                <h3 class="text-xl font-bold mb-6 flex items-center">
                    <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-crown"></i>
                    </div>
                    Subscrição
                </h3>
                @if($activeSubscription)
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Plano:</span>
                        <span class="text-sm font-bold">{{ $activeSubscription->plan->name }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Valor:</span>
                        <span class="text-sm font-bold">{{ number_format($activeSubscription->amount, 2) }} Kz</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Ciclo:</span>
                        <span class="text-sm font-bold">{{ $activeSubscription->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Início:</span>
                        <span class="text-sm font-bold">{{ $activeSubscription->current_period_start?->format('d/m/Y') ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Renovação:</span>
                        <span class="text-sm font-bold">{{ $activeSubscription->current_period_end?->format('d/m/Y') ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                        <span class="text-sm font-medium">Status:</span>
                        <span class="inline-flex items-center px-3 py-1 bg-green-500 rounded-full text-xs font-bold shadow-lg">
                            <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                            {{ ucfirst($activeSubscription->status) }}
                        </span>
                    </div>
                </div>
                @else
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-info-circle text-3xl"></i>
                    </div>
                    <p class="text-sm opacity-90">Sem subscrição ativa</p>
                    <p class="text-xs opacity-75 mt-1">Entre em contato com o administrador</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modules Section -->
    <div class="mt-8">
        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-puzzle-piece text-white"></i>
            </div>
            Módulos Ativos
        </h3>
        
        @if(auth()->user()->tenant && auth()->user()->tenant->modules()->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 stagger-animation">
            @foreach(auth()->user()->tenant->modules as $module)
            <div class="group bg-white rounded-xl shadow-lg p-4 border-l-4 {{ $module->pivot->is_active ? 'border-green-500' : 'border-gray-300' }} card-hover">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-{{ $module->pivot->is_active ? 'green' : 'gray' }}-500 to-{{ $module->pivot->is_active ? 'emerald' : 'gray' }}-600 rounded-xl flex items-center justify-center shadow-lg icon-float">
                        <i class="fas fa-{{ $module->icon }} text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-gray-900">{{ $module->name }}</h4>
                        <p class="text-xs text-gray-500">{{ $module->description }}</p>
                    </div>
                    @if($module->pivot->is_active)
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span>
                            Ativo
                        </span>
                    </div>
                    @else
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold">
                            Inativo
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-xl mb-8">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-3"></i>
                <div>
                    <h4 class="font-bold text-yellow-800">Nenhum módulo ativo</h4>
                    <p class="text-sm text-yellow-700">Entre em contato com o administrador para ativar módulos.</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Statistics Cards with Modern Design -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-3d border border-green-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-users text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-bold mb-1">Clientes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ \App\Models\Client::where('tenant_id', auth()->user()->tenant_id)->count() }}
            </p>
            <p class="text-xs text-gray-500">Total registados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-zoom border border-purple-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-box text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-bold mb-1">Produtos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ \App\Models\Product::where('tenant_id', auth()->user()->tenant_id)->count() }}
            </p>
            <p class="text-xs text-gray-500">No catálogo</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-glow border border-blue-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-file-invoice text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-bold mb-1">Faturas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">0</p>
            <p class="text-xs text-gray-500">Este mês</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-rotate border border-orange-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/50 icon-float">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-bold mb-1">Receita Total</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">0</p>
            <p class="text-xs text-gray-500">Kz este mês</p>
        </div>
    </div>
    @endif
</div>
@endsection

<div x-data="{ activeTab: 'pending' }">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Faturação</h2>
                    <p class="text-orange-100 text-sm">Pedidos, Subscriptions e Faturas</p>
                </div>
            </div>
        </div>
    </div>

    {{-- SAFT-AO (Software AGT) — APENAS DONO DO SISTEMA --}}
    @if(auth()->user()->isPlatformSuperAdmin())
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6 border-2 border-red-200" x-data="{ open: false }">
        <button @click="open = !open" class="w-full flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-file-code mr-2 text-purple-600"></i>SAFT-AO (Exportação Fiscal)
                <span class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full"><i class="fas fa-shield-alt mr-1"></i>DONO DO SISTEMA</span>
            </h3>
            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>

        <div x-show="open" x-cloak class="mt-4 space-y-4">
            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Identificadores do <strong>software</strong> emitidos pela AGT Angola (iguais para todas as empresas). Obrigatórios para a exportação SAFT-AO.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-certificate mr-1 text-green-500"></i>Certificado Software AGT</label>
                    <input type="text" wire:model="saft_software_cert" placeholder="Ex: AGT/2024/XXXX"
                           class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                    @error('saft_software_cert') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-tag mr-1 text-blue-500"></i>Product ID</label>
                    <input type="text" wire:model="saft_product_id" placeholder="Ex: SOSERP/v1.0"
                           class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                    @error('saft_product_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-code-branch mr-1 text-purple-500"></i>Versão SAFT</label>
                    <input type="text" wire:model="saft_version" placeholder="1.0.0"
                           class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                    @error('saft_version') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveSaftConfig" wire:loading.attr="disabled"
                        class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold shadow hover:from-purple-700 hover:to-indigo-700">
                    <i class="fas fa-save mr-1"></i>Guardar SAFT-AO
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Receita Total</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalRevenue, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Kz</p>
                </div>
                <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Pendente</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($pendingRevenue, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Kz</p>
                </div>
                <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Faturas</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $invoices->total() }}</p>
                    <p class="text-xs text-gray-500 mt-1">documentos</p>
                </div>
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="bg-white rounded-xl shadow-lg mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button @click="activeTab = 'pending'" 
                        :class="activeTab === 'pending' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="group inline-flex items-center py-4 px-6 border-b-2 font-medium text-sm transition-all">
                    <i class="fas fa-bell mr-2"></i>
                    Pedidos Pendentes
                    @if($pendingOrders->count() > 0)
                        <span class="ml-2 bg-orange-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $pendingOrders->count() }}</span>
                    @endif
                </button>
                
                <button @click="activeTab = 'subscriptions'" 
                        :class="activeTab === 'subscriptions' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="group inline-flex items-center py-4 px-6 border-b-2 font-medium text-sm transition-all">
                    <i class="fas fa-crown mr-2"></i>
                    Subscriptions
                    <span class="ml-2 text-xs text-gray-500">({{ $subscriptions->count() }})</span>
                </button>
                
                <button @click="activeTab = 'invoices'" 
                        :class="activeTab === 'invoices' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="group inline-flex items-center py-4 px-6 border-b-2 font-medium text-sm transition-all">
                    <i class="fas fa-receipt mr-2"></i>
                    Faturas
                    <span class="ml-2 text-xs text-gray-500">({{ $invoices->total() }})</span>
                </button>
            </nav>
        </div>

        {{-- Tab: Pedidos Pendentes --}}
        <div x-show="activeTab === 'pending'" x-cloak class="p-6">
            @if($pendingOrders->count() > 0)
                <div class="space-y-4">
                    @foreach($pendingOrders as $order)
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-5">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-building text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900">{{ $order->tenant?->name ?? '— empresa apagada —' }}</h4>
                                            <p class="text-xs text-gray-600">{{ $order->user?->name ?? 'utilizador apagado' }} • {{ $order->user?->email ?? '—' }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-4 gap-4 mb-3">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Plano</p>
                                            <p class="font-semibold text-gray-900">{{ $order->plan?->name ?? '— plano apagado —' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Valor</p>
                                            <p class="font-semibold text-gray-900">{{ number_format($order->amount, 2) }} Kz</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Ciclo</p>
                                            <p class="font-semibold text-gray-900">{{ \App\Livewire\SuperAdmin\Billing::nomeDoCiclo($order->billing_cycle) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Data</p>
                                            <p class="font-semibold text-gray-900">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                    </div>
                                    
                                    @if($order->payment_proof)
                                        <a href="{{ Storage::url($order->payment_proof) }}" target="_blank" 
                                           class="inline-flex items-center text-xs bg-white px-3 py-1.5 rounded-lg hover:bg-gray-50 transition border border-gray-200">
                                            <i class="fas fa-file-download mr-1.5"></i>Ver Comprovativo
                                        </a>
                                    @endif
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <button wire:click="approveOrder({{ $order->id }})" 
                                            wire:confirm="Aprovar este pedido? O cliente receberá um email de confirmação."
                                            wire:loading.attr="disabled"
                                            wire:target="approveOrder({{ $order->id }})"
                                            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition disabled:opacity-50">
                                        <span wire:loading.remove wire:target="approveOrder({{ $order->id }})">
                                            <i class="fas fa-check mr-1"></i>Aprovar
                                        </span>
                                        <span wire:loading wire:target="approveOrder({{ $order->id }})">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>Processando...
                                        </span>
                                    </button>
                                    {{-- Abre a caixa que pergunta o motivo. Rejeitava-se às
                                         escuras e o cliente recebia "Não especificado". --}}
                                    <button wire:click="openRejectModal({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openRejectModal({{ $order->id }})"
                                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition disabled:opacity-50">
                                        <span wire:loading.remove wire:target="openRejectModal({{ $order->id }})">
                                            <i class="fas fa-times mr-1"></i>Rejeitar
                                        </span>
                                        <span wire:loading wire:target="openRejectModal({{ $order->id }})">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>Processando...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum pedido pendente</h3>
                    <p class="text-gray-500">Todos os pedidos foram processados</p>
                </div>
            @endif
        </div>

        {{-- Tab: Subscriptions --}}
        <div x-show="activeTab === 'subscriptions'" x-cloak>
            {{-- Filtros de Subscriptions --}}
            <div class="p-6 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative col-span-2">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input wire:model.live="subscriptionSearch" type="text" placeholder="Pesquisar por tenant ou plano..." 
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-filter text-gray-400"></i>
                        </div>
                        <select wire:model.live="subscriptionStatusFilter" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 appearance-none bg-white">
                            <option value="">Todos os status</option>
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="text-sm text-gray-600 flex items-center justify-between">
                        <span class="flex items-center">
                            <i class="fas fa-info-circle mr-2 text-purple-500"></i>
                            {{ $subscriptions->count() }} subscription(s)
                        </span>
                        <button wire:click="createSubscription"
                                class="ml-2 inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-semibold rounded-lg shadow transition">
                            <i class="fas fa-plus mr-2"></i>Nova Subscrição
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    @forelse($subscriptions as $subscription)
                    <div class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition" x-data="{ expanded: false }">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br {{ $subscription->status === 'active' ? 'from-purple-500 to-blue-600' : ($subscription->status === 'trial' ? 'from-blue-400 to-blue-600' : ($subscription->status === 'expired' ? 'from-red-400 to-red-600' : 'from-gray-400 to-gray-600')) }} rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-crown text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900">{{ $subscription->tenant?->name ?? '— empresa apagada —' }}</h4>
                                            <p class="text-sm text-gray-600">Plano: <span class="font-semibold text-purple-600">{{ $subscription->plan?->name ?? '— plano apagado —' }}</span></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full 
                                            {{ $subscription->status === 'active' ? 'bg-green-100 text-green-700' : 
                                               ($subscription->status === 'trial' ? 'bg-blue-100 text-blue-700' : 
                                               ($subscription->status === 'expired' ? 'bg-red-100 text-red-700' :
                                               ($subscription->status === 'cancelled' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-700'))) }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $subscription->status === 'active' ? 'bg-green-500' : ($subscription->status === 'trial' ? 'bg-blue-500' : ($subscription->status === 'expired' ? 'bg-red-500' : ($subscription->status === 'cancelled' ? 'bg-orange-500' : 'bg-gray-500'))) }} mr-1.5"></span>
                                            {{ ucfirst($subscription->status) }}
                                        </span>
                                        <button @click="expanded = !expanded" class="p-2 hover:bg-gray-100 rounded-lg transition">
                                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                {{-- Grid Principal --}}
                                <div class="grid grid-cols-5 gap-4 mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Valor</p>
                                        <p class="font-semibold text-gray-900">{{ number_format($subscription->amount, 2) }} Kz</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Ciclo</p>
                                        <p class="font-semibold text-gray-900">{{ \App\Livewire\SuperAdmin\Billing::nomeDoCiclo($subscription->billing_cycle) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Início</p>
                                        <p class="font-semibold text-gray-900">{{ $subscription->current_period_start?->format('d/m/Y') ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Renovação</p>
                                        <p class="font-semibold text-gray-900">{{ $subscription->current_period_end?->format('d/m/Y') ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Dias Restantes</p>
                                        @php
                                            // Dias inteiros e com sinal: negativo = já passou.
                                            // Imprimia-se o float cru do Carbon — "12.208333320301
                                            // dias" — e um período vencido aparecia em verde, como
                                            // se ainda faltassem esses dias.
                                            $fim  = $subscription->current_period_end;
                                            $dias = $fim ? (int) floor(now()->startOfDay()->diffInDays($fim->copy()->startOfDay(), false)) : null;
                                        @endphp
                                        @if($dias === null)
                                            <p class="font-semibold text-gray-400">—</p>
                                        @elseif($dias < 0)
                                            <p class="font-semibold text-red-600">Vencida há {{ abs($dias) }} dia(s)</p>
                                        @elseif($dias === 0)
                                            <p class="font-semibold text-orange-600">Termina hoje</p>
                                        @else
                                            <p class="font-semibold {{ $dias <= 7 ? 'text-orange-600' : 'text-green-600' }}">{{ $dias }} dia(s)</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Detalhes Expandidos --}}
                                <div x-show="expanded" x-collapse x-cloak class="border-t border-gray-200 pt-4 mt-4">
                                    {{-- Limites do Plano --}}
                                    <div class="mb-4">
                                        <h5 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-sliders-h text-purple-500 mr-2"></i>Limites do Plano
                                        </h5>
                                        <div class="grid grid-cols-4 gap-3">
                                            <div class="bg-blue-50 rounded-lg p-3">
                                                <p class="text-xs text-blue-600 mb-1">Utilizadores</p>
                                                <p class="text-lg font-bold text-blue-700">{{ $subscription->plan->max_users }}</p>
                                            </div>
                                            <div class="bg-purple-50 rounded-lg p-3">
                                                <p class="text-xs text-purple-600 mb-1">Empresas</p>
                                                <p class="text-lg font-bold text-purple-700">{{ $subscription->plan->max_companies >= 999 ? '∞' : $subscription->plan->max_companies }}</p>
                                            </div>
                                            <div class="bg-green-50 rounded-lg p-3">
                                                <p class="text-xs text-green-600 mb-1">Storage</p>
                                                <p class="text-lg font-bold text-green-700">{{ $subscription->plan->max_storage_mb >= 1024 ? number_format($subscription->plan->max_storage_mb / 1024, 1) . ' GB' : $subscription->plan->max_storage_mb . ' MB' }}</p>
                                            </div>
                                            <div class="bg-orange-50 rounded-lg p-3">
                                                <p class="text-xs text-orange-600 mb-1">Trial</p>
                                                <p class="text-lg font-bold text-orange-700">{{ $subscription->plan->trial_days }} dias</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Módulos --}}
                                    @if($subscription->plan->modules && $subscription->plan->modules->count() > 0)
                                    <div class="mb-4">
                                        <h5 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-puzzle-piece text-blue-500 mr-2"></i>Módulos Incluídos ({{ $subscription->plan->modules->count() }})
                                        </h5>
                                        <div class="grid grid-cols-4 gap-2">
                                            @foreach($subscription->plan?->modules ?? [] as $module)
                                                <div class="flex items-center px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                                                    <i class="fas fa-{{ $module->icon }} text-blue-600 mr-2"></i>
                                                    <span class="font-medium text-blue-700">{{ $module->name }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Ações --}}
                                    <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                                        {{-- Só nas linhas que ainda contam: editar uma expirada
                                             ou cancelada não tem alvo nenhum. --}}
                                        @if(in_array($subscription->status, ['active', 'pending', 'trial'], true))
                                            <button wire:click="editSubscription({{ $subscription->id }})"
                                                    class="px-4 py-2 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-100 transition">
                                                <i class="fas fa-edit mr-1"></i>Alterar Plano / Ciclo
                                            </button>
                                        @endif
                                        @if($subscription->status === 'active')
                                            <button wire:click="cancelSubscription({{ $subscription->id }})"
                                                    wire:confirm="Cancelar esta subscrição? A empresa mantém o acesso até ao fim do período já pago."
                                                    class="px-4 py-2 bg-orange-50 text-orange-700 rounded-lg text-xs font-medium hover:bg-orange-100 transition">
                                                <i class="fas fa-ban mr-1"></i>Cancelar Subscrição
                                            </button>
                                        @endif
                                        @if(!in_array($subscription->status, ['active', 'trial'], true))
                                            <button wire:click="deleteSubscription({{ $subscription->id }})"
                                                    wire:confirm="Excluir esta subscrição permanentemente?"
                                                    class="px-4 py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition">
                                                <i class="fas fa-trash mr-1"></i>Excluir
                                            </button>
                                        @endif
                                    </div>

                                    {{-- Features --}}
                                    @if($subscription->plan->features && count($subscription->plan->features) > 0)
                                    <div>
                                        <h5 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-check-circle text-green-500 mr-2"></i>Features ({{ count($subscription->plan->features) }})
                                        </h5>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach($subscription->plan->features as $feature)
                                                <div class="flex items-start px-3 py-2 bg-green-50 rounded-lg">
                                                    <i class="fas fa-check text-green-600 text-xs mr-2 mt-0.5"></i>
                                                    <span class="text-xs text-gray-700">{{ $feature }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-crown text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma subscription encontrada</h3>
                        <p class="text-gray-500">Aguarde aprovação de pedidos</p>
                    </div>
                @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Faturas --}}
        <div x-show="activeTab === 'invoices'" x-cloak>
            {{-- Filtros --}}
            <div class="p-6 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar faturas..." 
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-filter text-gray-400"></i>
                        </div>
                        <select wire:model.live="statusFilter" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 appearance-none bg-white">
                            <option value="">Todos os status</option>
                            <option value="paid">Pago</option>
                            <option value="pending">Pendente</option>
                            <option value="overdue">Atrasado</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    
                    <button wire:click="create" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                        <i class="fas fa-plus mr-2"></i>Nova Fatura
                    </button>
                </div>
            </div>

            {{-- Lista de Faturas --}}
            <div class="p-6">
                <div class="space-y-3">
                    @forelse($invoices as $invoice)
                        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $invoice->status === 'paid' ? 'from-green-400 to-green-600' : ($invoice->status === 'pending' ? 'from-yellow-400 to-orange-600' : 'from-gray-400 to-gray-600') }} flex items-center justify-center mr-3">
                                            <i class="fas fa-file-invoice text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-gray-900">{{ $invoice->invoice_number }}</h4>
                                            <p class="text-xs text-gray-500">{{ $invoice->tenant->name ?? 'N/A' }} • {{ $invoice->invoice_date->format('d/m/Y') }}</p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full 
                                            {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : 
                                               ($invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                        <p class="ml-4 text-lg font-bold text-gray-900">{{ number_format($invoice->total, 2) }} Kz</p>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <button wire:click="edit({{ $invoice->id }})" class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition">
                                        <i class="fas fa-edit mr-1"></i>Editar
                                    </button>
                                    @if($invoice->status === 'pending')
                                        <button wire:click="marcarFacturaComoPaga({{ $invoice->id }})" class="px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition">
                                            <i class="fas fa-check mr-1"></i>Marcar Paga
                                        </button>
                                    @endif
                                    <button wire:click="delete({{ $invoice->id }})" wire:confirm="Excluir esta fatura?" class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition">
                                        <i class="fas fa-trash mr-1"></i>Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma fatura encontrada</h3>
                            <p class="text-gray-500">Comece criando uma nova fatura</p>
                        </div>
                    @endforelse
                </div>

                {{-- Paginação --}}
                @if($invoices->hasPages())
                    <div class="mt-6">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal (mantido do original) --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak @keydown.escape.window="$wire.closeModal()">
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl" @click.stop>
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white">
                            {{ $editingInvoiceId ? 'Editar Fatura' : 'Nova Fatura' }}
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <form wire:submit.prevent="save" class="p-6 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-building text-blue-500 mr-2"></i>Tenant *
                                </label>
                                <select wire:model="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <option value="">Selecione um tenant</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('tenant_id') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-orange-500 mr-2"></i>Nº Fatura *
                                </label>
                                <input wire:model="invoice_number" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-gray-50" readonly>
                                <p class="text-xs text-gray-500 mt-1">Gerado automaticamente</p>
                                @error('invoice_number') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-info-circle text-purple-500 mr-2"></i>Status *
                                </label>
                                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <option value="pending">Pendente</option>
                                    <option value="paid">Pago</option>
                                    <option value="overdue">Atrasado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar text-green-500 mr-2"></i>Data Emissão *
                                </label>
                                <input wire:model="invoice_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('invoice_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar-check text-red-500 mr-2"></i>Data Vencimento *
                                </label>
                                <input wire:model="due_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                                @error('due_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                                </label>
                                <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" placeholder="Descrição da fatura..."></textarea>
                                @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill text-green-500 mr-2"></i>Subtotal (Kz) *
                                </label>
                                <input wire:model.live="subtotal" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('subtotal') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-percentage text-yellow-500 mr-2"></i>Imposto (Kz) *
                                </label>
                                <input wire:model.live="tax" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition">
                                @error('tax') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calculator text-blue-500 mr-2"></i>Total (Kz)
                                </label>
                                <input wire:model="total" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-blue-50 font-bold text-lg text-blue-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" readonly>
                                <p class="text-xs text-gray-500 mt-1">Calculado automaticamente (Subtotal + Imposto)</p>
                                @error('total') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingInvoiceId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingInvoiceId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Subscription (Criar/Editar plano + ciclo + marcar como pago) --}}
    @if($showSubscriptionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showSubscriptionModal') }" x-show="show" x-cloak @keydown.escape.window="$wire.closeSubscriptionModal()">
            <div class="flex items-start justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" wire:click="closeSubscriptionModal"></div>

                <div class="relative bg-white rounded-2xl max-w-3xl w-full shadow-2xl my-8" @click.stop>
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-crown text-2xl text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Atribuir / Alterar Plano</h3>
                                <p class="text-purple-100 text-xs">Trocar plano do tenant e definir ciclo + status de pagamento</p>
                            </div>
                        </div>
                        <button wire:click="closeSubscriptionModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveSubscription" class="p-6 max-h-[75vh] overflow-y-auto space-y-5">
                        {{-- Empresa.

                             A editar, o selector fica fechado: uma edição não
                             muda a subscrição de dono. Estava aberto, e trocar
                             de empresa aqui era silenciosamente ignorado ao
                             gravar — o ecrã deixava fazer uma coisa que não
                             fazia nada. --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-building text-blue-500 mr-1"></i>Empresa *
                            </label>
                            @if($editingSubscriptionId)
                                @php $empresaEmEdicao = $tenants->firstWhere('id', $tenant_id); @endphp
                                <div class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-gray-700 font-semibold">
                                    <i class="fas fa-lock text-gray-400 mr-2"></i>{{ $empresaEmEdicao->name ?? 'Empresa #' . $tenant_id }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">A empresa de uma subscrição não se altera. Para mudar de empresa, crie uma nova subscrição.</p>
                            @else
                                <select wire:model.live="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    <option value="">Selecione uma empresa…</option>
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }} {{ $t->company_name ? '— ' . $t->company_name : '' }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('tenant_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Plano --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-box text-purple-500 mr-1"></i>Plano *
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($plans as $p)
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="plan_id" value="{{ $p->id }}" class="peer sr-only">
                                        <div class="p-3 border-2 rounded-xl transition peer-checked:border-purple-600 peer-checked:bg-purple-50 hover:border-purple-400">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-bold text-sm text-gray-900">{{ $p->name }}</p>
                                                    <p class="text-xs text-gray-500">{{ $p->max_users }} users • {{ number_format($p->max_storage_mb / 1024, 1) }}GB</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-bold text-purple-700">{{ number_format($p->price_monthly, 0) }} Kz</p>
                                                    <p class="text-xs text-gray-500">/mês</p>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('plan_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Ciclo de Faturação --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt text-green-500 mr-1"></i>Ciclo de Faturação *
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @php
                                    $cycles = [
                                        'monthly'    => ['label' => 'Mensal', 'desc' => '1 mês', 'color' => 'green'],
                                        'quarterly'  => ['label' => 'Trimestral', 'desc' => '3 meses', 'color' => 'blue'],
                                        'semiannual' => ['label' => 'Semestral', 'desc' => '6 meses', 'color' => 'purple'],
                                        'yearly'     => ['label' => 'Anual', 'desc' => '14 meses (2 grátis 🎁)', 'color' => 'orange'],
                                    ];
                                @endphp
                                @foreach($cycles as $value => $cfg)
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="billing_cycle" value="{{ $value }}" class="peer sr-only">
                                        <div class="p-3 border-2 rounded-xl text-center transition peer-checked:border-{{ $cfg['color'] }}-600 peer-checked:bg-{{ $cfg['color'] }}-50 hover:border-{{ $cfg['color'] }}-400">
                                            <p class="font-bold text-sm text-gray-900">{{ $cfg['label'] }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $cfg['desc'] }}</p>
                                            @if($selectedPlan)
                                                <p class="text-xs font-bold text-{{ $cfg['color'] }}-700 mt-1">
                                                    {{ number_format($selectedPlan->getPrice($value), 0) }} Kz
                                                </p>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('billing_cycle') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Toggle: Marcar como Pago --}}
                        <div class="bg-gradient-to-br {{ $marcarComoPago ? 'from-green-50 to-emerald-50 border-green-300' : 'from-yellow-50 to-orange-50 border-yellow-300' }} border-2 rounded-xl p-4">
                            <label class="flex items-start cursor-pointer">
                                <input type="checkbox" wire:model.live="marcarComoPago" class="mt-1 w-5 h-5 rounded text-green-600 focus:ring-green-500">
                                <div class="ml-3 flex-1">
                                    <p class="font-bold text-sm text-gray-900">
                                        @if($marcarComoPago)
                                            <i class="fas fa-check-circle text-green-600 mr-1"></i>Marcar como PAGO
                                        @else
                                            <i class="fas fa-clock text-yellow-600 mr-1"></i>Aguardar Pagamento (Pending)
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        @if($marcarComoPago)
                                            Subscrição fica <strong>activa imediatamente</strong>, módulos sincronizados e <strong>fatura paga</strong> gerada.
                                        @else
                                            Subscrição fica <strong>pending</strong> aguardando o pagamento ser confirmado.
                                        @endif
                                    </p>
                                </div>
                            </label>
                        </div>

                        {{-- Detalhes de Pagamento (apenas se marcado como pago) --}}
                        @if($marcarComoPago)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-green-50/50 border border-green-200 rounded-xl">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        <i class="fas fa-money-bill-wave text-green-500 mr-1"></i>Método de Pagamento
                                    </label>
                                    <select wire:model="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                                        <option value="bank_transfer">Transferência Bancária</option>
                                        <option value="cash">Dinheiro</option>
                                        <option value="multicaixa">Multicaixa</option>
                                        <option value="credit_card">Cartão de Crédito</option>
                                        <option value="other">Outro</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        <i class="fas fa-hashtag text-green-500 mr-1"></i>Referência (opcional)
                                    </label>
                                    <input wire:model="paymentReference" type="text" placeholder="Ex: TRF-2025-001"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>
                        @endif

                        {{-- Resumo --}}
                        @if($selectedPlan && $tenant_id && $billing_cycle)
                            @php
                                $resumeAmount = $selectedPlan->getPrice($billing_cycle);
                                $resumeCycle = match($billing_cycle) {
                                    'yearly' => 'Anual (14 meses)',
                                    'semiannual' => 'Semestral (6 meses)',
                                    'quarterly' => 'Trimestral (3 meses)',
                                    default => 'Mensal',
                                };
                            @endphp
                            <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-xl p-4 text-white shadow-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-purple-200 text-xs uppercase font-bold tracking-wide">Total a {{ $marcarComoPago ? 'Cobrar' : 'Aguardar' }}</p>
                                        <p class="text-sm text-purple-100 mt-0.5">{{ $selectedPlan->name }} • {{ $resumeCycle }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-3xl font-bold">{{ number_format($resumeAmount, 0) }} <span class="text-base">Kz</span></p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Footer --}}
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                            <button type="button" wire:click="closeSubscriptionModal"
                                    class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled" wire:target="saveSubscription"
                                    class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white rounded-xl font-semibold shadow-lg transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveSubscription">
                                    <i class="fas {{ $marcarComoPago ? 'fa-check-circle' : 'fa-save' }} mr-2"></i>
                                    {{ $marcarComoPago ? 'Salvar e Marcar como Pago' : 'Salvar (Pending)' }}
                                </span>
                                <span wire:loading wire:target="saveSubscription">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>A processar…
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: motivo da recusa.

         A coluna `rejection_reason` existe desde sempre e nunca era escrita:
         quem via o pedido recusado recebia um email a dizer "Não especificado"
         e ficava sem saber se o comprovativo estava ilegível, se o valor não
         batia certo, ou o quê. Voltava a submeter o mesmo e era recusado outra
         vez. --}}
    @if($showRejectModal && $rejectingOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-cloak @keydown.escape.window="$wire.closeRejectModal()">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" wire:click="closeRejectModal"></div>

                <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                    <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white">
                            <i class="fas fa-times-circle mr-2"></i>Recusar pedido
                        </h3>
                        <button wire:click="closeRejectModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm">
                            <p class="font-semibold text-gray-900">{{ $rejectingOrder->tenant?->name ?? '— empresa apagada —' }}</p>
                            <p class="text-gray-600">
                                {{ $rejectingOrder->plan?->name ?? '—' }} ·
                                {{ \App\Livewire\SuperAdmin\Billing::nomeDoCiclo($rejectingOrder->billing_cycle) }} ·
                                {{ number_format($rejectingOrder->amount, 2, ',', '.') }} Kz
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Motivo da recusa <span class="text-red-600">*</span>
                            </label>
                            <textarea wire:model="rejectionReason" rows="4"
                                      class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition"
                                      placeholder="Ex: O comprovativo anexado é de uma transferência de 24.900 Kz e o plano custa 44.900 Kz."></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-circle-info mr-1"></i>É isto que o cliente vai ler no email.
                            </p>
                            @error('rejectionReason')
                                <span class="text-red-600 text-xs mt-1 block">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeRejectModal"
                                class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="button" wire:click="rejectOrder" wire:loading.attr="disabled"
                                class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="rejectOrder">
                                <i class="fas fa-times mr-2"></i>Recusar e avisar o cliente
                            </span>
                            <span wire:loading wire:target="rejectOrder">
                                <i class="fas fa-spinner fa-spin mr-2"></i>A enviar...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

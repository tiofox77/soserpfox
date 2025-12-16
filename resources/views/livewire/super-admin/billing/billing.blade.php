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
                                            <h4 class="font-bold text-gray-900">{{ $order->tenant->name }}</h4>
                                            <p class="text-xs text-gray-600">{{ $order->user->name }} • {{ $order->user->email }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-4 gap-4 mb-3">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Plano</p>
                                            <p class="font-semibold text-gray-900">{{ $order->plan->name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Valor</p>
                                            <p class="font-semibold text-gray-900">{{ number_format($order->amount, 2) }} Kz</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Ciclo</p>
                                            <p class="font-semibold text-gray-900">{{ $order->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }}</p>
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
                                    <button wire:click="rejectOrder({{ $order->id }})"
                                            wire:confirm="Rejeitar este pedido? O cliente receberá um email de notificação."
                                            wire:loading.attr="disabled"
                                            wire:target="rejectOrder({{ $order->id }})"
                                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition disabled:opacity-50">
                                        <span wire:loading.remove wire:target="rejectOrder({{ $order->id }})">
                                            <i class="fas fa-times mr-1"></i>Rejeitar
                                        </span>
                                        <span wire:loading wire:target="rejectOrder({{ $order->id }})">
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
                    
                    <div class="text-sm text-gray-600 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-purple-500"></i>
                        {{ $subscriptions->count() }} subscription(s)
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
                                            <h4 class="font-bold text-gray-900">{{ $subscription->tenant->name }}</h4>
                                            <p class="text-sm text-gray-600">Plano: <span class="font-semibold text-purple-600">{{ $subscription->plan->name }}</span></p>
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
                                        <p class="font-semibold text-gray-900">{{ $subscription->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }}</p>
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
                                            $daysLeft = $subscription->current_period_end ? $subscription->current_period_end->diffInDays(now(), false) : null;
                                        @endphp
                                        <p class="font-semibold {{ $daysLeft && $daysLeft > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $daysLeft !== null ? abs($daysLeft) . ' dias' : 'N/A' }}
                                        </p>
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
                                                <p class="text-lg font-bold text-green-700">{{ number_format($subscription->plan->max_storage_mb / 1000, 1) }} GB</p>
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
                                            @foreach($subscription->plan->modules as $module)
                                                <div class="flex items-center px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                                                    <i class="fas fa-{{ $module->icon }} text-blue-600 mr-2"></i>
                                                    <span class="font-medium text-blue-700">{{ $module->name }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

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
                        <input wire:model.live="search" type="text" placeholder="Pesquisar faturas..." 
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
                                        <button wire:click="markAsPaid({{ $invoice->id }})" class="px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition">
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
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
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
                    
                    <form wire:submit.prevent="save" class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tenant *</label>
                                <select wire:model="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione um tenant</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('tenant_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nº Fatura *</label>
                                <input wire:model="invoice_number" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                @error('invoice_number') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="pending">Pendente</option>
                                    <option value="paid">Pago</option>
                                    <option value="overdue">Atrasado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition">
                                Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                {{ $editingInvoiceId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div>
    <!-- Header with Gradient -->
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Faturação</h2>
                    <p class="text-orange-100 text-sm">Gerir faturas e pagamentos</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <button wire:click="createSubscription" class="bg-white text-orange-600 hover:bg-orange-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-crown mr-2"></i>Nova Subscrição
                </button>
                <button wire:click="create" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Nova Fatura
                </button>
            </div>
        </div>
    </div>

    <!-- Pedidos Pendentes -->
    @if($pendingOrders->count() > 0)
    <div class="mb-6 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl shadow-xl p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-bell text-2xl animate-pulse"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold">{{ $pendingOrders->count() }} Pedidos Pendentes</h3>
                    <p class="text-yellow-100 text-sm">Aguardando aprovação</p>
                </div>
            </div>
        </div>
        
        <div class="space-y-3">
            @foreach($pendingOrders as $order)
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-building text-orange-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white">{{ $order->tenant->name }}</h4>
                                    <p class="text-xs text-yellow-100">{{ $order->user->name }} • {{ $order->user->email }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p class="text-yellow-100 text-xs">Plano</p>
                                    <p class="font-semibold">{{ $order->plan->name }}</p>
                                </div>
                                <div>
                                    <p class="text-yellow-100 text-xs">Valor</p>
                                    <p class="font-semibold">{{ number_format($order->amount, 2) }} Kz</p>
                                </div>
                                <div>
                                    <p class="text-yellow-100 text-xs">Referência</p>
                                    <p class="font-semibold">{{ $order->payment_reference ?? 'N/A' }}</p>
                                </div>
                            </div>
                            
                            @if($order->payment_proof)
                                <div class="mt-2">
                                    <a href="{{ Storage::url($order->payment_proof) }}" target="_blank" class="inline-flex items-center text-xs bg-white/20 px-3 py-1 rounded-full hover:bg-white/30 transition">
                                        <i class="fas fa-file-download mr-1"></i>Ver Comprovativo
                                    </a>
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex space-x-2 ml-4">
                            <button wire:click="approveOrder({{ $order->id }})" 
                                    onclick="return confirm('Aprovar este pedido? O cliente receberá um email de confirmação.')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    wire:target="approveOrder({{ $order->id }})"
                                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="approveOrder({{ $order->id }})">
                                    <i class="fas fa-check mr-1"></i>Aprovar
                                </span>
                                <span wire:loading wire:target="approveOrder({{ $order->id }})">
                                    <i class="fas fa-spinner fa-spin mr-1"></i>Enviando email...
                                </span>
                            </button>
                            <button wire:click="rejectOrder({{ $order->id }})"
                                    onclick="return confirm('Rejeitar este pedido? O cliente receberá um email de notificação.')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    wire:target="rejectOrder({{ $order->id }})"
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="rejectOrder({{ $order->id }})">
                                    <i class="fas fa-times mr-1"></i>Rejeitar
                                </span>
                                <span wire:loading wire:target="rejectOrder({{ $order->id }})">
                                    <i class="fas fa-spinner fa-spin mr-1"></i>Enviando email...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="absolute top-0 right-0 w-32 h-32 bg-green-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float gradient-shift">
                        <i class="fas fa-check-circle text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-green-600 font-semibold mb-2">Receita Total</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ number_format($totalRevenue, 2) }} Kz</p>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-arrow-up text-green-500 mr-1 animate-bounce"></i>
                    Faturas pagas
                </p>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-glow">
            <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float gradient-shift">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-orange-600 font-semibold mb-2">Pendente</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ number_format($pendingRevenue, 2) }} Kz</p>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-hourglass-half text-yellow-500 mr-1 animate-spin"></i>
                    Aguardando pagamento
                </p>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-zoom">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float gradient-shift">
                        <i class="fas fa-file-invoice text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-blue-600 font-semibold mb-2">Total Faturas</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $invoices->total() }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-file-alt text-blue-500 mr-1"></i>
                    Todas as faturas
                </p>
            </div>
        </div>
    </div>

    <!-- Subscriptions List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-purple-50 border-b border-blue-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-crown mr-2 text-purple-600"></i>
                    Subscrições Ativas ({{ $subscriptions->count() }})
                </h3>
            </div>
        </div>
        
        <div class="divide-y divide-gray-100 stagger-animation">
            @forelse($subscriptions as $subscription)
                <div class="group p-6 hover:bg-blue-50 transition-all duration-300 card-hover">
                    <div class="flex items-start space-x-4">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $subscription->status === 'active' ? 'from-blue-500 to-purple-600' : 'from-gray-400 to-gray-600' }} flex items-center justify-center shadow-lg group-hover:shadow-2xl transition-all duration-300 flex-shrink-0 icon-float">
                            <i class="fas fa-crown text-white text-lg"></i>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $subscription->tenant->name }}</h4>
                                    <p class="text-sm text-gray-500">Plano: <span class="font-semibold text-purple-600">{{ $subscription->plan->name }}</span></p>
                                </div>
                                
                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full 
                                    {{ $subscription->status === 'active' ? 'bg-green-100 text-green-700' : 
                                       ($subscription->status === 'trial' ? 'bg-blue-100 text-blue-700' : 
                                       ($subscription->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700')) }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $subscription->status === 'active' ? 'bg-green-500' : ($subscription->status === 'trial' ? 'bg-blue-500' : ($subscription->status === 'cancelled' ? 'bg-red-500' : 'bg-yellow-500')) }} mr-1.5 animate-pulse"></span>
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </div>
                            
                            <!-- Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-money-bill-wave text-purple-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Valor</p>
                                        <p class="text-sm text-gray-900 font-bold">{{ number_format($subscription->amount, 2) }} Kz</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-sync text-blue-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Ciclo</p>
                                        <p class="text-sm text-gray-900">{{ $subscription->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar-check text-green-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Início</p>
                                        <p class="text-sm text-gray-900">{{ $subscription->current_period_start?->format('d/m/Y') ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar-times text-orange-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Renovação</p>
                                        <p class="text-sm text-gray-900">{{ $subscription->current_period_end?->format('d/m/Y') ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modules -->
                            @if($subscription->plan->modules->count() > 0)
                            <div class="mb-3">
                                <p class="text-xs text-gray-500 font-medium mb-2">Módulos:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($subscription->plan->modules as $module)
                                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-medium">
                                            <i class="fas fa-{{ $module->icon }} mr-1"></i>
                                            {{ $module->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            
                            <!-- Actions -->
                            <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="viewSubscription({{ $subscription->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition-all shadow-md hover:shadow-lg">
                                    <i class="fas fa-eye mr-1"></i>Detalhes
                                </button>
                                
                                @if($subscription->status === 'active')
                                <button wire:click="cancelSubscription({{ $subscription->id }})" class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-xs font-semibold transition-all shadow-md hover:shadow-lg">
                                    <i class="fas fa-pause mr-1"></i>Cancelar
                                </button>
                                @endif
                                
                                <button wire:click="deleteSubscription({{ $subscription->id }})" wire:confirm="Tem certeza que deseja excluir esta subscrição?" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition-all shadow-md hover:shadow-lg">
                                    <i class="fas fa-trash mr-1"></i>Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-crown text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma subscrição encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova subscrição para começar</p>
                    <button wire:click="createSubscription" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-plus mr-2"></i>Nova Subscrição
                    </button>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live="search" type="text" placeholder="Pesquisar faturas..." 
                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
            </div>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-filter text-gray-400"></i>
                </div>
                <select wire:model.live="statusFilter" class="pl-11 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 appearance-none bg-white">
                    <option value="">Todos os status</option>
                    <option value="paid">Pago</option>
                    <option value="pending">Pendente</option>
                    <option value="overdue">Atrasado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Invoices List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-receipt mr-2 text-orange-600"></i>
                    Lista de Faturas ({{ $invoices->total() }})
                </h3>
            </div>
        </div>
        
        <div class="divide-y divide-gray-100 stagger-animation">
            @forelse($invoices as $invoice)
                <div class="group p-6 hover:bg-gray-50 transition-all duration-300 card-hover cursor-pointer">
                    <div class="flex items-start space-x-4">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $invoice->status === 'paid' ? 'from-green-400 to-green-600' : ($invoice->status === 'pending' ? 'from-yellow-400 to-orange-600' : 'from-red-400 to-red-600') }} flex items-center justify-center shadow-lg group-hover:shadow-2xl transition-all duration-300 flex-shrink-0 icon-float gradient-shift">
                            <i class="fas fa-file-invoice text-white text-lg"></i>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $invoice->invoice_number }}</h4>
                                    <p class="text-sm text-gray-500">{{ $invoice->description }}</p>
                                </div>
                                
                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full 
                                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : 
                                       ($invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                       ($invoice->status === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $invoice->status === 'paid' ? 'bg-green-500' : ($invoice->status === 'pending' ? 'bg-yellow-500' : ($invoice->status === 'overdue' ? 'bg-red-500' : 'bg-gray-500')) }} mr-1.5"></span>
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </div>
                            
                            <!-- Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-building text-blue-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Tenant</p>
                                        <p class="text-sm text-gray-900 truncate">{{ $invoice->tenant->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar text-green-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Data Emissão</p>
                                        <p class="text-sm text-gray-900">{{ $invoice->invoice_date->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar-times text-red-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Vencimento</p>
                                        <p class="text-sm text-gray-900">{{ $invoice->due_date->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-money-bill-wave text-orange-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Valor</p>
                                        <p class="text-lg font-bold text-gray-900">{{ number_format($invoice->total, 2) }} Kz</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="edit({{ $invoice->id }})" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                                    <i class="fas fa-edit mr-1.5"></i>Editar
                                </button>
                                <button class="inline-flex items-center px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition-colors">
                                    <i class="fas fa-download mr-1.5"></i>PDF
                                </button>
                                @if($invoice->status === 'pending')
                                    <button wire:click="markAsPaid({{ $invoice->id }})" class="inline-flex items-center px-3 py-1.5 bg-yellow-50 text-yellow-700 rounded-lg text-xs font-medium hover:bg-yellow-100 transition-colors">
                                        <i class="fas fa-check mr-1.5"></i>Marcar Paga
                                    </button>
                                @endif
                                <button wire:click="delete({{ $invoice->id }})" onclick="return confirm('Excluir esta fatura?')" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                                    <i class="fas fa-trash mr-1.5"></i>Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium text-lg">Nenhuma fatura encontrada</p>
                    <p class="text-gray-400 text-sm mt-1">Comece criando uma nova fatura</p>
                </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if($invoices->hasPages())
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    {{-- @include('livewire.super-admin.billing.partials.form-modal') --}}
    {{-- Modal temporariamente inline até criar arquivo parcial --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl transform transition-all" @click.stop>
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-orange-600 to-red-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center text-white">
                            <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file-invoice-dollar text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold">
                                {{ $editingInvoiceId ? 'Editar Fatura' : 'Nova Fatura' }}
                            </h3>
                        </div>
                        <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-building text-blue-500 mr-2"></i>Tenant *
                                </label>
                                <select wire:model="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                    <option value="">Selecione um tenant</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('tenant_id') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-purple-500 mr-2"></i>Nº Fatura *
                                </label>
                                <input wire:model="invoice_number" type="text" placeholder="INV-2025-001" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                @error('invoice_number') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-tag text-orange-500 mr-2"></i>Status *
                                </label>
                                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                    <option value="pending">Pendente</option>
                                    <option value="paid">Pago</option>
                                    <option value="overdue">Atrasado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                                @error('status') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
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
                                    <i class="fas fa-calendar-times text-red-500 mr-2"></i>Data Vencimento *
                                </label>
                                <input wire:model="due_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                                @error('due_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                                </label>
                                <textarea wire:model="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"></textarea>
                                @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill-wave text-blue-500 mr-2"></i>Subtotal *
                                </label>
                                <input wire:model="subtotal" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                @error('subtotal') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-percent text-purple-500 mr-2"></i>IVA/Taxa *
                                </label>
                                <input wire:model="tax" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                @error('tax') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calculator text-green-500 mr-2"></i>Total *
                                </label>
                                <input wire:model="total" type="number" step="0.01" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-gray-50 text-gray-700 font-bold text-lg">
                                @error('total') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl font-semibold hover:from-orange-700 hover:to-red-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingInvoiceId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingInvoiceId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Criar Subscrição -->
    @if($showSubscriptionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showSubscriptionModal') }" x-show="show" x-cloak>
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            
            <!-- Modal -->
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-crown mr-3"></i>Nova Subscrição
                            </h3>
                            <button wire:click="$set('showSubscriptionModal', false)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <form wire:submit.prevent="saveSubscription" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Selecionar Tenant -->
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-building text-purple-500 mr-2"></i>Tenant *
                                </label>
                                <select wire:model="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    <option value="">Selecione um tenant</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->email }})</option>
                                    @endforeach
                                </select>
                                @error('tenant_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <!-- Selecionar Plano -->
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-box text-blue-500 mr-2"></i>Plano *
                                </label>
                                <select wire:model.live="plan_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <option value="">Selecione um plano</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}">{{ $plan->name }} - {{ number_format($plan->price_monthly, 2) }} Kz/mês</option>
                                    @endforeach
                                </select>
                                @error('plan_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <!-- Ciclo de Faturação -->
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-sync text-green-500 mr-2"></i>Ciclo de Faturação *
                                </label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="flex items-center p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-blue-50 transition border-2 {{ $billing_cycle === 'monthly' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                                        <input wire:model="billing_cycle" type="radio" value="monthly" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-3">
                                            <span class="block text-sm font-bold text-gray-900">Mensal</span>
                                            @if($selectedPlan)
                                                <span class="block text-xs text-gray-600">{{ number_format($selectedPlan->price_monthly, 2) }} Kz/mês</span>
                                            @endif
                                        </span>
                                    </label>
                                    
                                    <label class="flex items-center p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-purple-50 transition border-2 {{ $billing_cycle === 'yearly' ? 'border-purple-500 bg-purple-50' : 'border-gray-200' }}">
                                        <input wire:model="billing_cycle" type="radio" value="yearly" class="w-5 h-5 text-purple-600 focus:ring-purple-500">
                                        <span class="ml-3">
                                            <span class="block text-sm font-bold text-gray-900">Anual</span>
                                            @if($selectedPlan)
                                                <span class="block text-xs text-gray-600">{{ number_format($selectedPlan->price_yearly, 2) }} Kz/ano</span>
                                                <span class="block text-xs text-green-600 font-semibold">Poupe {{ $selectedPlan->getYearlySavingsPercentage() }}%</span>
                                            @endif
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Módulos Incluídos -->
                            @if($selectedPlan && $selectedPlan->modules->count() > 0)
                            <div class="col-span-2 bg-blue-50 rounded-xl p-4 border border-blue-200">
                                <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center">
                                    <i class="fas fa-puzzle-piece mr-2"></i>Módulos Incluídos no Plano
                                </h4>
                                <div class="grid grid-cols-2 gap-3">
                                    @foreach($selectedPlan->modules as $module)
                                        <div class="flex items-center p-2 bg-white rounded-lg border border-blue-100">
                                            <i class="fas fa-{{ $module->icon }} text-blue-600 mr-2"></i>
                                            <div class="flex-1">
                                                <span class="block text-sm font-semibold text-gray-900">{{ $module->name }}</span>
                                                <span class="block text-xs text-gray-500">{{ $module->description }}</span>
                                            </div>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="$set('showSubscriptionModal', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas fa-check mr-2"></i>Criar Subscrição
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- TODO: Criar arquivos parciais:
        @include('livewire.super-admin.billing.partials.form-modal')
        @include('livewire.super-admin.billing.partials.delete-modal')
        @include('livewire.super-admin.billing.partials.view-modal')
    --}}
</div>

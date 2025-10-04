<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Faturas</h2>
                    <p class="text-blue-100 text-sm">Gerir faturas e documentos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Fatura
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 stagger-animation">
        <!-- Total Faturas -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-file-invoice text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Total Faturas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $invoices->total() }}</p>
            <p class="text-xs text-gray-500">Todas as faturas</p>
        </div>

        <!-- Receita Total -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Receita Total</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ number_format(\App\Models\InvoicingInvoice::where('tenant_id', auth()->user()->tenant_id)->where('status', 'paid')->sum('total'), 2) }} Kz</p>
            <p class="text-xs text-gray-500">Faturas pagas</p>
        </div>

        <!-- Pendente -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Pendente</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ number_format(\App\Models\InvoicingInvoice::where('tenant_id', auth()->user()->tenant_id)->whereIn('status', ['draft', 'sent'])->sum('total'), 2) }} Kz</p>
            <p class="text-xs text-gray-500">Aguardando pagamento</p>
        </div>

        <!-- Este Mês -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-rotate">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-calendar-alt text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Este Mês</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\InvoicingInvoice::where('tenant_id', auth()->user()->tenant_id)->whereMonth('invoice_date', now()->month)->count() }}</p>
            <p class="text-xs text-gray-500">Faturas emitidas</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-blue-600"></i>
                Filtros Avançados
            </h3>
            <button wire:click="clearFilters" class="text-sm text-blue-600 hover:text-blue-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Número fatura, cliente, NIF..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-flag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    <option value="draft">Rascunho</option>
                    <option value="sent">Enviada</option>
                    <option value="paid">Paga</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>

            <!-- Client Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user mr-1"></i>Cliente
                </label>
                <select wire:model.live="clientFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Data da Fatura (De)
                </label>
                <input wire:model.live="dateFrom" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Data da Fatura (Até)
                </label>
                <input wire:model.live="dateTo" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($search || $statusFilter || $clientFilter || $dateFrom || $dateTo)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold text-gray-600">Filtros ativos:</span>
                    @if($search)
                        <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-search mr-1"></i>{{ $search }}
                            <button wire:click="$set('search', '')" class="ml-2 hover:text-blue-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($statusFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-flag mr-1"></i>{{ ucfirst($statusFilter) }}
                            <button wire:click="$set('statusFilter', '')" class="ml-2 hover:text-purple-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($clientFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-user mr-1"></i>{{ $clients->firstWhere('id', $clientFilter)?->name }}
                            <button wire:click="$set('clientFilter', '')" class="ml-2 hover:text-green-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateFrom)
                        <span class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>De: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                            <button wire:click="$set('dateFrom', '')" class="ml-2 hover:text-orange-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateTo)
                        <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>Até: {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                            <button wire:click="$set('dateTo', '')" class="ml-2 hover:text-red-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Lista de Faturas
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-file-invoice mr-1"></i>{{ $invoices->total() }} Total Faturas
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-3 flex items-center">
                <i class="fas fa-file-invoice mr-2 text-blue-500"></i>Fatura
            </div>
            <div class="col-span-3 flex items-center">
                <i class="fas fa-user mr-2 text-purple-500"></i>Cliente
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-calendar mr-2 text-green-500"></i>Data
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-money-bill-wave mr-2 text-orange-500"></i>Valor
            </div>
            <div class="col-span-1 flex items-center">
                <i class="fas fa-flag mr-2 text-gray-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($invoices as $invoice)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-blue-50 transition-all duration-300 items-center">
                    <!-- Fatura -->
                    <div class="col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br {{ $invoice->status === 'paid' ? 'from-green-500 to-emerald-600' : ($invoice->status === 'sent' ? 'from-blue-500 to-cyan-600' : ($invoice->status === 'cancelled' ? 'from-red-500 to-red-600' : 'from-gray-500 to-gray-600')) }} flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            <i class="fas fa-file-invoice text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs text-gray-500">ID: {{ $invoice->id }}</p>
                        </div>
                    </div>
                    
                    <!-- Cliente -->
                    <div class="col-span-3">
                        <p class="font-semibold text-gray-900 truncate">{{ $invoice->client->name ?? 'N/A' }}</p>
                        @if($invoice->client)
                            <p class="text-xs text-gray-500 flex items-center">
                                <i class="fas fa-id-card mr-1"></i>{{ $invoice->client->nif }}
                            </p>
                        @endif
                    </div>
                    
                    <!-- Data -->
                    <div class="col-span-2">
                        <p class="text-sm text-gray-700 flex items-center mb-0.5">
                            <i class="fas fa-calendar-day text-green-500 mr-1.5"></i>
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </p>
                        <p class="text-xs text-gray-500 flex items-center">
                            <i class="fas fa-calendar-times text-red-500 mr-1.5"></i>
                            Vcto: {{ $invoice->due_date->format('d/m/Y') }}
                        </p>
                    </div>
                    
                    <!-- Valor -->
                    <div class="col-span-2">
                        <p class="text-sm font-bold text-gray-900">{{ number_format($invoice->total, 2) }} Kz</p>
                        <p class="text-xs text-gray-500">IVA: {{ number_format($invoice->iva_amount, 2) }} Kz</p>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-span-1">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold
                            {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : 
                               ($invoice->status === 'sent' ? 'bg-blue-100 text-blue-700' : 
                               ($invoice->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $invoice->status === 'paid' ? 'bg-green-500' : ($invoice->status === 'sent' ? 'bg-blue-500' : ($invoice->status === 'cancelled' ? 'bg-red-500' : 'bg-gray-500')) }} mr-1"></span>
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $invoice->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $invoice->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma fatura encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova fatura para começar</p>
                </div>
            @endforelse
        </div>

        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.invoices.partials.form-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingInvoiceName" 
        entityType="a fatura" 
        icon="fa-file-invoice" 
    />
</div>

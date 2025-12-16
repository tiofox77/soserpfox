<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Gestão de Stock</h2>
        <p class="text-gray-600">Controle de inventário por armazém</p>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-sm font-medium">Total de Produtos</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['total_products'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-box text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-sm font-medium">Quantidade Total</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($stats['total_quantity'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-cubes text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-sm font-medium">Valor Total</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($stats['total_value'], 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-sm font-medium">Stock Baixo</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['low_stock'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar produto..." 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <select wire:model.live="warehouseFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos os Armazéns</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex items-center px-4 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" wire:model.live="lowStockFilter" class="rounded text-red-600 mr-2">
                    <span class="text-sm">Apenas Stock Baixo</span>
                </label>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas fa-boxes mr-2"></i>
                Lista de Stock por Produto
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-box mr-1 text-purple-600"></i>Produto
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-warehouse mr-1 text-blue-600"></i>Armazém
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-cubes mr-1 text-gray-600"></i>Quantidade
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-check-circle mr-1 text-green-600"></i>Disponível
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-lock mr-1 text-orange-600"></i>Reservado
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-dollar-sign mr-1 text-purple-600"></i>Custo Médio
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-cog mr-1 text-gray-600"></i>Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($stocks as $stock)
                    <tr class="hover:bg-purple-50 transition-all duration-200 ease-in-out transform hover:scale-[1.01] hover:shadow-md">
                        <!-- Product -->
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                @if($stock->product->image_url)
                                    <img src="{{ $stock->product->image_url }}" 
                                         class="h-12 w-12 rounded-xl object-cover shadow-md ring-2 ring-purple-200"
                                         loading="lazy"
                                         onerror="this.src='{{ asset('images/placeholder-product.png') }}'">
                                @else
                                    <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-md">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                @endif
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900">{{ $stock->product->name }}</div>
                                    <div class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-barcode mr-1"></i>
                                        {{ $stock->product->code }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Warehouse -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">
                                <i class="fas fa-warehouse mr-1.5"></i>
                                {{ $stock->warehouse->name }}
                            </span>
                        </td>
                        
                        <!-- Quantity -->
                        <td class="px-6 py-4 text-center">
                            @php
                                $percentage = $stock->product->stock_min > 0 
                                    ? ($stock->quantity / $stock->product->stock_min) * 100 
                                    : 100;
                                $isLowStock = $stock->quantity <= $stock->product->stock_min;
                            @endphp
                            
                            <div class="flex flex-col items-center">
                                <span class="text-2xl font-bold {{ $isLowStock ? 'text-red-600 animate-pulse' : 'text-gray-900' }}">
                                    {{ number_format($stock->quantity, 0) }}
                                </span>
                                <div class="text-xs text-gray-500 flex items-center mt-1">
                                    <i class="fas fa-balance-scale mr-1"></i>
                                    {{ $stock->product->unit }}
                                </div>
                                @if($isLowStock)
                                    <span class="mt-1 px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded-full animate-bounce">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Baixo
                                    </span>
                                @endif
                            </div>
                        </td>
                        
                        <!-- Available -->
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-col items-center">
                                <span class="text-lg font-bold text-green-600">
                                    {{ number_format($stock->available_quantity, 0) }}
                                </span>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2 max-w-[80px]">
                                    @php
                                        $availablePercent = $stock->quantity > 0 
                                            ? ($stock->available_quantity / $stock->quantity) * 100 
                                            : 0;
                                    @endphp
                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-300" 
                                         style="width: {{ min($availablePercent, 100) }}%"></div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Reserved -->
                        <td class="px-6 py-4 text-center">
                            @if($stock->reserved_quantity > 0)
                                <span class="inline-flex items-center px-3 py-1.5 bg-orange-100 text-orange-800 text-sm font-bold rounded-full">
                                    <i class="fas fa-lock mr-1.5"></i>
                                    {{ number_format($stock->reserved_quantity, 0) }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">
                                    <i class="fas fa-minus-circle"></i>
                                </span>
                            @endif
                        </td>
                        
                        <!-- Cost -->
                        <td class="px-6 py-4 text-center">
                            @if($stock->unit_cost)
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-bold text-purple-600">{{ number_format($stock->unit_cost, 2) }} Kz</span>
                                    <span class="text-xs text-gray-500 mt-0.5">
                                        Total: {{ number_format($stock->quantity * $stock->unit_cost, 2) }} Kz
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button 
                                    wire:click="openAdjustModal({{ $stock->id }})" 
                                    class="group relative p-2 bg-blue-100 hover:bg-blue-600 rounded-lg transition-all duration-200 transform hover:scale-110"
                                    title="Ajustar Stock">
                                    <i class="fas fa-edit text-blue-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        Ajustar
                                    </span>
                                </button>
                                
                                <button 
                                    wire:click="openTransferModal({{ $stock->id }})" 
                                    class="group relative p-2 bg-purple-100 hover:bg-purple-600 rounded-lg transition-all duration-200 transform hover:scale-110"
                                    title="Transferir">
                                    <i class="fas fa-exchange-alt text-purple-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        Transferir
                                    </span>
                                </button>
                                
                                <button 
                                    wire:click="showMovements({{ $stock->product_id }}, '{{ $stock->product->name }}')" 
                                    class="group relative p-2 bg-green-100 hover:bg-green-600 rounded-lg transition-all duration-200 transform hover:scale-110"
                                    title="Ver Movimentos">
                                    <i class="fas fa-history text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        Histórico
                                    </span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center animate-pulse">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-boxes text-gray-300 text-4xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold">Nenhum stock encontrado</p>
                                <p class="text-gray-400 text-sm mt-2">Tente ajustar os filtros ou adicione produtos ao inventário</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $stocks->links() }}
        </div>
    </div>

    {{-- Modals --}}
    @include('livewire.invoicing.stock.partials.adjust-modal')
    @include('livewire.invoicing.stock.partials.transfer-modal')
    @include('livewire.invoicing.stock.partials.movements-modal')
</div>

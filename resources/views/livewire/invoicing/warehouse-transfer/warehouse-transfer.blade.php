<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-exchange-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Transferências e Ajustes de Stock</h2>
                    <p class="text-purple-100 text-sm">Gerir movimentações de stock entre armazéns</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <button wire:click="openTransferModal" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-exchange-alt mr-2"></i>Nova Transferência
                </button>
                <button wire:click="openAdjustModal" class="bg-yellow-500 text-white hover:bg-yellow-600 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-sliders-h mr-2"></i>Ajustar Stock
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filtros
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">Pesquisar</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition"
                       placeholder="Produto...">
            </div>

            <!-- Warehouse -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">Armazém</label>
                <select wire:model.live="warehouseFilter" 
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                    <option value="">Todos</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">Data Início</label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">Data Fim</label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
            </div>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-indigo-50 border-b-2 border-purple-100">
            <h3 class="text-lg font-bold text-purple-900 flex items-center">
                <i class="fas fa-history mr-2"></i>
                Histórico de Movimentações
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Armazém</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Observações</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Usuário</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($movements as $movement)
                    <tr class="hover:bg-purple-50/50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $movement->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($movement->type == 'transfer')
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-exchange-alt mr-1"></i>Transferência
                                </span>
                            @else
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-sliders-h mr-1"></i>Ajuste
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900">
                                @if($movement->products_count > 1)
                                    {{ $movement->products_count }} produto(s)
                                @else
                                    {{ $movement->product->name ?? 'N/A' }}
                                @endif
                            </div>
                            @if($movement->reference_id)
                                <button 
                                    x-data 
                                    @click="$wire.selectedBatchId = {{ $movement->reference_id }}; $wire.openDetailsModal()"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-semibold cursor-pointer">
                                    <i class="fas fa-eye mr-1"></i>Ver detalhes
                                </button>
                            @else
                                <span class="text-xs text-gray-400 italic">Transferência antiga</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ $movement->warehouse->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-lg font-bold text-purple-600">
                                {{ number_format($movement->total_quantity, 2) }}
                            </span>
                            <div class="text-xs text-gray-500">total</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $movement->notes }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ $movement->user->name ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-history text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold mb-2">Nenhuma movimentação encontrada</p>
                                <p class="text-gray-400 text-sm">Use os botões acima para criar transferências ou ajustes</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $movements->links() }}
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.warehouse-transfer.partials.transfer-modal')
    @include('livewire.invoicing.warehouse-transfer.partials.quantity-modal')
    @include('livewire.invoicing.warehouse-transfer.partials.adjust-modal')
    @include('livewire.invoicing.warehouse-transfer.partials.adjust-quantity-modal')
    @include('livewire.invoicing.warehouse-transfer.partials.transfer-details-modal')
    @include('livewire.invoicing.warehouse-transfer.partials.adjust-details-modal')
</div>

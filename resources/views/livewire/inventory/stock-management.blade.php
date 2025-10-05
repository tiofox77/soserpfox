<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-boxes mr-3 text-indigo-600"></i>
                    Gestão de Stock
                </h2>
                <p class="text-gray-600 mt-1">Controle de inventário em tempo real</p>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-200 text-xs font-medium">Total Produtos</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total_products'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-box text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Stock Baixo</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['low_stock'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs font-medium">Sem Stock</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['out_of_stock'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_value'], 2) }}</p>
                    <p class="text-green-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" wire:model.live="search" placeholder="Pesquisar produto..." 
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select wire:model.live="filterWarehouse" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Armazéns</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="filterLowStock" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                    <span class="text-sm font-medium text-gray-700">Apenas Stock Baixo</span>
                </label>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-indigo-50 to-purple-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-indigo-700 uppercase">Produto</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-indigo-700 uppercase">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-indigo-700 uppercase">Armazém</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-indigo-700 uppercase">Quantidade</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-indigo-700 uppercase">Mín/Máx</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-indigo-700 uppercase">Custo Unit.</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-indigo-700 uppercase">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-indigo-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-indigo-700 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stocks as $stock)
                        <tr class="hover:bg-indigo-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($stock->product->featured_image)
                                        <img src="{{ Storage::url($stock->product->featured_image) }}" 
                                             class="w-10 h-10 rounded object-cover mr-3" 
                                             alt="{{ $stock->product->name }}">
                                    @else
                                        <div class="w-10 h-10 bg-indigo-100 rounded flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-indigo-600"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $stock->product->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $stock->product->category->name ?? 'Sem categoria' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded font-mono">
                                    {{ $stock->product->sku }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $stock->warehouse->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-lg font-bold text-indigo-600">{{ number_format($stock->quantity, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ $stock->product->unit }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-600">
                                {{ number_format($stock->product->stock_min ?? 0, 0) }} / 
                                {{ number_format($stock->product->stock_max ?? 0, 0) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                {{ number_format($stock->product->cost, 2) }} AOA
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-sm font-bold text-gray-900">
                                    {{ number_format($stock->quantity * $stock->product->cost, 2) }} AOA
                                </p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($stock->quantity <= 0)
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                        <i class="fas fa-times-circle mr-1"></i>Sem Stock
                                    </span>
                                @elseif($stock->quantity <= ($stock->product->stock_min ?? 0))
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Stock Baixo
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        <i class="fas fa-check-circle mr-1"></i>OK
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="openAdjustModal({{ $stock->id }})" 
                                        class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <i class="fas fa-boxes text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhum produto em stock</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($stocks->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $stocks->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Ajuste de Stock --}}
    @if($showAdjustModal && $adjustingStock)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-edit mr-2"></i>Ajustar Stock
                </h3>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Info do Produto --}}
                <div class="bg-indigo-50 rounded-xl p-4">
                    <p class="text-sm font-bold text-indigo-900">{{ $adjustingStock->product->name }}</p>
                    <p class="text-xs text-indigo-700">SKU: {{ $adjustingStock->product->sku }}</p>
                    <p class="text-xs text-indigo-700">Armazém: {{ $adjustingStock->warehouse->name }}</p>
                    <p class="text-lg font-bold text-indigo-600 mt-2">
                        Stock Atual: {{ number_format($adjustingStock->quantity, 2) }} {{ $adjustingStock->product->unit }}
                    </p>
                </div>

                {{-- Tipo de Ajuste --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Ajuste</label>
                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" 
                                wire:click="$set('adjustType', 'in')"
                                class="p-4 rounded-lg border-2 transition {{ $adjustType === 'in' ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                            <i class="fas fa-plus-circle text-2xl {{ $adjustType === 'in' ? 'text-green-600' : 'text-gray-400' }}"></i>
                            <p class="font-semibold mt-2 {{ $adjustType === 'in' ? 'text-green-700' : 'text-gray-600' }}">Entrada</p>
                        </button>
                        <button type="button" 
                                wire:click="$set('adjustType', 'out')"
                                class="p-4 rounded-lg border-2 transition {{ $adjustType === 'out' ? 'border-red-500 bg-red-50' : 'border-gray-200' }}">
                            <i class="fas fa-minus-circle text-2xl {{ $adjustType === 'out' ? 'text-red-600' : 'text-gray-400' }}"></i>
                            <p class="font-semibold mt-2 {{ $adjustType === 'out' ? 'text-red-700' : 'text-gray-600' }}">Saída</p>
                        </button>
                    </div>
                </div>

                {{-- Quantidade --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                    <input type="number" wire:model="adjustQuantity" step="0.01" min="0"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('adjustQuantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Motivo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo *</label>
                    <select wire:model="adjustReason" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione...</option>
                        <option value="Inventário Físico">Inventário Físico</option>
                        <option value="Correção de Erro">Correção de Erro</option>
                        <option value="Produto Danificado">Produto Danificado</option>
                        <option value="Produto Perdido">Produto Perdido</option>
                        <option value="Devolução Fornecedor">Devolução Fornecedor</option>
                        <option value="Amostra/Brinde">Amostra/Brinde</option>
                        <option value="Outro">Outro</option>
                    </select>
                    @error('adjustReason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Observações --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                    <textarea wire:model="adjustNotes" rows="3" 
                              class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                {{-- Preview --}}
                @if($adjustQuantity > 0)
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Preview:</p>
                    <p class="text-sm text-gray-600">
                        Stock Atual: <span class="font-bold">{{ number_format($adjustingStock->quantity, 2) }}</span>
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ $adjustType === 'in' ? 'Adicionar' : 'Remover' }}: 
                        <span class="font-bold {{ $adjustType === 'in' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $adjustType === 'in' ? '+' : '-' }}{{ number_format($adjustQuantity, 2) }}
                        </span>
                    </p>
                    <p class="text-lg font-bold {{ $adjustType === 'in' ? 'text-green-600' : 'text-red-600' }} mt-2">
                        Novo Stock: 
                        {{ number_format($adjustType === 'in' ? ($adjustingStock->quantity + $adjustQuantity) : ($adjustingStock->quantity - $adjustQuantity), 2) }}
                    </p>
                </div>
                @endif
            </div>
            
            <div class="px-6 pb-6 flex gap-3">
                <button wire:click="closeAdjustModal" 
                        class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancelar
                </button>
                <button wire:click="saveAdjustment" 
                        class="flex-1 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-save mr-2"></i>Salvar Ajuste
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

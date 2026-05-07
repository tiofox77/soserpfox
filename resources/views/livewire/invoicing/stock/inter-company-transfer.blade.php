<div>
    {{-- Toast Notifications --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:notify.window="show = true; message = $event.detail[0]?.message || $event.detail.message; type = $event.detail[0]?.type || $event.detail.type; setTimeout(() => show = false, 4000)"
         x-show="show" x-transition
         x-cloak
         class="fixed top-4 right-4 z-[100] max-w-sm">
        <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-xl shadow-2xl flex items-center">
            <i :class="type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'" class="mr-3 text-lg"></i>
            <span x-text="message" class="font-semibold text-sm"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-building text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Transferência Entre Empresas</h2>
                    <p class="text-purple-100 text-sm">Transfira stock entre as suas empresas</p>
                </div>
            </div>
            <button wire:click="openModal" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-exchange-alt mr-2"></i>Nova Transferência
            </button>
        </div>
    </div>

    {{-- Info Alert --}}
    <div class="mb-6 bg-blue-50 border-2 border-blue-200 rounded-2xl p-4">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-blue-500 text-lg mr-3"></i>
            <p class="text-sm text-blue-700">
                <strong>Transferência Inter-Empresas:</strong> O stock será removido da empresa origem e adicionado à empresa destino. Apenas empresas que você gerencia são listadas.
            </p>
        </div>
    </div>

    {{-- Transfer History --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b-2 border-purple-100">
            <h3 class="text-lg font-bold text-purple-900 flex items-center">
                <i class="fas fa-history mr-2"></i>Histórico de Transferências
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Origem</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Destino</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Observações</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Usuário</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($transfers as $transfer)
                    @php
                        $isOutgoing = $transfer->type === 'transfer';
                    @endphp
                    <tr class="hover:bg-purple-50/50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $transfer->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($isOutgoing)
                                <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-arrow-up mr-1"></i>Enviado
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-arrow-down mr-1"></i>Recebido
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900">{{ $transfer->product->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ $transfer->product->code ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($isOutgoing)
                                <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-warehouse mr-1"></i>{{ $transfer->warehouse->name ?? 'N/A' }}
                                </span>
                            @else
                                @if($transfer->fromWarehouse)
                                    <span class="px-3 py-1 text-xs font-bold bg-orange-100 text-orange-800 rounded-full">
                                        <i class="fas fa-warehouse mr-1"></i>{{ $transfer->fromWarehouse->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm italic">Empresa externa</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($isOutgoing)
                                @if($transfer->toWarehouse)
                                    <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-warehouse mr-1"></i>{{ $transfer->toWarehouse->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm">-</span>
                                @endif
                            @else
                                <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-warehouse mr-1"></i>{{ $transfer->warehouse->name ?? 'N/A' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-lg font-bold {{ $isOutgoing ? 'text-red-600' : 'text-green-600' }}">
                                {{ $isOutgoing ? '-' : '+' }}{{ number_format(abs($transfer->quantity), 2) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                            {{ $transfer->notes }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $transfer->user->name ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-building text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold mb-2">Nenhuma transferência realizada</p>
                                <p class="text-gray-400 text-sm">Use o botão acima para transferir stock entre empresas</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $transfers->links() }}
        </div>
    </div>

    {{-- Modal Transfer --}}
    @if($showTransferModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-start justify-center p-4 pt-10">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto transform transition-all">
            {{-- Header --}}
            <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-6 rounded-t-2xl flex items-center justify-between z-10">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-building text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white">Nova Transferência Inter-Empresas</h3>
                        <p class="text-purple-100 text-sm">Selecione armazéns, adicione produtos e confirme</p>
                    </div>
                </div>
                <button wire:click="$set('showTransferModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-8 space-y-6">
                {{-- Step 1: Armazéns --}}
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">1</div>
                        <div>
                            <h4 class="font-bold text-purple-900 text-lg">Origem e Destino</h4>
                            <p class="text-sm text-purple-700">Selecione os armazéns e a empresa destino</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Armazém Origem --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-red-600"></i>Armazém Origem *
                            </label>
                            <select wire:model.live="warehouseFromId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition font-semibold">
                                <option value="">Selecione...</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Empresa Destino --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-building mr-1 text-green-600"></i>Empresa Destino *
                            </label>
                            <select wire:model.live="tenantToId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition font-semibold">
                                <option value="">Selecione...</option>
                                @foreach($myTenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Armazém Destino --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-green-600"></i>Armazém Destino *
                            </label>
                            <select wire:model="warehouseToId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition font-semibold">
                                <option value="">Selecione...</option>
                                @if($tenantToId)
                                    @foreach($this->getWarehousesForTenant() as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Produtos --}}
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">2</div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-lg">Selecione os Produtos</h4>
                            <p class="text-sm text-blue-700">Clique nos produtos para adicionar à transferência</p>
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="mb-4">
                        <input type="text" wire:model.live.debounce.300ms="productSearch"
                               class="w-full px-5 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                               placeholder="🔍 Procurar produto por nome ou código...">
                    </div>

                    {{-- Products Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-1">
                        @php
                            $filteredProducts = $products;
                            if($productSearch) {
                                $filteredProducts = $products->filter(function($prod) use ($productSearch) {
                                    return stripos($prod->name, $productSearch) !== false ||
                                           stripos($prod->code, $productSearch) !== false;
                                });
                            }
                        @endphp

                        @forelse($filteredProducts as $prod)
                            <div wire:click="selectProduct({{ $prod->id }})"
                                 class="p-3 border-2 rounded-xl cursor-pointer transition border-gray-200 hover:border-purple-400 hover:shadow-lg bg-white hover:scale-[1.02]">
                                <p class="font-bold text-sm text-gray-900 truncate">{{ $prod->name }}</p>
                                <p class="text-xs text-gray-500">{{ $prod->code }}</p>
                                @if($warehouseFromId)
                                    @php
                                        $prodStock = $prod->stocks->where('warehouse_id', $warehouseFromId)->first();
                                        $qty = $prodStock ? $prodStock->available_quantity : 0;
                                    @endphp
                                    <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                        <span class="text-xs text-gray-500">Stock:</span>
                                        <span class="text-sm font-bold {{ $qty > 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format($qty, 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-8 text-gray-400">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                <p>Nenhum produto encontrado</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Step 3: Cart --}}
                @if(count($transferItems) > 0)
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">3</div>
                        <div class="flex-1">
                            <h4 class="font-bold text-green-900 text-lg">Produtos a Transferir</h4>
                            <p class="text-sm text-green-700">{{ count($transferItems) }} produto(s) no carrinho</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($transferItems as $index => $item)
                            <div class="flex items-center justify-between p-4 bg-white rounded-xl border-2 border-green-200 shadow-sm">
                                <div class="flex items-center flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-bold text-gray-900">{{ $item['product_name'] }}</p>
                                        <p class="text-sm text-gray-500">{{ $item['product_code'] }}</p>
                                    </div>
                                </div>
                                <div class="text-right mr-4">
                                    <p class="text-xs text-gray-500">Quantidade</p>
                                    <p class="text-2xl font-bold text-purple-600">{{ number_format($item['quantity'], 2) }}</p>
                                </div>
                                <div class="text-right mr-4">
                                    <p class="text-xs text-gray-500">Custo Unit.</p>
                                    <p class="text-sm font-semibold text-gray-700">{{ number_format($item['unit_cost'], 2) }} Kz</p>
                                </div>
                                <button type="button" wire:click="removeProduct({{ $index }})"
                                        class="text-red-500 hover:text-white hover:bg-red-500 p-3 rounded-xl transition">
                                    <i class="fas fa-trash-alt text-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Step 4: Notes --}}
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-yellow-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            {{ count($transferItems) > 0 ? '4' : '3' }}
                        </div>
                        <div>
                            <h4 class="font-bold text-yellow-900 text-lg">Motivo da Transferência</h4>
                            <p class="text-sm text-yellow-700">Descreva o motivo desta transferência</p>
                        </div>
                    </div>
                    <textarea wire:model="notes" rows="3"
                        placeholder="Ex: Reposição de stock, encomenda de cliente, etc..."
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"></textarea>
                </div>

                {{-- Warning --}}
                <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-lg mr-3"></i>
                        <p class="text-sm text-yellow-700">
                            <strong>Atenção:</strong> Esta acção é irreversível. O stock será removido da empresa origem e adicionado à empresa destino.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
                <button type="button" wire:click="$set('showTransferModal', false)"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" wire:click="saveTransfer" wire:loading.attr="disabled" wire:target="saveTransfer"
                    class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveTransfer"><i class="fas fa-check mr-2"></i>Confirmar Transferência ({{ count($transferItems) }})</span>
                    <span wire:loading wire:target="saveTransfer"><i class="fas fa-spinner fa-spin mr-2"></i>A processar...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Quantity Sub-Modal --}}
    @if($showQuantityModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-hashtag mr-2"></i>Definir Quantidade
                </h3>
            </div>

            <div class="p-6">
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex items-center mb-2">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-box text-white"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-gray-900">{{ $selectedProductName }}</p>
                            <p class="text-sm text-gray-600">{{ $selectedProductCode }}</p>
                        </div>
                    </div>
                    @if($availableStock > 0)
                        <div class="mt-3 pt-3 border-t border-blue-200 flex items-center justify-between">
                            <span class="text-sm text-gray-700">Stock Disponível:</span>
                            <span class="text-2xl font-bold text-green-600">{{ number_format($availableStock, 2) }}</span>
                        </div>
                    @else
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-sm text-red-700 font-semibold">Sem stock neste armazém</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-3">Quantidade a Transferir *</label>
                    <input type="number" wire:model="productQuantity" step="0.01" min="0.01" max="{{ $availableStock }}"
                           class="w-full px-6 py-4 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-200 transition text-3xl font-bold text-center"
                           placeholder="0.00" autofocus>

                    @if($availableStock > 0)
                    <div class="grid grid-cols-4 gap-2 mt-4">
                        @if($availableStock >= 1)
                            <button type="button" wire:click="$set('productQuantity', 1)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">1</button>
                        @endif
                        @if($availableStock >= 5)
                            <button type="button" wire:click="$set('productQuantity', 5)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">5</button>
                        @endif
                        @if($availableStock >= 10)
                            <button type="button" wire:click="$set('productQuantity', 10)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">10</button>
                        @endif
                        <button type="button" wire:click="$set('productQuantity', {{ $availableStock }})" class="px-3 py-2 bg-blue-100 hover:bg-blue-200 border-2 border-blue-400 rounded-lg font-semibold text-sm transition">Tudo</button>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end space-x-3">
                <button type="button" wire:click="$set('showQuantityModal', false)"
                        class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" wire:click="addProductToTransfer"
                        class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-8 py-3 rounded-xl font-bold transition shadow-lg hover:shadow-xl">
                    <i class="fas fa-cart-plus mr-2"></i>Adicionar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

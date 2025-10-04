<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Transferência Entre Empresas</h2>
        <p class="text-gray-600">Transfira stock entre as suas empresas</p>
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

    {{-- Info Alert --}}
    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Transferência Inter-Empresas:</strong> Esta funcionalidade permite transferir produtos entre empresas que você gerencia. 
                    O stock será removido da empresa origem e adicionado à empresa destino.
                </p>
            </div>
        </div>
    </div>

    {{-- Button --}}
    <div class="mb-6">
        <button wire:click="openModal" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition">
            <i class="fas fa-exchange-alt mr-2"></i>Nova Transferência
        </button>
    </div>

    {{-- Transfer History --}}
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Histórico de Transferências</h3>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">De</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Para</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($transfers as $transfer)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $transfer->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $transfer->product->name }}</div>
                        <div class="text-sm text-gray-500">{{ $transfer->product->code }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">{{ $transfer->warehouse->name }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($transfer->toWarehouse)
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">{{ $transfer->toWarehouse->name }}</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-semibold">{{ number_format($transfer->quantity, 2) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $transfer->user->name }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma transferência realizada</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t">
            {{ $transfers->links() }}
        </div>
    </div>

    {{-- Modal Transfer --}}
    @if($showTransferModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Transferência Entre Empresas</h3>
                <button wire:click="$set('showTransferModal', false)" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form wire:submit.prevent="saveTransfer">
                <div class="space-y-6">
                    {{-- Origem --}}
                    <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded">
                        <h4 class="font-semibold text-red-900 mb-3 flex items-center">
                            <i class="fas fa-arrow-up mr-2"></i> Origem (Empresa Atual)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Produto *</label>
                                <select wire:model.live="productId" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-red-500">
                                    <option value="">Selecione...</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">
                                            {{ $product->name }} (Stock: {{ number_format($product->stocks->sum('available_quantity'), 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('productId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Armazém Origem *</label>
                                <select wire:model.live="warehouseFromId" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-red-500">
                                    <option value="">Selecione...</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouseFromId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        @if($availableQuantity > 0)
                            <div class="mt-3 p-2 bg-white rounded">
                                <p class="text-sm"><strong>Disponível:</strong> <span class="text-green-600 font-bold">{{ number_format($availableQuantity, 2) }}</span></p>
                                @if($unitCost)
                                    <p class="text-sm"><strong>Custo Médio:</strong> {{ number_format($unitCost, 2) }} Kz</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Destino --}}
                    <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded">
                        <h4 class="font-semibold text-green-900 mb-3 flex items-center">
                            <i class="fas fa-arrow-down mr-2"></i> Destino
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Empresa Destino *</label>
                                <select wire:model.live="tenantToId" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    <option value="">Selecione...</option>
                                    @foreach($myTenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('tenantToId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Armazém Destino *</label>
                                <select wire:model="warehouseToId" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    <option value="">Selecione...</option>
                                    @if($tenantToId)
                                        @foreach($this->getWarehousesForTenant() as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('warehouseToId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Detalhes da Transferência --}}
                    <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <h4 class="font-semibold text-blue-900 mb-3">Detalhes da Transferência</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Quantidade * 
                                    @if($availableQuantity > 0)
                                        <span class="text-xs text-gray-500">(Máx: {{ number_format($availableQuantity, 2) }})</span>
                                    @endif
                                </label>
                                <input type="number" step="0.001" wire:model="quantity" 
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                @error('quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Custo Unitário</label>
                                <input type="number" step="0.01" wire:model="unitCost" 
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                @error('unitCost') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notas / Motivo *</label>
                            <textarea wire:model="notes" rows="3" 
                                placeholder="Descreva o motivo da transferência..." 
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Warning --}}
                    <div class="p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Atenção:</strong> Esta ação é irreversível. O stock será removido da empresa origem e adicionado à empresa destino.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="$set('showTransferModal', false)" 
                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit" 
                        class="px-6 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700">
                        <i class="fas fa-check mr-2"></i>Confirmar Transferência
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

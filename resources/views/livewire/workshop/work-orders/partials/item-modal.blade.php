{{-- Modal de Adicionar Item (Serviço ou Peça) --}}
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeItemModal"></div>

        <div class="inline-block w-full max-w-3xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 flex items-center justify-between"
                 :class="$wire.itemType === 'service' ? 'bg-gradient-to-r from-blue-600 to-indigo-600' : 'bg-gradient-to-r from-green-600 to-emerald-600'">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i :class="$wire.itemType === 'service' ? 'fas fa-wrench' : 'fas fa-cog'" class="text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">
                            <span x-show="$wire.itemType === 'service'">Adicionar Serviço</span>
                            <span x-show="$wire.itemType === 'part'">Adicionar Peça</span>
                        </h3>
                        <p class="text-sm" :class="$wire.itemType === 'service' ? 'text-blue-100' : 'text-green-100'">
                            Preencha os dados do item
                        </p>
                    </div>
                </div>
                <button wire:click="closeItemModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Content --}}
            <div class="p-6 space-y-6">
                
                {{-- Seleção Rápida (Serviço ou Produto) --}}
                <div x-show="$wire.itemType === 'service'">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-list-alt mr-1 text-blue-600"></i>Serviço Cadastrado
                        <span class="text-xs text-gray-500 normal-case ml-2">(opcional - preenche automaticamente)</span>
                    </label>
                    <select wire:model.live="itemServiceId" wire:change="loadServiceData"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="">Selecione um serviço...</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }} - {{ number_format($service->labor_cost, 2, ',', '.') }} Kz</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="$wire.itemType === 'part'">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-box mr-1 text-green-600"></i>Produto do Estoque
                        <span class="text-xs text-gray-500 normal-case ml-2">(opcional - preenche automaticamente)</span>
                    </label>
                    <select wire:model.live="itemProductId" wire:change="loadProductData"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        <option value="">Selecione um produto...</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} - {{ number_format($product->selling_price, 2, ',', '.') }} Kz</option>
                        @endforeach
                    </select>
                    
                    {{-- Alerta de Estoque --}}
                    @if($itemType === 'part' && $itemProductId)
                        @if($productStock !== null)
                            <div class="mt-2 flex items-center gap-2">
                                @if($productStock <= 0)
                                    <div class="flex-1 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-lg flex items-center">
                                        <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                                        <span class="font-semibold">Sem estoque disponível!</span>
                                    </div>
                                @elseif($lowStockWarning)
                                    <div class="flex-1 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-2 rounded-lg flex items-center">
                                        <i class="fas fa-exclamation-circle mr-2 text-yellow-600"></i>
                                        <span class="font-semibold">Estoque baixo: {{ $productStock }} unidades</span>
                                    </div>
                                @else
                                    <div class="flex-1 bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                                        <span class="font-semibold">Em estoque: {{ $productStock }} unidades</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Grid de Campos --}}
                <div class="grid grid-cols-2 gap-4">
                    {{-- Código --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                            <i class="fas fa-barcode mr-1 text-purple-600"></i>Código
                        </label>
                        <input type="text" wire:model="itemCode"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="Ex: SRV-001">
                    </div>

                    {{-- Nome/Descrição --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                            <i class="fas fa-tag mr-1 text-purple-600"></i>Nome *
                        </label>
                        <input type="text" wire:model="itemName" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="Ex: Troca de óleo">
                        @error('itemName') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Descrição Detalhada --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-align-left mr-1 text-purple-600"></i>Descrição Detalhada
                    </label>
                    <textarea wire:model="itemDescription" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                              placeholder="Detalhes adicionais..."></textarea>
                </div>

                {{-- Valores --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                            <i class="fas fa-sort-numeric-up mr-1 text-blue-600"></i>Quantidade *
                        </label>
                        <input type="number" wire:model="itemQuantity" step="0.01" min="0.01" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="1">
                        @error('itemQuantity') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                            <i class="fas fa-dollar-sign mr-1 text-green-600"></i>Preço Unitário (Kz) *
                        </label>
                        <input type="number" wire:model="itemUnitPrice" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="0.00">
                        @error('itemUnitPrice') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                            <i class="fas fa-percent mr-1 text-yellow-600"></i>Desconto (%)
                        </label>
                        <input type="number" wire:model="itemDiscountPercent" step="0.01" min="0" max="100"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                               placeholder="0">
                    </div>
                </div>

                {{-- Campos Específicos para Serviço --}}
                <div x-show="$wire.itemType === 'service'" class="grid grid-cols-2 gap-4 p-4 bg-blue-50 rounded-xl border-2 border-blue-200">
                    <div>
                        <label class="block text-sm font-bold text-blue-900 mb-2 uppercase">
                            <i class="fas fa-clock mr-1"></i>Horas Estimadas
                        </label>
                        <input type="number" wire:model="itemHours" step="0.5" min="0"
                               class="w-full px-4 py-2.5 border border-blue-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0.0">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-blue-900 mb-2 uppercase">
                            <i class="fas fa-user-cog mr-1"></i>Mecânico
                        </label>
                        <select wire:model="itemMechanicId"
                                class="w-full px-4 py-2.5 border border-blue-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="">Selecione...</option>
                            @foreach($mechanics as $mechanic)
                                <option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Campos Específicos para Peça --}}
                <div x-show="$wire.itemType === 'part'" class="grid grid-cols-2 gap-4 p-4 bg-green-50 rounded-xl border-2 border-green-200">
                    <div>
                        <label class="block text-sm font-bold text-green-900 mb-2 uppercase">
                            <i class="fas fa-hashtag mr-1"></i>Número da Peça
                        </label>
                        <input type="text" wire:model="itemPartNumber"
                               class="w-full px-4 py-2.5 border border-green-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="Ex: ABC12345">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-green-900 mb-2 uppercase">
                            <i class="fas fa-copyright mr-1"></i>Marca
                        </label>
                        <input type="text" wire:model="itemBrand"
                               class="w-full px-4 py-2.5 border border-green-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="Ex: Bosch">
                    </div>

                    <div class="col-span-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="itemIsOriginal" class="mr-2 h-5 w-5 text-green-600 rounded">
                            <span class="text-sm font-bold text-green-900">
                                <i class="fas fa-certificate mr-1"></i>Peça Original
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Preview do Subtotal --}}
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl p-4 border-2 border-purple-200">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-purple-900">Subtotal (estimado):</span>
                        <span class="text-2xl font-bold text-purple-900">
                            {{ number_format(($itemQuantity * $itemUnitPrice) - (($itemQuantity * $itemUnitPrice) * ($itemDiscountPercent / 100)), 2, ',', '.') }} Kz
                        </span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeItemModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Cancelar
                </button>
                
                <button wire:click="addItem" 
                        class="px-6 py-2 text-white rounded-xl font-semibold transition-all flex items-center shadow-lg"
                        :class="$wire.itemType === 'service' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700' : 'bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700'">
                    <i class="fas fa-check mr-2"></i>Adicionar Item
                </button>
            </div>
        </div>
    </div>
</div>

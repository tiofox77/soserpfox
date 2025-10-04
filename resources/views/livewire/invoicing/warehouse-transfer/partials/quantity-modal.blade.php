<!-- Quantity Modal -->
@if($showQuantityModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-hashtag mr-2"></i>
                Definir Quantidade
            </h3>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <div class="flex items-center mb-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-box text-white"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-gray-900">{{ $selectedProductName }}</p>
                        <p class="text-sm text-gray-600">📦 {{ $selectedProductCode }}</p>
                    </div>
                </div>
                
                @if($availableStock > 0)
                    <div class="mt-3 pt-3 border-t border-blue-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Stock Disponível:</span>
                            <span class="text-2xl font-bold text-green-600">{{ number_format($availableStock, 2) }}</span>
                        </div>
                    </div>
                @else
                    <div class="mt-3 pt-3 border-t border-blue-200">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-700 font-semibold">
                                ⚠️ Sem stock disponível neste armazém
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Quantity Input -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-3">
                    Quantidade a Transferir *
                </label>
                <input type="number" 
                       wire:model="productQuantity" 
                       step="0.01" 
                       min="0.01" 
                       max="{{ $availableStock }}"
                       class="w-full px-6 py-4 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-200 transition text-3xl font-bold text-center"
                       placeholder="0.00"
                       autofocus>
                
                <!-- Quick Amount Buttons -->
                @if($availableStock > 0)
                    <div class="grid grid-cols-4 gap-2 mt-4">
                        @if($availableStock >= 1)
                            <button type="button" wire:click="$set('productQuantity', 1)"
                                    class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">
                                1
                            </button>
                        @endif
                        @if($availableStock >= 5)
                            <button type="button" wire:click="$set('productQuantity', 5)"
                                    class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">
                                5
                            </button>
                        @endif
                        @if($availableStock >= 10)
                            <button type="button" wire:click="$set('productQuantity', 10)"
                                    class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">
                                10
                            </button>
                        @endif
                        <button type="button" wire:click="$set('productQuantity', {{ $availableStock }})"
                                class="px-3 py-2 bg-blue-100 hover:bg-blue-200 border-2 border-blue-400 rounded-lg font-semibold text-sm transition">
                            Tudo
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
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

<!-- Adjust Quantity Modal -->
@if($showAdjustQuantityModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[70] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-hashtag mr-2"></i>
                Definir Quantidade
            </h3>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4 mb-6">
                <div class="flex items-center mb-2">
                    <div class="w-10 h-10 bg-yellow-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-box text-white"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-gray-900">{{ $adjustSelectedProductName }}</p>
                        <p class="text-sm text-gray-600">📦 {{ $adjustSelectedProductCode }}</p>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-t border-yellow-200">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">Stock Atual:</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($adjustAvailableStock, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Quantity Input -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-3">
                    Nova Quantidade *
                </label>
                <input type="number" 
                       wire:model="adjustProductQuantity" 
                       step="0.01" 
                       min="0"
                       class="w-full px-6 py-4 rounded-xl border-2 border-gray-300 focus:border-yellow-500 focus:ring-4 focus:ring-yellow-200 transition text-3xl font-bold text-center"
                       placeholder="0.00"
                       autofocus>
                
                <!-- Quick Amount Buttons -->
                <div class="grid grid-cols-4 gap-2 mt-4">
                    <button type="button" wire:click="$set('adjustProductQuantity', 10)"
                            class="px-3 py-2 bg-gray-100 hover:bg-yellow-100 border-2 border-gray-300 hover:border-yellow-400 rounded-lg font-semibold text-sm transition">
                        10
                    </button>
                    <button type="button" wire:click="$set('adjustProductQuantity', 50)"
                            class="px-3 py-2 bg-gray-100 hover:bg-yellow-100 border-2 border-gray-300 hover:border-yellow-400 rounded-lg font-semibold text-sm transition">
                        50
                    </button>
                    <button type="button" wire:click="$set('adjustProductQuantity', 100)"
                            class="px-3 py-2 bg-gray-100 hover:bg-yellow-100 border-2 border-gray-300 hover:border-yellow-400 rounded-lg font-semibold text-sm transition">
                        100
                    </button>
                    <button type="button" wire:click="$set('adjustProductQuantity', 0)"
                            class="px-3 py-2 bg-red-100 hover:bg-red-200 border-2 border-red-400 rounded-lg font-semibold text-sm transition">
                        Zero
                    </button>
                </div>
                
                <p class="text-xs text-gray-600 mt-3 text-center">
                    💡 Esta será a quantidade final no armazém
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end space-x-3">
            <button type="button" wire:click="$set('showAdjustQuantityModal', false)"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="addProductToAdjust"
                    class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white px-8 py-3 rounded-xl font-bold transition shadow-lg hover:shadow-xl">
                <i class="fas fa-check mr-2"></i>Confirmar
            </button>
        </div>
    </div>
</div>
@endif

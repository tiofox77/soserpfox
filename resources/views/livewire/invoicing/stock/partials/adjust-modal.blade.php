<!-- Adjust Stock Modal -->
@if($showAdjustModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Ajustar Stock
            </h3>
            <button wire:click="$set('showAdjustModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Produto</p>
                        <p class="text-lg font-bold text-gray-900">{{ $adjustProductName }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Stock Atual</p>
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($adjustCurrentQty, 0) }}</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="saveAdjustment">
                <div class="space-y-6">
                    <!-- New Quantity -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-blue-600"></i>Nova Quantidade *
                        </label>
                        <input 
                            type="number" 
                            step="1" 
                            min="0"
                            wire:model="adjustNewQty" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition text-2xl font-bold text-center"
                            placeholder="0">
                        @error('adjustNewQty') 
                            <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Difference -->
                    @if($adjustNewQty && $adjustNewQty != $adjustCurrentQty)
                        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-700">Diferença:</span>
                                <span class="text-2xl font-bold {{ ($adjustNewQty - $adjustCurrentQty) > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($adjustNewQty - $adjustCurrentQty) > 0 ? '+' : '' }}{{ number_format($adjustNewQty - $adjustCurrentQty, 0) }}
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-blue-600"></i>Motivo/Observações
                        </label>
                        <textarea 
                            wire:model="adjustNotes" 
                            rows="3" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                            placeholder="Descreva o motivo do ajuste (ex: Inventário, correção, etc.)"></textarea>
                        @error('adjustNotes') 
                            <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button 
                        type="button" 
                        wire:click="$set('showAdjustModal', false)" 
                        class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button 
                        type="submit" 
                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-xl font-bold transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>Confirmar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Transfer Stock Modal -->
@if($showTransferModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-3xl w-full transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-exchange-alt mr-2"></i>
                Transferir Stock
            </h3>
            <button wire:click="$set('showTransferModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Produto</p>
                        <p class="text-lg font-bold text-gray-900">{{ $transferProductName }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Disponível para Transferir</p>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($transferMaxQty, 0) }}</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="saveTransfer">
                <div class="space-y-6">
                    <!-- Warehouses -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- From -->
                        <div class="bg-white rounded-xl p-4 border-2 border-red-200">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-warehouse mr-1 text-red-600"></i>
                                <span class="text-red-600">DE</span> (Origem)
                            </label>
                            <select 
                                wire:model="transferFromWarehouse" 
                                disabled
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 bg-gray-50 text-base font-semibold">
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            @error('transferFromWarehouse') 
                                <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                            @enderror
                        </div>

                        <!-- To -->
                        <div class="bg-white rounded-xl p-4 border-2 border-green-200">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-warehouse mr-1 text-green-600"></i>
                                <span class="text-green-600">PARA</span> (Destino)
                            </label>
                            <select 
                                wire:model="transferToWarehouse" 
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition text-base font-semibold">
                                <option value="">📦 Selecionar destino...</option>
                                @foreach($warehouses as $warehouse)
                                    @if($warehouse->id != $transferFromWarehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('transferToWarehouse') 
                                <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>

                    <!-- Arrow Indicator -->
                    <div class="flex justify-center -my-3">
                        <i class="fas fa-arrow-down text-purple-600 text-3xl"></i>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-purple-600"></i>
                            Quantidade a Transferir * 
                            <span class="text-sm font-normal text-gray-500">(Máximo: {{ number_format($transferMaxQty, 0) }})</span>
                        </label>
                        <input 
                            type="number" 
                            step="1" 
                            min="0"
                            max="{{ $transferMaxQty }}"
                            wire:model="transferQuantity" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition text-2xl font-bold text-center"
                            placeholder="0">
                        @error('transferQuantity') 
                            <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                        @enderror
                        
                        <!-- Quick buttons -->
                        @if($transferMaxQty > 0)
                            <div class="grid grid-cols-4 gap-2 mt-3">
                                <button type="button" onclick="@this.set('transferQuantity', {{ min(10, $transferMaxQty) }})"
                                        class="px-3 py-2 bg-gray-100 hover:bg-purple-100 border-2 border-gray-300 hover:border-purple-400 rounded-lg font-semibold text-sm transition">
                                    10
                                </button>
                                <button type="button" onclick="@this.set('transferQuantity', {{ min(50, $transferMaxQty) }})"
                                        class="px-3 py-2 bg-gray-100 hover:bg-purple-100 border-2 border-gray-300 hover:border-purple-400 rounded-lg font-semibold text-sm transition">
                                    50
                                </button>
                                <button type="button" onclick="@this.set('transferQuantity', {{ min(100, $transferMaxQty) }})"
                                        class="px-3 py-2 bg-gray-100 hover:bg-purple-100 border-2 border-gray-300 hover:border-purple-400 rounded-lg font-semibold text-sm transition">
                                    100
                                </button>
                                <button type="button" onclick="@this.set('transferQuantity', {{ $transferMaxQty }})"
                                        class="px-3 py-2 bg-purple-100 hover:bg-purple-200 border-2 border-purple-400 rounded-lg font-semibold text-sm transition">
                                    Tudo
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-purple-600"></i>Observações
                        </label>
                        <textarea 
                            wire:model="transferNotes" 
                            rows="3" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition"
                            placeholder="Motivo da transferência, instruções especiais, etc."></textarea>
                        @error('transferNotes') 
                            <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button 
                        type="button" 
                        wire:click="$set('showTransferModal', false)" 
                        class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button 
                        type="submit" 
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold transition shadow-lg">
                        <i class="fas fa-exchange-alt mr-2"></i>Executar Transferência
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

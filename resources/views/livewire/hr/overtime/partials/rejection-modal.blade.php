{{-- Modal Backdrop --}}
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     x-data="{ show: @entangle('showRejectionModal') }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click.self="$wire.set('showRejectionModal', false)">
    
    {{-- Modal Content --}}
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95">
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-red-600 to-pink-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-white font-bold text-xl flex items-center">
                <i class="fas fa-times-circle mr-3"></i>
                Rejeitar Horas Extras
            </h3>
            <button wire:click="$set('showRejectionModal', false)" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <form wire:submit.prevent="reject">
            <div class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <p class="text-sm font-semibold text-red-900 mb-1">Atenção!</p>
                            <p class="text-xs text-red-700">
                                Esta ação rejeitará o registro de horas extras. Por favor, forneça um motivo para a rejeição.
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-comment-alt mr-1 text-red-600"></i>Motivo da Rejeição *
                    </label>
                    <textarea wire:model="rejection_reason" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all @error('rejection_reason') border-red-500 @enderror"
                              placeholder="Explique o motivo da rejeição..."></textarea>
                    @error('rejection_reason') 
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3 rounded-b-2xl">
                <button type="button" 
                        wire:click="$set('showRejectionModal', false)"
                        class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        wire:loading.attr="disabled" 
                        wire:target="reject"
                        class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="reject">
                        <i class="fas fa-times-circle mr-2"></i>Rejeitar
                    </span>
                    <span wire:loading wire:target="reject">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Rejeitando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

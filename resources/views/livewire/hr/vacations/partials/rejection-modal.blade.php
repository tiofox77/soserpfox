{{-- Modal Rejection - Rejeitar Solicitação de Férias --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>

        {{-- Modal Panel --}}
        <div class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-times-circle text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Rejeitar Solicitação de Férias</h3>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <form wire:submit.prevent="reject" class="mt-4">
                {{-- Aviso --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-0.5"></i>
                        <div class="text-sm text-yellow-900">
                            <p class="font-semibold">Atenção!</p>
                            <p class="mt-1">Você está prestes a rejeitar esta solicitação de férias. Por favor, informe o motivo da rejeição.</p>
                        </div>
                    </div>
                </div>

                {{-- Motivo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Motivo da Rejeição <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="rejection_reason" 
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all @error('rejection_reason') border-red-500 @enderror" 
                              rows="4" 
                              placeholder="Descreva o motivo da rejeição (mínimo 10 caracteres)..."
                              required></textarea>
                    @error('rejection_reason')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">Este motivo será visível para o funcionário.</p>
                </div>

                {{-- Footer --}}
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal"
                            class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                        <i class="fas fa-arrow-left mr-2"></i>Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white rounded-xl font-semibold shadow-lg transition-all">
                        <i class="fas fa-times-circle mr-2"></i>Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

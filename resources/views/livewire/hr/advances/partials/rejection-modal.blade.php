{{-- Modal Rejeitar Adiantamento --}}
@if($showRejectionModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-red-600 to-pink-600 p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-times-circle mr-3"></i>
                        Rejeitar Adiantamento
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                <form wire:submit.prevent="reject">
                    {{-- Informação do Adiantamento --}}
                    @if($selectedAdvance)
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-red-600 mt-1 mr-3 text-xl"></i>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-red-900 mb-3">Detalhes da Solicitação</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Funcionário:</span>
                                            <span class="font-bold text-red-900">{{ $selectedAdvance->employee->full_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Valor Solicitado:</span>
                                            <span class="font-bold text-red-900">{{ number_format($selectedAdvance->requested_amount, 2, ',', '.') }} Kz</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Parcelas:</span>
                                            <span class="font-bold text-red-900">{{ $selectedAdvance->installments }}x</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Alerta --}}
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-4 rounded-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-yellow-600 mr-3 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 mb-1">Atenção!</h4>
                                <p class="text-sm text-yellow-700">
                                    Esta ação rejeitará permanentemente o pedido de adiantamento. 
                                    É obrigatório informar o motivo da rejeição.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Motivo da Rejeição --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-slash mr-2 text-red-600"></i>Motivo da Rejeição *
                        </label>
                        <textarea wire:model="rejection_reason" rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                                  placeholder="Descreva o motivo da rejeição (mínimo 10 caracteres)..."></textarea>
                        @error('rejection_reason') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            O funcionário será notificado sobre a rejeição e o motivo
                        </p>
                    </div>

                    {{-- Footer --}}
                    <div class="flex gap-3 pt-4 border-t">
                        <button type="button" 
                                wire:click="closeModal"
                                class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="reject"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white rounded-lg font-semibold transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="reject">
                                <i class="fas fa-times-circle mr-2"></i>Confirmar Rejeição
                            </span>
                            <span wire:loading wire:target="reject">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Rejeitando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

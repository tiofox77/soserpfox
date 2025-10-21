{{-- Modal Aprovar Adiantamento --}}
@if($showApprovalModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        Aprovar Adiantamento
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                <form wire:submit.prevent="approve">
                    {{-- Informação do Adiantamento --}}
                    @if($selectedAdvance)
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-green-600 mt-1 mr-3 text-xl"></i>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-green-900 mb-3">Detalhes da Solicitação</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-green-700">Funcionário:</span>
                                            <span class="font-bold text-green-900">{{ $selectedAdvance->employee->full_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-green-700">Valor Solicitado:</span>
                                            <span class="font-bold text-green-900">{{ number_format($selectedAdvance->requested_amount, 2, ',', '.') }} Kz</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-green-700">Parcelas:</span>
                                            <span class="font-bold text-green-900">{{ $selectedAdvance->installments }}x</span>
                                        </div>
                                        <div class="flex justify-between border-t border-green-300 pt-2 mt-2">
                                            <span class="text-green-700">Por Parcela:</span>
                                            <span class="font-bold text-green-900">{{ number_format($selectedAdvance->requested_amount / $selectedAdvance->installments, 2, ',', '.') }} Kz</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Valor a Aprovar --}}
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-money-check-alt mr-2 text-green-600"></i>Valor a Aprovar (Kz) *
                        </label>
                        <input type="number" step="0.01" wire:model.live="approved_amount" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                               placeholder="0.00">
                        @error('approved_amount') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Pode aprovar um valor diferente do solicitado
                        </p>
                    </div>

                    {{-- Observações --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment mr-2 text-blue-600"></i>Observações da Aprovação
                        </label>
                        <textarea wire:model="approval_notes" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                  placeholder="Observações sobre a aprovação (opcional)..."></textarea>
                    </div>

                    {{-- Footer --}}
                    <div class="flex gap-3 pt-4 border-t">
                        <button type="button" 
                                wire:click="closeModal"
                                class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="approve"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-semibold transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="approve">
                                <i class="fas fa-check-circle mr-2"></i>Aprovar
                            </span>
                            <span wire:loading wire:target="approve">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Aprovando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

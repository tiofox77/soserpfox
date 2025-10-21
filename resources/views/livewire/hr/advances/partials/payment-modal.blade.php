{{-- Modal de Pagamento --}}
@if($showPaymentModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 rounded-t-2xl sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-hand-holding-usd mr-3"></i>
                        Registrar Pagamento
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                @if($paymentAdvanceId)
                    @php
                        $advance = \App\Models\HR\SalaryAdvance::with('employee')->find($paymentAdvanceId);
                    @endphp
                    
                    {{-- Informações do Adiantamento --}}
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3 text-xl"></i>
                            <div class="flex-1">
                                <h4 class="font-semibold text-blue-900 mb-3">Detalhes do Adiantamento</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-blue-700">Número:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ $advance->advance_number }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Funcionário:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ $advance->employee->full_name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Valor Aprovado:</span>
                                        <span class="font-bold text-green-600 ml-2">{{ number_format($advance->approved_amount, 2, ',', '.') }} Kz</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Saldo Restante:</span>
                                        <span class="font-bold text-orange-600 ml-2">{{ number_format($advance->balance ?? $advance->approved_amount, 2, ',', '.') }} Kz</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tipo de Pagamento --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-credit-card mr-2 text-green-600"></i>Tipo de Pagamento
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button"
                                    wire:click="$set('paymentType', 'total')"
                                    class="p-4 border-2 rounded-xl transition-all {{ $paymentType === 'total' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300' }}">
                                <div class="text-center">
                                    <i class="fas fa-check-circle text-2xl mb-2 {{ $paymentType === 'total' ? 'text-green-600' : 'text-gray-400' }}"></i>
                                    <div class="font-bold {{ $paymentType === 'total' ? 'text-green-700' : 'text-gray-700' }}">Total</div>
                                    <div class="text-xs text-gray-600 mt-1">Pagar tudo de uma vez</div>
                                </div>
                            </button>

                            <button type="button"
                                    wire:click="$set('paymentType', 'installment')"
                                    class="p-4 border-2 rounded-xl transition-all {{ $paymentType === 'installment' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300' }}">
                                <div class="text-center">
                                    <i class="fas fa-calendar-alt text-2xl mb-2 {{ $paymentType === 'installment' ? 'text-green-600' : 'text-gray-400' }}"></i>
                                    <div class="font-bold {{ $paymentType === 'installment' ? 'text-green-700' : 'text-gray-700' }}">Parcelado</div>
                                    <div class="text-xs text-gray-600 mt-1">Pagar em parcelas</div>
                                </div>
                            </button>

                            <button type="button"
                                    wire:click="$set('paymentType', 'custom')"
                                    class="p-4 border-2 rounded-xl transition-all {{ $paymentType === 'custom' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300' }}">
                                <div class="text-center">
                                    <i class="fas fa-edit text-2xl mb-2 {{ $paymentType === 'custom' ? 'text-green-600' : 'text-gray-400' }}"></i>
                                    <div class="font-bold {{ $paymentType === 'custom' ? 'text-green-700' : 'text-gray-700' }}">Customizado</div>
                                    <div class="text-xs text-gray-600 mt-1">Valor personalizado</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    {{-- Valor do Pagamento --}}
                    @if($paymentType === 'total')
                        <div class="mb-6 p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                            <div class="text-center">
                                <p class="text-sm text-green-700 mb-2">Valor Total a Pagar</p>
                                <p class="text-4xl font-bold text-green-600">{{ number_format($advance->balance ?? $advance->approved_amount, 2, ',', '.') }} Kz</p>
                                <p class="text-xs text-green-600 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Quitação completa do adiantamento
                                </p>
                            </div>
                        </div>
                    @elseif($paymentType === 'installment')
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list-ol mr-2 text-purple-600"></i>Número de Parcelas
                            </label>
                            <select wire:model.live="paymentInstallments" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                <option value="">Selecione...</option>
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">{{ $i }}x de {{ number_format(($advance->balance ?? $advance->approved_amount) / $i, 2, ',', '.') }} Kz</option>
                                @endfor
                            </select>
                            
                            @if($paymentInstallments)
                                <div class="mt-3 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-purple-700">Valor por parcela:</span>
                                        <span class="font-bold text-purple-900">{{ number_format(($advance->balance ?? $advance->approved_amount) / $paymentInstallments, 2, ',', '.') }} Kz</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @elseif($paymentType === 'custom')
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Valor Personalizado (Kz)
                            </label>
                            <input type="number" 
                                   wire:model.live="paymentAmount" 
                                   step="0.01"
                                   max="{{ $advance->balance ?? $advance->approved_amount }}"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                   placeholder="0.00">
                            
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="text-gray-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Máximo: {{ number_format($advance->balance ?? $advance->approved_amount, 2, ',', '.') }} Kz
                                </span>
                                @if($paymentAmount > 0 && $paymentAmount < ($advance->balance ?? $advance->approved_amount))
                                    <span class="text-orange-600 font-semibold">
                                        Saldo restante: {{ number_format(($advance->balance ?? $advance->approved_amount) - $paymentAmount, 2, ',', '.') }} Kz
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Data do Pagamento --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-2 text-blue-600"></i>Data do Pagamento
                        </label>
                        <input type="date" 
                               wire:model="paymentDate" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                               value="{{ date('Y-m-d') }}">
                    </div>

                    {{-- Observações --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment mr-2 text-gray-600"></i>Observações (opcional)
                        </label>
                        <textarea wire:model="paymentNotes" 
                                  rows="3"
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none"
                                  placeholder="Adicione observações sobre este pagamento..."></textarea>
                    </div>

                    {{-- Alerta --}}
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-4 rounded-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 mb-1">Atenção!</h4>
                                <p class="text-sm text-yellow-700">
                                    Esta ação registrará o pagamento e atualizará o status do adiantamento.
                                    @if($paymentType === 'total')
                                        O adiantamento será marcado como completamente pago.
                                    @elseif($paymentType === 'custom' && $paymentAmount > 0)
                                        @if($paymentAmount >= ($advance->balance ?? $advance->approved_amount))
                                            O adiantamento será quitado.
                                        @else
                                            O saldo será atualizado e o adiantamento continuará em dedução.
                                        @endif
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex gap-3 pt-4 border-t">
                        <button type="button" 
                                wire:click="closeModal"
                                class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="button"
                                wire:click="processPayment"
                                wire:loading.attr="disabled"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-semibold transition shadow-lg disabled:opacity-50">
                            <span wire:loading.remove wire:target="processPayment">
                                <i class="fas fa-check-circle mr-2"></i>Confirmar Pagamento
                            </span>
                            <span wire:loading wire:target="processPayment">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

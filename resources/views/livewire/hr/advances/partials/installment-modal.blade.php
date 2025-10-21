{{-- Modal Processar Prestação --}}
@if($showInstallmentModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-money-check-alt mr-3"></i>
                        Processar Prestação
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                @if($installmentAdvanceId)
                    @php
                        $advance = \App\Models\HR\SalaryAdvance::find($installmentAdvanceId);
                    @endphp
                    
                    {{-- Informações do Adiantamento --}}
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3 text-xl"></i>
                            <div class="flex-1">
                                <h4 class="font-semibold text-blue-900 mb-3">Informações do Adiantamento</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-blue-700">Número:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ $advance->advance_number }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Funcionário:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ $advance->employee->full_name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Total Aprovado:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ number_format($advance->approved_amount, 2, ',', '.') }} Kz</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Saldo Restante:</span>
                                        <span class="font-bold text-orange-600 ml-2">{{ number_format($advance->balance, 2, ',', '.') }} Kz</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Prestações Pagas:</span>
                                        <span class="font-bold text-blue-900 ml-2">{{ $advance->installments_paid }}/{{ $advance->installments }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Valor da Prestação:</span>
                                        <span class="font-bold text-green-600 ml-2">{{ number_format($advance->installment_amount, 2, ',', '.') }} Kz</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Valor da Prestação --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Valor da Prestação (Kz)
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   wire:model="installmentAmount" 
                                   step="0.01"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-gray-50 font-bold text-xl"
                                   readonly>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">Kz</div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Valor fixo de prestação mensal
                        </p>
                    </div>

                    {{-- Progresso --}}
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-700">Progresso do Pagamento</span>
                            <span class="text-sm font-bold text-blue-600">
                                {{ round(($advance->installments_paid / $advance->installments) * 100, 1) }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-500"
                                 style="width: {{ ($advance->installments_paid / $advance->installments) * 100 }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-center">
                            {{ $advance->installments - $advance->installments_paid }} prestações restantes
                        </p>
                    </div>

                    {{-- Alerta --}}
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-4 rounded-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 mb-1">Atenção!</h4>
                                <p class="text-sm text-yellow-700">
                                    Esta ação registrará o pagamento de mais uma prestação e atualizará o saldo devedor.
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
                                wire:click="processInstallment"
                                wire:loading.attr="disabled"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-semibold transition shadow-lg disabled:opacity-50">
                            <span wire:loading.remove wire:target="processInstallment">
                                <i class="fas fa-check-circle mr-2"></i>Processar Prestação
                            </span>
                            <span wire:loading wire:target="processInstallment">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

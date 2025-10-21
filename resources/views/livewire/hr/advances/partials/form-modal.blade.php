{{-- Modal Criar/Editar Adiantamento --}}
@if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-hand-holding-usd mr-3"></i>
                        {{ $editMode ? 'Editar Adiantamento' : 'Novo Adiantamento' }}
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                <form wire:submit.prevent="save">
                    {{-- Funcionário --}}
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user mr-2 text-blue-600"></i>Funcionário *
                        </label>
                        <select wire:model.live="employee_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Selecione um funcionário</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->full_name }} - {{ $emp->employee_number }}</option>
                            @endforeach
                        </select>
                        @error('employee_id') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Informações de Limite --}}
                    @if($employee_id && $baseSalary > 0)
                        <div class="mb-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4">
                            <h4 class="font-bold text-green-900 mb-3 flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                Limite de Adiantamento
                            </h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="bg-white rounded-lg p-3 border border-green-200">
                                    <p class="text-gray-600 text-xs mb-1">Salário Base</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($baseSalary, 2, ',', '.') }} Kz</p>
                                </div>
                                <div class="bg-white rounded-lg p-3 border border-green-200">
                                    <p class="text-gray-600 text-xs mb-1">Máximo Permitido ({{ $maxPercentage }}%)</p>
                                    <p class="text-xl font-bold text-green-600">{{ number_format($maxAllowed, 2, ',', '.') }} Kz</p>
                                </div>
                            </div>
                            @if($availableAmount < $maxAllowed)
                                <div class="mt-3 p-2 bg-orange-100 border border-orange-300 rounded-lg">
                                    <p class="text-xs text-orange-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <strong>Disponível:</strong> {{ number_format($availableAmount, 2, ',', '.') }} Kz
                                        (há adiantamentos em dedução)
                                    </p>
                                </div>
                            @else
                                <div class="mt-3 p-2 bg-green-100 border border-green-300 rounded-lg">
                                    <p class="text-xs text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <strong>Disponível para solicitar:</strong> {{ number_format($availableAmount, 2, ',', '.') }} Kz
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Valor Solicitado --}}
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Valor Solicitado (Kz) *
                        </label>
                        <input type="number" step="0.01" wire:model.live="requested_amount" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="0.00">
                        @error('requested_amount') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Parcelas --}}
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>Número de Parcelas *
                        </label>
                        <select wire:model.live="installments" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Selecione...</option>
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}x parcelas</option>
                            @endfor
                        </select>
                        @error('installments') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                        
                        @if($installmentAmount > 0)
                            <div class="mt-2 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                <span class="text-purple-700 text-sm">
                                    <i class="fas fa-calculator mr-2"></i>
                                    Valor por parcela: <strong>{{ number_format($installmentAmount, 2, ',', '.') }} Kz</strong>
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Motivo --}}
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-dots mr-2 text-orange-600"></i>Motivo *
                        </label>
                        <textarea wire:model="reason" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                  placeholder="Descreva o motivo do adiantamento..."></textarea>
                        @error('reason') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Observações --}}
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2 text-gray-600"></i>Observações
                        </label>
                        <textarea wire:model="notes" rows="2"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                  placeholder="Observações adicionais (opcional)..."></textarea>
                        @error('notes') 
                            <span class="text-red-500 text-xs mt-1 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span>
                        @enderror
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
                                wire:target="save"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg font-semibold transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="save">
                                <i class="fas fa-save mr-2"></i>Salvar
                            </span>
                            <span wire:loading wire:target="save">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<div>
@if($show)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl transform animate-scale-in">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-xl">{{ __('Registrar Pagamento') }}</h3>
                        <p class="text-blue-100 text-sm">Fatura: {{ $invoice->invoice_number ?? 'N/A' }}</p>
                    </div>
                </div>
                <button wire:click="close" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        {{-- Informações da Fatura --}}
        <div class="p-6 bg-blue-50 border-b-2 border-blue-200">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-600 font-semibold uppercase">{{ __('Cliente/Fornecedor') }}</p>
                    <p class="font-bold text-gray-900">
                        {{ $invoiceType === 'sale' ? $invoice->client->name : $invoice->supplier->name }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 font-semibold uppercase">{{ __('Total da Fatura') }}</p>
                    <p class="font-bold text-2xl text-blue-600">{{ number_format($invoice->total, 2) }} AOA</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 font-semibold uppercase">{{ __('Já Pago') }}</p>
                    <p class="font-bold text-green-600">{{ number_format($invoice->paid_amount ?? 0, 2) }} AOA</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 font-semibold uppercase">{{ __('Valor em Dívida') }}</p>
                    <p class="font-bold text-2xl text-red-600">{{ number_format($total_due, 2) }} AOA</p>
                </div>
            </div>
        </div>

        {{-- Formulário --}}
        <div class="p-6 space-y-6">
            {{-- Método de Pagamento --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-credit-card mr-1 text-blue-600"></i>{{ __('Método de Pagamento *') }}
                </label>
                <select wire:model.live="payment_method" 
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                    <option value="cash">💵 {{ __('Dinheiro') }}</option>
                    <option value="transfer">🏦 {{ __('Transferência Bancária') }}</option>
                    <option value="multicaixa">💳 {{ __('Multicaixa') }}</option>
                    <option value="tpa">💳 TPA</option>
                    <option value="check">📝 {{ __('Cheque') }}</option>
                    <option value="mbway">📱 {{ __('MB Way') }}</option>
                    <option value="other">❓ {{ __('Outro') }}</option>
                </select>
            </div>

            {{-- Seleção de Conta Bancária (quando não for dinheiro) --}}
            @if($payment_method !== 'cash')
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-university mr-1 text-blue-600"></i>{{ __('Conta Bancária de Destino *') }}
                </label>
                <select wire:model="selected_account_id" 
                        class="w-full px-4 py-3 border-2 border-blue-300 rounded-xl focus:ring-2 focus:ring-blue-500 bg-white">
                    @forelse($available_accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->bank->name ?? 'Banco' }} - {{ $account->account_name }} ({{ $account->account_number }})
                            @if($account->is_default) ⭐ @endif
                            - Saldo: {{ number_format($account->current_balance, 2) }} {{ $account->currency }}
                        </option>
                    @empty
                        <option value="">{{ __('Nenhuma conta cadastrada') }}</option>
                    @endforelse
                </select>
                <p class="text-xs text-blue-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>{{ __('O saldo desta conta será atualizado automaticamente') }}
                </p>
            </div>
            @else
            {{-- Seleção de Caixa (quando for dinheiro) --}}
            <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-4">
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-cash-register mr-1 text-orange-600"></i>{{ __('Caixa de Destino *') }}
                </label>
                <select wire:model="selected_cash_register_id" 
                        class="w-full px-4 py-3 border-2 border-orange-300 rounded-xl focus:ring-2 focus:ring-orange-500 bg-white">
                    @forelse($available_cash_registers as $cash)
                        <option value="{{ $cash->id }}">
                            {{ $cash->name }} ({{ $cash->code }})
                            @if($cash->is_default) ⭐ @endif
                            - Saldo: {{ number_format($cash->current_balance, 2) }} AOA
                        </option>
                    @empty
                        <option value="">{{ __('Nenhum caixa cadastrado') }}</option>
                    @endforelse
                </select>
                <p class="text-xs text-orange-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>{{ __('O saldo deste caixa será atualizado automaticamente') }}
                </p>
            </div>
            @endif

            {{-- Valor do Pagamento --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-money-bill-wave mr-1 text-blue-600"></i>{{ __('Valor do Pagamento (AOA) *') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-dollar-sign text-blue-500"></i>
                    </div>
                    <input type="number" wire:model.live="amount" step="0.01" min="0.01"
                           class="w-full pl-12 pr-4 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-2xl font-bold" 
                           placeholder="0.00">
                </div>
                @error('amount') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-600 mt-1">Valor em dívida: {{ number_format($total_due, 2) }} AOA</p>
                @if(($amount ?? 0) > $total_due)
                    <p class="text-xs text-yellow-600 mt-1 font-semibold">
                        ⚠️ Pagamento excede o valor devido. Será criado um adiantamento de {{ number_format(($amount ?? 0) - $total_due, 2) }} AOA
                    </p>
                @endif
            </div>

            {{-- Usar Adiantamento --}}
            @if($invoiceType === 'sale' && count($available_advances) > 0)
            <div class="border-2 border-yellow-200 bg-yellow-50 rounded-xl p-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="use_advance" 
                           class="w-5 h-5 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500">
                    <span class="ml-3 font-bold text-gray-900">
                        <i class="fas fa-coins text-yellow-600 mr-1"></i>
                        {{ __('Usar Adiantamento do Cliente') }}
                    </span>
                </label>

                @if($use_advance)
                <div class="mt-3 space-y-3">
                    <select wire:model.live="advance_id" 
                            class="w-full px-4 py-2 border-2 border-yellow-300 rounded-lg">
                        @foreach($available_advances as $adv)
                        <option value="{{ $adv->id }}">
                            {{ $adv->advance_number }} - Disponível: {{ number_format($adv->remaining_amount, 2) }} AOA
                        </option>
                        @endforeach
                    </select>

                    <div class="bg-white rounded-lg p-3 border border-yellow-300">
                        <p class="text-sm font-semibold text-gray-700">{{ __('Valor do Adiantamento a Usar:') }}</p>
                        <p class="text-2xl font-bold text-yellow-600">{{ number_format($advance_amount, 2) }} AOA</p>
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- Referência --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-hashtag mr-1 text-blue-600"></i>{{ __('Referência') }}
                </label>
                <input type="text" wire:model="reference" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500" 
                       placeholder="{{ __('Ex: Nº comprovante...') }}">
            </div>

            {{-- Observações --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-comment mr-1 text-blue-600"></i>{{ __('Observações') }}
                </label>
                <textarea wire:model="notes" rows="2" 
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"
                          placeholder="{{ __('Observações sobre o pagamento...') }}"></textarea>
            </div>

            {{-- Resumo do Pagamento --}}
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 rounded-xl p-4">
                <h4 class="font-bold text-green-800 mb-3 flex items-center">
                    <i class="fas fa-calculator mr-2"></i>{{ __('Resumo do Pagamento') }}
                </h4>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">{{ __('Pagamento em Dinheiro:') }}</span>
                        <span class="font-bold">{{ number_format($amount, 2) }} AOA</span>
                    </div>
                    @if($use_advance && $advance_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">{{ __('Adiantamento Usado:') }}</span>
                        <span class="font-bold text-yellow-600">{{ number_format($advance_amount, 2) }} AOA</span>
                    </div>
                    @endif
                    <div class="border-t-2 border-green-300 pt-2 flex justify-between">
                        <span class="font-bold text-gray-900">{{ __('Total do Pagamento:') }}</span>
                        <span class="font-bold text-2xl text-green-600">
                            {{ number_format(($amount ?? 0) + ($advance_amount ?? 0), 2) }} AOA
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">{{ __('Valor da Fatura:') }}</span>
                        <span class="font-bold text-gray-800">{{ number_format($total_due, 2) }} AOA</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">{{ __('Restante Após Pagamento:') }}</span>
                        <span class="font-bold {{ $remaining_after_payment > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format($remaining_after_payment, 2) }} AOA
                        </span>
                    </div>
                    
                    @php
                        $overpayment = (($amount ?? 0) + ($advance_amount ?? 0)) - $total_due;
                    @endphp
                    
                    @if($overpayment > 0)
                    <div class="bg-yellow-100 border-2 border-yellow-400 rounded-lg p-2 mt-2">
                        <p class="text-xs text-yellow-800 font-semibold mb-1">💰 {{ __('Adiantamento Automático:') }}</p>
                        <p class="font-bold text-lg text-yellow-700">{{ number_format($overpayment, 2) }} AOA</p>
                        <p class="text-xs text-yellow-700">{{ __('Será criado para uso futuro') }}</p>
                    </div>
                    @endif
                    
                    <div class="bg-white rounded-lg p-2 mt-2">
                        <p class="text-xs text-gray-600">{{ __('Novo Status da Fatura:') }}</p>
                        <p class="font-bold text-lg">
                            @if((($amount ?? 0) + ($advance_amount ?? 0)) >= $total_due)
                                <span class="text-green-600">✅ PAGA</span>
                            @elseif(($amount ?? 0) + ($advance_amount ?? 0) > 0)
                                <span class="text-yellow-600">⚠️ {{ __('PARCIALMENTE PAGA') }}</span>
                            @else
                                <span class="text-red-600">❌ PENDENTE</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-4 sm:px-6 py-4 border-t-2 border-gray-200 flex gap-3">
            <button wire:click="close" 
                    class="flex-1 px-4 sm:px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition-all duration-300 hover:scale-105 active:scale-95">
                <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
            </button>
            <button wire:click="registerPayment" 
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 scale-95"
                    class="flex-1 px-4 sm:px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-bold transition-all duration-300 shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>
                    <i class="fas fa-check-circle mr-2"></i>{{ __('Registrar Pagamento') }}
                </span>
                <span wire:loading>
                    <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Processando...') }}
                </span>
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scale-in { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .animate-fade-in { animation: fade-in 0.2s ease-out; }
    .animate-scale-in { animation: scale-in 0.2s ease-out; }
</style>
@endif
</div>

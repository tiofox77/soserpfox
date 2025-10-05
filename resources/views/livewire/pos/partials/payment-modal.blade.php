{{-- Modal de Pagamento --}}
@if($showPaymentModal)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-cash-register mr-2"></i>Finalizar Pagamento
            </h3>
            <button wire:click="$set('showPaymentModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            {{-- Resumo do Pedido --}}
            <div class="bg-gray-50 rounded-xl p-4">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-shopping-cart mr-2 text-green-600"></i>
                    Resumo do Pedido
                </h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span class="font-bold">{{ number_format($cartSubtotal, 2) }} Kz</span>
                    </div>
                    @if($cartDiscount > 0)
                    <div class="flex justify-between text-orange-600">
                        <span>Desconto:</span>
                        <span class="font-bold">-{{ number_format($cartDiscount, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span>IVA (14%):</span>
                        <span class="font-bold">{{ number_format($cartTax, 2) }} Kz</span>
                    </div>
                    <div class="border-t-2 border-gray-300 pt-2 flex justify-between text-lg">
                        <span class="font-bold">TOTAL:</span>
                        <span class="font-bold text-green-600">{{ number_format($cartTotal, 2) }} Kz</span>
                    </div>
                </div>
            </div>

            {{-- Método de Pagamento --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">💳 Método de Pagamento</label>
                <select wire:model="paymentMethod" 
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-lg">
                    @if($paymentMethods && $paymentMethods->count() > 0)
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}">
                                {{ $method->name }}
                            </option>
                        @endforeach
                    @else
                        <option value="cash">💵 Dinheiro</option>
                        <option value="transfer">🏦 Transferência Bancária</option>
                        <option value="multicaixa">💳 Multicaixa</option>
                        <option value="tpa">💳 TPA</option>
                        <option value="mbway">📱 MB Way</option>
                    @endif
                </select>
            </div>

            {{-- Trocos Rápidos --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">⚡ Trocos Rápidos</label>
                <div class="grid grid-cols-4 gap-2">
                    @foreach($quickAmounts as $amount)
                    <button wire:click="setQuickAmount({{ $amount }})" 
                            class="px-3 py-2 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg font-bold text-sm transition">
                        {{ number_format($amount / 1000, 0) }}K
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Valor Recebido --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">💰 Valor Recebido</label>
                <input type="number" wire:model.live="amountReceived" 
                       placeholder="Digite o valor..."
                       step="0.01"
                       class="w-full px-4 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 text-2xl font-bold text-center">
            </div>

            {{-- Troco --}}
            @if($amountReceived > 0)
            <div class="bg-gradient-to-br {{ $change >= 0 ? 'from-green-50 to-emerald-50 border-green-300' : 'from-red-50 to-pink-50 border-red-300' }} border-2 rounded-xl p-4">
                <p class="text-sm font-bold {{ $change >= 0 ? 'text-green-800' : 'text-red-800' }} mb-1">
                    {{ $change >= 0 ? '✅ TROCO:' : '❌ VALOR INSUFICIENTE!' }}
                </p>
                <p class="text-4xl font-bold {{ $change >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format(abs($change), 2) }} Kz
                </p>
            </div>
            @endif

            {{-- Observações --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">📝 Observações (opcional)</label>
                <textarea wire:model="notes" 
                          placeholder="Adicione observações..."
                          rows="2"
                          class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"></textarea>
            </div>

            {{-- Botões --}}
            <div class="flex gap-3">
                <button wire:click="$set('showPaymentModal', false)" 
                        class="flex-1 px-6 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold text-lg transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="completeSale" 
                        wire:loading.attr="disabled"
                        class="flex-1 px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold text-lg shadow-lg transition {{ $change < 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                        @if($change < 0) disabled @endif>
                    <span wire:loading.remove wire:target="completeSale">
                        <i class="fas fa-check-circle mr-2"></i>Confirmar Venda
                    </span>
                    <span wire:loading wire:target="completeSale">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal de Pagamento --}}
@if($paymentModal && $payingReservation)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closePaymentModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg m-4 overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cash-register text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Registar Pagamento</h3>
                        <p class="text-green-100 text-sm">{{ $payingReservation->reservation_number }}</p>
                    </div>
                </div>
                <button wire:click="closePaymentModal" class="text-white/80 hover:text-white p-2 hover:bg-white/20 rounded-lg transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="p-6">
            {{-- Resumo --}}
            <div class="bg-gray-50 rounded-xl p-4 mb-6">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600">Total da Reserva</span>
                    <span class="font-bold text-gray-900">{{ number_format($payingReservation->total, 0, ',', '.') }} Kz</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600">Ja Pago</span>
                    <span class="font-bold text-green-600">{{ number_format($payingReservation->paid_amount, 0, ',', '.') }} Kz</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                    <span class="font-bold text-gray-700">Em Falta</span>
                    <span class="font-bold text-orange-600 text-lg">{{ number_format($payingReservation->total - $payingReservation->paid_amount, 0, ',', '.') }} Kz</span>
                </div>
            </div>

            {{-- Formulario --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Valor a Pagar (Kz)</label>
                    <input type="number" wire:model="payment_amount" step="0.01" min="0"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-lg font-bold text-center">
                    @error('payment_amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Metodo de Pagamento</label>
                    <div class="grid grid-cols-2 gap-3">
                        @forelse($this->paymentMethods as $method)
                            <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition {{ $payment_method_id == $method->id ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300' }}">
                                <input type="radio" wire:model="payment_method_id" value="{{ $method->id }}" class="text-green-600">
                                <i class="{{ $method->icon ?? 'fas fa-wallet' }}" style="color: {{ $method->color ?? '#10B981' }}"></i>
                                <span class="font-medium text-sm">{{ $method->name }}</span>
                            </label>
                        @empty
                            <div class="col-span-2 text-center py-4 text-gray-500">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                Nenhum metodo de pagamento configurado
                            </div>
                        @endforelse
                    </div>
                    @error('payment_method_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Opcao de Fatura --}}
                <div class="bg-blue-50 rounded-xl p-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="generate_invoice" class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                        <div>
                            <span class="font-semibold text-blue-900">Gerar Fatura</span>
                            <p class="text-xs text-blue-700">Criar fatura no modulo de faturacao</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 border-t flex justify-end gap-3">
            <button wire:click="closePaymentModal" type="button" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-semibold">
                Cancelar
            </button>
            <button wire:click="registerPayment" wire:loading.attr="disabled" class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:shadow-lg transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="registerPayment">
                    <i class="fas fa-check mr-2"></i>Confirmar Pagamento
                </span>
                <span wire:loading wire:target="registerPayment">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                </span>
            </button>
        </div>
    </div>
</div>
@endif

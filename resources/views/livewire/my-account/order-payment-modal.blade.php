<!-- Modal Anexar Comprovativo -->
@if($showOrderPaymentModal && $payingOrder)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showOrderPaymentModal') }" x-show="show" x-cloak>
    <!-- Overlay -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" x-show="show" x-transition wire:click="closePaymentModal"></div>

    <!-- Modal -->
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl" x-show="show" x-transition @click.away="$wire.closePaymentModal()">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5 flex items-center justify-between rounded-t-2xl">
                <div>
                    <h3 class="text-2xl font-bold text-white">Anexar Comprovativo de Pagamento</h3>
                    <p class="text-green-100 text-sm">Pedido #{{ $payingOrder->id }}</p>
                </div>
                <button wire:click="closePaymentModal" class="text-white/80 hover:text-white text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6">
                
                <!-- Resumo do Pedido -->
                <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-6 border-2 border-blue-200">
                    <h4 class="font-bold text-gray-900 mb-4">Resumo do Pedido</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600">Plano:</p>
                            <p class="font-bold text-gray-900">{{ $payingOrder->plan->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Ciclo:</p>
                            <p class="font-bold text-gray-900">{{ ucfirst($payingOrder->billing_cycle ?? 'mensal') }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-600 mb-1">Valor a Pagar:</p>
                            <p class="text-3xl font-bold text-blue-600">{{ number_format($payingOrder->amount, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>

                <!-- Dados Bancários -->
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-university text-green-600 mr-2"></i>
                        Dados para Transferência
                    </h4>
                    <div class="space-y-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-600">Banco:</p>
                            <p class="font-bold text-gray-900">BAI - Banco Angolano de Investimentos</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-600">IBAN:</p>
                            <div class="flex items-center justify-between">
                                <p class="font-bold text-gray-900">AO06 0040 0000 1234 5678 9012 3</p>
                                <button type="button" onclick="navigator.clipboard.writeText('AO06004000001234567890123')" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-600">Referência:</p>
                            @php
                                $reference = 'PAY-' . $payingOrder->id . '-' . auth()->user()->id;
                            @endphp
                            <div class="flex items-center justify-between">
                                <p class="font-bold text-gray-900">{{ $reference }}</p>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $reference }}')" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload -->
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-file-upload text-purple-600 mr-2"></i>
                        Anexar Comprovativo
                    </h4>
                    <div class="space-y-3">
                        <input type="file" 
                               wire:model="newPaymentProof" 
                               accept="image/*,application/pdf"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer">
                        
                        @if($newPaymentProof)
                            <div class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-sm text-green-800 font-semibold">Arquivo selecionado!</span>
                            </div>
                        @endif

                        @error('newPaymentProof') 
                            <span class="text-red-500 text-sm flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span> 
                        @enderror
                        
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>Formatos: PDF, JPG, PNG (máx. 5MB)
                        </p>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between rounded-b-2xl">
                <button wire:click="closePaymentModal" class="px-6 py-3 bg-gray-500 text-white rounded-xl font-semibold hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="uploadPaymentProof" wire:loading.attr="disabled" class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="uploadPaymentProof">
                        <i class="fas fa-check-circle mr-2"></i>Anexar Comprovativo
                    </span>
                    <span wire:loading wire:target="uploadPaymentProof">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal de Crédito/Estorno --}}
@if($showCreditModal && $creditingTransaction)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full">
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-undo mr-2"></i>Creditar Transação
            </h3>
            <button wire:click="closeCreditModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            {{-- Alerta de Atenção --}}
            <div class="bg-orange-50 border-2 border-orange-300 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-orange-600 text-2xl mr-3 mt-1"></i>
                    <div>
                        <p class="text-sm font-bold text-orange-900 mb-1">Atenção!</p>
                        <p class="text-xs text-orange-800">
                            Esta ação criará uma transação de <strong>estorno/crédito</strong> que reverterá o valor desta transação. 
                            O saldo será atualizado automaticamente.
                        </p>
                        @if($creditingTransaction->invoice_id)
                        <div class="mt-3 pt-3 border-t border-orange-300">
                            <p class="text-xs text-orange-900 font-semibold flex items-center">
                                <i class="fas fa-file-circle-minus mr-2"></i>
                                Fatura Associada Detectada
                            </p>
                            <p class="text-xs text-orange-800 mt-1">
                                Uma <strong>Nota de Crédito</strong> será criada automaticamente no módulo de Faturação, 
                                creditando o valor da fatura associada.
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detalhes da Transação Original --}}
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-xs text-gray-500 mb-3 font-semibold">Transação Original:</p>
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Número:</span>
                        <span class="text-sm font-mono font-bold text-gray-900">{{ $creditingTransaction->transaction_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Data:</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $creditingTransaction->transaction_date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Descrição:</span>
                        <span class="text-sm font-semibold text-gray-900">{{ Str::limit($creditingTransaction->description, 30) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-300">
                        <span class="text-sm text-gray-600">Valor a Creditar:</span>
                        <span class="text-xl font-bold text-red-600">
                            - {{ number_format($creditingTransaction->amount, 2) }} {{ $creditingTransaction->currency }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Detalhes do Crédito a Criar --}}
            <div class="bg-red-50 border-2 border-red-300 rounded-xl p-4">
                <p class="text-xs text-red-700 font-semibold mb-3">Transação de Crédito que será criada:</p>
                
                <div class="space-y-2 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check text-red-600 mr-2"></i>
                        <span class="text-gray-700">Tipo: <strong>Saída (Estorno)</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-red-600 mr-2"></i>
                        <span class="text-gray-700">Categoria: <strong>Nota de Crédito</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-red-600 mr-2"></i>
                        <span class="text-gray-700">Referência: <strong>CREDIT-{{ $creditingTransaction->transaction_number }}</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-red-600 mr-2"></i>
                        <span class="text-gray-700">Status: <strong>Concluído</strong></span>
                    </div>
                </div>
            </div>

            @if($creditingTransaction->invoice_id)
            {{-- Nota de Crédito que será Criada --}}
            <div class="bg-green-50 border-2 border-green-300 rounded-xl p-4">
                <p class="text-xs text-green-700 font-semibold mb-3 flex items-center">
                    <i class="fas fa-file-circle-minus mr-2"></i>
                    Nota de Crédito (Faturação) que será criada:
                </p>
                
                <div class="space-y-2 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check text-green-600 mr-2"></i>
                        <span class="text-gray-700">Tipo: <strong>Crédito Total</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-green-600 mr-2"></i>
                        <span class="text-gray-700">Motivo: <strong>Devolução/Estorno</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-green-600 mr-2"></i>
                        <span class="text-gray-700">Itens: <strong>Copiados da fatura</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-green-600 mr-2"></i>
                        <span class="text-gray-700">Status: <strong>Emitida</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check text-green-600 mr-2"></i>
                        <span class="text-gray-700">Fatura: <strong>Será creditada</strong></span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Botões --}}
            <div class="flex gap-3 pt-4">
                <button wire:click="closeCreditModal" 
                        class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="creditTransaction" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-bold transition">
                    <i class="fas fa-undo mr-2"></i>Confirmar Crédito
                </button>
            </div>
        </div>
    </div>
</div>
@endif

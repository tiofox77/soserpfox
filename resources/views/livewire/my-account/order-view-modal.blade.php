<!-- Modal Ver Pedido -->
@if($showOrderViewModal && $viewingOrder)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showOrderViewModal') }" x-show="show" x-cloak>
    <!-- Overlay -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" 
         x-show="show"
         x-transition
         wire:click="closeOrderViewModal">
    </div>

    <!-- Modal -->
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl" x-show="show" x-transition @click.away="$wire.closeOrderViewModal()">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-5 flex items-center justify-between rounded-t-2xl">
                <div>
                    <h3 class="text-2xl font-bold text-white">Detalhes do Pedido</h3>
                    <p class="text-blue-100 text-sm">#{{ $viewingOrder->id }}</p>
                </div>
                <button wire:click="closeOrderViewModal" class="text-white/80 hover:text-white text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6">
                
                <!-- Status Badge -->
                <div class="flex justify-center">
                    @php
                        $statusConfig = [
                            'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'clock', 'label' => 'Pendente'],
                            'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'check', 'label' => 'Aprovado'],
                            'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle', 'label' => 'Completo'],
                            'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'times-circle', 'label' => 'Cancelado'],
                        ];
                        $status = $statusConfig[$viewingOrder->status] ?? $statusConfig['pending'];
                    @endphp
                    <span class="inline-flex items-center px-6 py-3 rounded-full text-lg font-bold {{ $status['bg'] }} {{ $status['text'] }}">
                        <i class="fas fa-{{ $status['icon'] }} mr-2"></i>
                        {{ $status['label'] }}
                    </span>
                </div>

                <!-- Informações do Pedido -->
                <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Empresa:</p>
                            <p class="font-semibold text-gray-900">{{ $viewingOrder->tenant->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Plano:</p>
                            <p class="font-semibold text-gray-900">{{ $viewingOrder->plan->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Ciclo:</p>
                            <p class="font-semibold text-gray-900">{{ ucfirst($viewingOrder->billing_cycle ?? 'mensal') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Valor:</p>
                            <p class="font-semibold text-gray-900 text-xl text-blue-600">{{ number_format($viewingOrder->amount, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Data do Pedido:</p>
                            <p class="font-semibold text-gray-900">{{ $viewingOrder->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Método de Pagamento:</p>
                            <p class="font-semibold text-gray-900">{{ $viewingOrder->payment_method === 'bank_transfer' ? 'Transferência Bancária' : 'Outro' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Comprovativo -->
                @if($viewingOrder->payment_proof)
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <p class="font-semibold text-green-900 mb-2">
                            <i class="fas fa-check-circle mr-2"></i>Comprovativo Anexado
                        </p>
                        <a href="{{ Storage::url($viewingOrder->payment_proof) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-download mr-2"></i>Baixar Comprovativo
                        </a>
                    </div>
                @else
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                        <p class="text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Nenhum comprovativo anexado
                        </p>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
                <button wire:click="closeOrderViewModal" class="px-6 py-3 bg-gray-600 text-white rounded-xl font-semibold hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>
@endif

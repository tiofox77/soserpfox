<!-- Adjust Details Modal -->
@if($showDetailsModal && count($selectedBatchDetails) > 0 && $selectedBatchDetails[0]['type'] == 'adjustment')
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-sliders-h mr-2"></i>
                Detalhes do Ajuste de Stock
            </h3>
            <button wire:click="$set('showDetailsModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6">
            @php
                $first = $selectedBatchDetails[0];
                $totalProducts = count($selectedBatchDetails);
                $isEntry = $first['quantity'] > 0;
            @endphp
            
            <!-- Adjust Summary -->
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-xl p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-2">Tipo de Ajuste</p>
                        @if($isEntry)
                            <div class="inline-flex items-center px-4 py-2 bg-green-100 border-2 border-green-500 rounded-xl">
                                <i class="fas fa-arrow-up text-green-600 text-xl mr-2"></i>
                                <span class="font-bold text-green-900">ENTRADA</span>
                            </div>
                        @else
                            <div class="inline-flex items-center px-4 py-2 bg-red-100 border-2 border-red-500 rounded-xl">
                                <i class="fas fa-arrow-down text-red-600 text-xl mr-2"></i>
                                <span class="font-bold text-red-900">SAÍDA</span>
                            </div>
                        @endif
                    </div>
                    
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Armazém</p>
                        <p class="font-bold text-gray-900">{{ $first['warehouse']['name'] }}</p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Data do Ajuste</p>
                        <p class="font-bold text-gray-900">
                            {{ \Carbon\Carbon::parse($first['created_at'])->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Total de Produtos</p>
                        <p class="font-bold text-yellow-600 text-2xl">{{ $totalProducts }}</p>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-yellow-200">
                    <p class="text-xs text-gray-600 mb-1">Responsável</p>
                    <p class="font-bold text-gray-900">
                        <i class="fas fa-user mr-1 text-yellow-600"></i>
                        {{ $first['user']['name'] ?? 'N/A' }}
                    </p>
                </div>
            </div>

            <!-- Products Table -->
            <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b-2 border-gray-200">
                    <h4 class="font-bold text-gray-900 flex items-center">
                        <i class="fas fa-boxes mr-2 text-yellow-600"></i>
                        Produtos Ajustados
                    </h4>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Código</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Quantidade Ajustada</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Custo Unit.</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($selectedBatchDetails as $detail)
                            <tr class="hover:bg-yellow-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-white"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-sm text-gray-900">{{ $detail['product']['name'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $detail['product']['code'] }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-2xl {{ $detail['quantity'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $detail['quantity'] > 0 ? '+' : '' }}{{ number_format(abs($detail['quantity']), 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                    {{ number_format($detail['unit_cost'], 2) }} Kz
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Notes/Reason -->
            @if($first['notes'])
                <div class="mt-6 bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-comment-alt text-yellow-600 mr-2"></i>
                        Motivo do Ajuste
                    </p>
                    <p class="text-sm text-gray-700">{{ $first['notes'] }}</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end">
            <button type="button" wire:click="$set('showDetailsModal', false)"
                    class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>
@endif

<!-- Transfer Details Modal -->
@if($showDetailsModal && count($selectedBatchDetails) > 0 && $selectedBatchDetails[0]['type'] == 'transfer')
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-exchange-alt mr-2"></i>
                Detalhes da Transferência
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
                
                // Agrupar por armazém para encontrar origem e destino
                $warehouseGroups = collect($selectedBatchDetails)->groupBy('warehouse_id');
                $fromWarehouse = null;
                $toWarehouse = null;
                
                foreach($warehouseGroups as $items) {
                    if($items->first()['quantity'] < 0) {
                        $fromWarehouse = $items->first()['warehouse'];
                    } else {
                        $toWarehouse = $items->first()['warehouse'];
                    }
                }
            @endphp
            
            <!-- Transfer Summary -->
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- From Warehouse -->
                    <div class="bg-white rounded-xl p-4 border-2 border-red-200">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-warehouse text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Origem</p>
                                <p class="font-bold text-gray-900">{{ $fromWarehouse['name'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center justify-center">
                        <i class="fas fa-arrow-right text-blue-600 text-4xl"></i>
                    </div>

                    <!-- To Warehouse -->
                    <div class="bg-white rounded-xl p-4 border-2 border-green-200">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-warehouse text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Destino</p>
                                <p class="font-bold text-gray-900">{{ $toWarehouse['name'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-blue-200">
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Data da Transferência</p>
                        <p class="font-bold text-gray-900">
                            {{ \Carbon\Carbon::parse($first['created_at'])->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Total de Produtos</p>
                        <p class="font-bold text-blue-600 text-2xl">{{ $totalProducts / 2 }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Responsável</p>
                        <p class="font-bold text-gray-900">
                            {{ $first['user']['name'] ?? 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b-2 border-gray-200">
                    <h4 class="font-bold text-gray-900 flex items-center">
                        <i class="fas fa-boxes mr-2 text-blue-600"></i>
                        Produtos Transferidos
                    </h4>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Código</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Custo Unit.</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @php
                            // Mostrar apenas os produtos com quantidade positiva (destino)
                            $transferProducts = collect($selectedBatchDetails)->where('quantity', '>', 0);
                        @endphp
                        @foreach($transferProducts as $detail)
                            <tr class="hover:bg-blue-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center mr-3">
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
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-lg text-green-600">
                                        {{ number_format($detail['quantity'], 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                    {{ number_format($detail['unit_cost'], 2) }} Kz
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-gray-900">
                                        {{ number_format($detail['quantity'] * $detail['unit_cost'], 2) }} Kz
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-bold text-gray-900">
                                TOTAL GERAL:
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-bold text-xl text-blue-600">
                                    @php
                                        $total = $transferProducts->sum(function($item) {
                                            return $item['quantity'] * $item['unit_cost'];
                                        });
                                    @endphp
                                    {{ number_format($total, 2) }} Kz
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Notes -->
            @if($first['notes'])
                <div class="mt-6 bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-sticky-note text-blue-600 mr-2"></i>
                        Observações
                    </p>
                    <p class="text-sm text-gray-700">{{ $first['notes'] }}</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end">
            <button type="button" wire:click="$set('showDetailsModal', false)"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>
@endif

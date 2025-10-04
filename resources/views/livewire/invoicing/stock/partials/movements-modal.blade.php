<!-- Movements Modal -->
@if($showMovementsModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-history mr-2"></i>
                Histórico de Movimentos
            </h3>
            <button wire:click="$set('showMovementsModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4 mb-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-box text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Produto</p>
                        <p class="text-lg font-bold text-gray-900">{{ $movementsProductName }}</p>
                    </div>
                </div>
            </div>

            <!-- Movements Table -->
            @php
                $movements = \App\Models\Invoicing\StockMovement::where('tenant_id', activeTenantId())
                    ->where('product_id', $movementsProductId)
                    ->with(['warehouse', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            @endphp

            @if($movements->count() > 0)
                <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Armazém</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Observações</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Usuário</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($movements as $movement)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                        {{ $movement->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($movement->type == 'in')
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-arrow-down mr-1"></i>Entrada
                                            </span>
                                        @elseif($movement->type == 'out')
                                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-arrow-up mr-1"></i>Saída
                                            </span>
                                        @elseif($movement->type == 'transfer')
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-exchange-alt mr-1"></i>Transferência
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-adjust mr-1"></i>Ajuste
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $movement->warehouse->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-lg font-bold {{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $movement->notes ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $movement->user->name ?? 'Sistema' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    @php
                        $totalIn = $movements->where('quantity', '>', 0)->sum('quantity');
                        $totalOut = abs($movements->where('quantity', '<', 0)->sum('quantity'));
                        $netChange = $totalIn - $totalOut;
                    @endphp
                    
                    <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Total Entradas</p>
                        <p class="text-2xl font-bold text-green-600">+{{ number_format($totalIn, 2) }}</p>
                    </div>
                    
                    <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Total Saídas</p>
                        <p class="text-2xl font-bold text-red-600">-{{ number_format($totalOut, 2) }}</p>
                    </div>
                    
                    <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Variação Líquida</p>
                        <p class="text-2xl font-bold {{ $netChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $netChange >= 0 ? '+' : '' }}{{ number_format($netChange, 2) }}
                        </p>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-history text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhum movimento registado para este produto</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end border-t border-gray-200">
            <button 
                type="button" 
                wire:click="$set('showMovementsModal', false)" 
                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>
@endif

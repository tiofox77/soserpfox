<!-- Transfer Details Modal -->
@if($showDetailsModal && count($selectedBatchDetails) > 0 && $selectedBatchDetails[0]['type'] == 'transfer')
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-[calc(100%-1rem)] sm:w-full max-h-[94vh] overflow-y-auto transform transition-all animate-scale-in">
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
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-center">
                    <!-- From Warehouse -->
                    <div class="bg-white rounded-xl p-4 border-2 border-red-200">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-warehouse text-white"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Origem</p>
                                <p class="font-bold text-gray-900">{{ $fromWarehouse['name'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center justify-center px-2">
                        <i class="fas fa-arrow-right text-blue-600 text-4xl"></i>
                    </div>

                    <!-- To Warehouse -->
                    <div class="bg-white rounded-xl p-4 border-2 border-green-200">
                        <div class="flex items-center">
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

                @if(!empty($first['batch_reference']))
                    <div class="mt-4 pt-4 border-t border-blue-200 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Referência</p>
                            <p class="font-mono font-bold text-indigo-700">{{ $first['batch_reference'] }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('invoicing.stock.batch-preview', ['reference' => $first['batch_reference']]) }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 px-3 py-2 bg-white border-2 border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $first['batch_reference']]) }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                @endif
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
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase align-bottom">Produto</th>
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase align-bottom">Código</th>
                            <th rowspan="2" class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase align-bottom">Quantidade</th>
                            <th colspan="2" class="px-4 py-2 text-center text-xs font-bold text-red-700 uppercase border-l">Origem</th>
                            <th colspan="2" class="px-4 py-2 text-center text-xs font-bold text-green-700 uppercase border-l">Destino</th>
                            <th rowspan="2" class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase align-bottom border-l">Total</th>
                        </tr>
                        <tr class="text-[11px] text-gray-500 uppercase">
                            <th class="px-4 py-1 text-right font-semibold border-l">Antes</th>
                            <th class="px-4 py-1 text-right font-semibold">Ficou</th>
                            <th class="px-4 py-1 text-right font-semibold border-l">Antes</th>
                            <th class="px-4 py-1 text-right font-semibold">Ficou</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @php
                            // Uma linha por artigo, com as duas pernas juntas: a
                            // negativa é a origem, a positiva o destino. Antes
                            // mostrava-se só a positiva, e por isso não havia
                            // forma de ver com quanto a origem tinha ficado.
                            $porProduto = collect($selectedBatchDetails)->groupBy('product_id');

                            $qtdFmt = fn ($v) => $v === null
                                ? '—'
                                : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
                        @endphp
                        @foreach($porProduto as $pernas)
                            @php
                                $saidaMov   = collect($pernas)->first(fn ($d) => (float) $d['quantity'] < 0);
                                $entradaMov = collect($pernas)->first(fn ($d) => (float) $d['quantity'] > 0);
                                $ref        = $entradaMov ?: $saidaMov;
                                $movida     = abs((float) $ref['quantity']);
                            @endphp
                            <tr class="hover:bg-blue-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-white"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-sm text-gray-900">{{ $ref['product']['name'] ?? '(produto removido)' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $ref['product']['code'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-lg text-indigo-600">
                                        {{ $qtdFmt($movida) }}
                                    </span>
                                </td>

                                {{-- Saldos gravados no momento do movimento. As
                                     transferências anteriores a estas colunas
                                     mostram "—" em vez de um número inventado. --}}
                                <td class="px-4 py-3 text-right text-sm text-gray-500 border-l">
                                    {{ $qtdFmt($saidaMov['balance_before'] ?? null) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold {{ isset($saidaMov['balance_after']) && (float) $saidaMov['balance_after'] <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $qtdFmt($saidaMov['balance_after'] ?? null) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500 border-l">
                                    {{ $qtdFmt($entradaMov['balance_before'] ?? null) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-green-700">
                                    {{ $qtdFmt($entradaMov['balance_after'] ?? null) }}
                                </td>

                                <td class="px-4 py-3 text-right border-l">
                                    <span class="font-bold text-gray-900">
                                        {{ number_format($movida * (float) ($ref['unit_cost'] ?? 0), 2) }} Kz
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="7" class="px-4 py-3 text-right font-bold text-gray-900">
                                TOTAL GERAL:
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-bold text-xl text-blue-600">
                                    @php
                                        // Só a perna positiva entra na soma: a negativa é
                                        // o mesmo artigo do outro lado e contá-la duplicava
                                        // o valor da transferência.
                                        $total = collect($selectedBatchDetails)
                                            ->filter(fn ($d) => (float) $d['quantity'] > 0)
                                            ->sum(fn ($d) => (float) $d['quantity'] * (float) ($d['unit_cost'] ?? 0));
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

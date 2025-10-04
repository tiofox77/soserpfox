<div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-hand-holding-usd mr-3 text-orange-600"></i>
        Contas a Receber
    </h3>

    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-orange-100 to-amber-200 rounded-xl p-6">
            <p class="text-sm font-semibold text-orange-700 uppercase">Total a Receber</p>
            <p class="text-3xl font-bold text-orange-800 mt-2">{{ number_format($totalReceivables, 2) }} AOA</p>
            <p class="text-xs text-orange-600 mt-1">{{ count($receivables) }} fatura(s)</p>
        </div>
        <div class="bg-gradient-to-br from-red-100 to-rose-200 rounded-xl p-6">
            <p class="text-sm font-semibold text-red-700 uppercase">Vencidas</p>
            <p class="text-3xl font-bold text-red-800 mt-2">{{ number_format($totalOverdue, 2) }} AOA</p>
            <p class="text-xs text-red-600 mt-1">{{ $receivables->where('overdue', true)->count() }} fatura(s)</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-orange-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Fatura</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Data</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Vencimento</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Pago</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Saldo</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($receivables as $item)
                <tr class="hover:bg-orange-50 {{ $item['overdue'] ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3">
                        <span class="font-bold text-orange-600">{{ $item['invoice_number'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $item['client'] }}</td>
                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                        {{ $item['invoice_date']->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm {{ $item['overdue'] ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                            {{ $item['due_date'] ? $item['due_date']->format('d/m/Y') : '-' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">
                        {{ number_format($item['total'], 2) }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">
                        {{ number_format($item['paid'], 2) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-lg font-bold {{ $item['overdue'] ? 'text-red-600' : 'text-orange-600' }}">
                            {{ number_format($item['balance'], 2) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($item['overdue'])
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Vencida
                            </span>
                        @else
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                <i class="fas fa-clock mr-1"></i>Pendente
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>Nenhuma conta a receber no período</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if(count($receivables) > 0)
            <tfoot class="bg-orange-100">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-right font-bold text-gray-800">TOTAL A RECEBER:</td>
                    <td class="px-4 py-3 text-right text-xl font-bold text-orange-700">
                        {{ number_format($totalReceivables, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

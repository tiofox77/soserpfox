<div>
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-clock text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Aging de Clientes</h2><p class="text-indigo-100 text-sm">Antiguidade de saldos por faixa de dias</p></div>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-print mr-1"></i>Imprimir</button>
            <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-6">
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-green-500"><p class="text-xs font-bold text-green-600 uppercase">A Vencer</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['current'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-yellow-500"><p class="text-xs font-bold text-yellow-600 uppercase">1-30 dias</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['days_30'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-orange-500"><p class="text-xs font-bold text-orange-600 uppercase">31-60 dias</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['days_60'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-red-500"><p class="text-xs font-bold text-red-600 uppercase">61-90 dias</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['days_90'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-red-700"><p class="text-xs font-bold text-red-700 uppercase">91-120 dias</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['days_120'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-3 border-l-4 border-purple-700"><p class="text-xs font-bold text-purple-700 uppercase">+120 dias</p><p class="text-sm font-bold mt-1">{{ number_format($bucketTotals['over_120'], 2, ',', '.') }}</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-table mr-2 text-indigo-600"></i>Saldos por Cliente — Total: <span class="text-indigo-700">{{ number_format($bucketTotals['grand'], 2, ',', '.') }} Kz</span></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Cliente</th>
                        <th class="px-3 py-2 text-right text-green-700">A Vencer</th>
                        <th class="px-3 py-2 text-right text-yellow-700">1-30</th>
                        <th class="px-3 py-2 text-right text-orange-700">31-60</th>
                        <th class="px-3 py-2 text-right text-red-700">61-90</th>
                        <th class="px-3 py-2 text-right text-red-800">91-120</th>
                        <th class="px-3 py-2 text-right text-purple-800">+120</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($grouped as $row)
                        <tr class="hover:bg-indigo-50">
                            <td class="px-3 py-2 font-semibold">{{ $row['name'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['current'] > 0 ? number_format($row['current'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['days_30'] > 0 ? number_format($row['days_30'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['days_60'] > 0 ? number_format($row['days_60'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['days_90'] > 0 ? number_format($row['days_90'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['days_120'] > 0 ? number_format($row['days_120'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right text-purple-800 font-bold">{{ $row['over_120'] > 0 ? number_format($row['over_120'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right font-bold text-indigo-700">{{ number_format($row['total'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">Nenhum saldo em aberto</td></tr>
                    @endforelse
                </tbody>
                @if(count($grouped))
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-3 py-2 text-right">TOTAIS:</td>
                        <td class="px-3 py-2 text-right text-green-700">{{ number_format($bucketTotals['current'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-yellow-700">{{ number_format($bucketTotals['days_30'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($bucketTotals['days_60'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-red-700">{{ number_format($bucketTotals['days_90'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-red-800">{{ number_format($bucketTotals['days_120'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-purple-800">{{ number_format($bucketTotals['over_120'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-indigo-700">{{ number_format($bucketTotals['grand'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

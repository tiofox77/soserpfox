<div>
    <div class="mb-6 bg-gradient-to-r from-red-600 to-orange-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-percent text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Mapa de IVA</h2><p class="text-red-100 text-sm">IVA liquidado, dedutível e a entregar</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    <x-report-filters color="red" />

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500">
            <p class="text-xs font-bold text-green-600 uppercase">IVA Liquidado (Vendas)</p>
            <p class="text-2xl font-bold mt-1 text-green-700">{{ number_format($totals['sales_tax'], 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Base tributável: {{ number_format($totals['sales_base'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
            <p class="text-xs font-bold text-orange-600 uppercase">IVA Dedutível (Compras)</p>
            <p class="text-2xl font-bold mt-1 text-orange-700">{{ number_format($totals['purchases_tax'], 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Base tributável: {{ number_format($totals['purchases_base'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $totals['tax_payable'] >= 0 ? 'border-red-500' : 'border-emerald-500' }}">
            <p class="text-xs font-bold {{ $totals['tax_payable'] >= 0 ? 'text-red-600' : 'text-emerald-600' }} uppercase">{{ $totals['tax_payable'] >= 0 ? 'IVA a Entregar' : 'IVA a Recuperar' }}</p>
            <p class="text-2xl font-bold mt-1 {{ $totals['tax_payable'] >= 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format(abs($totals['tax_payable']), 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Liquidado − Dedutível</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-5 py-3 bg-green-50 border-b">
                <h3 class="font-bold text-green-800"><i class="fas fa-arrow-up mr-2"></i>IVA Liquidado por Taxa</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Taxa</th>
                        <th class="px-3 py-2 text-right">Base</th>
                        <th class="px-3 py-2 text-right">IVA</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($salesByRate as $row)
                        <tr>
                            <td class="px-3 py-2 font-semibold">{{ number_format($row->tax_rate, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">{{ number_format($row->base, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-green-700 font-bold">{{ number_format($row->tax, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400 italic">Sem dados</td></tr>
                    @endforelse
                </tbody>
                @if($salesByRate->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-3 py-2">TOTAL</td>
                        <td class="px-3 py-2 text-right">{{ number_format($totals['sales_base'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-green-700">{{ number_format($totals['sales_tax'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-5 py-3 bg-orange-50 border-b">
                <h3 class="font-bold text-orange-800"><i class="fas fa-arrow-down mr-2"></i>IVA Dedutível por Taxa</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Taxa</th>
                        <th class="px-3 py-2 text-right">Base</th>
                        <th class="px-3 py-2 text-right">IVA</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($purchasesByRate as $row)
                        <tr>
                            <td class="px-3 py-2 font-semibold">{{ number_format($row->tax_rate, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">{{ number_format($row->base, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700 font-bold">{{ number_format($row->tax, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400 italic">Sem dados</td></tr>
                    @endforelse
                </tbody>
                @if($purchasesByRate->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-3 py-2">TOTAL</td>
                        <td class="px-3 py-2 text-right">{{ number_format($totals['purchases_base'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($totals['purchases_tax'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

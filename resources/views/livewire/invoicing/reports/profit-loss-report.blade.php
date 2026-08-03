<div>
    <div class="mb-6 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-chart-line text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Lucros e Perdas (DRE)</h2><p class="text-emerald-100 text-sm">Demonstração de resultados do período</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    <x-report-filters color="emerald" />

    {{-- Resultado --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-blue-500">
            <p class="text-xs font-bold text-blue-600 uppercase">Receita Líquida</p>
            <p class="text-3xl font-bold mt-2 text-blue-700">{{ number_format($netRevenue, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Bruto: {{ number_format($grossRevenue, 2, ',', '.') }} − Devoluções: {{ number_format($returns, 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-orange-500">
            <p class="text-xs font-bold text-orange-600 uppercase">Custo (CMV)</p>
            <p class="text-3xl font-bold mt-2 text-orange-700">{{ number_format($cogs, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Custo dos produtos vendidos</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 {{ $netResult >= 0 ? 'border-emerald-500' : 'border-red-500' }}">
            <p class="text-xs font-bold {{ $netResult >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">{{ $netResult >= 0 ? 'Lucro Bruto' : 'Prejuízo' }}</p>
            <p class="text-3xl font-bold mt-2 {{ $netResult >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($netResult, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Margem: <strong>{{ number_format($grossMargin, 1, ',', '.') }}%</strong></p>
        </div>
    </div>

    {{-- DRE Tabular --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
        <div class="px-5 py-3 bg-gradient-to-r from-emerald-50 to-teal-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-table mr-2 text-emerald-600"></i>Demonstração de Resultados</h3>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y">
                <tr><td class="px-5 py-3 font-semibold">(+) Receita Bruta de Vendas</td><td class="px-5 py-3 text-right font-bold">{{ number_format($grossRevenue, 2, ',', '.') }}</td></tr>
                <tr class="text-red-600"><td class="px-5 py-3 pl-10">(−) Descontos concedidos</td><td class="px-5 py-3 text-right">({{ number_format($discounts, 2, ',', '.') }})</td></tr>
                <tr class="text-red-600"><td class="px-5 py-3 pl-10">(−) Devoluções (Notas de Crédito)</td><td class="px-5 py-3 text-right">({{ number_format($returns, 2, ',', '.') }})</td></tr>
                <tr class="bg-blue-50 font-bold"><td class="px-5 py-3">(=) Receita Líquida</td><td class="px-5 py-3 text-right text-blue-700">{{ number_format($netRevenue, 2, ',', '.') }}</td></tr>
                <tr class="text-orange-600"><td class="px-5 py-3 pl-10">(−) Custo dos Produtos Vendidos (CMV)</td><td class="px-5 py-3 text-right">({{ number_format($cogs, 2, ',', '.') }})</td></tr>
                <tr class="bg-{{ $grossProfit >= 0 ? 'emerald' : 'red' }}-50 font-bold text-lg">
                    <td class="px-5 py-3">(=) {{ $grossProfit >= 0 ? 'Lucro Bruto' : 'Prejuízo Bruto' }}</td>
                    <td class="px-5 py-3 text-right text-{{ $grossProfit >= 0 ? 'emerald' : 'red' }}-700">{{ number_format($grossProfit, 2, ',', '.') }}</td>
                </tr>
                <tr><td class="px-5 py-3 text-xs text-gray-500 pl-10">Margem bruta: {{ number_format($grossMargin, 2, ',', '.') }}%</td><td></td></tr>
                <tr class="bg-gray-50"><td class="px-5 py-3 text-xs text-gray-500 italic">Despesas operacionais não modeladas (incluir manualmente na contabilidade)</td><td class="px-5 py-3 text-right text-xs text-gray-500">Referência compras: {{ number_format($expenses, 2, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Evolução --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-chart-area mr-2 text-emerald-600"></i>Evolução dos Últimos 6 Meses</h3>
        </div>
        <div class="p-5">
            @php $maxVal = max(array_map(fn($m) => max($m['revenue'], $m['cogs'], abs($m['profit'])), $monthly)) ?: 1; @endphp
            @foreach($monthly as $m)
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-gray-700 w-16">{{ $m['label'] }}</span>
                        <span class="flex-1 text-right text-gray-500">
                            Rec: <strong class="text-blue-700">{{ number_format($m['revenue'], 0, ',', '.') }}</strong>
                            · CMV: <strong class="text-orange-700">{{ number_format($m['cogs'], 0, ',', '.') }}</strong>
                            · Lucro: <strong class="{{ $m['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($m['profit'], 0, ',', '.') }}</strong>
                        </span>
                    </div>
                    <div class="space-y-1">
                        <div class="h-2 bg-blue-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500" style="width: {{ ($m['revenue'] / $maxVal) * 100 }}%"></div></div>
                        <div class="h-2 bg-orange-100 rounded-full overflow-hidden"><div class="h-full bg-orange-500" style="width: {{ ($m['cogs'] / $maxVal) * 100 }}%"></div></div>
                        <div class="h-2 bg-emerald-100 rounded-full overflow-hidden"><div class="h-full {{ $m['profit'] >= 0 ? 'bg-emerald-500' : 'bg-red-500' }}" style="width: {{ (abs($m['profit']) / $maxVal) * 100 }}%"></div></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

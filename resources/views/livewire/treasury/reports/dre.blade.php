<div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-calculator mr-3 text-green-600"></i>
        DRE - Demonstração do Resultado do Exercício
    </h3>

    <div class="bg-gray-50 rounded-xl p-6 space-y-4">
        {{-- Receita Bruta --}}
        <div class="flex justify-between items-center py-3 border-b-2 border-gray-300">
            <div>
                <p class="font-bold text-gray-800">Receita Bruta de Vendas</p>
                <p class="text-xs text-gray-500">Faturamento total do período</p>
            </div>
            <p class="text-xl font-bold text-gray-800">{{ number_format($grossRevenue, 2) }} AOA</p>
        </div>

        {{-- Deduções --}}
        <div class="flex justify-between items-center py-2 pl-6">
            <p class="text-sm text-gray-600">(-) Deduções e Devoluções</p>
            <p class="text-sm font-semibold text-red-600">({{ number_format($deductions, 2) }} AOA)</p>
        </div>

        {{-- Receita Líquida --}}
        <div class="flex justify-between items-center py-3 bg-blue-100 rounded-lg px-4">
            <p class="font-bold text-blue-800">(=) Receita Líquida</p>
            <p class="text-xl font-bold text-blue-800">{{ number_format($netRevenue, 2) }} AOA</p>
        </div>

        {{-- Custos --}}
        <div class="flex justify-between items-center py-2 pl-6">
            <p class="text-sm text-gray-600">(-) Custos Operacionais (Compras)</p>
            <p class="text-sm font-semibold text-red-600">({{ number_format($operationalCosts, 2) }} AOA)</p>
        </div>

        {{-- Lucro Bruto --}}
        <div class="flex justify-between items-center py-3 bg-green-100 rounded-lg px-4">
            <p class="font-bold text-green-800">(=) Lucro Bruto</p>
            <p class="text-xl font-bold text-green-800">{{ number_format($grossProfit, 2) }} AOA</p>
        </div>

        {{-- Despesas Operacionais --}}
        <div class="pl-6 space-y-2">
            <p class="text-sm font-semibold text-gray-700 mb-2">(-) Despesas Operacionais:</p>
            @forelse($expensesByCategory as $expense)
            <div class="flex justify-between items-center py-1 text-sm">
                <span class="text-gray-600 pl-4">• {{ $expense->category }}</span>
                <span class="font-semibold text-red-600">({{ number_format($expense->total, 2) }} AOA)</span>
            </div>
            @empty
            <p class="text-gray-400 text-xs pl-4">Sem despesas registradas</p>
            @endforelse
            <div class="flex justify-between items-center py-2 border-t border-gray-300 mt-2">
                <span class="font-semibold text-gray-700">Total Despesas:</span>
                <span class="font-bold text-red-600">({{ number_format($totalExpenses, 2) }} AOA)</span>
            </div>
        </div>

        {{-- Lucro Operacional --}}
        <div class="flex justify-between items-center py-3 bg-{{ $operationalProfit >= 0 ? 'green' : 'red' }}-100 rounded-lg px-4">
            <p class="font-bold text-{{ $operationalProfit >= 0 ? 'green' : 'red' }}-800">(=) Lucro Operacional</p>
            <p class="text-xl font-bold text-{{ $operationalProfit >= 0 ? 'green' : 'red' }}-800">{{ number_format($operationalProfit, 2) }} AOA</p>
        </div>

        {{-- Lucro Líquido --}}
        <div class="flex justify-between items-center py-4 bg-gradient-to-r from-{{ $netProfit >= 0 ? 'green' : 'red' }}-600 to-{{ $netProfit >= 0 ? 'emerald' : 'rose' }}-600 rounded-xl px-6 shadow-lg">
            <div>
                <p class="text-white font-bold text-lg">(=) LUCRO LÍQUIDO</p>
                <p class="text-white/80 text-xs">Resultado final do período</p>
            </div>
            <p class="text-3xl font-bold text-white">{{ number_format($netProfit, 2) }} AOA</p>
        </div>

        {{-- Margin --}}
        @if($netRevenue > 0)
        <div class="flex justify-between items-center pt-3 text-sm">
            <span class="text-gray-600">Margem Líquida:</span>
            <span class="font-bold text-{{ $netProfit >= 0 ? 'green' : 'red' }}-600">
                {{ number_format(($netProfit / $netRevenue) * 100, 2) }}%
            </span>
        </div>
        @endif
    </div>
</div>

<div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-chart-line mr-3 text-blue-600"></i>
        Relatório de Fluxo de Caixa
    </h3>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-gray-600 uppercase">Saldo Inicial</p>
            <p class="text-2xl font-bold text-gray-800 mt-2">{{ number_format($initialBalance, 2) }} AOA</p>
        </div>
        <div class="bg-gradient-to-br from-green-100 to-emerald-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-green-700 uppercase">Total Entradas</p>
            <p class="text-2xl font-bold text-green-800 mt-2">{{ number_format($totalIncome, 2) }} AOA</p>
        </div>
        <div class="bg-gradient-to-br from-red-100 to-rose-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-red-700 uppercase">Total Saídas</p>
            <p class="text-2xl font-bold text-red-800 mt-2">{{ number_format($totalExpense, 2) }} AOA</p>
        </div>
        <div class="bg-gradient-to-br from-{{ $finalBalance >= 0 ? 'blue' : 'red' }}-100 to-{{ $finalBalance >= 0 ? 'indigo' : 'rose' }}-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-{{ $finalBalance >= 0 ? 'blue' : 'red' }}-700 uppercase">Saldo Final</p>
            <p class="text-2xl font-bold text-{{ $finalBalance >= 0 ? 'blue' : 'red' }}-800 mt-2">{{ number_format($finalBalance, 2) }} AOA</p>
        </div>
    </div>

    {{-- Detailed Tables --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Income --}}
        <div>
            <h4 class="text-lg font-bold text-green-700 mb-4 flex items-center">
                <i class="fas fa-arrow-down mr-2"></i>Entradas por Categoria
            </h4>
            <div class="bg-green-50 rounded-xl p-4">
                @forelse($incomeByCategory as $category)
                <div class="flex justify-between items-center py-2 border-b border-green-200 last:border-0">
                    <span class="text-sm font-semibold text-gray-700">{{ $category->category ?? 'Sem categoria' }}</span>
                    <span class="text-sm font-bold text-green-600">{{ number_format($category->total, 2) }} AOA</span>
                </div>
                @empty
                <p class="text-center text-gray-400 py-4">Nenhuma entrada no período</p>
                @endforelse
                <div class="flex justify-between items-center pt-3 mt-3 border-t-2 border-green-300">
                    <span class="font-bold text-gray-800">TOTAL</span>
                    <span class="text-xl font-bold text-green-600">{{ number_format($totalIncome, 2) }} AOA</span>
                </div>
            </div>
        </div>

        {{-- Expense --}}
        <div>
            <h4 class="text-lg font-bold text-red-700 mb-4 flex items-center">
                <i class="fas fa-arrow-up mr-2"></i>Saídas por Categoria
            </h4>
            <div class="bg-red-50 rounded-xl p-4">
                @forelse($expenseByCategory as $category)
                <div class="flex justify-between items-center py-2 border-b border-red-200 last:border-0">
                    <span class="text-sm font-semibold text-gray-700">{{ $category->category ?? 'Sem categoria' }}</span>
                    <span class="text-sm font-bold text-red-600">{{ number_format($category->total, 2) }} AOA</span>
                </div>
                @empty
                <p class="text-center text-gray-400 py-4">Nenhuma saída no período</p>
                @endforelse
                <div class="flex justify-between items-center pt-3 mt-3 border-t-2 border-red-300">
                    <span class="font-bold text-gray-800">TOTAL</span>
                    <span class="text-xl font-bold text-red-600">{{ number_format($totalExpense, 2) }} AOA</span>
                </div>
            </div>
        </div>
    </div>
</div>

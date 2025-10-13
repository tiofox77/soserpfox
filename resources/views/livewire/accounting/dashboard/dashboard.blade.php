<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-chart-line mr-3 text-emerald-600"></i>
                    Dashboard Contabilidade
                </h1>
                <p class="text-gray-600 mt-1">Visão geral financeira e contabilística</p>
            </div>
            <div class="flex space-x-2">
                <input type="date" wire:model.live="dateFrom" 
                       class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                <input type="date" wire:model.live="dateTo" 
                       class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        {{-- Total Ativo --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-wallet text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Contas Ativo</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalAssets }}</p>
            <p class="text-xs text-gray-500">Disponibilidades, Clientes, etc</p>
        </div>

        {{-- Total Passivo --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50">
                    <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Contas Passivo</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalLiabilities }}</p>
            <p class="text-xs text-gray-500">Fornecedores, Impostos, etc</p>
        </div>

        {{-- Total Receitas --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-arrow-trend-up text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Contas Receitas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalRevenue }}</p>
            <p class="text-xs text-gray-500">Vendas e Serviços</p>
        </div>

        {{-- Total Gastos --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                    <i class="fas fa-arrow-trend-down text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Contas Gastos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalExpenses }}</p>
            <p class="text-xs text-gray-500">Custos e Despesas</p>
        </div>
    </div>

    {{-- Lançamentos Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Lançamentos</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalMoves }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-book text-indigo-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Lançados</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">{{ $postedMoves }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Rascunhos</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-2">{{ $draftMoves }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-pen-to-square text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Lançamentos Recentes --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-emerald-600"></i>
            Lançamentos Recentes
        </h2>
        
        @if($recentMoves->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Referência</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Diário</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Débito</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Crédito</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($recentMoves as $move)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $move->date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ $move->ref }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $move->journal->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-green-600">
                                {{ number_format($move->total_debit, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-red-600">
                                {{ number_format($move->total_credit, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $move->state === 'posted' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $move->state === 'posted' ? 'Lançado' : 'Rascunho' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg">Nenhum lançamento registrado ainda</p>
                <p class="text-gray-400 text-sm mt-2">Comece criando seu primeiro lançamento contabilístico</p>
            </div>
        @endif
    </div>
</div>

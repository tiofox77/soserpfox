<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-line text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Dashboard Tesouraria</h2>
                    <p class="text-green-100 text-sm mt-1">Visão geral financeira em tempo real</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('period', 'today')" 
                        class="px-4 py-2 {{ $period === 'today' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    Hoje
                </button>
                <button wire:click="$set('period', 'week')" 
                        class="px-4 py-2 {{ $period === 'week' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    Semana
                </button>
                <button wire:click="$set('period', 'month')" 
                        class="px-4 py-2 {{ $period === 'month' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    Mês
                </button>
                <button wire:click="$set('period', 'year')" 
                        class="px-4 py-2 {{ $period === 'year' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    Ano
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Saldo Total --}}
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-blue-100 text-sm font-semibold uppercase tracking-wider">Saldo Total</p>
                    <h3 class="text-3xl font-bold mt-2">{{ number_format($totalBalance, 2) }}</h3>
                    <p class="text-blue-100 text-xs mt-1">AOA</p>
                </div>
                <div class="bg-white/20 p-4 rounded-xl">
                    <i class="fas fa-wallet text-3xl"></i>
                </div>
            </div>
        </div>

        {{-- Entradas --}}
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-green-100 text-sm font-semibold uppercase tracking-wider">
                        Entradas ({{ ucfirst($period) }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ number_format($totalIncome, 2) }}</h3>
                    <p class="text-green-100 text-xs mt-1">AOA</p>
                </div>
                <div class="bg-white/20 p-4 rounded-xl">
                    <i class="fas fa-arrow-down text-3xl"></i>
                </div>
            </div>
        </div>

        {{-- Saídas --}}
        <div class="bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-red-100 text-sm font-semibold uppercase tracking-wider">
                        Saídas ({{ ucfirst($period) }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ number_format($totalExpense, 2) }}</h3>
                    <p class="text-red-100 text-xs mt-1">AOA</p>
                </div>
                <div class="bg-white/20 p-4 rounded-xl">
                    <i class="fas fa-arrow-up text-3xl"></i>
                </div>
            </div>
        </div>

        {{-- Saldo do Período --}}
        <div class="bg-gradient-to-br from-{{ $periodBalance >= 0 ? 'green' : 'red' }}-500 to-{{ $periodBalance >= 0 ? 'emerald' : 'rose' }}-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-white/80 text-sm font-semibold uppercase tracking-wider">
                        Saldo ({{ ucfirst($period) }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ number_format($periodBalance, 2) }}</h3>
                    <p class="text-white/80 text-xs mt-1">AOA</p>
                </div>
                <div class="bg-white/20 p-4 rounded-xl">
                    <i class="fas fa-{{ $periodBalance >= 0 ? 'chart-line' : 'chart-line-down' }} text-3xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráfico e Categorias --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Gráfico --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-area mr-2 text-blue-600"></i>
                Fluxo de Caixa (Últimos 7 Dias)
            </h3>
            <canvas id="cashFlowChart" class="w-full" height="100"></canvas>
        </div>

        {{-- Top Categorias --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-list mr-2 text-purple-600"></i>
                Top Categorias
            </h3>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-semibold text-gray-700 mb-2">Receitas</p>
                    @forelse($topIncomeCategories as $category)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">{{ $category->category ?? 'Sem categoria' }}</span>
                        <span class="text-sm font-bold text-green-600">{{ number_format($category->total, 2) }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">Nenhuma receita</p>
                    @endforelse
                </div>

                <div class="pt-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Despesas</p>
                    @forelse($topExpenseCategories as $category)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">{{ $category->category ?? 'Sem categoria' }}</span>
                        <span class="text-sm font-bold text-red-600">{{ number_format($category->total, 2) }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">Nenhuma despesa</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Caixas e Contas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Caixas --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-cash-register mr-2 text-orange-600"></i>
                Caixas ({{ number_format($totalCashRegisters, 2) }} AOA)
            </h3>
            <div class="space-y-3">
                @forelse($cashRegisters as $cash)
                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $cash->name }}</p>
                        <p class="text-xs text-gray-600">{{ $cash->code }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-orange-600">{{ number_format($cash->current_balance, 2) }}</p>
                        <p class="text-xs text-gray-500">AOA</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-400 text-center py-4">Nenhum caixa cadastrado</p>
                @endforelse
            </div>
        </div>

        {{-- Contas Bancárias --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-university mr-2 text-blue-600"></i>
                Contas Bancárias ({{ number_format($totalBankAccounts, 2) }} AOA)
            </h3>
            <div class="space-y-3">
                @forelse($bankAccounts as $account)
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $account->account_name }}</p>
                        <p class="text-xs text-gray-600">{{ $account->bank->name ?? 'N/A' }} - {{ $account->account_number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-blue-600">{{ number_format($account->current_balance, 2) }}</p>
                        <p class="text-xs text-gray-500">{{ $account->currency }}</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-400 text-center py-4">Nenhuma conta cadastrada</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Transações Recentes --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-gray-600"></i>
            Transações Recentes
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Categoria</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Descrição</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentTransactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $transaction->transaction_date->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-bold rounded-full {{ $transaction->type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $transaction->type === 'income' ? 'Entrada' : 'Saída' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $transaction->category ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ Str::limit($transaction->description, 50) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-lg font-bold {{ $transaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($transaction->amount, 2) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                            Nenhuma transação encontrada
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Chart.js Script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('cashFlowChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($chartData['labels']),
                        datasets: [
                            {
                                label: 'Entradas',
                                data: @json($chartData['income']),
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Saídas',
                                data: @json($chartData['expense']),
                                borderColor: 'rgb(239, 68, 68)',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += new Intl.NumberFormat('pt-AO', { 
                                            style: 'currency', 
                                            currency: 'AOA' 
                                        }).format(context.parsed.y);
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-AO').format(value) + ' Kz';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</div>

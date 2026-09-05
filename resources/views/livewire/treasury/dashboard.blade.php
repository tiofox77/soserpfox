<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-line text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">{{ __('Dashboard Tesouraria') }}</h2>
                    <p class="text-green-100 text-sm mt-1">{{ __('Visão geral financeira em tempo real') }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('period', 'today')" 
                        class="px-4 py-2 {{ $period === 'today' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    {{ __('Hoje') }}
                </button>
                <button wire:click="$set('period', 'week')" 
                        class="px-4 py-2 {{ $period === 'week' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    {{ __('Semana') }}
                </button>
                <button wire:click="$set('period', 'month')" 
                        class="px-4 py-2 {{ $period === 'month' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    {{ __('Mês') }}
                </button>
                <button wire:click="$set('period', 'year')" 
                        class="px-4 py-2 {{ $period === 'year' ? 'bg-white text-green-600' : 'bg-white/20 text-white' }} rounded-lg font-semibold transition">
                    {{ __('Ano') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Guia de leitura financeira --}}
    <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="font-bold text-blue-900"><i class="fas fa-compass mr-2"></i>Como acompanhar o dinheiro da empresa</h3>
                <p class="text-sm text-blue-800 mt-1"><b>Facturado</b> é o valor dos documentos emitidos. <b>Recebido/Pago</b> é o dinheiro que já entrou ou saiu. <b>Saldo</b> é onde o dinheiro está agora: caixas + contas bancárias.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('treasury.payment-methods') }}" class="px-3 py-2 rounded-lg bg-white border border-blue-300 text-blue-700 text-sm font-semibold"><i class="fas fa-route mr-1"></i>Configurar destinos</a>
                <a href="{{ route('treasury.transactions') }}" class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold"><i class="fas fa-search-dollar mr-1"></i>Ver movimentos</a>
                <a href="{{ route('treasury.reports') }}" class="px-3 py-2 rounded-lg bg-white border border-blue-300 text-blue-700 text-sm font-semibold"><i class="fas fa-file-alt mr-1"></i>Relatórios</a>
            </div>
        </div>
        @if($unallocatedMovements || $unconfiguredMethods)
            <div class="mt-4 rounded-xl bg-amber-100 border border-amber-300 px-4 py-3 text-sm text-amber-900">
                <b>Atenção:</b>
                @if($unconfiguredMethods) {{ $unconfiguredMethods }} método(s) de pagamento sem destino padrão. @endif
                @if($unallocatedMovements) {{ $unallocatedMovements }} movimento(s) histórico(s) sem conta ou caixa associado. @endif
                Configure os destinos para os próximos recebimentos ficarem rastreados correctamente.
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs uppercase font-bold text-gray-500">Vendas facturadas</p><p class="text-2xl font-bold text-indigo-700">{{ valorProtegido($invoicedVolume, 'treasury.reports.view') }} Kz</p><p class="text-xs text-gray-500">Volume documental do período</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs uppercase font-bold text-gray-500">Recebido de clientes</p><p class="text-2xl font-bold text-emerald-700">{{ valorProtegido($salesCollected, 'treasury.reports.view') }} Kz</p><p class="text-xs {{ $receivable > 0 ? 'text-amber-600' : 'text-gray-500' }}">Por receber: {{ valorProtegido($receivable, 'treasury.reports.view') }} Kz</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs uppercase font-bold text-gray-500">Compras registadas</p><p class="text-2xl font-bold text-slate-700">{{ valorProtegido($purchasedVolume, 'treasury.reports.view') }} Kz</p><p class="text-xs text-gray-500">Obrigações com fornecedores</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs uppercase font-bold text-gray-500">Pago a fornecedores</p><p class="text-2xl font-bold text-rose-700">{{ valorProtegido($suppliersPaid, 'treasury.reports.view') }} Kz</p><p class="text-xs {{ $payable > 0 ? 'text-amber-600' : 'text-gray-500' }}">Por pagar: {{ valorProtegido($payable, 'treasury.reports.view') }} Kz</p></div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Saldo Total --}}
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-blue-100 text-sm font-semibold uppercase tracking-wider">Saldo Total</p>
                    <h3 class="text-3xl font-bold mt-2">{{ valorProtegido($totalBalance, 'treasury.reports.view') }}</h3>
                    <p class="text-blue-100 text-xs mt-1">AOA</p>
                </div>
                <div class="bg-white/20 p-4 rounded-xl">
                    <i class="fas fa-wallet text-3xl"></i>
                </div>
            </div>
        </div>

        {{-- Bloco de PHP e nao a forma de uma linha: o match tem quatro ramos
             e a forma curta parte a compilacao do Blade. --}}
        @php
            $__periodo = match ($period) {
                'week'  => __('Semana'),
                'month' => __('Mês'),
                'year'  => __('Ano'),
                default => __('Hoje'),
            };
        @endphp

        {{-- Entradas --}}
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-green-100 text-sm font-semibold uppercase tracking-wider">
                        {{ __('Entradas') }} ({{ $__periodo }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ valorProtegido($totalIncome, 'treasury.reports.view') }}</h3>
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
                        {{ __('Saídas') }} ({{ $__periodo }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ valorProtegido($totalExpense, 'treasury.reports.view') }}</h3>
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
                        {{ __('Saldo') }} ({{ $__periodo }})
                    </p>
                    <h3 class="text-3xl font-bold mt-2">{{ valorProtegido($periodBalance, 'treasury.reports.view') }}</h3>
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
                        <span class="text-sm font-bold text-green-600">{{ valorProtegido($category->total, 'treasury.reports.view') }}</span>
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
                        <span class="text-sm font-bold text-red-600">{{ valorProtegido($category->total, 'treasury.reports.view') }}</span>
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
                Caixas ({{ valorProtegido($totalCashRegisters, 'treasury.reports.view') }} AOA)
            </h3>
            <div class="space-y-3">
                @forelse($cashRegisters as $cash)
                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $cash->name }}</p>
                        <p class="text-xs text-gray-600">{{ $cash->code }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-orange-600">{{ valorProtegido($cash->current_balance, 'treasury.reports.view') }}</p>
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
                Contas Bancárias ({{ valorProtegido($totalBankAccounts, 'treasury.reports.view') }} AOA)
            </h3>
            <div class="space-y-3">
                @forelse($bankAccounts as $account)
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $account->account_name }}</p>
                        <p class="text-xs text-gray-600">{{ $account->bank->name ?? 'N/A' }} - {{ $account->account_number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-blue-600">{{ valorProtegido($account->current_balance, 'treasury.reports.view') }}</p>
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
                                {{ valorProtegido($transaction->amount, 'treasury.reports.view') }}
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

    {{-- O gráfico. A base da casa traz o Chart.js local, a paleta e o
         formato de números da língua de quem está a ver. --}}
    @include('partials.graficos')
    <script>
        sosDesenhar(function () {
            sosGrafico('cashFlowChart', {
                    type: 'line',
                    data: {
                        labels: @json($chartData['labels']),
                        datasets: [
                            {
                                label: @json(__('Entradas')),
                                data: @json($chartData['income']),
                                borderColor: SOS_ESTADOS.bom,
                                backgroundColor: SOS_ESTADOS.bom + '1f',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: @json(__('Saídas')),
                                data: @json($chartData['expense']),
                                borderColor: SOS_ESTADOS.critico,
                                backgroundColor: SOS_ESTADOS.critico + '1f',
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
                                        label += sosMoeda(context.parsed.y);
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
                                        return sosNumero(value) + ' Kz';
                                    }
                                }
                            }
                        }
                    }
                });
        });
    </script>
</div>

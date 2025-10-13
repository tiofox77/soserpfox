<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Extrato Financeiro</h2>
                    <p class="text-amber-100 text-sm">Acompanhe suas movimentações e saldo</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 stagger-animation">
        {{-- Saldo Devedor --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50 icon-float">
                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Saldo Devedor</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($stats['balance_due'], 2, ',', '.') }} Kz</p>
            <p class="text-xs text-gray-500">Total a pagar</p>
        </div>

        {{-- Faturas Atrasadas --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Faturas Atrasadas</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ $stats['overdue_count'] }}</p>
            <p class="text-xs text-gray-500">{{ number_format($stats['overdue_amount'], 2, ',', '.') }} Kz</p>
        </div>

        {{-- Parcialmente Pagas --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-hand-holding-usd text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Parcialmente Pagas</p>
            <p class="text-2xl font-bold text-gray-900 mb-1">{{ number_format($stats['partially_paid'], 2, ',', '.') }} Kz</p>
            <p class="text-xs text-green-600">✓ Pago: {{ number_format($stats['partially_paid_amount'], 2, ',', '.') }} Kz</p>
        </div>

        {{-- Total Pago --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-double text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Total Pago</p>
            <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($stats['total_paid'], 2, ',', '.') }} Kz</p>
            <p class="text-xs text-gray-500">Faturas quitadas</p>
        </div>
    </div>

    {{-- Timeline de Pagamentos --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <div class="flex items-center mb-6">
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                <i class="fas fa-chart-bar text-white text-xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Histórico dos Últimos 6 Meses</h3>
        </div>

        <div class="grid grid-cols-6 gap-4">
            @foreach($timeline as $month)
                <div class="text-center">
                    <div class="mb-2">
                        <div class="h-32 bg-gray-100 rounded-lg relative overflow-hidden">
                            @php
                                $maxValue = max(array_column($timeline, 'paid')) ?: 1;
                                $paidHeight = ($month['paid'] / $maxValue) * 100;
                                $pendingHeight = ($month['pending'] / $maxValue) * 100;
                            @endphp
                            
                            {{-- Barra Paga (Verde) --}}
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-green-500 to-emerald-400 transition-all duration-500"
                                 style="height: {{ $paidHeight }}%"></div>
                            
                            {{-- Barra Pendente (Vermelho) sobreposta --}}
                            @if($month['pending'] > 0)
                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-red-500 to-orange-400 opacity-60 transition-all duration-500"
                                     style="height: {{ $pendingHeight }}%"></div>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-gray-700">{{ $month['month'] }}</p>
                    <p class="text-xs text-green-600">{{ number_format($month['paid']/1000, 0) }}k</p>
                    @if($month['pending'] > 0)
                        <p class="text-xs text-red-600">{{ number_format($month['pending']/1000, 0) }}k</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex items-center justify-center gap-6 text-sm">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-gradient-to-br from-green-500 to-emerald-400 rounded mr-2"></div>
                <span class="text-gray-600">Pagas</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-gradient-to-br from-red-500 to-orange-400 rounded mr-2"></div>
                <span class="text-gray-600">Pendentes</span>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-amber-600"></i>
                Filtros Avançados
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar mr-1"></i>Período
                </label>
                <select wire:model.live="periodFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 appearance-none bg-white text-sm">
                    <option value="all">📅 Todos os períodos</option>
                    <option value="month">📆 Este mês</option>
                    <option value="quarter">🗓️ Este trimestre</option>
                    <option value="year">📊 Este ano</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-tag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 appearance-none bg-white text-sm">
                    <option value="">Todos os status</option>
                    <option value="pending">⏳ Pendente</option>
                    <option value="partially_paid">💰 Parcialmente Pago</option>
                    <option value="paid">✅ Pago</option>
                    <option value="overdue">⚠️ Atrasado</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Transações --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-amber-600"></i>
                Movimentações Detalhadas
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Data Emissão</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Valor Pago</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Saldo</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600">{{ $invoice->invoice_date->format('d/m/Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm {{ $invoice->due_date && $invoice->due_date->isPast() && $invoice->status !== 'paid' ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-bold text-gray-900">{{ number_format($invoice->total ?? 0, 2, ',', '.') }} Kz</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-green-600">{{ number_format($invoice->paid_amount ?? 0, 2, ',', '.') }} Kz</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $balance = ($invoice->total ?? 0) - ($invoice->paid_amount ?? 0);
                                @endphp
                                <span class="text-sm font-semibold {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ number_format($balance, 2, ',', '.') }} Kz
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($invoice->status === 'paid')
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        ✅ Paga
                                    </span>
                                @elseif($invoice->status === 'partially_paid')
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        💰 Parcial
                                    </span>
                                @elseif($invoice->status === 'pending' && $invoice->due_date && $invoice->due_date->isPast())
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        ⚠️ Atrasada
                                    </span>
                                @elseif($invoice->status === 'pending')
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                        ⏳ Pendente
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p class="text-sm">Nenhuma movimentação encontrada</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>

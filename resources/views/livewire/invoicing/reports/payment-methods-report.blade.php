<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-money-check-alt text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Recebimentos por Meio de Pagamento</h1>
                    <p class="text-emerald-100 text-sm">Total recebido por forma de pagamento no período</p>
                </div>
            </div>
            <a href="{{ route('invoicing.reports.hub') }}" class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold">
                <i class="fas fa-arrow-left mr-1"></i>Relatórios
            </a>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Período</label>
                <select wire:model.live="period" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="today">Hoje</option>
                    <option value="week">Esta semana</option>
                    <option value="month">Este mês</option>
                    <option value="quarter">Trimestre</option>
                    <option value="year">Ano</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">De</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Até</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Meio</label>
                <select wire:model.live="method" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach($methods as $m)
                        <option value="{{ $m }}">{{ \App\Livewire\Invoicing\Reports\PaymentMethodsReport::methodLabel($m) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Totais --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-5 border border-emerald-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Total Recebido</p>
            <p class="text-3xl font-bold text-emerald-600">{{ number_format($grandTotal, 2, ',', '.') }} Kz</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-teal-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Nº de Recebimentos</p>
            <p class="text-3xl font-bold text-teal-600">{{ $grandCount }}</p>
        </div>
    </div>

    {{-- Resumo por meio --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
        <div class="px-6 py-4 border-b bg-gray-50"><h3 class="font-bold text-gray-900"><i class="fas fa-chart-pie mr-2 text-emerald-600"></i>Resumo por Meio de Pagamento</h3></div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Meio</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Nº</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($byMethod as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $row['label'] }}</td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ $row['count'] }}</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ number_format($row['total'], 2, ',', '.') }} Kz</td>
                        <td class="px-6 py-3 text-right text-gray-500">{{ $grandTotal > 0 ? number_format($row['total'] / $grandTotal * 100, 1) : '0,0' }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2"></i><p class="text-sm">Sem recebimentos no período</p></td></tr>
                @endforelse
            </tbody>
            @if($byMethod->count() > 0)
            <tfoot class="bg-emerald-50">
                <tr>
                    <td class="px-6 py-3 font-bold">TOTAL</td>
                    <td class="px-6 py-3 text-right font-bold">{{ $grandCount }}</td>
                    <td class="px-6 py-3 text-right font-bold text-emerald-700">{{ number_format($grandTotal, 2, ',', '.') }} Kz</td>
                    <td class="px-6 py-3 text-right font-bold">100%</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Detalhe --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50"><h3 class="font-bold text-gray-900"><i class="fas fa-list mr-2 text-emerald-600"></i>Recebimentos (últimos {{ $receipts->count() }})</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Meio</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($receipts as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-2.5 text-sm text-gray-600">{{ $r->payment_date ? \Carbon\Carbon::parse($r->payment_date)->format('d/m/Y') : '-' }}</td>
                            <td class="px-6 py-2.5 text-sm text-gray-900">{{ $r->client_name ?: '—' }}</td>
                            <td class="px-6 py-2.5 text-sm text-gray-600">{{ \App\Livewire\Invoicing\Reports\PaymentMethodsReport::methodLabel($r->payment_method) }}</td>
                            <td class="px-6 py-2.5 text-sm text-right font-semibold text-gray-900">{{ number_format($r->amount_paid ?? 0, 2, ',', '.') }} Kz</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400">Sem recebimentos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

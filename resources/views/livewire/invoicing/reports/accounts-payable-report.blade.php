<div>
    <div class="mb-6 bg-gradient-to-r from-red-600 to-pink-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-money-bill-wave text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Contas a Pagar') }}</h2><p class="text-red-100 text-sm">{{ __('Faturas de compra pendentes') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <div class="bg-white rounded-2xl shadow-md p-5 mb-6 border-l-4 border-red-500 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h3 class="font-bold text-gray-800"><i class="fas fa-filter mr-2 text-red-600"></i>{{ __('Filtro') }}</h3>
            <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="open">{{ __('Todas em Aberto') }}</option>
                <option value="overdue">{{ __('Apenas Vencidas') }}</option>
                <option value="current">{{ __('Apenas A Vencer') }}</option>
            </select>
        </div>
        <button onclick="window.print()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition"><i class="fas fa-print mr-1"></i>{{ __('Imprimir') }}</button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500"><p class="text-xs font-bold text-red-600 uppercase">{{ __('Documentos') }}</p><p class="text-2xl font-bold mt-1">{{ $invoices->count() }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-gray-500"><p class="text-xs font-bold text-gray-600 uppercase">{{ __('Total') }}</p><p class="text-lg font-bold mt-1">{{ number_format($totals['total'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-500"><p class="text-xs font-bold text-emerald-600 uppercase">{{ __('Já Pago') }}</p><p class="text-lg font-bold mt-1 text-emerald-700">{{ number_format($totals['paid'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500"><p class="text-xs font-bold text-orange-600 uppercase">{{ __('Saldo a Pagar') }}</p><p class="text-lg font-bold mt-1 text-orange-700">{{ number_format($totals['balance'], 2, ',', '.') }}</p></div>
    </div>

    @if($overdueTotal > 0)
    <div class="mb-4 bg-orange-50 border-l-4 border-orange-500 p-4 rounded-lg flex items-center">
        <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mr-3"></i>
        <div>
            <p class="font-bold text-orange-900">{{ __('Existem faturas com pagamento vencido') }}</p>
            <p class="text-sm text-orange-700">{{ __('Total vencido:') }} <strong>{{ number_format($overdueTotal, 2, ',', '.') }} Kz</strong></p>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Nº Fatura') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Fornecedor') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Emissão') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Vencimento') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Dias') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Pago') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Saldo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($invoices as $inv)
                        @php
                            $balance = $inv->total - ($inv->paid_amount ?? 0);
                            $isOverdue = $inv->due_date && $inv->due_date->lt($today);
                            $days = $inv->due_date ? $today->diffInDays($inv->due_date, false) : null;
                        @endphp
                        <tr class="{{ $isOverdue ? 'bg-orange-50/30' : '' }} hover:bg-red-50">
                            <td class="px-3 py-2 font-bold">{{ $inv->invoice_number }}</td>
                            <td class="px-3 py-2">{{ $inv->supplier->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 {{ $isOverdue ? 'text-orange-600 font-bold' : '' }}">{{ $inv->due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $isOverdue ? 'text-orange-600 font-bold' : 'text-gray-600' }}">
                                @if($days !== null){{ $days < 0 ? '-' . abs($days) : '+' . $days }}@else — @endif
                            </td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format($inv->total, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($inv->paid_amount ?? 0, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold text-orange-700">{{ number_format($balance, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Nenhuma fatura em aberto') }}</td></tr>
                    @endforelse
                </tbody>
                @if($invoices->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="5" class="px-3 py-2 text-right">{{ __('TOTAIS:') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($totals['total'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($totals['paid'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($totals['balance'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<div>
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-shopping-cart text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Mapa de Compras</h2><p class="text-orange-100 text-sm">Faturas de compra por período</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    <x-report-filters color="orange">
        <x-slot:extra>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fornecedor</label>
            <select wire:model.live="supplierId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
        </x-slot:extra>
    </x-report-filters>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500"><p class="text-xs font-bold text-blue-600 uppercase">Documentos</p><p class="text-2xl font-bold mt-1">{{ $totals['count'] }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-gray-500"><p class="text-xs font-bold text-gray-600 uppercase">Subtotal</p><p class="text-lg font-bold mt-1">{{ number_format($totals['subtotal'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500"><p class="text-xs font-bold text-purple-600 uppercase">IVA</p><p class="text-lg font-bold mt-1">{{ number_format($totals['tax'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500"><p class="text-xs font-bold text-orange-600 uppercase">Total</p><p class="text-lg font-bold mt-1 text-orange-700">{{ number_format($totals['total'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500"><p class="text-xs font-bold text-red-600 uppercase">A Pagar</p><p class="text-lg font-bold mt-1 text-red-700">{{ number_format($totals['pending'], 2, ',', '.') }}</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b flex items-center justify-between">
            <h3 class="font-bold text-gray-800"><i class="fas fa-list mr-2 text-orange-600"></i>Faturas no Período</h3>
            <span class="text-xs text-gray-500">{{ $invoices->count() }} resultado(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Nº</th><th class="px-3 py-2 text-left">Data</th>
                        <th class="px-3 py-2 text-left">Fornecedor</th>
                        <th class="px-3 py-2 text-right">Subtotal</th><th class="px-3 py-2 text-right">IVA</th>
                        <th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-right">Pago</th>
                        <th class="px-3 py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-orange-50">
                            <td class="px-3 py-2 font-bold">{{ $inv->invoice_number }}</td>
                            <td class="px-3 py-2">{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2">{{ $inv->supplier->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($inv->subtotal, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($inv->tax_amount, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format($inv->total, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($inv->paid_amount ?? 0, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">{{ $inv->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">Sem compras no período</td></tr>
                    @endforelse
                </tbody>
                @if($invoices->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="3" class="px-3 py-2 text-right">TOTAIS:</td>
                        <td class="px-3 py-2 text-right">{{ number_format($totals['subtotal'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($totals['tax'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($totals['total'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($totals['paid'], 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

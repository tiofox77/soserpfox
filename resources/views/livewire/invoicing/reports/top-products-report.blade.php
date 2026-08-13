<div>
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-star text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Top Produtos Vendidos') }}</h2><p class="text-purple-100 text-sm">{{ __('Produtos com maior volume e receita') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <x-report-filters color="purple">
        <x-slot:extra>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Limite') }}</label>
            <select wire:model.live="limit" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="10">{{ __('Top 10') }}</option><option value="20">{{ __('Top 20') }}</option><option value="50">{{ __('Top 50') }}</option><option value="100">{{ __('Top 100') }}</option>
            </select>
        </x-slot:extra>
    </x-report-filters>

    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500"><p class="text-xs font-bold text-purple-600 uppercase">{{ __('Qtd Total') }}</p><p class="text-2xl font-bold mt-1">{{ number_format($grandQty, 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-pink-500"><p class="text-xs font-bold text-pink-600 uppercase">{{ __('Valor Total') }}</p><p class="text-2xl font-bold mt-1 text-pink-700">{{ number_format($grandValue, 2, ',', '.') }} Kz</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">{{ __('Produto') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Qtd Total') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Faturas') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Clientes') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Receita') }}</th>
                        <th class="px-3 py-2">{{ __('Volume') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $idx => $r)
                        @php $pct = $grandQty > 0 ? ($r->total_qty / $grandQty * 100) : 0; @endphp
                        <tr class="hover:bg-purple-50">
                            <td class="px-3 py-2 text-gray-500 font-semibold">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->name ?? 'Produto removido' }}</p>
                                @if($r->sku)<p class="text-xs text-gray-500">{{ $r->sku }}</p>@endif
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-purple-700">{{ number_format($r->total_qty, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->invoices_count }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->clients_count }}</td>
                            <td class="px-3 py-2 text-right font-bold text-pink-700">{{ number_format($r->total_value, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 w-1/5">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-purple-400 to-pink-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Sem dados no período') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

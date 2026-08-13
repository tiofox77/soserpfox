<div>
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-amber-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-truck text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Top Fornecedores') }}</h2><p class="text-orange-100 text-sm">{{ __('Ranking por valor comprado') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <x-report-filters color="orange">
        <x-slot:extra>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Limite') }}</label>
            <select wire:model.live="limit" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="10">{{ __('Top 10') }}</option><option value="20">{{ __('Top 20') }}</option><option value="50">{{ __('Top 50') }}</option><option value="100">{{ __('Top 100') }}</option>
            </select>
        </x-slot:extra>
    </x-report-filters>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gradient-to-r from-orange-50 to-amber-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-trophy mr-2 text-orange-600"></i>{{ __('Ranking — Total comprado:') }} <span class="text-orange-700">{{ number_format($grandTotal, 2, ',', '.') }} Kz</span></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">{{ __('Fornecedor') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Faturas') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Comprado') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Pago') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('% do Total') }}</th>
                        <th class="px-3 py-2">{{ __('Participação') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $idx => $r)
                        @php $pct = $grandTotal > 0 ? ($r->total_value / $grandTotal * 100) : 0; @endphp
                        <tr class="hover:bg-orange-50">
                            <td class="px-3 py-2">
                                @if($idx < 3)
                                    <span class="inline-flex w-7 h-7 rounded-full bg-gradient-to-br {{ ['from-yellow-400 to-amber-500','from-gray-300 to-gray-400','from-orange-400 to-orange-500'][$idx] }} text-white font-bold items-center justify-center">{{ $idx + 1 }}</span>
                                @else
                                    <span class="text-gray-500 font-semibold">{{ $idx + 1 }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-semibold">{{ $r->supplier->name ?? 'Fornecedor removido' }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->invoices_count }}</td>
                            <td class="px-3 py-2 text-right font-bold text-orange-700">{{ number_format($r->total_value, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($r->total_paid ?? 0, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format($pct, 1, ',', '.') }}%</td>
                            <td class="px-3 py-2 w-1/4">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-orange-400 to-amber-500" style="width: {{ $pct }}%"></div>
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

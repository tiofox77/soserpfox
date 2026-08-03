<div>
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-percentage text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Análise de Margem por Produto</h2><p class="text-cyan-100 text-sm">Lucro/prejuízo por produto vendido</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    <x-report-filters color="cyan">
        <x-slot:extra>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ordenar por</label>
            <select wire:model.live="sortBy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="profit_desc">Maior lucro</option>
                <option value="profit_asc">Maior prejuízo</option>
                <option value="margin_desc">Maior margem %</option>
                <option value="margin_asc">Menor margem %</option>
                <option value="revenue_desc">Maior receita</option>
            </select>
        </x-slot:extra>
    </x-report-filters>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500"><p class="text-xs font-bold text-blue-600 uppercase">Receita</p><p class="text-lg font-bold mt-1">{{ number_format($totals['revenue'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500"><p class="text-xs font-bold text-orange-600 uppercase">Custo</p><p class="text-lg font-bold mt-1">{{ number_format($totals['cost'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $totals['profit'] >= 0 ? 'border-emerald-500' : 'border-red-500' }}">
            <p class="text-xs font-bold {{ $totals['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">{{ $totals['profit'] >= 0 ? 'Lucro Total' : 'Prejuízo Total' }}</p>
            <p class="text-lg font-bold mt-1 {{ $totals['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($totals['profit'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500">
            <p class="text-xs font-bold text-purple-600 uppercase">Margem Média</p>
            <p class="text-lg font-bold mt-1 text-purple-700">{{ number_format($totals['margin'], 1, ',', '.') }}%</p>
            @if($lossCount > 0)<p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle"></i> {{ $lossCount }} produto(s) com prejuízo</p>@endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Produto</th>
                        <th class="px-3 py-2 text-center">Tipo</th>
                        <th class="px-3 py-2 text-right">Qtd</th>
                        <th class="px-3 py-2 text-right">Preço Méd.</th>
                        <th class="px-3 py-2 text-right">Custo Unit.</th>
                        <th class="px-3 py-2 text-right">Receita</th>
                        <th class="px-3 py-2 text-right">Custo Total</th>
                        <th class="px-3 py-2 text-right">Lucro</th>
                        <th class="px-3 py-2 text-right">Margem %</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $r)
                        <tr class="hover:bg-cyan-50 {{ $r->profit < 0 ? 'bg-red-50/30' : '' }}">
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->name ?? 'Produto removido' }}</p>
                                @if($r->sku)<p class="text-xs text-gray-500">{{ $r->sku }}</p>@endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $r->type === 'servico' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $r->type === 'servico' ? 'Serviço' : 'Produto' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($r->qty, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($r->avg_price, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">{{ number_format($r->cost ?? 0, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold text-blue-700">{{ number_format($r->revenue, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">{{ number_format($r->total_cost, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($r->profit, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->margin >= 0 ? ($r->margin >= 30 ? 'text-emerald-700' : 'text-amber-600') : 'text-red-700' }}">{{ number_format($r->margin, 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400 italic">Sem vendas no período</td></tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="5" class="px-3 py-2 text-right">TOTAIS:</td>
                        <td class="px-3 py-2 text-right text-blue-700">{{ number_format($totals['revenue'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-orange-700">{{ number_format($totals['cost'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right {{ $totals['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($totals['profit'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-purple-700">{{ number_format($totals['margin'], 1, ',', '.') }}%</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

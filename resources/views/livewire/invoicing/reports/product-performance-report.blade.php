<div>
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-rose-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-chart-pie text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Desempenho de Produtos</h2><p class="text-pink-100 text-sm">Vendas, compras, stock, lucro e rotação</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    <x-report-filters color="pink">
        <x-slot:extra>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipo</label>
                    <select wire:model.live="typeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="produto">Produtos</option>
                        <option value="servico">Serviços</option>
                        <option value="all">Todos</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ordenar</label>
                    <select wire:model.live="sortBy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="profit_desc">Maior lucro</option>
                        <option value="revenue_desc">Maior receita</option>
                        <option value="qty_sold_desc">Mais vendidos</option>
                        <option value="margin_desc">Maior margem</option>
                        <option value="rotation_desc">Maior rotação</option>
                        <option value="stock_asc">Menor stock</option>
                    </select>
                </div>
            </div>
        </x-slot:extra>
    </x-report-filters>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-pink-500"><p class="text-xs font-bold text-pink-600 uppercase">Produtos</p><p class="text-2xl font-bold mt-1">{{ $totals['products_count'] }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500"><p class="text-xs font-bold text-green-600 uppercase">Vendido</p><p class="text-lg font-bold mt-1">{{ number_format($totals['qty_sold'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500"><p class="text-xs font-bold text-orange-600 uppercase">Comprado</p><p class="text-lg font-bold mt-1">{{ number_format($totals['qty_bought'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500"><p class="text-xs font-bold text-blue-600 uppercase">Receita</p><p class="text-lg font-bold mt-1 text-blue-700">{{ number_format($totals['revenue'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $totals['profit'] >= 0 ? 'border-emerald-500' : 'border-red-500' }}"><p class="text-xs font-bold {{ $totals['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">Lucro</p><p class="text-lg font-bold mt-1 {{ $totals['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($totals['profit'], 2, ',', '.') }}</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Produto</th>
                        <th class="px-3 py-2 text-center">Tipo</th>
                        <th class="px-3 py-2 text-right">Preço</th>
                        <th class="px-3 py-2 text-right">Custo</th>
                        <th class="px-3 py-2 text-right">Stock</th>
                        <th class="px-3 py-2 text-right">Vendido</th>
                        <th class="px-3 py-2 text-right">Comprado</th>
                        <th class="px-3 py-2 text-right">Receita</th>
                        <th class="px-3 py-2 text-right">Lucro</th>
                        <th class="px-3 py-2 text-right">Margem</th>
                        <th class="px-3 py-2 text-center">Rotação</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $r)
                        <tr class="hover:bg-pink-50 {{ $r->profit < 0 ? 'bg-red-50/30' : '' }}">
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->name }}</p>
                                @if($r->sku)<p class="text-xs text-gray-500">{{ $r->sku }}</p>@endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $r->type === 'servico' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $r->type === 'servico' ? 'S' : 'P' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($r->price, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">{{ number_format($r->cost ?? 0, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->stock <= 0 ? 'text-red-700' : ($r->stock < 10 ? 'text-amber-600' : 'text-gray-700') }}">{{ number_format($r->stock, 0) }}</td>
                            <td class="px-3 py-2 text-right text-green-700">{{ number_format($r->qty_sold, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">{{ number_format($r->qty_bought, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold text-blue-700">{{ number_format($r->revenue, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($r->profit, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->margin >= 30 ? 'text-emerald-700' : ($r->margin >= 0 ? 'text-amber-600' : 'text-red-700') }}">{{ number_format($r->margin, 1, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-center">
                                @if($r->rotation > 1)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">{{ number_format($r->rotation, 1, ',', '.') }}x</span>
                                @elseif($r->rotation > 0)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ number_format($r->rotation, 2, ',', '.') }}x</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-3 py-8 text-center text-gray-400 italic">Sem movimento no período</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

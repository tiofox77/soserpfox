<div>
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-emerald-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-tags text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Tabela de Preços e Lucro') }}</h2><p class="text-teal-100 text-sm">{{ __('Preço de compra, venda, lucro e margem de todos os produtos') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-md p-5 mb-6 border-l-4 border-teal-500">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800"><i class="fas fa-filter mr-2 text-teal-600"></i>{{ __('Filtros') }}</h3>
            <button onclick="window.print()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold"><i class="fas fa-print mr-1"></i>{{ __('Imprimir') }}</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Pesquisar') }}</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Nome, SKU, código...') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Tipo') }}</label>
                <select wire:model.live="typeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="all">{{ __('Todos') }}</option>
                    <option value="produto">{{ __('Produtos') }}</option>
                    <option value="servico">{{ __('Serviços') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Estado') }}</label>
                <select wire:model.live="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="active">{{ __('Activos') }}</option>
                    <option value="inactive">{{ __('Inactivos') }}</option>
                    <option value="all">{{ __('Todos') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ __('Ordenar') }}</label>
                <select wire:model.live="sortBy" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="name_asc">{{ __('Nome (A-Z)') }}</option>
                    <option value="profit_desc">{{ __('Maior lucro') }}</option>
                    <option value="margin_desc">{{ __('Maior margem') }}</option>
                    <option value="price_desc">{{ __('Preço mais alto') }}</option>
                    <option value="cost_desc">{{ __('Custo mais alto') }}</option>
                    <option value="stock_asc">{{ __('Menor stock') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-teal-500">
            <p class="text-xs font-bold text-teal-600 uppercase">{{ __('Total') }}</p>
            <p class="text-2xl font-bold mt-1">{{ $totals['count'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-amber-500">
            <p class="text-xs font-bold text-amber-600 uppercase">{{ __('Sem Custo') }}</p>
            <p class="text-2xl font-bold mt-1 text-amber-700">{{ $totals['without_cost'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-orange-500">
            <p class="text-xs font-bold text-orange-600 uppercase">{{ __('Sem Preço') }}</p>
            <p class="text-2xl font-bold mt-1 text-orange-700">{{ $totals['without_price'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
            <p class="text-xs font-bold text-red-600 uppercase">{{ __('Margem Negativa') }}</p>
            <p class="text-2xl font-bold mt-1 text-red-700">{{ $totals['negative_margin'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-500">
            <p class="text-xs font-bold text-emerald-600 uppercase">{{ __('Margem Média') }}</p>
            <p class="text-2xl font-bold mt-1 text-emerald-700">{{ number_format($totals['avg_margin'], 1, ',', '.') }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <p class="text-xs font-bold text-blue-600 uppercase">{{ __('Valor Stock (Venda)') }}</p>
            <p class="text-sm font-bold mt-1 text-blue-700">{{ number_format($totals['total_stock_value_sale'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-500">Custo: {{ number_format($totals['total_stock_value_cost'], 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">{{ __('Produto') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Tipo') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Stock') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Preço Compra') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Preço Venda') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Lucro Unit.') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Margem %') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Markup %') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Estado') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $idx => $p)
                        <tr class="hover:bg-teal-50 {{ $p->profit < 0 ? 'bg-red-50/40' : ($p->cost == 0 ? 'bg-amber-50/30' : '') }}">
                            <td class="px-3 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $p->name }}</p>
                                <div class="flex gap-2 text-xs text-gray-500">
                                    @if($p->sku)<span>SKU: {{ $p->sku }}</span>@endif
                                    @if($p->barcode)<span>• {{ $p->barcode }}</span>@endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $p->type === 'servico' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $p->type === 'servico' ? 'Serviço' : 'Produto' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($p->type === 'servico')
                                    <span class="text-gray-400">—</span>
                                @else
                                    <span class="font-bold {{ $p->stock_quantity <= 0 ? 'text-red-700' : ($p->stock_quantity < 10 ? 'text-amber-600' : 'text-gray-700') }}">{{ number_format($p->stock_quantity ?? 0, 0) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($p->cost > 0)
                                    <span class="font-bold text-orange-700">{{ number_format($p->cost, 2, ',', '.') }}</span>
                                @else
                                    <span class="text-xs text-amber-600 italic">{{ __('sem custo') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($p->price > 0)
                                    <span class="font-bold text-blue-700">{{ number_format($p->price, 2, ',', '.') }}</span>
                                @else
                                    <span class="text-xs text-red-600 italic">{{ __('sem preço') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-bold {{ $p->profit > 0 ? 'text-emerald-700' : ($p->profit < 0 ? 'text-red-700' : 'text-gray-400') }}">
                                {{ number_format($p->profit, 2, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($p->price > 0)
                                    <span class="inline-flex px-2 py-1 rounded-lg font-bold text-xs {{ $p->margin >= 30 ? 'bg-emerald-100 text-emerald-800' : ($p->margin >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                        {{ number_format($p->margin, 1, ',', '.') }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-gray-600">
                                @if($p->cost > 0)
                                    {{ number_format($p->markup, 1, ',', '.') }}%
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($p->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">{{ __('Activo') }}</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs">{{ __('Inactivo') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Nenhum produto encontrado') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-500 mt-3 text-center">
        <i class="fas fa-info-circle mr-1"></i>
        <strong>{{ __('Margem') }}</strong> {{ __('= (Preço − Custo) / Preço &nbsp;•&nbsp;') }} <strong>{{ __('Markup') }}</strong> {{ __('= (Preço − Custo) / Custo') }}
    </p>
</div>

<div>
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-fuchsia-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-concierge-bell text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Mapa de Serviços') }}</h2><p class="text-purple-100 text-sm">{{ __('Análise específica de serviços prestados') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <x-report-filters color="purple" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-500">
            <p class="text-xs font-bold text-purple-600 uppercase">{{ __('Serviços Activos') }}</p>
            <p class="text-2xl font-bold mt-1">{{ $totals['services_count'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($totals['qty'], 0) }} prestações</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <p class="text-xs font-bold text-blue-600 uppercase">{{ __('Receita de Serviços') }}</p>
            <p class="text-lg font-bold mt-1 text-blue-700">{{ number_format($totals['revenue'], 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($servicesShare, 1, ',', '.') }}% das vendas totais</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $totals['profit'] >= 0 ? 'border-emerald-500' : 'border-red-500' }}">
            <p class="text-xs font-bold {{ $totals['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">{{ __('Lucro') }}</p>
            <p class="text-lg font-bold mt-1 {{ $totals['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($totals['profit'], 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Margem:') }} <strong>{{ number_format($totals['margin'], 1, ',', '.') }}%</strong></p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-cyan-500">
            <p class="text-xs font-bold text-cyan-600 uppercase">{{ __('Alcance') }}</p>
            <p class="text-2xl font-bold mt-1 text-cyan-700">{{ $totals['invoices'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('faturas com serviços') }}</p>
        </div>
    </div>

    {{-- Peso dos serviços --}}
    <div class="bg-white rounded-2xl shadow p-5 mb-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-gray-800"><i class="fas fa-chart-pie mr-2 text-purple-600"></i>{{ __('Peso dos Serviços vs. Total de Vendas') }}</h3>
            <span class="text-2xl font-bold text-purple-700">{{ number_format($servicesShare, 1, ',', '.') }}%</span>
        </div>
        <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-purple-500 to-fuchsia-500" style="width: {{ min(100, $servicesShare) }}%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-500 mt-1">
            <span>Serviços: {{ number_format($totals['revenue'], 2, ',', '.') }} Kz</span>
            <span>Total vendas: {{ number_format($totalRevenueAll, 2, ',', '.') }} Kz</span>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gradient-to-r from-purple-50 to-fuchsia-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-list mr-2 text-purple-600"></i>{{ __('Detalhe por Serviço') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Serviço') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Qtd') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Preço Méd.') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Faturas') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Clientes') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Receita') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Custo') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Lucro') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Margem') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $r)
                        <tr class="hover:bg-purple-50 {{ $r->profit < 0 ? 'bg-red-50/30' : '' }}">
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->name }}</p>
                                @if($r->sku)<p class="text-xs text-gray-500">{{ $r->sku }}</p>@endif
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-purple-700">{{ number_format($r->qty, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($r->avg_price, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->invoices_count }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->clients_count }}</td>
                            <td class="px-3 py-2 text-right font-bold text-blue-700">{{ number_format($r->revenue, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-orange-700">{{ number_format($r->total_cost, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($r->profit, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->margin >= 30 ? 'text-emerald-700' : ($r->margin >= 0 ? 'text-amber-600' : 'text-red-700') }}">{{ number_format($r->margin, 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Nenhum serviço vendido no período') }}</td></tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-3 py-2">TOTAIS</td>
                        <td class="px-3 py-2 text-right text-purple-700">{{ number_format($totals['qty'], 2, ',', '.') }}</td>
                        <td colspan="3"></td>
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

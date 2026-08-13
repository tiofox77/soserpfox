<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-tie text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">{{ __('Vendas por Vendedor') }}</h1>
                    <p class="text-blue-100 text-sm">{{ __('Desempenho de vendas por utilizador (exclui canceladas/creditadas)') }}</p>
                </div>
            </div>
            <a href="{{ route('invoicing.reports.hub') }}" class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold">
                <i class="fas fa-arrow-left mr-1"></i>{{ __('Relatórios') }}
            </a>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Período') }}</label>
                <select wire:model.live="period" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="today">{{ __('Hoje') }}</option>
                    <option value="week">{{ __('Esta semana') }}</option>
                    <option value="month">{{ __('Este mês') }}</option>
                    <option value="quarter">{{ __('Trimestre') }}</option>
                    <option value="year">{{ __('Ano') }}</option>
                    <option value="custom">{{ __('Personalizado') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('De') }}</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Até') }}</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    {{-- Totais --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-5 border border-blue-100">
            <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Total Vendido') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ number_format($grandTotal, 2, ',', '.') }} Kz</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-indigo-100">
            <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Nº de Documentos') }}</p>
            <p class="text-3xl font-bold text-indigo-600">{{ $grandCount }}</p>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50"><h3 class="font-bold text-gray-900"><i class="fas fa-ranking-star mr-2 text-blue-600"></i>{{ __('Ranking de Vendedores') }}</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ __('Vendedor') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('Docs') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('Total Vendido') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('Recebido') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('Pendente') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('Ticket Médio') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">{{ __('% do Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($byUser as $i => $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-gray-400 font-bold">{{ $i + 1 }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $row['user'] }}</td>
                            <td class="px-6 py-3 text-right text-gray-600">{{ $row['count'] }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900">{{ number_format($row['total'], 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-3 text-right text-green-600">{{ number_format($row['paid'], 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-3 text-right {{ $row['pending'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($row['pending'], 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-3 text-right text-gray-600">{{ number_format($row['avg'], 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-3 text-right text-gray-500">{{ $grandTotal > 0 ? number_format($row['total'] / $grandTotal * 100, 1) : '0,0' }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2"></i><p class="text-sm">{{ __('Sem vendas no período') }}</p></td></tr>
                    @endforelse
                </tbody>
                @if($byUser->count() > 0)
                <tfoot class="bg-blue-50">
                    <tr>
                        <td colspan="2" class="px-6 py-3 font-bold">TOTAL</td>
                        <td class="px-6 py-3 text-right font-bold">{{ $grandCount }}</td>
                        <td class="px-6 py-3 text-right font-bold text-blue-700">{{ number_format($grandTotal, 2, ',', '.') }} Kz</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

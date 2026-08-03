<div>
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-balance-scale text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">Comparativo entre Períodos</h2><p class="text-violet-100 text-sm">Análise de variações período A vs período B</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>Voltar</a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-md p-5 mb-6 border-l-4 border-violet-500">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800"><i class="fas fa-cog mr-2 text-violet-600"></i>Configuração</h3>
            <button onclick="window.print()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold"><i class="fas fa-print mr-1"></i>Imprimir</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Modo</label>
                <select wire:model.live="mode" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="month">Mês actual vs anterior</option>
                    <option value="year">Ano actual vs anterior</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div><label class="block text-xs font-bold text-violet-600 uppercase mb-1">Período A — De</label><input wire:model.live="periodAFrom" type="date" class="w-full px-3 py-2 border border-violet-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs font-bold text-violet-600 uppercase mb-1">Período A — Até</label><input wire:model.live="periodATo" type="date" class="w-full px-3 py-2 border border-violet-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Período B — De</label><input wire:model.live="periodBFrom" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Período B — Até</label><input wire:model.live="periodBTo" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        </div>
    </div>

    @php
        $metrics = [
            'revenue' => ['label' => 'Receita Bruta', 'icon' => 'fa-arrow-trend-up', 'color' => 'green', 'format' => 'money'],
            'invoices_count' => ['label' => 'Nº Faturas', 'icon' => 'fa-file-invoice', 'color' => 'blue', 'format' => 'int'],
            'clients_active' => ['label' => 'Clientes Activos', 'icon' => 'fa-users', 'color' => 'cyan', 'format' => 'int'],
            'avg_ticket' => ['label' => 'Ticket Médio', 'icon' => 'fa-receipt', 'color' => 'purple', 'format' => 'money'],
            'purchases' => ['label' => 'Compras', 'icon' => 'fa-shopping-cart', 'color' => 'orange', 'format' => 'money'],
            'purchases_count' => ['label' => 'Nº Compras', 'icon' => 'fa-truck', 'color' => 'amber', 'format' => 'int'],
            'cogs' => ['label' => 'CMV', 'icon' => 'fa-warehouse', 'color' => 'red', 'format' => 'money'],
            'profit' => ['label' => 'Lucro Bruto', 'icon' => 'fa-coins', 'color' => 'emerald', 'format' => 'money'],
        ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($metrics as $key => $meta)
            @php
                $v = $variance[$key];
                $isUp = $v['diff'] >= 0;
                $isPositiveDirection = in_array($key, ['cogs', 'purchases', 'purchases_count']) ? !$isUp : $isUp;
            @endphp
            <div class="bg-white rounded-2xl shadow-lg p-5 border-l-4 border-{{ $meta['color'] }}-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-{{ $meta['color'] }}-100 rounded-xl flex items-center justify-center">
                        <i class="fas {{ $meta['icon'] }} text-{{ $meta['color'] }}-600"></i>
                    </div>
                    <span class="text-xs font-bold px-2 py-1 rounded-full {{ $isPositiveDirection ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        <i class="fas fa-arrow-{{ $isUp ? 'up' : 'down' }}"></i>
                        {{ number_format(abs($v['pct']), 1, ',', '.') }}%
                    </span>
                </div>
                <p class="text-xs font-bold text-gray-500 uppercase">{{ $meta['label'] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    @if($meta['format'] === 'money')
                        {{ number_format($a[$key], 2, ',', '.') }}
                    @else
                        {{ number_format($a[$key], 0, ',', '.') }}
                    @endif
                </p>
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Período B:
                        <strong class="text-gray-700">
                            @if($meta['format'] === 'money'){{ number_format($b[$key], 2, ',', '.') }}@else{{ number_format($b[$key], 0, ',', '.') }}@endif
                        </strong>
                    </p>
                    <p class="text-xs {{ $isPositiveDirection ? 'text-emerald-600' : 'text-red-600' }} mt-0.5">
                        Diferença: <strong>{{ $v['diff'] >= 0 ? '+' : '' }}{{ number_format($v['diff'], $meta['format'] === 'money' ? 2 : 0, ',', '.') }}</strong>
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tabela comparativa --}}
    <div class="mt-6 bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gradient-to-r from-violet-50 to-purple-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-table mr-2 text-violet-600"></i>Comparativo Detalhado</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-left">Indicador</th>
                    <th class="px-3 py-2 text-right">Período A</th>
                    <th class="px-3 py-2 text-right">Período B</th>
                    <th class="px-3 py-2 text-right">Diferença</th>
                    <th class="px-3 py-2 text-right">Variação %</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($metrics as $key => $meta)
                    @php $v = $variance[$key]; $isUp = $v['diff'] >= 0; $isPositiveDirection = in_array($key, ['cogs', 'purchases', 'purchases_count']) ? !$isUp : $isUp; @endphp
                    <tr class="hover:bg-violet-50">
                        <td class="px-3 py-2 font-semibold"><i class="fas {{ $meta['icon'] }} mr-2 text-{{ $meta['color'] }}-500"></i>{{ $meta['label'] }}</td>
                        <td class="px-3 py-2 text-right font-bold">
                            @if($meta['format'] === 'money'){{ number_format($a[$key], 2, ',', '.') }}@else{{ number_format($a[$key], 0, ',', '.') }}@endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-600">
                            @if($meta['format'] === 'money'){{ number_format($b[$key], 2, ',', '.') }}@else{{ number_format($b[$key], 0, ',', '.') }}@endif
                        </td>
                        <td class="px-3 py-2 text-right font-bold {{ $isPositiveDirection ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $v['diff'] >= 0 ? '+' : '' }}@if($meta['format'] === 'money'){{ number_format($v['diff'], 2, ',', '.') }}@else{{ number_format($v['diff'], 0, ',', '.') }}@endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <span class="inline-flex px-2 py-1 rounded-lg font-bold text-xs {{ $isPositiveDirection ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                <i class="fas fa-arrow-{{ $isUp ? 'up' : 'down' }} mr-1"></i>{{ number_format(abs($v['pct']), 1, ',', '.') }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

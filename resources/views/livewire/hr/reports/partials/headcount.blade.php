{{-- Evolução Quadro de Pessoal --}}
<div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-violet-50 border-b">
    <h3 class="text-lg font-bold text-indigo-800 flex items-center">
        <i class="fas fa-chart-line mr-2"></i>Evolução do Quadro de Pessoal — {{ $year }}
    </h3>
</div>

@if(is_array($data) && count($data) > 0)
<div class="p-6">
    {{-- Gráfico de Barras --}}
    <div class="flex items-end justify-between space-x-3 h-56 mb-6">
        @php $maxCount = max(array_column($data, 'count')) ?: 1; @endphp
        @foreach($data as $item)
        <div class="flex-1 flex flex-col items-center group">
            <span class="text-xs font-bold text-indigo-700 mb-1 opacity-0 group-hover:opacity-100 transition">{{ $item['count'] }}</span>
            <div class="w-full bg-gradient-to-t from-indigo-500 to-violet-500 rounded-t-lg transition-all hover:from-indigo-600 hover:to-violet-600 relative"
                 style="height: {{ ($item['count'] / $maxCount) * 100 }}%">
            </div>
            <div class="mt-2 text-center">
                <p class="text-xs font-bold text-gray-800">{{ $item['count'] }}</p>
                <p class="text-xs text-gray-500">{{ ucfirst(substr($item['month_name'], 0, 3)) }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Tabela --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left font-bold text-gray-700">Mês</th>
                    <th class="px-4 py-2 text-center font-bold text-gray-700">Funcionários Activos</th>
                    <th class="px-4 py-2 text-center font-bold text-gray-700">Variação</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $i => $item)
                @php
                    $prev = $i > 0 ? $data[$i-1]['count'] : $item['count'];
                    $diff = $item['count'] - $prev;
                @endphp
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-2 font-semibold text-gray-800">{{ ucfirst($item['month_name']) }}</td>
                    <td class="px-4 py-2 text-center font-bold text-indigo-700">{{ $item['count'] }}</td>
                    <td class="px-4 py-2 text-center">
                        @if($diff > 0)
                            <span class="text-green-600 font-bold"><i class="fas fa-arrow-up text-xs"></i> +{{ $diff }}</span>
                        @elseif($diff < 0)
                            <span class="text-red-600 font-bold"><i class="fas fa-arrow-down text-xs"></i> {{ $diff }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="p-12 text-center">
    <i class="fas fa-chart-line text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-500">Sem dados para o ano selecionado.</p>
</div>
@endif

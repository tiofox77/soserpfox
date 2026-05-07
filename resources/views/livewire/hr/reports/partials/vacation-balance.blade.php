{{-- Saldo de Férias --}}
<div class="px-6 py-4 bg-gradient-to-r from-amber-50 to-yellow-50 border-b">
    <h3 class="text-lg font-bold text-amber-800 flex items-center">
        <i class="fas fa-umbrella-beach mr-2"></i>Saldo de Férias — {{ $year }}
    </h3>
</div>

@if($data instanceof \Illuminate\Support\Collection && $data->count() > 0)
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-700">
                <th class="px-4 py-3 text-left font-bold">#</th>
                <th class="px-4 py-3 text-left font-bold">Funcionário</th>
                <th class="px-4 py-3 text-left font-bold">Departamento</th>
                <th class="px-4 py-3 text-center font-bold">Direito (dias)</th>
                <th class="px-4 py-3 text-center font-bold">Gozadas (dias)</th>
                <th class="px-4 py-3 text-center font-bold">Saldo</th>
                <th class="px-4 py-3 text-center font-bold">Progresso</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $i => $row)
            @php
                $pct = $row['entitled'] > 0 ? round(($row['taken'] / $row['entitled']) * 100) : 0;
            @endphp
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-4 py-2 text-gray-500 text-xs">{{ $i + 1 }}</td>
                <td class="px-4 py-2 font-semibold text-gray-800">{{ $row['employee']->full_name }}</td>
                <td class="px-4 py-2 text-gray-600">{{ $row['employee']->department->name ?? '—' }}</td>
                <td class="px-4 py-2 text-center font-semibold">{{ $row['entitled'] }}</td>
                <td class="px-4 py-2 text-center">
                    <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">{{ $row['taken'] }}</span>
                </td>
                <td class="px-4 py-2 text-center">
                    <span class="px-2 py-1 rounded-full text-xs font-bold {{ $row['remaining'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $row['remaining'] }}
                    </span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-8">{{ $pct }}%</span>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="p-12 text-center">
    <i class="fas fa-umbrella-beach text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-500">Sem funcionários activos.</p>
</div>
@endif

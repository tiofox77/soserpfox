{{-- Resumo de Presenças --}}
<div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b">
    <h3 class="text-lg font-bold text-green-800 flex items-center">
        <i class="fas fa-clipboard-check mr-2"></i>Resumo de Presenças — {{ \Carbon\Carbon::create(null, $month)->locale('pt_BR')->monthName }} {{ $year }}
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
                <th class="px-4 py-3 text-center font-bold text-green-600"><i class="fas fa-check mr-1"></i>Equiv. trabalhado</th>
                <th class="px-4 py-3 text-center font-bold text-yellow-600"><i class="fas fa-clock mr-1"></i>Atrasos</th>
                <th class="px-4 py-3 text-center font-bold text-orange-600"><i class="fas fa-adjust mr-1"></i>Meio-dia</th>
                <th class="px-4 py-3 text-center font-bold text-red-600"><i class="fas fa-times mr-1"></i>Faltas</th>
                <th class="px-4 py-3 text-center font-bold text-blue-600"><i class="fas fa-file mr-1"></i>Justificadas</th>
                <th class="px-4 py-3 text-center font-bold">Taxa</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $i => $row)
            @php $rate = $row['attendance_rate']; @endphp
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-4 py-2 text-gray-500 text-xs">{{ $i + 1 }}</td>
                <td class="px-4 py-2 font-semibold text-gray-800">{{ $row['employee']->full_name }}</td>
                <td class="px-4 py-2 text-gray-600">{{ $row['employee']->department->name ?? '—' }}</td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">{{ number_format($row['worked_equivalent'], 1, ',', '.') }}</span></td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold">{{ $row['late'] }}</span></td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">{{ $row['half_day'] }}</span></td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">{{ $row['absent'] }}</span></td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ $row['justified'] }}</span></td>
                <td class="px-4 py-2 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $rate >= 90 ? 'bg-green-500' : ($rate >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $rate }}%"></div>
                        </div>
                        <span class="text-xs font-bold {{ $rate >= 90 ? 'text-green-700' : ($rate >= 70 ? 'text-yellow-700' : 'text-red-700') }}">{{ number_format($rate, 1, ',', '.') }}%</span>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="p-12 text-center">
    <i class="fas fa-clipboard-check text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-500">Sem dados de presenças para o período selecionado.</p>
</div>
@endif

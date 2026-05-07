{{-- Custos por Departamento --}}
<div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
    <h3 class="text-lg font-bold text-blue-800 flex items-center">
        <i class="fas fa-building mr-2"></i>Custos por Departamento — {{ \Carbon\Carbon::create(null, $month)->locale('pt_BR')->monthName }} {{ $year }}
    </h3>
</div>

@if($data instanceof \Illuminate\Support\Collection && $data->count() > 0)
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-700">
                <th class="px-4 py-3 text-left font-bold">Departamento</th>
                <th class="px-4 py-3 text-center font-bold">Funcionários</th>
                <th class="px-4 py-3 text-right font-bold">Total Bruto</th>
                <th class="px-4 py-3 text-right font-bold">INSS (Emp+Ent)</th>
                <th class="px-4 py-3 text-right font-bold">IRT</th>
                <th class="px-4 py-3 text-right font-bold">Total Líquido</th>
                <th class="px-4 py-3 text-right font-bold">Média Líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-semibold text-gray-800">
                    <i class="fas fa-building text-blue-400 mr-2"></i>{{ $row['department'] }}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ $row['employees'] }}</span>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ number_format($row['total_gross'], 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['total_inss'], 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-red-600">{{ number_format($row['total_irt'], 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right font-bold text-blue-700">{{ number_format($row['total_net'], 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ number_format($row['avg_salary'], 0, ',', '.') }} Kz</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-gray-100 font-bold text-gray-900">
                <td class="px-4 py-3">TOTAL</td>
                <td class="px-4 py-3 text-center">{{ $data->sum('employees') }}</td>
                <td class="px-4 py-3 text-right text-emerald-700">{{ number_format($data->sum('total_gross'), 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-red-600">{{ number_format($data->sum('total_inss'), 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-red-600">{{ number_format($data->sum('total_irt'), 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-blue-700">{{ number_format($data->sum('total_net'), 0, ',', '.') }} Kz</td>
                <td class="px-4 py-3 text-right text-gray-600">—</td>
            </tr>
        </tfoot>
    </table>
</div>
@else
<div class="p-12 text-center">
    <i class="fas fa-building text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-500">Não existe folha de pagamento para o período selecionado.</p>
</div>
@endif

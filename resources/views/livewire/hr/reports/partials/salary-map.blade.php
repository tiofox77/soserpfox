{{-- Mapa de Salários --}}
<div class="px-6 py-4 bg-gradient-to-r from-emerald-50 to-teal-50 border-b">
    <h3 class="text-lg font-bold text-emerald-800 flex items-center">
        <i class="fas fa-money-check-alt mr-2"></i>Mapa de Salários — {{ \Carbon\Carbon::create(null, $month)->locale('pt_BR')->monthName }} {{ $year }}
    </h3>
</div>

@if(isset($data['items']) && $data['items']->count() > 0)
<div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
            <tr class="bg-gray-50 text-gray-700">
                <th class="px-3 py-2 text-left font-bold">#</th>
                <th class="px-3 py-2 text-left font-bold">Funcionário</th>
                <th class="px-3 py-2 text-left font-bold">Departamento</th>
                <th class="px-3 py-2 text-right font-bold">Salário Base</th>
                <th class="px-3 py-2 text-right font-bold">Sub. Alim.</th>
                <th class="px-3 py-2 text-right font-bold">Sub. Transp.</th>
                <th class="px-3 py-2 text-right font-bold">Bruto</th>
                <th class="px-3 py-2 text-right font-bold">INSS</th>
                <th class="px-3 py-2 text-right font-bold">IRT</th>
                <th class="px-3 py-2 text-right font-bold">Deduções</th>
                <th class="px-3 py-2 text-right font-bold">Líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $i => $item)
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                <td class="px-3 py-2 font-semibold text-gray-800">{{ $item->employee->full_name ?? '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $item->employee->department->name ?? '—' }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($item->base_salary, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($item->food_allowance, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($item->transport_allowance, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ number_format($item->gross_salary, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right text-red-600">{{ number_format($item->inss_employee, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right text-red-600">{{ number_format($item->irt_amount, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right text-red-700 font-semibold">{{ number_format($item->total_deductions, 0, ',', '.') }}</td>
                <td class="px-3 py-2 text-right font-bold text-blue-700">{{ number_format($item->net_salary, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        @if($data['totals'])
        <tfoot>
            <tr class="bg-gray-100 font-bold text-gray-900">
                <td class="px-3 py-3" colspan="3">TOTAIS ({{ $data['items']->count() }} funcionários)</td>
                <td class="px-3 py-3 text-right">{{ number_format($data['totals']['base_salary'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right">{{ number_format($data['totals']['food_allowance'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right">{{ number_format($data['totals']['transport_allowance'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right text-emerald-700">{{ number_format($data['totals']['gross_salary'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right text-red-600">{{ number_format($data['totals']['inss_employee'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right text-red-600">{{ number_format($data['totals']['irt_amount'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right text-red-700">{{ number_format($data['totals']['total_deductions'], 0, ',', '.') }}</td>
                <td class="px-3 py-3 text-right text-blue-700">{{ number_format($data['totals']['net_salary'], 0, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
@else
<div class="p-12 text-center">
    <i class="fas fa-file-invoice text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-500">Não existe folha de pagamento para o período selecionado.</p>
</div>
@endif

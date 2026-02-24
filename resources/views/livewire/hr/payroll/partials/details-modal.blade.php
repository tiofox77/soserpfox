<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeDetailsModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-[95vw] w-full max-h-[95vh] overflow-hidden flex flex-col"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-file-invoice-dollar text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        Folha: {{ $months[$selectedPayroll->month] }}/{{ $selectedPayroll->year }}
                    </h3>
                    <p class="text-green-100 text-sm">{{ $selectedPayroll->payroll_number }} • {{ $selectedPayroll->total_employees }} funcionários</p>
                </div>
            </div>
            <button wire:click="closeDetailsModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Resumo Geral --}}
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 border border-blue-100">
                    <p class="text-xs text-blue-600 font-semibold mb-1">Total Bruto</p>
                    <p class="text-lg font-bold text-blue-600">{{ number_format($selectedPayroll->total_gross_salary, 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-green-100">
                    <p class="text-xs text-green-600 font-semibold mb-1">Subsídios</p>
                    <p class="text-lg font-bold text-green-600">{{ number_format($selectedPayroll->total_allowances, 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-purple-100">
                    <p class="text-xs text-purple-600 font-semibold mb-1">Bônus</p>
                    <p class="text-lg font-bold text-purple-600">{{ number_format($selectedPayroll->total_bonuses, 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-red-100">
                    <p class="text-xs text-red-600 font-semibold mb-1">IRT</p>
                    <p class="text-lg font-bold text-red-600">{{ number_format($selectedPayroll->total_irt, 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-orange-100">
                    <p class="text-xs text-orange-600 font-semibold mb-1">INSS (3%)</p>
                    <p class="text-lg font-bold text-orange-600">{{ number_format($selectedPayroll->total_inss_employee, 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl shadow-md p-4">
                    <p class="text-xs text-white font-semibold mb-1">Total Líquido</p>
                    <p class="text-lg font-bold text-white">{{ number_format($selectedPayroll->total_net_salary, 2, ',', '.') }} Kz</p>
                </div>
            </div>
        </div>

        {{-- Tabela de Funcionários --}}
        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gradient-to-r from-gray-700 to-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Funcionário</th>
                            <th class="px-3 py-3 text-center font-semibold">
                                <i class="fas fa-calendar-check text-xs mr-1"></i>Dias
                            </th>
                            <th class="px-3 py-3 text-center font-semibold">
                                <i class="fas fa-calendar-times text-xs mr-1"></i>Faltas
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">Bruto</th>
                            <th class="px-4 py-3 text-right font-semibold">Subsídios</th>
                            <th class="px-4 py-3 text-right font-semibold">Bônus</th>
                            <th class="px-4 py-3 text-right font-semibold">INSS (3%)</th>
                            <th class="px-4 py-3 text-right font-semibold">IRT</th>
                            <th class="px-4 py-3 text-right font-semibold">Deduções</th>
                            <th class="px-4 py-3 text-right font-semibold">Líquido</th>
                            <th class="px-4 py-3 text-center font-semibold">Status</th>
                            <th class="px-4 py-3 text-center font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($selectedPayroll->items as $item)
                            <tr class="hover:bg-gray-50 transition-colors group">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-9 h-9 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-xs mr-3">
                                            {{ strtoupper(substr($item->employee->first_name, 0, 1) . substr($item->employee->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $item->employee->full_name }}</p>
                                            <p class="text-xs text-gray-500">{{ $item->employee->employee_number }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <div class="inline-flex items-center px-2 py-1 bg-blue-50 rounded-lg" 
                                         title="Dias trabalhados no mês"
                                         data-tooltip="true">
                                        <i class="fas fa-check text-xs text-blue-600 mr-1"></i>
                                        <span class="font-bold text-blue-700 text-sm">{{ $item->worked_days ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($item->absence_days > 0)
                                        <div class="inline-flex items-center px-2 py-1 bg-red-50 rounded-lg"
                                             title="Faltas injustificadas (descontadas do salário)"
                                             data-tooltip="true">
                                            <i class="fas fa-times text-xs text-red-600 mr-1"></i>
                                            <span class="font-bold text-red-700 text-sm">{{ $item->absence_days }}</span>
                                        </div>
                                    @else
                                        <div class="inline-flex items-center px-2 py-1 bg-green-50 rounded-lg"
                                             title="Sem faltas"
                                             data-tooltip="true">
                                            <i class="fas fa-check-double text-xs text-green-600 mr-1"></i>
                                            <span class="font-semibold text-green-700 text-xs">0</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-blue-600">
                                    {{ number_format($item->gross_salary, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-green-600">
                                    {{ number_format($item->total_allowances, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-purple-600">
                                    {{ number_format($item->total_bonuses, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-orange-600">
                                    {{ number_format($item->inss_employee, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">
                                    {{ number_format($item->irt_amount, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-600">
                                    {{ number_format($item->total_deductions, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-emerald-600 text-base">
                                        {{ number_format($item->net_salary, 2, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($item->status === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-check-double mr-1"></i>Pago
                                        </span>
                                    @elseif($item->status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-check mr-1"></i>Aprovado
                                        </span>
                                    @elseif($item->status === 'calculated')
                                        <span class="inline-flex items-center px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-calculator mr-1"></i>Calculado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-hourglass-half mr-1"></i>Pendente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($selectedPayroll->status === 'draft')
                                        <button wire:click="editItem({{ $item->id }})" 
                                                class="opacity-0 group-hover:opacity-100 w-8 h-8 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all shadow-md hover:shadow-lg transform hover:scale-110"
                                                title="Editar Item">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    @else
                                        <button class="w-8 h-8 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed" 
                                                title="Não editável"
                                                disabled>
                                            <i class="fas fa-lock text-xs"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gradient-to-r from-green-700 to-emerald-700 text-white font-bold">
                        <tr>
                            <td class="px-4 py-3">TOTAIS ({{ $selectedPayroll->total_employees }} funcionários)</td>
                            <td class="px-3 py-3 text-center">
                                <i class="fas fa-users text-xs"></i>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <i class="fas fa-calendar-times text-xs"></i>
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_gross_salary, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_allowances, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_bonuses, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_inss_employee, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_irt, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($selectedPayroll->total_deductions, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-lg">{{ number_format($selectedPayroll->total_net_salary, 2, ',', '.') }}</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Informações Adicionais --}}
            @if($selectedPayroll->approved_at)
                <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-600 text-xl mr-3"></i>
                        <div class="text-sm">
                            <p class="text-blue-900"><strong>Aprovada por:</strong> {{ $selectedPayroll->approvedBy->name ?? 'Sistema' }}</p>
                            <p class="text-blue-700">Data: {{ $selectedPayroll->approved_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if($selectedPayroll->payment_date)
                <div class="mt-4 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-emerald-600 text-xl mr-3"></i>
                        <div class="text-sm">
                            <p class="text-emerald-900"><strong>Paga em:</strong> {{ $selectedPayroll->payment_date->format('d/m/Y') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between space-x-3 border-t border-gray-200">
            <div class="flex items-center space-x-2">
                <button type="button"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-file-pdf mr-2"></i>Exportar PDF
                </button>
                <button type="button"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-file-excel mr-2"></i>Exportar Excel
                </button>
                
                @if($selectedPayroll->status !== 'paid')
                    <button wire:click="deletePayroll({{ $selectedPayroll->id }})" 
                            type="button"
                            class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-trash mr-2"></i>Excluir Folha
                    </button>
                @endif
            </div>
            <button wire:click="closeDetailsModal" 
                    type="button"
                    class="px-6 py-2.5 bg-gray-600 hover:bg-gray-700 text-white rounded-xl font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>

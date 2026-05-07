<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeEditItemModal()">
    
    @php
        $item = $editingItem;
        $totalLoans = $this->activeLoans->sum('installment_amount');
        $totalOtherDisc = $this->otherActiveDiscounts->sum('installment_amount');
    @endphp

    <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-edit text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Editar Item da Folha</h3>
                    <p class="text-blue-100 text-sm">{{ $editingItem->employee->full_name }} • {{ $editingItem->employee->employee_number }}</p>
                </div>
            </div>
            <button wire:click="closeEditItemModal" 
                    class="text-white hover:text-blue-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            
            {{-- Informações de Presença --}}
            @if($item->notes)
            <div class="mb-5 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-4">
                <h4 class="text-sm font-bold text-blue-900 mb-2 flex items-center">
                    <i class="fas fa-calendar-check mr-2"></i>
                    Resumo de Presenças e Horários
                </h4>
                <div class="text-sm text-gray-700 space-y-1">
                    @foreach(explode(' | ', $item->notes) as $note)
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-xs"></i>
                            <span>{{ $note }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Indicadores Rápidos --}}
            <div class="grid grid-cols-4 gap-3 mb-5">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">
                    <p class="text-xs text-blue-600 font-semibold">Dias Úteis</p>
                    <p class="text-lg font-bold text-blue-800">{{ $item->total_working_days ?? '-' }}</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-3 text-center">
                    <p class="text-xs text-green-600 font-semibold">Presenças</p>
                    <p class="text-lg font-bold text-green-800">{{ $item->present_days ?? '-' }}</p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-xl p-3 text-center">
                    <p class="text-xs text-red-600 font-semibold">Faltas</p>
                    <p class="text-lg font-bold text-red-800">{{ $item->absence_days ?? 0 }}</p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
                    <p class="text-xs text-amber-600 font-semibold">H. Extra</p>
                    <p class="text-lg font-bold text-amber-800">{{ $item->overtime_hours ?? 0 }}h</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- ═══ COLUNA ESQUERDA: Proventos ═══ --}}
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>
                    Proventos (Créditos)
                </h4>

                <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-money-bill-wave w-4 text-green-600 mr-2"></i>Salário Base
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->base_salary ?? 0, 2, ',', '.') }} Kz</span>
                        </div>
                        @if(($item->food_allowance ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-utensils w-4 text-green-600 mr-2"></i>Alimentação
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->food_allowance, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->transport_allowance ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-car w-4 text-green-600 mr-2"></i>Transporte
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->transport_allowance, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->housing_allowance ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-home w-4 text-green-600 mr-2"></i>Habitação
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->housing_allowance, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->overtime_pay ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-clock w-4 text-blue-600 mr-2"></i>Horas Extra
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->overtime_pay, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->night_shift_allowance ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-moon w-4 text-indigo-500 mr-2"></i>Turno Noturno
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->night_shift_allowance, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->bonus ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-gift w-4 text-purple-600 mr-2"></i>Bônus
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->bonus, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->family_allowance ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-users w-4 text-blue-500 mr-2"></i>Abono Família
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->family_allowance, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->position_subsidy ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-briefcase w-4 text-teal-500 mr-2"></i>Subs. Cargo
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->position_subsidy, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->performance_subsidy ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-trophy w-4 text-yellow-500 mr-2"></i>Subs. Desempenho
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->performance_subsidy, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->christmas_subsidy_amount ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-tree w-4 text-red-500 mr-2"></i>Subs. Natal (13º)
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->christmas_subsidy_amount, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->vacation_subsidy_amount ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-umbrella-beach w-4 text-cyan-500 mr-2"></i>Subs. Férias (14º)
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($item->vacation_subsidy_amount, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                    </div>
                    
                    <div class="pt-3 mt-3 border-t-2 border-green-300">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900">Total Bruto</span>
                            <span class="text-xl font-bold text-green-600">{{ number_format($item->gross_salary ?? 0, 2, ',', '.') }} Kz</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">* Antes de impostos e descontos</p>
                    </div>
                </div>
            </div>

            {{-- ═══ COLUNA DIREITA: Descontos ═══ --}}
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-minus-circle mr-2 text-red-600"></i>
                    Descontos (Débitos)
                </h4>

                {{-- Adiantamentos (Cards Informativos) --}}
                @if($this->activeAdvances->isNotEmpty())
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-clock mr-1 text-orange-600"></i>Adiantamentos Ativos
                        <span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full">{{ $this->activeAdvances->count() }}</span>
                    </label>
                    <div class="space-y-2">
                        @foreach($this->activeAdvances as $advance)
                        <div class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-xl p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-gray-900 text-sm">{{ $advance->advance_number }}</span>
                                    <span class="px-1.5 py-0.5 bg-orange-600 text-white text-[10px] rounded-full ml-1">Em dedução</span>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $advance->installments_paid }}/{{ $advance->installments }} prest. · Saldo: {{ number_format($advance->balance, 2, ',', '.') }} Kz</p>
                                </div>
                                <span class="text-lg font-bold text-orange-600">{{ number_format($advance->installment_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Descontos Salariais (Cards Informativos) --}}
                @if($this->activeDiscounts->isNotEmpty())
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-percentage mr-1 text-red-600"></i>Descontos Salariais Ativos
                        <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">{{ $this->activeDiscounts->count() }}</span>
                    </label>
                    <div class="space-y-2">
                        @foreach($this->activeDiscounts as $discount)
                        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-gray-900 text-sm">{{ $discount->discount_type_name }}</span>
                                    <span class="px-1.5 py-0.5 bg-red-600 text-white text-[10px] rounded-full ml-1">Em dedução</span>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $discount->installments - $discount->remaining_installments }}/{{ $discount->installments }} prest. · Restante: {{ number_format($discount->remaining_installments * $discount->installment_amount, 2, ',', '.') }} Kz</p>
                                </div>
                                <span class="text-lg font-bold text-red-600">{{ number_format($discount->installment_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Resumo Completo de Descontos --}}
                <div class="p-4 bg-gradient-to-r from-red-50 to-orange-50 rounded-xl border border-red-200">
                    <h5 class="text-sm font-bold text-gray-900 mb-3">Resumo de Descontos</h5>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-shield-alt w-4 text-orange-600 mr-2"></i>INSS (3%)
                            </span>
                            <span class="font-bold text-orange-600">{{ number_format($item->inss_employee ?? 0, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-receipt w-4 text-red-600 mr-2"></i>IRT ({{ $item->irt_rate ?? 0 }}%)
                            </span>
                            <span class="font-bold text-red-600">{{ number_format($item->irt_amount ?? 0, 2, ',', '.') }} Kz</span>
                        </div>
                        @if(($item->advance_payment ?? 0) > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-hand-holding-usd w-4 text-purple-600 mr-2"></i>Adiantamentos
                            </span>
                            <span class="font-bold text-purple-600">{{ number_format($item->advance_payment, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->discount_deduction ?? 0) > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-percentage w-4 text-red-500 mr-2"></i>Descontos Salariais
                            </span>
                            <span class="font-bold text-red-500">{{ number_format($item->discount_deduction, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->absence_deduction ?? 0) > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-calendar-times w-4 text-amber-600 mr-2"></i>Faltas
                            </span>
                            <span class="font-bold text-amber-600">{{ number_format($item->absence_deduction, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if(($item->food_deduction ?? 0) > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-utensils w-4 text-orange-500 mr-2"></i>Desc. Alimentação
                            </span>
                            <span class="font-bold text-orange-500">{{ number_format($item->food_deduction, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if($totalLoans > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-money-bill-wave w-4 text-blue-600 mr-2"></i>Empréstimos
                            </span>
                            <span class="font-bold text-blue-600">{{ number_format($totalLoans, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        @if($totalOtherDisc > 0)
                        <div class="flex items-center justify-between py-1 border-b border-dashed border-red-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-ellipsis-h w-4 text-gray-600 mr-2"></i>Outros Descontos
                            </span>
                            <span class="font-bold text-gray-600">{{ number_format($totalOtherDisc, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                    </div>
                    
                    <div class="pt-3 mt-2 border-t-2 border-red-300">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 flex items-center">
                                <i class="fas fa-calculator mr-2 text-red-700"></i>Total Descontos
                            </span>
                            <span class="text-xl font-bold text-red-700">{{ number_format($item->total_deductions ?? 0, 2, ',', '.') }} Kz</span>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            {{-- ═══ FIM 2 COLUNAS ═══ --}}

            {{-- Impostos (Info) --}}
            <div class="mt-5 bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Impostos (Lei Angolana)
                </h4>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                    <div class="bg-white p-3 rounded-lg text-center">
                        <span class="text-gray-500 block">Base INSS</span>
                        <span class="font-bold text-orange-600 text-sm">{{ number_format($item->inss_base ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="bg-white p-3 rounded-lg text-center">
                        <span class="text-gray-500 block">INSS Emp. (3%)</span>
                        <span class="font-bold text-orange-600 text-sm">{{ number_format($item->inss_employee ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="bg-white p-3 rounded-lg text-center">
                        <span class="text-gray-500 block">Base IRT</span>
                        <span class="font-bold text-red-600 text-sm">{{ number_format($item->irt_base ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="bg-white p-3 rounded-lg text-center">
                        <span class="text-gray-500 block">IRT ({{ $item->irt_rate ?? 0 }}%)</span>
                        <span class="font-bold text-red-600 text-sm">{{ number_format($item->irt_amount ?? 0, 2, ',', '.') }} Kz</span>
                    </div>
                </div>
                <p class="text-xs text-blue-700 mt-2">
                    <i class="fas fa-building mr-1"></i>
                    INSS Entidade Patronal (8%): <strong>{{ number_format($item->inss_employer ?? 0, 2, ',', '.') }} Kz</strong>
                </p>
            </div>

            {{-- Salário Líquido --}}
            <div class="p-5 bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl shadow-lg text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-emerald-100 mb-1">Salário Líquido</p>
                        <p class="text-xs text-emerald-200">({{ number_format($item->gross_salary ?? 0, 2, ',', '.') }} Kz - {{ number_format($item->total_deductions ?? 0, 2, ',', '.') }} Kz)</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold">{{ number_format($item->net_salary ?? 0, 2, ',', '.') }} Kz</p>
                    </div>
                </div>
            </div>

            {{-- Nota --}}
            <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-xs text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Nota:</strong> Ao salvar, o sistema recalcula IRT/INSS e atualiza empréstimos e descontos automaticamente da BD.
                </p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
            <a href="{{ route('hr.payroll.payslip.pdf', $editingItem->id) }}" target="_blank"
               class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-file-pdf mr-2"></i>Imprimir Recibo
            </a>
            <div class="flex items-center space-x-3">
                <button wire:click="closeEditItemModal" 
                        type="button"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="saveItem" 
                        type="button"
                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

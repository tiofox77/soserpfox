<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeEditItemModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop
         x-data="{ 
            baseSalary: @entangle('itemBaseSalary'),
            foodAllowance: @entangle('itemFoodAllowance'),
            transportAllowance: @entangle('itemTransportAllowance'),
            overtimePay: @entangle('itemOvertimePay'),
            bonuses: @entangle('itemBonuses'),
            advancePayment: @entangle('itemAdvancePayment'),
            absenceDeduction: @entangle('itemAbsenceDeduction'),
            loanDeduction: @entangle('itemLoanDeduction'),
            otherDeductions: @entangle('itemOtherDeductions'),
            
            // ===== FÓRMULA IDÊNTICA AO PayrollService =====
            
            get totalAllowances() {
                return parseFloat(this.foodAllowance || 0) + parseFloat(this.transportAllowance || 0);
            },
            
            get totalGross() {
                // Gross = Salário + Subsídios + Horas Extras + Bônus
                return parseFloat(this.baseSalary || 0) 
                     + this.totalAllowances 
                     + parseFloat(this.overtimePay || 0)
                     + parseFloat(this.bonuses || 0);
            },
            
            // INSS (Decreto 227/18)
            get inssBase() {
                // Base INSS = Remuneração Total (tudo que o trabalhador recebe)
                return parseFloat(this.baseSalary || 0)
                     + parseFloat(this.foodAllowance || 0)
                     + parseFloat(this.transportAllowance || 0)
                     + parseFloat(this.overtimePay || 0)
                     + parseFloat(this.bonuses || 0);
            },
            
            get inssEmployee() {
                return this.inssBase * 0.03; // 3%
            },
            
            get inssEmployer() {
                return this.inssBase * 0.08; // 8%
            },
            
            // IRT (Código IRT)
            get taxableFoodAllowance() {
                return Math.max(0, parseFloat(this.foodAllowance || 0) - 30000);
            },
            
            get taxableTransportAllowance() {
                return Math.max(0, parseFloat(this.transportAllowance || 0) - 30000);
            },
            
            get totalTaxableAllowances() {
                return this.taxableFoodAllowance + this.taxableTransportAllowance;
            },
            
            get irtBase() {
                // Base IRT = Salário + Bônus + Horas Extras + Subsídios Tributáveis - INSS
                return parseFloat(this.baseSalary || 0)
                     + parseFloat(this.overtimePay || 0)
                     + parseFloat(this.bonuses || 0)
                     + this.totalTaxableAllowances
                     - this.inssEmployee;
            },
            
            get irtAmount() {
                let base = this.irtBase;
                // Tabela progressiva de IRT (Angola)
                if (base > 70000) return (base - 70000) * 0.175 + 2675;
                if (base > 50000) return (base - 50000) * 0.125 + 625;
                if (base > 20000) return (base - 20000) * 0.065;
                return 0;
            },
            
            get totalDeductions() {
                // Total de TODOS os descontos
                return this.inssEmployee
                     + this.irtAmount
                     + parseFloat(this.absenceDeduction || 0)
                     + parseFloat(this.advancePayment || 0)
                     + parseFloat(this.loanDeduction || 0)
                     + parseFloat(this.otherDeductions || 0);
            },
            
            get estimatedNet() {
                // Líquido = Bruto - Total Descontos
                return this.totalGross - this.totalDeductions;
            }
         }">
        
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
            @if($editingItem->notes)
            <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-4">
                <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-calendar-check mr-2"></i>
                    Resumo de Presenças e Horários
                </h4>
                <div class="text-sm text-gray-700 space-y-1">
                    @foreach(explode(' | ', $editingItem->notes) as $note)
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-xs"></i>
                            <span>{{ $note }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            {{-- Proventos (Créditos) --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>
                    Proventos (Créditos)
                </h4>
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Valores Calculados Automaticamente:</strong> Baseados em presenças, configurações e contrato do funcionário.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Salário Bruto (Readonly) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Salário Bruto
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   value="{{ number_format($itemBaseSalary, 2, ',', '.') }}"
                                   readonly
                                   class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-xl text-gray-700 font-semibold cursor-not-allowed"
                                   title="Calculado automaticamente baseado nas presenças">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Proporcional aos dias trabalhados</p>
                    </div>

                    {{-- Subsídio Alimentação (Readonly) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-utensils mr-1 text-green-600"></i>Alimentação
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   value="{{ number_format($itemFoodAllowance, 2, ',', '.') }}"
                                   readonly
                                   class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-xl text-gray-700 font-semibold cursor-not-allowed"
                                   title="Calculado automaticamente baseado nas presenças">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Proporcional aos dias trabalhados</p>
                    </div>

                    {{-- Subsídio Transporte (Readonly) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-car mr-1 text-green-600"></i>Transporte
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   value="{{ number_format($itemTransportAllowance, 2, ',', '.') }}"
                                   readonly
                                   class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-xl text-gray-700 font-semibold cursor-not-allowed"
                                   title="Calculado automaticamente baseado nas presenças">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Proporcional aos dias trabalhados</p>
                    </div>

                    {{-- Bônus (Readonly) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-gift mr-1 text-purple-600"></i>Bônus
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   value="{{ number_format($itemBonuses, 2, ',', '.') }}"
                                   readonly
                                   class="w-full px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-xl text-gray-700 font-semibold cursor-not-allowed"
                                   title="Inclui bônus fixo + horas extras">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Fixo + Horas extras (150%)</p>
                    </div>
                </div>

                {{-- Breakdown de Proventos --}}
                <div class="mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                    <h5 class="text-sm font-bold text-gray-900 mb-3">Resumo de Proventos</h5>
                    
                    <div class="space-y-2 text-sm mb-3">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-money-bill-wave w-4 text-green-600 mr-2"></i>Salário Base
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($itemBaseSalary, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-utensils w-4 text-green-600 mr-2"></i>Alimentação
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($itemFoodAllowance, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-car w-4 text-green-600 mr-2"></i>Transporte
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($itemTransportAllowance, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-gift w-4 text-purple-600 mr-2"></i>Bônus
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($itemBonuses, 2, ',', '.') }} Kz</span>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-t-2 border-green-300">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900">Total Bruto</span>
                            <span class="text-xl font-bold text-green-600" x-text="totalGross.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">* Antes de impostos e descontos</p>
                    </div>
                </div>
            </div>

            {{-- Descontos (Débitos) --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-minus-circle mr-2 text-red-600"></i>
                    Descontos (Débitos)
                </h4>
                
                {{-- Adiantamentos (Cards Informativos) --}}
                @if($this->activeAdvances->isNotEmpty())
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-clock mr-1 text-orange-600"></i>Adiantamentos Ativos
                        <span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full">
                            {{ $this->activeAdvances->count() }}
                        </span>
                    </label>
                    
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($this->activeAdvances as $advance)
                            <div class="bg-gradient-to-r from-orange-50 to-amber-50 border-2 border-orange-200 rounded-xl p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-bold text-gray-900">{{ $advance->advance_number }}</span>
                                            <span class="px-2 py-0.5 bg-orange-600 text-white text-xs rounded-full">
                                                Em dedução
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            Aprovado: {{ number_format($advance->approved_amount, 2, ',', '.') }} Kz
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-orange-600">{{ number_format($advance->installment_amount, 2, ',', '.') }} Kz</p>
                                        <p class="text-xs text-gray-500">por mês</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 pt-3 border-t border-orange-200 text-sm">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-list text-orange-600"></i>
                                        <span class="text-gray-700">
                                            <strong>{{ $advance->installments_paid }}</strong>/{{ $advance->installments }} prestações
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-wallet text-orange-600"></i>
                                        <span class="text-gray-700">
                                            Saldo: <strong>{{ number_format($advance->balance, 2, ',', '.') }} Kz</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        
                        <div class="bg-orange-100 border border-orange-300 rounded-xl p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-orange-900">
                                    <i class="fas fa-calculator mr-2"></i>Total a Deduzir nesta Folha
                                </span>
                                <span class="text-xl font-bold text-orange-700">
                                    {{ number_format($this->activeAdvances->sum('installment_amount'), 2, ',', '.') }} Kz
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Empréstimo --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hand-holding-usd mr-1 text-orange-600"></i>Empréstimo
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   wire:model.live="itemLoanDeduction"
                                   x-model="loanDeduction"
                                   step="0.01" 
                                   min="0"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('itemLoanDeduction') border-red-500 @enderror"
                                   placeholder="0.00">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        @error('itemLoanDeduction')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Outros Descontos --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-ellipsis-h mr-1 text-orange-600"></i>Outros Descontos
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   wire:model.live="itemOtherDeductions"
                                   x-model="otherDeductions"
                                   step="0.01" 
                                   min="0"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('itemOtherDeductions') border-red-500 @enderror"
                                   placeholder="0.00">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                        </div>
                        @error('itemOtherDeductions')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Breakdown de Descontos --}}
                <div class="mt-4 p-4 bg-gradient-to-r from-red-50 to-orange-50 rounded-xl border border-red-200">
                    <h5 class="text-sm font-bold text-gray-900 mb-3">Resumo de Descontos</h5>
                    
                    <div class="space-y-2 text-sm mb-3">
                        @if($this->activeAdvances->isNotEmpty())
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-clock w-4 text-orange-600 mr-2"></i>Adiantamento (automático)
                            </span>
                            <span class="font-semibold text-gray-900">{{ number_format($itemAdvancePayment, 2, ',', '.') }} Kz</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-hand-holding-usd w-4 text-orange-600 mr-2"></i>Empréstimo (manual)
                            </span>
                            <span class="font-semibold text-gray-900" x-text="parseFloat(loanDeduction || 0).toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-ellipsis-h w-4 text-orange-600 mr-2"></i>Outros (manual)
                            </span>
                            <span class="font-semibold text-gray-900" x-text="parseFloat(otherDeductions || 0).toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-t-2 border-red-300">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900">Subtotal Descontos Editáveis</span>
                            <span class="text-xl font-bold text-red-600" x-text="totalDeductions.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">* Adiantamentos + IRT + INSS serão adicionados automaticamente</p>
                    </div>
                </div>
            </div>

            {{-- Impostos Automáticos (Info) --}}
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Impostos Calculados Automaticamente (Lei Angolana)
                </h4>
                <div class="grid grid-cols-2 gap-3 text-xs mb-3">
                    <div class="flex items-center justify-between bg-white p-3 rounded-lg">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-orange-600"></i>INSS (3%)
                        </span>
                        <span class="font-bold text-orange-600" x-text="inssEmployee.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    <div class="flex items-center justify-between bg-white p-3 rounded-lg">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-receipt mr-2 text-red-600"></i>IRT (Progressivo)
                        </span>
                        <span class="font-bold text-red-600" x-text="irtAmount.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                </div>
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-xs text-yellow-800 mb-2">
                        <i class="fas fa-star mr-1"></i>
                        <strong>Subsídios:</strong> Isentos de IRT até <strong>30.000 Kz CADA</strong>. Valores acima tributam apenas o excedente.
                    </p>
                    <div class="grid grid-cols-2 gap-2" x-show="taxableFoodAllowance > 0 || taxableTransportAllowance > 0">
                        <div x-show="taxableFoodAllowance > 0" class="bg-white p-2 rounded">
                            <span class="text-xs text-gray-600">Alimentação tributável:</span>
                            <strong class="block text-sm text-orange-600" x-text="taxableFoodAllowance.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></strong>
                        </div>
                        <div x-show="taxableTransportAllowance > 0" class="bg-white p-2 rounded">
                            <span class="text-xs text-gray-600">Transporte tributável:</span>
                            <strong class="block text-sm text-orange-600" x-text="taxableTransportAllowance.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resumo de Descontos --}}
            <div class="bg-white border-2 border-gray-300 rounded-xl p-4 mb-4">
                <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center border-b pb-2">
                    <i class="fas fa-minus-circle mr-2 text-red-600"></i>
                    Resumo de Descontos
                </h4>
                <div class="space-y-2">
                    {{-- INSS --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-orange-600 w-4"></i>
                            INSS (3%)
                        </span>
                        <span class="font-bold text-orange-600" x-text="inssEmployee.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- IRT --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-receipt mr-2 text-red-600 w-4"></i>
                            IRT (Progressivo)
                        </span>
                        <span class="font-bold text-red-600" x-text="irtAmount.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- Adiantamentos --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed" x-show="advancePayment > 0">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-hand-holding-usd mr-2 text-purple-600 w-4"></i>
                            Adiantamentos
                        </span>
                        <span class="font-bold text-purple-600" x-text="advancePayment.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- Faltas --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed" x-show="absenceDeduction > 0">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-calendar-times mr-2 text-amber-600 w-4"></i>
                            Faltas Injustificadas
                        </span>
                        <span class="font-bold text-amber-600" x-text="absenceDeduction.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- Empréstimos --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed" x-show="loanDeduction > 0">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-money-bill-wave mr-2 text-blue-600 w-4"></i>
                            Empréstimos
                        </span>
                        <span class="font-bold text-blue-600" x-text="loanDeduction.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- Outros Descontos --}}
                    <div class="flex items-center justify-between py-2 border-b border-dashed" x-show="otherDeductions > 0">
                        <span class="text-sm text-gray-700 flex items-center">
                            <i class="fas fa-ellipsis-h mr-2 text-gray-600 w-4"></i>
                            Outros Descontos
                        </span>
                        <span class="font-bold text-gray-600" x-text="otherDeductions.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                    
                    {{-- Total Descontos --}}
                    <div class="flex items-center justify-between pt-3 mt-2 border-t-2 border-gray-300">
                        <span class="text-base font-bold text-gray-900 flex items-center">
                            <i class="fas fa-calculator mr-2 text-red-700"></i>
                            Total Descontos
                        </span>
                        <span class="text-lg font-bold text-red-700" x-text="totalDeductions.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></span>
                    </div>
                </div>
            </div>

            {{-- Estimativa Líquido --}}
            <div class="p-5 bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl shadow-lg text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-emerald-100 mb-1">Estimativa Salário Líquido</p>
                        <p class="text-xs text-emerald-200">(Salário Bruto - Total Descontos)</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold" x-text="estimatedNet.toLocaleString('pt-AO', {minimumFractionDigits: 2}) + ' Kz'"></p>
                    </div>
                </div>
            </div>

            {{-- Nota --}}
            <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-xs text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Nota:</strong> Os valores de IRT e INSS serão recalculados automaticamente pelo sistema ao salvar, seguindo as tabelas atualizadas da legislação angolana.
                </p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
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

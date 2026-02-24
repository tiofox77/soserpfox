{{-- Modal Details - Detalhes da Solicitação de Férias --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>

        {{-- Modal Panel --}}
        <div class="relative inline-block w-full max-w-6xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Detalhes da Solicitação de Férias</h3>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="mt-4 space-y-4">
                @if($selectedVacation)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Informações do Funcionário --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-user mr-2 text-gray-600"></i>Informações do Funcionário
                                </h6>
                            </div>
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Nome:</span>
                                    <span class="text-sm text-gray-900">{{ $selectedVacation->employee->full_name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Nº Funcionário:</span>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-semibold">
                                        {{ $selectedVacation->employee->employee_number ?? '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Departamento:</span>
                                    <span class="text-sm text-gray-900">{{ $selectedVacation->employee->department->name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Cargo:</span>
                                    <span class="text-sm text-gray-900">{{ $selectedVacation->employee->position->title ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Informações das Férias --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-umbrella-beach mr-2 text-gray-600"></i>Informações das Férias
                                </h6>
                            </div>
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Nº Férias:</span>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-semibold">
                                        {{ $selectedVacation->vacation_number }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Ano Referência:</span>
                                    <span class="text-sm text-gray-900">{{ $selectedVacation->reference_year }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Tipo:</span>
                                    @if($selectedVacation->vacation_type === 'normal')
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">Normal</span>
                                    @elseif($selectedVacation->vacation_type === 'accumulated')
                                        <span class="px-2 py-1 bg-cyan-100 text-cyan-700 rounded text-xs font-semibold">Acumuladas</span>
                                    @elseif($selectedVacation->vacation_type === 'advance')
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-semibold">Antecipadas</span>
                                    @else
                                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-semibold">Coletivas</span>
                                    @endif
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Período Aquisitivo:</span>
                                    <span class="text-sm text-gray-900">
                                        {{ $selectedVacation->period_start ? $selectedVacation->period_start->format('d/m/Y') : '-' }} a 
                                        {{ $selectedVacation->period_end ? $selectedVacation->period_end->format('d/m/Y') : '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Status:</span>
                                    @if($selectedVacation->status === 'pending')
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-semibold">Pendente</span>
                                    @elseif($selectedVacation->status === 'approved')
                                        <span class="px-2 py-1 bg-cyan-100 text-cyan-700 rounded text-xs font-semibold">Aprovada</span>
                                    @elseif($selectedVacation->status === 'rejected')
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-semibold">Rejeitada</span>
                                    @elseif($selectedVacation->status === 'in_progress')
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">Em Andamento</span>
                                    @elseif($selectedVacation->status === 'completed')
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">Concluída</span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-semibold">Cancelada</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Período de Férias --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-calendar-alt mr-2 text-gray-600"></i>Período de Férias
                                </h6>
                            </div>
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Data de Início:</span>
                                    <span class="text-sm text-blue-600">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        {{ $selectedVacation->start_date->format('d/m/Y') }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Data de Término:</span>
                                    <span class="text-sm text-green-600">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        {{ $selectedVacation->end_date->format('d/m/Y') }}
                                    </span>
                                </div>
                                @if($selectedVacation->expected_return_date ?? null)
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Retorno Previsto:</span>
                                    <span class="text-sm text-cyan-600">
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        {{ $selectedVacation->expected_return_date->format('d/m/Y') }}
                                    </span>
                                </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Dias Corridos:</span>
                                    <span class="text-sm font-bold text-gray-900">{{ $selectedVacation->requested_days }} dias</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Dias Úteis:</span>
                                    <span class="text-sm font-bold text-purple-600">{{ $selectedVacation->working_days }} dias úteis</span>
                                </div>
                                @if($selectedVacation->split_number)
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Divisão:</span>
                                    <span class="px-2 py-1 bg-cyan-100 text-cyan-700 rounded text-xs font-semibold">
                                        {{ $selectedVacation->split_number }}ª parcela de {{ $selectedVacation->total_splits }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Direito e Valores --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-money-bill-wave mr-2 text-gray-600"></i>Cálculos Financeiros
                                </h6>
                            </div>
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Dias com Direito:</span>
                                    <span class="text-sm text-gray-900">
                                        {{ $selectedVacation->entitled_days }} dias ({{ $selectedVacation->working_months }} meses)
                                    </span>
                                </div>
                                @if(($selectedVacation->previous_balance ?? 0) > 0)
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Saldo Anterior:</span>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-semibold">
                                        +{{ $selectedVacation->previous_balance }} dias
                                    </span>
                                </div>
                                @endif
                                @if(($selectedVacation->accumulated_days ?? 0) > 0)
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Dias Acumulados:</span>
                                    <span class="px-2 py-1 bg-cyan-100 text-cyan-700 rounded text-xs font-semibold">
                                        {{ $selectedVacation->accumulated_days }} dias
                                    </span>
                                </div>
                                @endif
                                @if(($selectedVacation->days_remaining ?? 0) > 0)
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Dias Restantes:</span>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-semibold">
                                        {{ $selectedVacation->days_remaining }} dias
                                    </span>
                                </div>
                                @endif
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-sm font-semibold text-gray-600">Taxa Diária:</span>
                                    <span class="text-sm text-gray-900">{{ number_format($selectedVacation->daily_rate, 2, ',', '.') }} Kz</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Pagamento Férias:</span>
                                    <span class="text-sm font-bold text-green-600">{{ number_format($selectedVacation->vacation_pay, 2, ',', '.') }} Kz</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-semibold text-gray-600">Subsídio (14º):</span>
                                    <span class="text-sm font-bold text-green-600">{{ number_format($selectedVacation->subsidy_amount, 2, ',', '.') }} Kz</span>
                                </div>
                                <div class="flex justify-between bg-green-50 -mx-4 px-4 py-2 mt-2">
                                    <span class="text-sm font-bold text-gray-900">Total a Receber:</span>
                                    <span class="text-lg font-bold text-green-600">{{ number_format($selectedVacation->total_amount, 2, ',', '.') }} Kz</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Aprovação/Rejeição --}}
                    @if($selectedVacation->status === 'approved' || $selectedVacation->status === 'rejected')
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 border-b {{ $selectedVacation->status === 'approved' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                                <h6 class="font-semibold {{ $selectedVacation->status === 'approved' ? 'text-green-900' : 'text-red-900' }}">
                                    @if($selectedVacation->status === 'approved')
                                        <i class="fas fa-check-circle mr-2"></i>Aprovação
                                    @else
                                        <i class="fas fa-times-circle mr-2"></i>Rejeição
                                    @endif
                                </h6>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-600">
                                            {{ $selectedVacation->status === 'approved' ? 'Aprovado por:' : 'Rejeitado por:' }}
                                        </span>
                                        <p class="text-sm text-gray-900">
                                            {{ $selectedVacation->status === 'approved' ? ($selectedVacation->approvedBy->name ?? '-') : ($selectedVacation->rejectedBy->name ?? '-') }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-semibold text-gray-600">Data:</span>
                                        <p class="text-sm text-gray-900">
                                            {{ $selectedVacation->status === 'approved' ? $selectedVacation->approved_at->format('d/m/Y H:i') : $selectedVacation->rejected_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                    @if($selectedVacation->rejection_reason)
                                        <div class="col-span-2">
                                            <span class="text-sm font-semibold text-gray-600">Motivo da Rejeição:</span>
                                            <p class="text-sm text-gray-700 mt-1">{{ $selectedVacation->rejection_reason }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Adiantamento (Subsídio) --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-hand-holding-usd mr-2 text-gray-600"></i>Adiantamento (14º)
                                </h6>
                            </div>
                            <div class="p-4">
                                @if($selectedVacation->advance_paid)
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-check-circle text-green-600 text-lg mr-2 mt-0.5"></i>
                                            <div class="text-sm">
                                                <p class="font-semibold text-green-900">Pago em:</p>
                                                <p class="text-green-700">{{ $selectedVacation->advance_paid_date ? $selectedVacation->advance_paid_date->format('d/m/Y') : '-' }}</p>
                                                <p class="font-semibold text-green-900 mt-2">Autorizado por:</p>
                                                <p class="text-green-700">{{ $selectedVacation->advancePaidBy->name ?? '-' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 text-lg mr-2 mt-0.5"></i>
                                            <div class="text-sm">
                                                <p class="font-semibold text-yellow-900">Adiantamento não pago</p>
                                                @if($selectedVacation->advance_payment_date ?? null)
                                                    <p class="text-yellow-700 text-xs mt-1">
                                                        Data limite: {{ $selectedVacation->advance_payment_date->format('d/m/Y') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Pagamento Final --}}
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-money-check mr-2 text-gray-600"></i>Pagamento Final
                                </h6>
                            </div>
                            <div class="p-4">
                                @if($selectedVacation->paid)
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-check-circle text-green-600 text-lg mr-2 mt-0.5"></i>
                                            <div class="text-sm">
                                                <p class="font-semibold text-green-900">Pago em:</p>
                                                <p class="text-green-700">{{ $selectedVacation->paid_date ? $selectedVacation->paid_date->format('d/m/Y') : '-' }}</p>
                                                <p class="font-semibold text-green-900 mt-2">Pago por:</p>
                                                <p class="text-green-700">{{ $selectedVacation->paidBy->name ?? '-' }}</p>
                                                @if($selectedVacation->processed_in_payroll ?? false)
                                                    <span class="inline-block mt-2 px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">
                                                        Processado em folha
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 text-lg mr-2 mt-0.5"></i>
                                            <div class="text-sm">
                                                <p class="font-semibold text-yellow-900">Pagamento ainda não realizado</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Retorno ao Trabalho --}}
                    @if($selectedVacation->actual_return_date || $selectedVacation->status === 'completed')
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-calendar-check mr-2 text-gray-600"></i>Retorno ao Trabalho
                                </h6>
                            </div>
                            <div class="p-4">
                                @if($selectedVacation->actual_return_date)
                                    <div class="space-y-2">
                                        <div>
                                            <span class="text-sm font-semibold text-gray-600">Data de Retorno:</span>
                                            <span class="text-sm text-gray-900 ml-2">
                                                {{ $selectedVacation->actual_return_date->format('d/m/Y') }}
                                            </span>
                                            @if($selectedVacation->returned_on_time !== null)
                                                @if($selectedVacation->returned_on_time)
                                                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">No Prazo</span>
                                                @else
                                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-semibold">Com Atraso</span>
                                                @endif
                                            @endif
                                        </div>
                                        @if($selectedVacation->return_notes)
                                            <div class="mt-3">
                                                <span class="text-sm font-semibold text-gray-600">Observações do Retorno:</span>
                                                <p class="text-sm text-gray-700 mt-1">{{ $selectedVacation->return_notes }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Funcionário Substituto --}}
                    @if($selectedVacation->replacementEmployee ?? null)
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-user-friends mr-2 text-gray-600"></i>Funcionário Substituto
                                </h6>
                            </div>
                            <div class="p-4">
                                <p class="text-sm text-gray-900">
                                    <strong>{{ $selectedVacation->replacementEmployee->full_name }}</strong>
                                    ({{ $selectedVacation->replacementEmployee->employee_number }})
                                    - {{ $selectedVacation->replacementEmployee->position->title ?? '-' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Férias Coletivas --}}
                    @if($selectedVacation->is_collective ?? false)
                        <div class="bg-purple-50 border border-purple-200 rounded-xl overflow-hidden">
                            <div class="bg-purple-600 px-4 py-3 border-b border-purple-700">
                                <h6 class="font-semibold text-white">
                                    <i class="fas fa-users mr-2"></i>Férias Coletivas
                                </h6>
                            </div>
                            <div class="p-4">
                                <div class="bg-cyan-50 border border-cyan-200 rounded-xl p-3">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-cyan-600 text-lg mr-2 mt-0.5"></i>
                                        <div class="text-sm text-cyan-900">
                                            Esta é uma solicitação de <strong>férias coletivas</strong>
                                            @if($selectedVacation->collective_group)
                                                para: <strong>{{ $selectedVacation->collective_group }}</strong>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Documento Anexo --}}
                    @if($selectedVacation->attachment_path ?? null)
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-paperclip mr-2 text-gray-600"></i>Documento Anexo
                                </h6>
                            </div>
                            <div class="p-4">
                                <a href="{{ asset('storage/' . $selectedVacation->attachment_path) }}" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all">
                                    <i class="fas fa-download mr-2"></i>Baixar Documento
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- Observações --}}
                    @if($selectedVacation->notes)
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h6 class="font-semibold text-gray-900">
                                    <i class="fas fa-sticky-note mr-2 text-gray-600"></i>Observações
                                </h6>
                            </div>
                            <div class="p-4">
                                <p class="text-sm text-gray-700">{{ $selectedVacation->notes }}</p>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500">A carregar detalhes...</p>
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
                <button type="button" wire:click="closeModal"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>

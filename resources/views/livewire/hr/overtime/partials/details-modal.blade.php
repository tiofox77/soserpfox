{{-- Modal Backdrop --}}
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     x-data="{ show: @entangle('showDetailsModal') }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click.self="$wire.set('showDetailsModal', false)">
    
    {{-- Modal Content --}}
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95">
        
        @if($selectedOvertime)
            {{-- Header --}}
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-xl flex items-center">
                    <i class="fas fa-info-circle mr-3"></i>
                    Detalhes das Horas Extras
                </h3>
                <button wire:click="$set('showDetailsModal', false)" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- Informações do Funcionário --}}
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-blue-900 mb-4 flex items-center">
                            <i class="fas fa-user mr-2"></i>Funcionário
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Nome</p>
                                <p class="font-bold text-gray-900">{{ $selectedOvertime->employee->full_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Número</p>
                                <p class="font-semibold text-blue-600">{{ $selectedOvertime->employee->employee_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Departamento</p>
                                <p class="font-semibold text-gray-900">{{ $selectedOvertime->employee->department->name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Cargo</p>
                                <p class="font-semibold text-gray-900">{{ $selectedOvertime->employee->position->title ?? '-' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Informações do Registro --}}
                    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-indigo-900 mb-4 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Registro
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Número</p>
                                <p class="font-bold text-indigo-600">{{ $selectedOvertime->overtime_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Data</p>
                                <p class="font-semibold text-gray-900">{{ $selectedOvertime->date->format('d/m/Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Tipo</p>
                                <p class="font-semibold text-gray-900">
                                    @if($selectedOvertime->overtime_type === 'regular')
                                        ⏰ Regular (+50%)
                                    @elseif($selectedOvertime->overtime_type === 'holiday')
                                        📅 Feriado (+100%)
                                    @elseif($selectedOvertime->overtime_type === 'night')
                                        🌙 Noturno (+75%)
                                    @else
                                        🗓️ Fim de Semana (+100%)
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Status</p>
                                @if($selectedOvertime->status === 'pending')
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pendente</span>
                                @elseif($selectedOvertime->status === 'approved')
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Aprovada</span>
                                @elseif($selectedOvertime->status === 'rejected')
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Rejeitada</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Horários --}}
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-green-900 mb-4 flex items-center">
                            <i class="fas fa-business-time mr-2"></i>Horários
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Início</p>
                                <p class="font-bold text-green-600 text-xl">{{ $selectedOvertime->start_time }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Término</p>
                                <p class="font-bold text-green-600 text-xl">{{ $selectedOvertime->end_time }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Total de Horas</p>
                                <p class="font-bold text-indigo-600 text-2xl">{{ number_format($selectedOvertime->total_hours, 1) }}h</p>
                            </div>
                        </div>
                    </div>

                    {{-- Valores --}}
                    <div class="bg-gradient-to-br from-green-50 to-teal-50 border border-green-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-green-900 mb-4 flex items-center">
                            <i class="fas fa-money-bill-wave mr-2"></i>Valores
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Taxa Horária Base</p>
                                <p class="font-semibold text-gray-900">{{ number_format($selectedOvertime->hourly_rate, 2, ',', '.') }} Kz/h</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Taxa Adicional</p>
                                <p class="font-semibold text-orange-600">{{ $selectedOvertime->overtime_rate }}%</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Valor Total</p>
                                <p class="font-bold text-green-600 text-2xl">{{ number_format($selectedOvertime->total_amount, 2, ',', '.') }} Kz</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Status Pagamento</p>
                                @if($selectedOvertime->paid)
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check mr-1"></i>Pago
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                        <i class="fas fa-clock mr-1"></i>Pendente
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Descrição --}}
                    <div class="md:col-span-2 bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-align-left mr-2"></i>Descrição das Atividades
                        </h4>
                        <p class="text-gray-700">{{ $selectedOvertime->description }}</p>
                    </div>

                    @if($selectedOvertime->notes)
                        {{-- Notas --}}
                        <div class="md:col-span-2 bg-gradient-to-br from-yellow-50 to-amber-50 border border-yellow-200 rounded-xl p-6">
                            <h4 class="text-lg font-bold text-yellow-900 mb-3 flex items-center">
                                <i class="fas fa-sticky-note mr-2"></i>Notas
                            </h4>
                            <p class="text-gray-700">{{ $selectedOvertime->notes }}</p>
                        </div>
                    @endif

                    {{-- Informações de Aprovação/Rejeição --}}
                    @if($selectedOvertime->status !== 'pending')
                        <div class="md:col-span-2 bg-gradient-to-br from-indigo-50 to-blue-50 border border-indigo-200 rounded-xl p-6">
                            <h4 class="text-lg font-bold text-indigo-900 mb-4 flex items-center">
                                <i class="fas fa-user-check mr-2"></i>
                                @if($selectedOvertime->status === 'approved')
                                    Informações de Aprovação
                                @else
                                    Informações de Rejeição
                                @endif
                            </h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-600">
                                        @if($selectedOvertime->status === 'approved')
                                            Aprovado por
                                        @else
                                            Rejeitado por
                                        @endif
                                    </p>
                                    <p class="font-semibold text-gray-900">
                                        {{ $selectedOvertime->approvedBy->name ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600">Data</p>
                                    <p class="font-semibold text-gray-900">
                                        @if($selectedOvertime->status === 'approved' && $selectedOvertime->approved_at)
                                            {{ $selectedOvertime->approved_at->format('d/m/Y H:i') }}
                                        @elseif($selectedOvertime->rejected_at)
                                            {{ $selectedOvertime->rejected_at->format('d/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                @if($selectedOvertime->status === 'rejected' && $selectedOvertime->rejection_reason)
                                    <div class="col-span-2">
                                        <p class="text-xs text-gray-600 mb-1">Motivo da Rejeição</p>
                                        <p class="text-red-700 bg-red-50 p-3 rounded-lg">{{ $selectedOvertime->rejection_reason }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end">
                <button wire:click="$set('showDetailsModal', false)"
                        class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        @endif
    </div>
</div>

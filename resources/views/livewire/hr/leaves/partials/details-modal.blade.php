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
        
        @if($selectedLeave)
            {{-- Header --}}
            <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-white font-bold text-xl flex items-center">
                    <i class="fas fa-info-circle mr-3"></i>
                    Detalhes da Licença
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
                                <p class="font-bold text-gray-900">{{ $selectedLeave->employee->full_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Número</p>
                                <p class="font-semibold text-blue-600">{{ $selectedLeave->employee->employee_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Departamento</p>
                                <p class="font-semibold text-gray-900">{{ $selectedLeave->employee->department->name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Cargo</p>
                                <p class="font-semibold text-gray-900">{{ $selectedLeave->employee->position->title ?? '-' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Informações da Licença --}}
                    <div class="bg-gradient-to-br from-orange-50 to-red-50 border border-orange-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-orange-900 mb-4 flex items-center">
                            <i class="fas fa-file-medical mr-2"></i>Licença
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Número</p>
                                <p class="font-bold text-orange-600">{{ $selectedLeave->leave_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Tipo</p>
                                <p class="font-semibold text-gray-900">
                                    @if($selectedLeave->leave_type === 'sick')
                                        🩺 Doença
                                    @elseif($selectedLeave->leave_type === 'personal')
                                        👤 Pessoal
                                    @elseif($selectedLeave->leave_type === 'bereavement')
                                        ✝️ Luto
                                    @elseif($selectedLeave->leave_type === 'maternity')
                                        👶 Maternidade
                                    @elseif($selectedLeave->leave_type === 'paternity')
                                        🍼 Paternidade
                                    @elseif($selectedLeave->leave_type === 'unpaid')
                                        💰 Sem Vencimento
                                    @else
                                        📄 Outro
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Status</p>
                                @if($selectedLeave->status === 'pending')
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pendente</span>
                                @elseif($selectedLeave->status === 'approved')
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Aprovada</span>
                                @elseif($selectedLeave->status === 'rejected')
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Rejeitada</span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Cancelada</span>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Pago</p>
                                @if($selectedLeave->paid)
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check mr-1"></i>Sim
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                        <i class="fas fa-times mr-1"></i>Não
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Período --}}
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-green-900 mb-4 flex items-center">
                            <i class="fas fa-calendar mr-2"></i>Período
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Data Início</p>
                                <p class="font-bold text-green-600">{{ $selectedLeave->start_date->format('d/m/Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Data Fim</p>
                                <p class="font-bold text-green-600">{{ $selectedLeave->end_date->format('d/m/Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Duração</p>
                                <p class="font-semibold text-gray-900">{{ $selectedLeave->working_days }} dias úteis</p>
                                <p class="text-xs text-gray-500">({{ $selectedLeave->total_days }} dias corridos)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Atestado e Documento --}}
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-purple-900 mb-4 flex items-center">
                            <i class="fas fa-paperclip mr-2"></i>Documentação
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Atestado Médico</p>
                                @if($selectedLeave->has_medical_certificate)
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check mr-1"></i>Sim
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                        <i class="fas fa-times mr-1"></i>Não
                                    </span>
                                @endif
                            </div>
                            @if($selectedLeave->document_path)
                                <div>
                                    <p class="text-xs text-gray-600 mb-2">Documento Anexado</p>
                                    <a href="{{ Storage::url($selectedLeave->document_path) }}" target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm">
                                        <i class="fas fa-download mr-2"></i>Download
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Motivo --}}
                    <div class="md:col-span-2 bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-6">
                        <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-comment-alt mr-2"></i>Motivo
                        </h4>
                        <p class="text-gray-700">{{ $selectedLeave->reason }}</p>
                    </div>

                    @if($selectedLeave->notes)
                        {{-- Notas --}}
                        <div class="md:col-span-2 bg-gradient-to-br from-yellow-50 to-amber-50 border border-yellow-200 rounded-xl p-6">
                            <h4 class="text-lg font-bold text-yellow-900 mb-3 flex items-center">
                                <i class="fas fa-sticky-note mr-2"></i>Notas
                            </h4>
                            <p class="text-gray-700">{{ $selectedLeave->notes }}</p>
                        </div>
                    @endif

                    {{-- Informações de Aprovação/Rejeição --}}
                    @if($selectedLeave->status !== 'pending')
                        <div class="md:col-span-2 bg-gradient-to-br from-indigo-50 to-blue-50 border border-indigo-200 rounded-xl p-6">
                            <h4 class="text-lg font-bold text-indigo-900 mb-4 flex items-center">
                                <i class="fas fa-user-check mr-2"></i>
                                @if($selectedLeave->status === 'approved')
                                    Informações de Aprovação
                                @else
                                    Informações de Rejeição
                                @endif
                            </h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-600">
                                        @if($selectedLeave->status === 'approved')
                                            Aprovado por
                                        @else
                                            Rejeitado por
                                        @endif
                                    </p>
                                    <p class="font-semibold text-gray-900">
                                        @if($selectedLeave->status === 'approved')
                                            {{ $selectedLeave->approvedBy->name ?? '-' }}
                                        @else
                                            {{ $selectedLeave->rejectedBy->name ?? '-' }}
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600">Data</p>
                                    <p class="font-semibold text-gray-900">
                                        @if($selectedLeave->status === 'approved' && $selectedLeave->approved_at)
                                            {{ $selectedLeave->approved_at->format('d/m/Y H:i') }}
                                        @elseif($selectedLeave->rejected_at)
                                            {{ $selectedLeave->rejected_at->format('d/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                @if($selectedLeave->status === 'rejected' && $selectedLeave->rejection_reason)
                                    <div class="col-span-2">
                                        <p class="text-xs text-gray-600 mb-1">Motivo da Rejeição</p>
                                        <p class="text-red-700 bg-red-50 p-3 rounded-lg">{{ $selectedLeave->rejection_reason }}</p>
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

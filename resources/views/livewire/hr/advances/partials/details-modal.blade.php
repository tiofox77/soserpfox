{{-- Modal Detalhes do Adiantamento --}}
@if($showDetailsModal && $selectedAdvance)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-file-invoice-dollar mr-3"></i>
                            Detalhes do Adiantamento
                        </h3>
                        <p class="text-purple-200 text-sm mt-1">{{ $selectedAdvance->advance_number }}</p>
                    </div>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6">
                {{-- Status Badge --}}
                <div class="mb-6 flex justify-center">
                    @if($selectedAdvance->status === 'pending')
                        <span class="px-6 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold flex items-center">
                            <i class="fas fa-clock mr-2"></i>Pendente de Aprovação
                        </span>
                    @elseif($selectedAdvance->status === 'approved')
                        <span class="px-6 py-2 bg-green-100 text-green-800 rounded-full text-sm font-semibold flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>Aprovado
                        </span>
                    @elseif($selectedAdvance->status === 'rejected')
                        <span class="px-6 py-2 bg-red-100 text-red-800 rounded-full text-sm font-semibold flex items-center">
                            <i class="fas fa-times-circle mr-2"></i>Rejeitado
                        </span>
                    @elseif($selectedAdvance->status === 'paid')
                        <span class="px-6 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold flex items-center">
                            <i class="fas fa-money-bill-wave mr-2"></i>Pago
                        </span>
                    @else
                        <span class="px-6 py-2 bg-gray-100 text-gray-800 rounded-full text-sm font-semibold flex items-center">
                            <i class="fas fa-check-double mr-2"></i>Completado
                        </span>
                    @endif
                </div>

                {{-- Informações do Funcionário --}}
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-5 mb-6 border border-blue-200">
                    <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-circle text-blue-600 mr-2 text-xl"></i>
                        Informações do Funcionário
                    </h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Nome:</span>
                            <p class="font-semibold text-gray-800">{{ $selectedAdvance->employee->full_name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600">Nº Funcionário:</span>
                            <p class="font-semibold text-gray-800">{{ $selectedAdvance->employee->employee_number }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600">Departamento:</span>
                            <p class="font-semibold text-gray-800">{{ $selectedAdvance->employee->department->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600">Cargo:</span>
                            <p class="font-semibold text-gray-800">{{ $selectedAdvance->employee->position ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Informações Financeiras --}}
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-5 mb-6 border border-green-200">
                    <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-money-bill-wave text-green-600 mr-2 text-xl"></i>
                        Informações Financeiras
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center pb-3 border-b border-green-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-hand-holding-usd mr-2 text-blue-600"></i>Valor Solicitado:
                            </span>
                            <span class="text-xl font-bold text-blue-600">{{ number_format($selectedAdvance->requested_amount, 2, ',', '.') }} Kz</span>
                        </div>
                        
                        @if($selectedAdvance->status !== 'pending' && $selectedAdvance->status !== 'rejected')
                            <div class="flex justify-between items-center pb-3 border-b border-green-200">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-green-600"></i>Valor Aprovado:
                                </span>
                                <span class="text-xl font-bold text-green-600">{{ number_format($selectedAdvance->approved_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        @endif
                        
                        <div class="flex justify-between items-center pb-3 border-b border-green-200">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-calendar-check mr-2 text-purple-600"></i>Número de Parcelas:
                            </span>
                            <span class="text-lg font-bold text-purple-600">{{ $selectedAdvance->installments }}x</span>
                        </div>
                        
                        @if($selectedAdvance->status !== 'pending' && $selectedAdvance->status !== 'rejected')
                            <div class="flex justify-between items-center bg-purple-100 p-3 rounded-lg">
                                <span class="text-purple-700 font-semibold flex items-center">
                                    <i class="fas fa-calculator mr-2"></i>Valor por Parcela:
                                </span>
                                <span class="text-lg font-bold text-purple-700">{{ number_format($selectedAdvance->installment_amount, 2, ',', '.') }} Kz</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Motivo e Observações --}}
                <div class="bg-gray-50 rounded-xl p-5 mb-6 border border-gray-200">
                    <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-comment-dots text-orange-600 mr-2"></i>
                        Motivo do Adiantamento
                    </h4>
                    <p class="text-gray-700 text-sm leading-relaxed">{{ $selectedAdvance->reason }}</p>
                    
                    @if($selectedAdvance->notes)
                        <div class="mt-4 pt-4 border-t border-gray-300">
                            <h5 class="font-semibold text-gray-700 mb-2 flex items-center text-sm">
                                <i class="fas fa-sticky-note text-gray-600 mr-2"></i>
                                Observações Adicionais
                            </h5>
                            <p class="text-gray-600 text-sm">{{ $selectedAdvance->notes }}</p>
                        </div>
                    @endif
                </div>

                {{-- Informações de Aprovação/Rejeição --}}
                @if($selectedAdvance->status === 'approved' || $selectedAdvance->status === 'paid' || $selectedAdvance->status === 'completed')
                    <div class="bg-green-50 rounded-xl p-5 mb-6 border border-green-200">
                        <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-user-check text-green-600 mr-2"></i>
                            Informações de Aprovação
                        </h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Aprovado por:</span>
                                <p class="font-semibold text-gray-800">{{ $selectedAdvance->approvedBy->name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-600">Data de Aprovação:</span>
                                <p class="font-semibold text-gray-800">{{ $selectedAdvance->approved_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                        @if($selectedAdvance->approval_notes)
                            <div class="mt-3 pt-3 border-t border-green-300">
                                <span class="text-gray-600 text-sm">Observações:</span>
                                <p class="text-gray-700 text-sm mt-1">{{ $selectedAdvance->approval_notes }}</p>
                            </div>
                        @endif
                    </div>
                @elseif($selectedAdvance->status === 'rejected')
                    <div class="bg-red-50 rounded-xl p-5 mb-6 border border-red-200">
                        <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-user-times text-red-600 mr-2"></i>
                            Informações de Rejeição
                        </h4>
                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <span class="text-gray-600">Rejeitado por:</span>
                                <p class="font-semibold text-gray-800">{{ $selectedAdvance->rejectedBy->name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-600">Data de Rejeição:</span>
                                <p class="font-semibold text-gray-800">{{ $selectedAdvance->rejected_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                        <div class="bg-red-100 p-3 rounded-lg">
                            <span class="text-red-700 font-semibold text-sm">Motivo da Rejeição:</span>
                            <p class="text-red-800 text-sm mt-1">{{ $selectedAdvance->rejection_reason }}</p>
                        </div>
                    </div>
                @endif

                {{-- Timeline --}}
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                    <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Histórico
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="bg-blue-500 rounded-full p-2 mr-3">
                                <i class="fas fa-plus text-white text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-800">Solicitação Criada</p>
                                <p class="text-xs text-gray-500">{{ $selectedAdvance->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                        
                        @if($selectedAdvance->approved_at)
                            <div class="flex items-start">
                                <div class="bg-green-500 rounded-full p-2 mr-3">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">Aprovado</p>
                                    <p class="text-xs text-gray-500">{{ $selectedAdvance->approved_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        @endif
                        
                        @if($selectedAdvance->rejected_at)
                            <div class="flex items-start">
                                <div class="bg-red-500 rounded-full p-2 mr-3">
                                    <i class="fas fa-times text-white text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">Rejeitado</p>
                                    <p class="text-xs text-gray-500">{{ $selectedAdvance->rejected_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        @endif
                        
                        @if($selectedAdvance->paid_at)
                            <div class="flex items-start">
                                <div class="bg-emerald-500 rounded-full p-2 mr-3">
                                    <i class="fas fa-money-bill-wave text-white text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">Pago</p>
                                    <p class="text-xs text-gray-500">{{ $selectedAdvance->paid_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="border-t p-6">
                <button wire:click="closeModal"
                        class="w-full px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        </div>
    </div>
@endif

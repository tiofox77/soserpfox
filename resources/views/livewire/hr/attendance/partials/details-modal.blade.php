{{-- Modal View - Detalhes do Registro de Presença --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>

        {{-- Modal Panel --}}
        <div class="relative inline-block w-full max-w-2xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Detalhes do Registro de Presença</h3>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="mt-4 space-y-4">
                @if($selectedAttendance)
                    {{-- Grid 2 colunas --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Informações do Funcionário --}}
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h6 class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                <i class="fas fa-user mr-2"></i>Funcionário
                            </h6>
                            <h5 class="font-bold text-gray-900 mb-2">{{ $selectedAttendance->employee->full_name ?? 'N/A' }}</h5>
                            <p class="text-sm text-gray-600 mb-1">
                                <i class="fas fa-id-badge mr-2 text-gray-400"></i>
                                {{ $selectedAttendance->employee->employee_number ?? '-' }}
                            </p>
                            @if($selectedAttendance->employee->department ?? null)
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-building mr-2 text-gray-400"></i>
                                    {{ $selectedAttendance->employee->department->name }}
                                </p>
                            @endif
                        </div>

                        {{-- Data e Status --}}
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h6 class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                <i class="fas fa-calendar mr-2"></i>Data e Status
                            </h6>
                            <p class="mb-2">
                                <span class="text-sm text-gray-600">Data:</span>
                                <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm font-semibold">
                                    {{ $selectedAttendance->date ? $selectedAttendance->date->format('d/m/Y') : '-' }}
                                </span>
                            </p>
                            <p>
                                <span class="text-sm text-gray-600">Status:</span>
                                @if($selectedAttendance->status === 'present')
                                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 rounded-lg text-sm font-semibold">Presente</span>
                                @elseif($selectedAttendance->status === 'absent')
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-700 rounded-lg text-sm font-semibold">Ausente</span>
                                @elseif($selectedAttendance->status === 'late')
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-sm font-semibold">Atrasado</span>
                                @elseif($selectedAttendance->status === 'half_day')
                                    <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm font-semibold">Meio Período</span>
                                @elseif($selectedAttendance->status === 'sick')
                                    <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm font-semibold">Doente</span>
                                @else
                                    <span class="ml-2 px-2 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-semibold">Férias</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Horários --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase mb-4">
                            <i class="fas fa-clock mr-2"></i>Controle de Horário
                        </h6>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="p-4 bg-green-50 rounded-xl">
                                <i class="fas fa-sign-in-alt text-2xl text-green-600 mb-2"></i>
                                <p class="text-xs text-gray-500 mb-1">Entrada</p>
                                <p class="text-xl font-bold text-gray-900">
                                    {{ $selectedAttendance->check_in ? substr($selectedAttendance->check_in, 0, 5) : '-' }}
                                </p>
                            </div>
                            <div class="p-4 bg-red-50 rounded-xl">
                                <i class="fas fa-sign-out-alt text-2xl text-red-600 mb-2"></i>
                                <p class="text-xs text-gray-500 mb-1">Saída</p>
                                <p class="text-xl font-bold text-gray-900">
                                    {{ $selectedAttendance->check_out ? substr($selectedAttendance->check_out, 0, 5) : '-' }}
                                </p>
                            </div>
                            <div class="p-4 bg-cyan-50 rounded-xl">
                                <i class="fas fa-hourglass-half text-2xl text-cyan-600 mb-2"></i>
                                <p class="text-xs text-gray-500 mb-1">Horas Trabalhadas</p>
                                <p class="text-xl font-bold text-gray-900">
                                    {{ $selectedAttendance->hours_worked ? number_format($selectedAttendance->hours_worked, 2) . 'h' : '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Observações --}}
                    @if($selectedAttendance->notes)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h6 class="text-xs font-semibold text-gray-500 uppercase mb-3">
                                <i class="fas fa-sticky-note mr-2"></i>Observações
                            </h6>
                            <p class="text-gray-700">{{ $selectedAttendance->notes }}</p>
                        </div>
                    @endif

                    {{-- Informações de Registro --}}
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="flex flex-wrap justify-between text-xs text-gray-500">
                            <span>
                                <i class="fas fa-calendar-plus mr-1"></i>
                                Registrado em: {{ $selectedAttendance->created_at->format('d/m/Y H:i') }}
                            </span>
                            @if($selectedAttendance->updated_at != $selectedAttendance->created_at)
                                <span>
                                    <i class="fas fa-edit mr-1"></i>
                                    Atualizado em: {{ $selectedAttendance->updated_at->format('d/m/Y H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>
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
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end">
                <button type="button" wire:click="closeModal"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>

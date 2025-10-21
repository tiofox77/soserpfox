{{-- Modal de Visualização de Turno --}}
@if($showViewModal && $viewingShift)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100">
    
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm" wire:click="closeViewModal"></div>
    
    {{-- Modal --}}
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border-4 border-purple-500"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                             style="background-color: {{ $viewingShift->color }};">
                            <i class="fas {{ $viewingShift->is_night_shift ? 'fa-moon' : 'fa-sun' }} text-2xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $viewingShift->name }}</h3>
                            <p class="text-purple-100 text-sm">{{ $viewingShift->code ?? 'Sem código' }}</p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:bg-white/20 p-2 rounded-lg transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                
                {{-- Informações Básicas --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-purple-700 mb-4 flex items-center border-b-2 border-purple-200 pb-2">
                        <i class="fas fa-info-circle mr-2"></i>Informações Básicas
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Nome do Turno</label>
                            <p class="font-bold text-gray-800">{{ $viewingShift->name }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Código</label>
                            <p class="font-bold text-gray-800">{{ $viewingShift->code ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Status</label>
                            <p class="font-bold">
                                @if($viewingShift->is_active)
                                    <span class="text-green-600">✓ Ativo</span>
                                @else
                                    <span class="text-gray-600">✗ Inativo</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    @if($viewingShift->description)
                    <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                        <label class="text-xs text-gray-500 uppercase">Descrição</label>
                        <p class="text-gray-800 mt-1">{{ $viewingShift->description }}</p>
                    </div>
                    @endif
                </div>

                {{-- Horário e Carga Horária --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-indigo-700 mb-4 flex items-center border-b-2 border-indigo-200 pb-2">
                        <i class="fas fa-clock mr-2"></i>Horário e Carga Horária
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 p-4 rounded-lg border border-blue-200">
                            <label class="text-xs text-blue-600 uppercase font-bold">Hora Início</label>
                            <p class="font-bold text-blue-700 text-2xl mt-1">
                                {{ $viewingShift->start_time ? $viewingShift->start_time->format('H:i') : '—' }}
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-pink-50 p-4 rounded-lg border border-red-200">
                            <label class="text-xs text-red-600 uppercase font-bold">Hora Fim</label>
                            <p class="font-bold text-red-700 text-2xl mt-1">
                                {{ $viewingShift->end_time ? $viewingShift->end_time->format('H:i') : '—' }}
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-4 rounded-lg border border-green-200">
                            <label class="text-xs text-green-600 uppercase font-bold">Horas por Dia</label>
                            <p class="font-bold text-green-700 text-2xl mt-1">
                                {{ number_format($viewingShift->hours_per_day, 1) }}h
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Dias de Trabalho --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-teal-700 mb-4 flex items-center border-b-2 border-teal-200 pb-2">
                        <i class="fas fa-calendar-week mr-2"></i>Dias de Trabalho
                    </h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        @if($viewingShift->work_days && count($viewingShift->work_days) > 0)
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $daysMap = [
                                        1 => ['name' => 'Segunda', 'short' => 'Seg', 'color' => 'blue'],
                                        2 => ['name' => 'Terça', 'short' => 'Ter', 'color' => 'indigo'],
                                        3 => ['name' => 'Quarta', 'short' => 'Qua', 'color' => 'purple'],
                                        4 => ['name' => 'Quinta', 'short' => 'Qui', 'color' => 'pink'],
                                        5 => ['name' => 'Sexta', 'short' => 'Sex', 'color' => 'red'],
                                        6 => ['name' => 'Sábado', 'short' => 'Sáb', 'color' => 'orange'],
                                        7 => ['name' => 'Domingo', 'short' => 'Dom', 'color' => 'yellow'],
                                    ];
                                @endphp
                                @foreach($viewingShift->work_days as $day)
                                    @php $dayInfo = $daysMap[$day] ?? null; @endphp
                                    @if($dayInfo)
                                        <span class="inline-flex items-center px-4 py-2 bg-{{ $dayInfo['color'] }}-100 text-{{ $dayInfo['color'] }}-700 rounded-lg font-semibold">
                                            {{ $dayInfo['name'] }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 italic">Não definido</p>
                        @endif
                    </div>
                </div>

                {{-- Características --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-gray-700 mb-4 flex items-center border-b-2 border-gray-200 pb-2">
                        <i class="fas fa-tags mr-2"></i>Características
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center justify-between">
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Tipo</label>
                                <p class="font-bold text-gray-800">
                                    {{ $viewingShift->is_night_shift ? 'Turno Noturno' : 'Turno Diurno' }}
                                </p>
                            </div>
                            <i class="fas {{ $viewingShift->is_night_shift ? 'fa-moon' : 'fa-sun' }} text-3xl {{ $viewingShift->is_night_shift ? 'text-indigo-500' : 'text-yellow-500' }}"></i>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center justify-between">
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Cor de Identificação</label>
                                <p class="font-bold text-gray-800">{{ $viewingShift->color }}</p>
                            </div>
                            <div class="w-12 h-12 rounded-full border-4 border-white shadow-lg" 
                                 style="background-color: {{ $viewingShift->color }};"></div>
                        </div>
                    </div>
                </div>

                {{-- Funcionários Vinculados --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-blue-700 mb-4 flex items-center border-b-2 border-blue-200 pb-2">
                        <i class="fas fa-users mr-2"></i>Funcionários Vinculados ({{ $viewingShift->employees->count() }})
                    </h4>
                    @if($viewingShift->employees->count() > 0)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($viewingShift->employees as $employee)
                                <div class="flex items-center bg-white p-3 rounded-lg border border-gray-200">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                        {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ $employee->full_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $employee->employee_number }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                            <i class="fas fa-info-circle text-yellow-600 text-2xl mb-2"></i>
                            <p class="text-yellow-700 font-semibold">Nenhum funcionário vinculado a este turno</p>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 border-t flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-calendar mr-2"></i>
                    Criado em {{ $viewingShift->created_at->format('d/m/Y H:i') }}
                </div>
                <div class="flex gap-3">
                    <button wire:click="closeViewModal" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                    <button wire:click="edit({{ $viewingShift->id }}); closeViewModal()" 
                            class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

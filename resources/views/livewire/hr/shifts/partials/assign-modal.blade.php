<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeAssignModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-users text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Atribuir Funcionários ao Turno</h3>
                    <p class="text-purple-100 text-sm">Selecione os funcionários para adicionar ao turno</p>
                </div>
            </div>
            <button wire:click="closeAssignModal" 
                    class="text-white hover:text-purple-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            
            {{-- Select All --}}
            <div class="mb-4 p-4 bg-purple-50 rounded-xl border border-purple-200">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" 
                           wire:model.live="selectAll" 
                           class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-3 text-sm font-bold text-gray-900">
                        <i class="fas fa-check-double mr-2 text-purple-600"></i>Selecionar Todos os Funcionários Ativos
                    </span>
                </label>
            </div>

            {{-- Search --}}
            <div class="mb-4">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" 
                           wire:model.live="search" 
                           placeholder="Pesquisar funcionário..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>
            </div>

            {{-- Selected Count --}}
            @if(count($selectedEmployees) > 0)
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                    <p class="text-sm font-semibold text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        {{ count($selectedEmployees) }} funcionário(s) selecionado(s)
                    </p>
                </div>
            @endif

            {{-- Employees List --}}
            <div class="space-y-2">
                @forelse($employees as $employee)
                    <label class="flex items-center p-4 bg-gray-50 hover:bg-purple-50 rounded-xl border border-gray-200 hover:border-purple-300 cursor-pointer transition-all">
                        <input type="checkbox" 
                               wire:model.live="selectedEmployees" 
                               value="{{ $employee->id }}"
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">
                                        {{ $employee->first_name }} {{ $employee->last_name }}
                                    </p>
                                    <div class="flex items-center space-x-3 mt-1">
                                        @if($employee->department)
                                            <span class="text-xs text-gray-600">
                                                <i class="fas fa-building mr-1"></i>{{ $employee->department->name }}
                                            </span>
                                        @endif
                                        @if($employee->position)
                                            <span class="text-xs text-gray-600">
                                                <i class="fas fa-briefcase mr-1"></i>{{ $employee->position->title }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                @if($employee->shift)
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">
                                        <i class="fas fa-clock mr-1"></i>{{ $employee->shift->name }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                        <i class="fas fa-minus-circle mr-1"></i>Sem turno
                                    </span>
                                @endif
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="text-center py-12">
                        <i class="fas fa-users-slash text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Nenhum funcionário ativo encontrado</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                Os funcionários já alocados em outro turno serão transferidos
            </p>
            <div class="flex space-x-3">
                <button type="button" 
                        wire:click="closeAssignModal"
                        class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button"
                        wire:click="assignEmployeesToShift"
                        wire:loading.attr="disabled"
                        wire:target="assignEmployeesToShift"
                        class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="assignEmployeesToShift">
                        <i class="fas fa-check mr-2"></i>Atribuir ao Turno
                    </span>
                    <span wire:loading wire:target="assignEmployeesToShift">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Atribuindo...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

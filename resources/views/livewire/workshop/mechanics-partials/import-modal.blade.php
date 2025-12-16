<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm" wire:click="closeImportModal"></div>
    
    {{-- Modal --}}
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border-4 border-purple-500"
             x-transition:enter="transition ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-import text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Importar Funcionários de RH</h3>
                        <p class="text-purple-100 text-sm">Selecione os funcionários que deseja adicionar como mecânicos</p>
                    </div>
                </div>
                <button wire:click="closeImportModal" class="text-white hover:bg-white/20 p-2 rounded-lg transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                @php
                    $availableEmployees = $this->getAvailableEmployees();
                @endphp

                @if($availableEmployees->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-inbox text-4xl text-gray-400"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-700 mb-2">Nenhum funcionário disponível</h4>
                        <p class="text-gray-500">Todos os funcionários de RH já foram importados ou não possuem dados válidos.</p>
                    </div>
                @else
                    {{-- Info --}}
                    <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-purple-600 text-xl mr-3 mt-1"></i>
                            <div>
                                <p class="font-bold text-purple-900 mb-1">{{ $availableEmployees->count() }} funcionário(s) disponível(is)</p>
                                <p class="text-sm text-purple-700">Os funcionários selecionados serão copiados para a área de mecânicos com nível "Pleno" e especialidade baseada no cargo.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Select All --}}
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg border-2 border-gray-200">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" 
                                   wire:click="$set('selectedEmployees', @js($availableEmployees->pluck('id')->toArray()))"
                                   {{ count($selectedEmployees) === $availableEmployees->count() ? 'checked' : '' }}
                                   class="w-5 h-5 text-purple-600 rounded">
                            <span class="font-bold text-gray-700">
                                <i class="fas fa-check-double mr-2 text-purple-600"></i>
                                Selecionar Todos
                            </span>
                        </label>
                    </div>

                    {{-- Lista de Funcionários --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($availableEmployees as $employee)
                        <div wire:key="emp-{{ $employee->id }}" 
                             class="group border-2 rounded-xl p-4 transition-all duration-200 cursor-pointer hover:shadow-lg
                                    {{ in_array($employee->id, $selectedEmployees) ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-300' }}">
                            <label class="flex items-start gap-4 cursor-pointer">
                                <input type="checkbox" 
                                       wire:model.live="selectedEmployees" 
                                       value="{{ $employee->id }}"
                                       class="w-5 h-5 text-purple-600 rounded mt-1">
                                
                                <div class="flex-1">
                                    {{-- Nome --}}
                                    <h4 class="font-bold text-gray-800 text-lg mb-1">
                                        <i class="fas fa-user mr-2 text-purple-600"></i>
                                        {{ $employee->full_name }}
                                    </h4>
                                    
                                    {{-- Informações --}}
                                    <div class="space-y-1 text-sm">
                                        @if($employee->position)
                                        <p class="text-gray-600">
                                            <i class="fas fa-briefcase mr-2 text-gray-400"></i>
                                            <span class="font-semibold">Cargo:</span> {{ $employee->position }}
                                        </p>
                                        @endif
                                        
                                        @if($employee->email)
                                        <p class="text-gray-600">
                                            <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                            {{ $employee->email }}
                                        </p>
                                        @endif
                                        
                                        @if($employee->phone)
                                        <p class="text-gray-600">
                                            <i class="fas fa-phone mr-2 text-gray-400"></i>
                                            {{ $employee->phone }}
                                        </p>
                                        @endif
                                    </div>

                                    {{-- Badge de importação --}}
                                    @if(in_array($employee->id, $selectedEmployees))
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-600 text-white">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Será importado
                                        </span>
                                    </div>
                                    @endif
                                </div>
                            </label>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 border-t flex flex-col sm:flex-row gap-3 justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-users mr-2 text-purple-600"></i>
                    <span class="font-bold" wire:loading.remove wire:target="selectedEmployees">{{ count($selectedEmployees) }}</span>
                    <span wire:loading wire:target="selectedEmployees">...</span>
                    funcionário(s) selecionado(s)
                </div>
                
                <div class="flex gap-3">
                    <button wire:click="closeImportModal" 
                            type="button"
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    
                    <button wire:click="importSelected" 
                            wire:loading.attr="disabled"
                            {{ empty($selectedEmployees) ? 'disabled' : '' }}
                            class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="importSelected">
                            <i class="fas fa-file-import mr-2"></i>
                            Importar Selecionados
                        </span>
                        <span wire:loading wire:target="importSelected">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Importando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user-clock text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Registro de Presença' : 'Novo Registro de Presença' }}
                    </h3>
                    <p class="text-green-100 text-sm">
                        {{ $editMode ? 'Atualize as informações de presença' : 'Registre a presença do funcionário' }}
                    </p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <form wire:submit.prevent="save">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Funcionário --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-green-600"></i>
                            Funcionário <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="employee_id" 
                                class="w-full px-4 py-2.5 border @error('employee_id') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">Selecione o funcionário</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">
                                    {{ $employee->full_name }} ({{ $employee->employee_number }})
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Data --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>
                            Data <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               wire:model="date" 
                               max="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2.5 border @error('date') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        @error('date')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-info-circle mr-1 text-green-600"></i>
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="status" 
                                class="w-full px-4 py-2.5 border @error('status') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="present">✅ Presente</option>
                            <option value="absent">❌ Ausente</option>
                            <option value="late">⏰ Atrasado</option>
                            <option value="half_day">🌓 Meio Período</option>
                            <option value="sick">💊 Doente</option>
                            <option value="vacation">🏖️ Férias</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Horário de Entrada --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sign-in-alt mr-1 text-blue-600"></i>
                            Horário de Entrada <span class="text-red-500">*</span>
                        </label>
                        <input type="time" 
                               wire:model="check_in" 
                               class="w-full px-4 py-2.5 border @error('check_in') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        @error('check_in')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Horário de Saída --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sign-out-alt mr-1 text-red-600"></i>
                            Horário de Saída
                        </label>
                        <input type="time" 
                               wire:model="check_out" 
                               class="w-full px-4 py-2.5 border @error('check_out') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        @error('check_out')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs text-gray-500">Deixe em branco se ainda não saiu</p>
                        @enderror
                    </div>

                    {{-- Observações --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-green-600"></i>
                            Observações
                        </label>
                        <textarea wire:model="notes" 
                                  rows="3" 
                                  placeholder="Observações sobre a presença..."
                                  class="w-full px-4 py-2.5 border @error('notes') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all resize-none"></textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Info Card --}}
                    <div class="md:col-span-2">
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-0.5"></i>
                                <div>
                                    <p class="font-semibold text-blue-900 text-sm">Dica de Registro</p>
                                    <p class="text-blue-700 text-sm mt-1">
                                        Para registrar apenas a entrada, deixe o campo "Horário de Saída" em branco. 
                                        Você pode registrar a saída posteriormente.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                <button type="button" 
                        wire:click="closeModal"
                        class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        wire:loading.attr="disabled" 
                        wire:target="save"
                        class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Registrar' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>{{ $editMode ? 'Atualizando...' : 'Registrando...' }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

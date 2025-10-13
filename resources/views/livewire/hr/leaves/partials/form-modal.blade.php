{{-- Modal Backdrop --}}
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     x-data="{ show: @entangle('showModal') }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click.self="$wire.closeModal()">
    
    {{-- Modal Content --}}
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95">
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold text-xl flex items-center">
                <i class="fas fa-file-medical mr-3"></i>
                {{ $editMode ? 'Editar Licença' : 'Nova Licença' }}
            </h3>
            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <form wire:submit.prevent="save">
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- Funcionário --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-orange-600"></i>Funcionário *
                        </label>
                        <select wire:model.live="employee_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all @error('employee_id') border-red-500 @enderror">
                            <option value="">Selecione um funcionário</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} - {{ $employee->employee_number }}</option>
                            @endforeach
                        </select>
                        @error('employee_id') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tipo de Licença --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tags mr-1 text-red-600"></i>Tipo de Licença *
                        </label>
                        <select wire:model.live="leave_type" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all @error('leave_type') border-red-500 @enderror">
                            <option value="">Selecione o tipo</option>
                            <option value="sick">🩺 Doença</option>
                            <option value="personal">👤 Pessoal</option>
                            <option value="bereavement">✝️ Luto</option>
                            <option value="maternity">👶 Maternidade</option>
                            <option value="paternity">🍼 Paternidade</option>
                            <option value="unpaid">💰 Sem Vencimento</option>
                            <option value="other">📄 Outro</option>
                        </select>
                        @error('leave_type') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Atestado Médico --}}
                    <div class="flex items-center pt-8">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="has_medical_certificate" 
                                   class="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-2 focus:ring-orange-500">
                            <span class="ml-2 text-sm font-semibold text-gray-700">
                                <i class="fas fa-file-medical text-red-600 mr-1"></i>Possui Atestado Médico
                            </span>
                        </label>
                    </div>

                    {{-- Data Início --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Data Início *
                        </label>
                        <input type="date" wire:model.live="start_date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all @error('start_date') border-red-500 @enderror">
                        @error('start_date') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Data Fim --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-check mr-1 text-blue-600"></i>Data Fim *
                        </label>
                        <input type="date" wire:model.live="end_date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all @error('end_date') border-red-500 @enderror">
                        @error('end_date') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Dias Calculados --}}
                    @if($workingDays > 0)
                        <div class="md:col-span-2">
                            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                                <div class="flex items-center justify-around">
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-orange-600">{{ $totalDays }}</p>
                                        <p class="text-xs text-gray-600">Dias Corridos</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-orange-600">{{ $workingDays }}</p>
                                        <p class="text-xs text-gray-600">Dias Úteis</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Motivo --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-comment-alt mr-1 text-purple-600"></i>Motivo *
                        </label>
                        <textarea wire:model="reason" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all @error('reason') border-red-500 @enderror"
                                  placeholder="Descreva o motivo da licença"></textarea>
                        @error('reason') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Notas --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-yellow-600"></i>Notas (Opcional)
                        </label>
                        <textarea wire:model="notes" rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Informações adicionais"></textarea>
                    </div>

                    {{-- Documento --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-paperclip mr-1 text-indigo-600"></i>Anexar Documento
                        </label>
                        <input type="file" wire:model="document" accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 transition-all">
                        <p class="text-xs text-gray-500 mt-1">Formatos aceitos: PDF, JPG, PNG (máx. 2MB)</p>
                        @error('document') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
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
                        class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Salvar' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>{{ $editMode ? 'Atualizando...' : 'Salvando...' }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

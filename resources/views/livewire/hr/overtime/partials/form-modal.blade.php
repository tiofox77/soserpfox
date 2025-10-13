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
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold text-xl flex items-center">
                <i class="fas fa-clock mr-3"></i>
                {{ $editMode ? 'Editar Horas Extras' : 'Registrar Horas Extras' }}
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
                            <i class="fas fa-user mr-1 text-indigo-600"></i>Funcionário *
                        </label>
                        <select wire:model.live="employee_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('employee_id') border-red-500 @enderror">
                            <option value="">Selecione um funcionário</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} - {{ $employee->employee_number }}</option>
                            @endforeach
                        </select>
                        @error('employee_id') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Data --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Data *
                        </label>
                        <input type="date" wire:model.live="date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('date') border-red-500 @enderror">
                        @error('date') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tags mr-1 text-purple-600"></i>Tipo *
                        </label>
                        <select wire:model.live="overtime_type" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('overtime_type') border-red-500 @enderror">
                            <option value="">Selecione o tipo</option>
                            <option value="regular">⏰ Regular (+50%)</option>
                            <option value="holiday">📅 Feriado (+100%)</option>
                            <option value="night">🌙 Noturno (+75%)</option>
                            <option value="weekend">🗓️ Fim de Semana (+100%)</option>
                        </select>
                        @error('overtime_type') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Hora Início --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-blue-600"></i>Hora Início *
                        </label>
                        <input type="time" wire:model.live="start_time" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('start_time') border-red-500 @enderror">
                        @error('start_time') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Hora Fim --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-red-600"></i>Hora Fim *
                        </label>
                        <input type="time" wire:model.live="end_time" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('end_time') border-red-500 @enderror">
                        @error('end_time') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Cálculo Automático --}}
                    @if($totalHours > 0)
                        <div class="md:col-span-2">
                            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-6">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <p class="text-2xl font-bold text-indigo-600">{{ number_format($totalHours, 1) }}h</p>
                                        <p class="text-xs text-gray-600">Total de Horas</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-purple-600">{{ number_format($overtimeRate, 0) }}%</p>
                                        <p class="text-xs text-gray-600">Taxa Adicional</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-green-600">{{ number_format($totalAmount, 2, ',', '.') }} Kz</p>
                                        <p class="text-xs text-gray-600">Valor Total</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Descrição --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-1 text-orange-600"></i>Descrição *
                        </label>
                        <textarea wire:model="description" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('description') border-red-500 @enderror"
                                  placeholder="Descreva as atividades realizadas durante as horas extras"></textarea>
                        @error('description') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Notas --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-yellow-600"></i>Notas (Opcional)
                        </label>
                        <textarea wire:model="notes" rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                                  placeholder="Informações adicionais"></textarea>
                    </div>

                    {{-- Info Box --}}
                    <div class="md:col-span-2">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                                <div>
                                    <p class="text-sm font-semibold text-blue-900 mb-1">Sobre Horas Extras</p>
                                    <ul class="text-xs text-blue-700 space-y-1">
                                        <li>• <strong>Regular:</strong> Dias úteis normais (+50%)</li>
                                        <li>• <strong>Feriado:</strong> Trabalho em feriados (+100%)</li>
                                        <li>• <strong>Noturno:</strong> 22h às 5h (+75%)</li>
                                        <li>• <strong>Fim de Semana:</strong> Sábados e domingos (+100%)</li>
                                    </ul>
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
                        class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
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

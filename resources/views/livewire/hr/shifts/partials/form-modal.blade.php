<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-clock text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Turno' : 'Novo Turno' }}
                    </h3>
                    <p class="text-purple-100 text-sm">Configure os detalhes do turno</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-purple-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-140px)]">
            <div class="p-6 space-y-6">
                
                {{-- Nome e Código --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1 text-purple-600"></i>Nome do Turno *
                        </label>
                        <input type="text" wire:model="name"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('name') border-red-500 @enderror">
                        @error('name') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1 text-gray-600"></i>Código
                        </label>
                        <input type="text" wire:model="code"
                               placeholder="Ex: T1, T2, T3"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    </div>
                </div>

                {{-- Descrição --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1 text-gray-600"></i>Descrição
                    </label>
                    <textarea wire:model="description" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                              placeholder="Descrição opcional do turno..."></textarea>
                </div>

                {{-- Horários --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div x-data="{ 
                        showPicker: false,
                        value: '{{ $start_time ?? '' }}',
                        setHour(hour) {
                            let minute = this.value ? this.value.split(':')[1] : '00';
                            this.value = hour + ':' + (minute || '00');
                            $wire.set('start_time', this.value);
                            this.showPicker = false;
                            $refs.startInput.value = this.value;
                        }
                    }" x-init="$watch('value', val => $refs.startInput.value = val)">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-blue-600"></i>Horário Início *
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   x-ref="startInput"
                                   :value="value"
                                   @focus="showPicker = true"
                                   @input="
                                       let val = $event.target.value.replace(/[^0-9:]/g, '');
                                       let nums = val.replace(/:/g, '');
                                       if (nums.length >= 2 && !val.includes(':')) {
                                           val = nums.slice(0, 2) + ':' + nums.slice(2, 4);
                                       }
                                       $event.target.value = val.slice(0, 5);
                                       value = val;
                                   "
                                   @blur="
                                       let parts = $event.target.value.split(':');
                                       if (parts[0]) {
                                           if (parts[0].length === 1) parts[0] = '0' + parts[0];
                                           if (parseInt(parts[0]) > 23) parts[0] = '23';
                                       }
                                       if (parts[1]) {
                                           if (parts[1].length === 1) parts[1] = parts[1] + '0';
                                           if (parseInt(parts[1]) > 59) parts[1] = '59';
                                       }
                                       if (parts[0] && parts[1]) {
                                           value = parts.join(':');
                                           $wire.set('start_time', value);
                                           $event.target.value = value;
                                       }
                                       setTimeout(() => showPicker = false, 200);
                                   "
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('start_time') border-red-500 @enderror cursor-pointer"
                                   placeholder="00:00"
                                   maxlength="5">
                            
                            {{-- Quick Picker --}}
                            <div x-show="showPicker" 
                                 @click.away="showPicker = false"
                                 class="absolute z-50 mt-1 bg-white border border-gray-300 rounded-xl shadow-lg p-2 w-full">
                                <p class="text-xs font-bold text-gray-600 mb-2 px-2">Selecione a hora (24h):</p>
                                <div class="grid grid-cols-6 gap-1 max-h-48 overflow-y-auto">
                                    @for($h = 0; $h < 24; $h++)
                                        <button type="button" @click="setHour('{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}')"
                                                class="px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-purple-100 hover:text-purple-700 rounded transition">
                                            {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00
                                        </button>
                                    @endfor
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Clique para selecionar ou digite: HH:MM
                        </p>
                        @error('start_time') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-data="{ 
                        showPicker: false,
                        value: '{{ $end_time ?? '' }}',
                        setHour(hour) {
                            let minute = this.value ? this.value.split(':')[1] : '00';
                            this.value = hour + ':' + (minute || '00');
                            $wire.set('end_time', this.value);
                            this.showPicker = false;
                            $refs.endInput.value = this.value;
                        }
                    }" x-init="$watch('value', val => $refs.endInput.value = val)">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-red-600"></i>Horário Fim *
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   x-ref="endInput"
                                   :value="value"
                                   @focus="showPicker = true"
                                   @input="
                                       let val = $event.target.value.replace(/[^0-9:]/g, '');
                                       let nums = val.replace(/:/g, '');
                                       if (nums.length >= 2 && !val.includes(':')) {
                                           val = nums.slice(0, 2) + ':' + nums.slice(2, 4);
                                       }
                                       $event.target.value = val.slice(0, 5);
                                       value = val;
                                   "
                                   @blur="
                                       let parts = $event.target.value.split(':');
                                       if (parts[0]) {
                                           if (parts[0].length === 1) parts[0] = '0' + parts[0];
                                           if (parseInt(parts[0]) > 23) parts[0] = '23';
                                       }
                                       if (parts[1]) {
                                           if (parts[1].length === 1) parts[1] = parts[1] + '0';
                                           if (parseInt(parts[1]) > 59) parts[1] = '59';
                                       }
                                       if (parts[0] && parts[1]) {
                                           value = parts.join(':');
                                           $wire.set('end_time', value);
                                           $event.target.value = value;
                                       }
                                       setTimeout(() => showPicker = false, 200);
                                   "
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('end_time') border-red-500 @enderror cursor-pointer"
                                   placeholder="00:00"
                                   maxlength="5">
                            
                            {{-- Quick Picker --}}
                            <div x-show="showPicker" 
                                 @click.away="showPicker = false"
                                 class="absolute z-50 mt-1 bg-white border border-gray-300 rounded-xl shadow-lg p-2 w-full">
                                <p class="text-xs font-bold text-gray-600 mb-2 px-2">Selecione a hora (24h):</p>
                                <div class="grid grid-cols-6 gap-1 max-h-48 overflow-y-auto">
                                    @for($h = 0; $h < 24; $h++)
                                        <button type="button" @click="setHour('{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}')"
                                                class="px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-purple-100 hover:text-purple-700 rounded transition">
                                            {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00
                                        </button>
                                    @endfor
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Clique para selecionar ou digite: HH:MM
                        </p>
                        @error('end_time') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hourglass-half mr-1 text-green-600"></i>Horas/Dia *
                        </label>
                        <input type="number" wire:model="hours_per_day" step="0.5" min="1" max="24"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('hours_per_day') border-red-500 @enderror">
                        @error('hours_per_day') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Dias da Semana --}}
                <div x-data="{ 
                    selectedDays: @entangle('work_days'),
                    isSelected(day) {
                        if (!Array.isArray(this.selectedDays)) return false;
                        return this.selectedDays.includes(day) || this.selectedDays.includes(String(day));
                    }
                }">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-calendar-week mr-1 text-indigo-600"></i>Dias de Trabalho
                    </label>
                    <div class="grid grid-cols-7 gap-2">
                        @php
                            $days = [
                                1 => 'Seg',
                                2 => 'Ter',
                                3 => 'Qua',
                                4 => 'Qui',
                                5 => 'Sex',
                                6 => 'Sáb',
                                7 => 'Dom',
                            ];
                        @endphp
                        @foreach($days as $num => $label)
                        <label class="flex items-center justify-center px-3 py-2 border-2 rounded-lg cursor-pointer transition-all"
                               :class="isSelected({{ $num }}) ? 'bg-purple-100 border-purple-500 text-purple-700 shadow-md' : 'border-gray-300 text-gray-600 hover:border-purple-300 hover:bg-purple-50'">
                            <input type="checkbox" wire:model.live="work_days" value="{{ $num }}" class="sr-only">
                            <span class="text-xs font-semibold" :class="isSelected({{ $num }}) ? 'font-bold' : ''">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span x-show="!selectedDays || selectedDays.length === 0">Nenhum dia selecionado</span>
                        <span x-show="selectedDays && selectedDays.length > 0" x-text="selectedDays.length + ' dia(s) selecionado(s)'"></span>
                    </p>
                </div>

                {{-- Cor e Opções --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-palette mr-1 text-pink-600"></i>Cor
                        </label>
                        <input type="color" wire:model="color"
                               class="w-full h-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 cursor-pointer">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sort-numeric-down mr-1 text-gray-600"></i>Ordem
                        </label>
                        <input type="number" wire:model="display_order" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    </div>

                    <div class="flex flex-col justify-end space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_night_shift" class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <span class="ml-2 text-sm font-semibold text-gray-700">
                                <i class="fas fa-moon mr-1 text-indigo-600"></i>Turno Noturno
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <span class="ml-2 text-sm font-semibold text-gray-700">
                                <i class="fas fa-check-circle mr-1 text-green-600"></i>Ativo
                            </span>
                        </label>
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
                        class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
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

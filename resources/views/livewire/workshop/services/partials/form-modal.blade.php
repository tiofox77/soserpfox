<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-tools text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Serviço' : 'Novo Serviço' }}
                    </h3>
                    <p class="text-purple-100 text-sm">Configure os detalhes do serviço oferecido</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-purple-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-200px)]">
            <div class="p-6 space-y-4">
                {{-- Nome --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-wrench mr-1 text-purple-600"></i>Nome do Serviço *
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-2.5 border @error('name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Ex: Troca de óleo e filtros"
                           required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Categoria --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1 text-indigo-600"></i>Categoria *
                    </label>
                    <select wire:model="category" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            required>
                        <option value="Manutenção">🔧 Manutenção</option>
                        <option value="Reparação">🛠️ Reparação</option>
                        <option value="Inspeção">🔍 Inspeção</option>
                        <option value="Pintura">🎨 Pintura</option>
                        <option value="Mecânica">⚙️ Mecânica</option>
                        <option value="Elétrica">⚡ Elétrica</option>
                        <option value="Chapa">🔨 Chapa</option>
                        <option value="Pneus">🚗 Pneus</option>
                        <option value="Outro">📦 Outro</option>
                    </select>
                </div>

                {{-- Descrição --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1 text-blue-600"></i>Descrição
                    </label>
                    <textarea wire:model="description" 
                              rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                              placeholder="Descrição detalhada do serviço oferecido..."></textarea>
                </div>

                {{-- Valores --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Custo Mão de Obra (Kz) *
                        </label>
                        <input type="number" wire:model="labor_cost" 
                               step="0.01"
                               class="w-full px-4 py-2.5 border @error('labor_cost') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="0.00"
                               required>
                        @error('labor_cost') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-orange-600"></i>Horas Estimadas *
                        </label>
                        <input type="number" wire:model="estimated_hours" 
                               step="0.5"
                               class="w-full px-4 py-2.5 border @error('estimated_hours') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="0.0"
                               required>
                        @error('estimated_hours') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Status --}}
                <div class="flex items-center p-4 bg-purple-50 rounded-xl border border-purple-200">
                    <input type="checkbox" wire:model="is_active" 
                           id="is_active"
                           class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-3 text-sm font-bold text-gray-700">
                        <i class="fas fa-check-circle mr-1 text-purple-600"></i>
                        Serviço Ativo e Disponível
                    </label>
                </div>
            </div>
        </form>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button type="button" wire:click="closeModal" 
                    class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="submit" wire:click="save"
                    class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-semibold shadow-lg transition-all">
                <i class="fas fa-save mr-2"></i>Salvar Serviço
            </button>
        </div>
    </div>
</div>

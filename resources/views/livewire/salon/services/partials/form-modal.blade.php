@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-spa mr-3"></i>{{ $editingId ? 'Editar' : 'Novo' }} Serviço
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <form wire:submit.prevent="save" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-signature text-indigo-500 mr-2"></i>Nome do Serviço *
                        </label>
                        <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-folder text-purple-500 mr-2"></i>Categoria *
                        </label>
                        <select wire:model="category_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                            <option value="">Selecione...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-blue-500 mr-2"></i>Duração (minutos) *
                        </label>
                        <input wire:model="duration" type="number" min="5" step="5" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        @error('duration') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-green-500 mr-2"></i>Preço (Kz) *
                        </label>
                        <input wire:model="price" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-coins text-orange-500 mr-2"></i>Custo (Kz)
                        </label>
                        <input wire:model="cost" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-percent text-pink-500 mr-2"></i>Comissão (%)
                        </label>
                        <input wire:model="commission_percent" type="number" min="0" max="100" step="0.5" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                    
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                        </label>
                        <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="Descrição do serviço..."></textarea>
                    </div>
                    
                    <!-- Opções -->
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-cog text-gray-500 mr-2"></i>Opções
                        </h4>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model="is_active" type="checkbox" class="w-5 h-5 text-green-500 border-gray-300 rounded focus:ring-green-500">
                                <span class="text-sm text-gray-700"><i class="fas fa-check-circle text-green-500 mr-1"></i> Serviço Ativo</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model="online_booking" type="checkbox" class="w-5 h-5 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm text-gray-700"><i class="fas fa-globe text-blue-500 mr-1"></i> Agendamento Online</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition" wire:loading.attr="disabled">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold hover:from-indigo-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas {{ $editingId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                            {{ $editingId ? 'Atualizar' : 'Criar' }}
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin mr-2"></i>A processar...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

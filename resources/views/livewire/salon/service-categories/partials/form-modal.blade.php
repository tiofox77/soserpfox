@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-folder-plus mr-3"></i>{{ $editingId ? 'Editar' : 'Nova' }} Categoria
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <form wire:submit.prevent="save" class="p-6">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-purple-500 mr-2"></i>Nome da Categoria *
                        </label>
                        <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" placeholder="Ex: Cabelo, Unhas, Massagem...">
                        @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-icons text-indigo-500 mr-2"></i>Ícone
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center border-2 border-gray-200" style="background-color: {{ $color }}20">
                                    <i class="{{ $icon }}" style="color: {{ $color }}"></i>
                                </div>
                                <input wire:model.live="icon" type="text" placeholder="fas fa-spa" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition text-sm">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">FontAwesome: fas fa-cut, fas fa-spa...</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-palette text-pink-500 mr-2"></i>Cor
                            </label>
                            <div class="flex gap-2">
                                <input wire:model.live="color" type="color" class="w-12 h-12 border border-gray-300 rounded-xl cursor-pointer">
                                <input wire:model.live="color" type="text" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sort-numeric-down text-blue-500 mr-2"></i>Ordem de Exibição
                        </label>
                        <input wire:model="order" type="number" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                        <p class="text-xs text-gray-500 mt-1">Categorias com menor número aparecem primeiro</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                        </label>
                        <textarea wire:model="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" placeholder="Descrição da categoria..."></textarea>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-600 uppercase mb-3">Pré-visualização</p>
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow" style="background-color: {{ $color }}20">
                                <i class="{{ $icon ?? 'fas fa-folder' }} text-xl" style="color: {{ $color }}"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">{{ $name ?: 'Nome da Categoria' }}</p>
                                <p class="text-xs text-gray-500">{{ $description ?: 'Descrição da categoria' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition" wire:loading.attr="disabled">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
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

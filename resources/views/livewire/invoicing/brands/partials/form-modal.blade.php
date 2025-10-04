@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-gradient-to-r from-pink-600 to-rose-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-tag mr-3"></i>{{ $editingBrandId ? 'Editar' : 'Nova' }} Marca
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
                                <i class="fas fa-signature text-pink-500 mr-2"></i>Nome *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-icons text-orange-500 mr-2"></i>Ícone *
                            </label>
                            <x-icon-picker model="icon" :selected="$icon" />
                            <p class="text-xs text-gray-500 mt-1">Clique para selecionar um ícone</p>
                            @error('icon') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sort-numeric-up text-purple-500 mr-2"></i>Ordem
                            </label>
                            <input wire:model="order" type="number" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            <p class="text-xs text-gray-500 mt-1">Ordem de exibição (menor = primeiro)</p>
                            @error('order') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                            </label>
                            <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-transparent transition"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image text-purple-500 mr-2"></i>Logo (URL)
                            </label>
                            <input wire:model="logo" type="url" placeholder="https://exemplo.com/logo.png" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            <p class="text-xs text-gray-500 mt-1">URL da imagem do logo da marca</p>
                            @error('logo') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-globe text-blue-500 mr-2"></i>Website
                            </label>
                            <input wire:model="website" type="url" placeholder="https://www.exemplo.com" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            @error('website') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <div class="mt-8 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                        <button type="button" wire:click="closeModal" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 font-semibold transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <x-loading-button 
                            action="save" 
                            icon="save" 
                            color="red"
                            class="px-6 py-3">
                            {{ $editingBrandId ? 'Atualizar' : 'Salvar' }}
                        </x-loading-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

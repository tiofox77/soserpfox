<!-- Modal Create/Edit Module -->
@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-puzzle-piece text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">
                            {{ $editingModuleId ? 'Editar Módulo' : 'Novo Módulo' }}
                        </h3>
                    </div>
                    <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-purple-500 mr-2"></i>Nome *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-link text-pink-500 mr-2"></i>Slug *
                            </label>
                            <input wire:model="slug" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @error('slug') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-icons text-blue-500 mr-2"></i>Ícone * (FontAwesome)
                            </label>
                            <input wire:model="icon" type="text" placeholder="puzzle-piece" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            @error('icon') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-code-branch text-green-500 mr-2"></i>Versão *
                            </label>
                            <input wire:model="version" type="text" placeholder="1.0.0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('version') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sort text-orange-500 mr-2"></i>Ordem *
                            </label>
                            <input wire:model="order" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            @error('order') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                            </label>
                            <textarea wire:model="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2 flex space-x-4">
                            <label class="flex-1 flex items-center px-4 py-3 bg-green-50 rounded-xl cursor-pointer hover:bg-green-100 transition">
                                <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500 w-5 h-5">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    <i class="fas fa-power-off text-green-500 mr-2"></i>Ativo
                                </span>
                            </label>
                            
                            <label class="flex-1 flex items-center px-4 py-3 bg-yellow-50 rounded-xl cursor-pointer hover:bg-yellow-100 transition">
                                <input wire:model="is_core" type="checkbox" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500 w-5 h-5">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    <i class="fas fa-star text-yellow-500 mr-2"></i>Core
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas {{ $editingModuleId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                            {{ $editingModuleId ? 'Atualizar' : 'Criar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

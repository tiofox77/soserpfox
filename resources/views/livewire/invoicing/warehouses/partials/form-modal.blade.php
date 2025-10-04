<!-- Form Modal -->
@if($showModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-6 rounded-t-2xl flex items-center justify-between z-10">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-warehouse text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-white">
                        {{ $editMode ? 'Editar Armazém' : 'Novo Armazém' }}
                    </h3>
                    <p class="text-indigo-100 text-sm">{{ $editMode ? 'Atualizar informações' : 'Preencha os dados' }}</p>
                </div>
            </div>
            <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nome -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-warehouse mr-1 text-indigo-600"></i>Nome do Armazém *
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-3 rounded-xl border-2 @error('name') border-red-500 @else border-gray-200 @enderror focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: Armazém Principal">
                    @error('name') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Código -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-barcode mr-1 text-indigo-600"></i>Código *
                    </label>
                    <input type="text" wire:model="code" 
                           class="w-full px-4 py-3 rounded-xl border-2 @error('code') border-red-500 @else border-gray-200 @enderror focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: ARM-001">
                    @error('code') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Localização -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1 text-indigo-600"></i>Localização
                    </label>
                    <input type="text" wire:model="location" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: Sede">
                    @error('location') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Endereço -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-location-dot mr-1 text-indigo-600"></i>Endereço
                    </label>
                    <input type="text" wire:model="address" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: Rua ABC, 123">
                    @error('address') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Cidade -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-city mr-1 text-indigo-600"></i>Cidade
                    </label>
                    <input type="text" wire:model="city" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: Luanda">
                    @error('city') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Código Postal -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-mailbox mr-1 text-indigo-600"></i>Código Postal
                    </label>
                    <input type="text" wire:model="postal_code" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: 1000-001">
                    @error('postal_code') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-phone mr-1 text-indigo-600"></i>Telefone
                    </label>
                    <input type="text" wire:model="phone" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: +244 912 345 678">
                    @error('phone') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1 text-indigo-600"></i>Email
                    </label>
                    <input type="email" wire:model="email" 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Ex: armazem@empresa.com">
                    @error('email') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Gestor -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user-tie mr-1 text-indigo-600"></i>Gestor Responsável
                    </label>
                    <select wire:model="manager_id" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                        <option value="">Selecionar Gestor...</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </select>
                    @error('manager_id') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Descrição -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1 text-indigo-600"></i>Descrição
                    </label>
                    <textarea wire:model="description" rows="3"
                              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                              placeholder="Informações adicionais..."></textarea>
                    @error('description') <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <!-- Status e Padrão -->
                <div class="md:col-span-2 flex items-center space-x-6">
                    <!-- Ativo -->
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-indigo-600 rounded focus:ring-2 focus:ring-indigo-500 transition">
                        <span class="ml-2 text-sm font-semibold text-gray-700 group-hover:text-indigo-600 transition">
                            <i class="fas fa-check-circle mr-1"></i>Armazém Ativo
                        </span>
                    </label>

                    <!-- Padrão -->
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" wire:model="is_default" class="w-5 h-5 text-yellow-600 rounded focus:ring-2 focus:ring-yellow-500 transition">
                        <span class="ml-2 text-sm font-semibold text-gray-700 group-hover:text-yellow-600 transition">
                            <i class="fas fa-star mr-1"></i>Armazém Padrão
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
            <button type="button" wire:click="$set('showModal', false)" 
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="save" 
                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition">
                <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Criar' }} Armazém
            </button>
        </div>
    </div>
</div>
@endif

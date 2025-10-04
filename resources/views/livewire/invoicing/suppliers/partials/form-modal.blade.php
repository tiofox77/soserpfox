@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-truck mr-3"></i>{{ $editingSupplierId ? 'Editar' : 'Novo' }} Fornecedor
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form wire:submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-orange-500 mr-2"></i>Tipo *
                            </label>
                            <select wire:model="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="pessoa_juridica">Pessoa Jurídica</option>
                                <option value="pessoa_fisica">Pessoa Física</option>
                            </select>
                            @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-signature text-orange-500 mr-2"></i>Nome *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-id-card text-blue-500 mr-2"></i>NIF
                            </label>
                            <input wire:model="nif" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            @error('nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image text-cyan-500 mr-2"></i>Logo
                            </label>
                            
                            @if($currentLogo)
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <img src="{{ Storage::url($currentLogo) }}" alt="Logo atual" class="h-20 w-20 object-cover rounded-lg shadow-md">
                                    <p class="text-xs text-gray-500 mt-2">Logo atual</p>
                                </div>
                            @endif
                            
                            <input wire:model="logo" type="file" accept="image/*" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                            <p class="text-xs text-gray-500 mt-1">Máximo 2MB - PNG, JPG, GIF</p>
                            @error('logo') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            
                            @if($logo)
                                <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                    <p class="text-xs text-green-700 flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>Nova imagem selecionada
                                    </p>
                                </div>
                            @endif
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-purple-500 mr-2"></i>Email
                            </label>
                            <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-orange-500 mr-2"></i>Telefone
                            </label>
                            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-mobile-alt text-pink-500 mr-2"></i>Celular
                            </label>
                            <input wire:model="mobile" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>Endereço
                            </label>
                            <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-globe text-blue-500 mr-2"></i>País *
                            </label>
                            <select wire:model.live="country" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                @foreach(\App\Models\Supplier::PAISES as $pais)
                                    <option value="{{ $pais }}">{{ $pais }}</option>
                                @endforeach
                            </select>
                            @error('country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        @if($country === 'Angola')
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marked-alt text-red-500 mr-2"></i>Província *
                            </label>
                            <select wire:model="province" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\Supplier::PROVINCIAS_ANGOLA as $provincia)
                                    <option value="{{ $provincia }}">{{ $provincia }}</option>
                                @endforeach
                            </select>
                            @error('province') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        @else
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marked-alt text-red-500 mr-2"></i>Província/Estado
                            </label>
                            <input wire:model="province" type="text" placeholder="Digite a província..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-city text-indigo-500 mr-2"></i>Cidade
                            </label>
                            <input wire:model="city" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-mail-bulk text-cyan-500 mr-2"></i>Código Postal
                            </label>
                            <input wire:model="postal_code" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition">
                        </div>
                    </div>
                    
                    <div class="mt-8 flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                        <button type="button" wire:click="closeModal" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 font-semibold transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <x-loading-button 
                            action="save" 
                            icon="save" 
                            color="orange"
                            class="px-6 py-3">
                            {{ $editingSupplierId ? 'Atualizar' : 'Salvar' }}
                        </x-loading-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

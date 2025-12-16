<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" 
     style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between sticky top-0 z-10 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-user-cog mr-3"></i>
                {{ $editingId ? 'Editar' : 'Novo' }} Mecânico
            </h3>
            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            {{-- Nome Completo --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-user mr-1 text-orange-600"></i>
                    Nome Completo *
                </label>
                <input type="text" wire:model="name" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 @error('name') border-red-500 @enderror"
                       placeholder="Ex: João Silva Santos">
                @error('name') <p class="text-red-600 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
            </div>
            
            {{-- Grid de Contatos --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-phone mr-1 text-orange-600"></i>
                        Telefone *
                    </label>
                    <input type="text" wire:model="phone" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 @error('phone') border-red-500 @enderror"
                           placeholder="+244 939 779 902">
                    @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-mobile-alt mr-1 text-gray-600"></i>
                        Celular
                    </label>
                    <input type="text" wire:model="mobile" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                           placeholder="+244 923 456 789">
                </div>
            </div>
            
            {{-- Email --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-envelope mr-1 text-gray-600"></i>
                    Email
                </label>
                <input type="email" wire:model="email" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 @error('email') border-red-500 @enderror"
                       placeholder="joao@email.com">
                @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            
            {{-- Documento --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-id-card mr-1 text-gray-600"></i>
                    Documento (BI/NIF)
                </label>
                <input type="text" wire:model="document" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                       placeholder="Ex: 123456789LA">
            </div>
            
            {{-- Especialidades --}}
            <div class="bg-orange-50 rounded-lg p-4 border-2 border-orange-200">
                <label class="block text-sm font-bold text-gray-700 mb-3">
                    <i class="fas fa-tools mr-1 text-orange-600"></i>
                    Especialidades * (selecione pelo menos uma)
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Mecânica Geral', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Mecânica Geral" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">🔧 Mecânica Geral</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Motor', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Motor" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">⚙️ Motor</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Suspensão', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Suspensão" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">🔩 Suspensão</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Elétrica', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Elétrica" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">⚡ Elétrica</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Pintura', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Pintura" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">🎨 Pintura</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-orange-100 transition border-2 {{ in_array('Chapa', $specialties) ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                        <input type="checkbox" wire:model="specialties" value="Chapa" class="w-5 h-5 text-orange-600 rounded">
                        <span class="font-semibold">🔨 Chapa</span>
                    </label>
                </div>
                @error('specialties') <p class="text-red-600 text-xs mt-2 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
            </div>
            
            {{-- Nível --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-layer-group mr-1 text-orange-600"></i>
                    Nível de Experiência *
                </label>
                <select wire:model="level" 
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                    <option value="junior">🟢 Junior</option>
                    <option value="pleno">🔵 Pleno</option>
                    <option value="senior">🟣 Senior</option>
                    <option value="master">🟠 Master</option>
                </select>
            </div>
            
            {{-- Valores --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>
                        Valor por Hora (Kz)
                    </label>
                    <input type="number" wire:model="hourly_rate" step="0.01" min="0"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-calendar-day mr-1 text-green-600"></i>
                        Valor por Dia (Kz)
                    </label>
                    <input type="number" wire:model="daily_rate" step="0.01" min="0"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                           placeholder="0.00">
                </div>
            </div>
            
            {{-- Status Ativo --}}
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                <input type="checkbox" wire:model="is_active" id="is_active" class="w-5 h-5 text-orange-600 rounded">
                <label for="is_active" class="font-semibold text-gray-700 cursor-pointer">
                    Mecânico Ativo
                </label>
            </div>
            
            {{-- Botões de Ação --}}
            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
                <button wire:click="save" 
                        wire:loading.attr="disabled"
                        class="flex-1 bg-gradient-to-r from-orange-600 to-red-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>
                        {{ $editingId ? 'Atualizar' : 'Salvar' }} Mecânico
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Salvando...
                    </span>
                </button>
                <button wire:click="closeModal" 
                        type="button"
                        class="px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

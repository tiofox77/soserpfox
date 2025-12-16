<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data="{ activeTab: 'owner' }"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-car text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Veículo' : 'Novo Veículo' }}
                    </h3>
                    <p class="text-blue-100 text-sm">Preencha os dados completos do veículo e proprietário</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-blue-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Tabs Navigation --}}
        <div class="bg-gray-50 border-b border-gray-200 px-6">
            <div class="flex space-x-1 -mb-px">
                <button @click="activeTab = 'owner'" 
                        :class="activeTab === 'owner' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-user mr-2"></i>Proprietário
                </button>
                <button @click="activeTab = 'vehicle'" 
                        :class="activeTab === 'vehicle' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-car mr-2"></i>Veículo
                </button>
                <button @click="activeTab = 'documents'" 
                        :class="activeTab === 'documents' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-file-alt mr-2"></i>Documentação
                </button>
            </div>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-200px)]">
            
            {{-- Tab: Proprietário --}}
            <div x-show="activeTab === 'owner'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Seleção de Cliente Existente --}}
                    <div class="md:col-span-2 mb-4">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-4">
                            <label class="block text-sm font-bold text-blue-900 mb-3">
                                <i class="fas fa-user-tie mr-2 text-blue-600"></i>Cliente Vinculado
                                <span class="ml-2 text-xs text-blue-600">(opcional)</span>
                            </label>
                            <select wire:model.live="client_id" 
                                    class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white">
                                <option value="">➕ Criar novo cliente / Preencher manualmente</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">
                                        {{ $client->name }} 
                                        @if($client->nif) • NIF: {{ $client->nif }} @endif
                                        @if($client->phone) • {{ $client->phone }} @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-blue-700 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Selecione um cliente existente ou deixe em branco para criar novo
                            </p>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-blue-600"></i>Nome do Proprietário *
                        </label>
                        <input type="text" wire:model="owner_name" 
                               class="w-full px-4 py-2.5 border @error('owner_name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Nome completo do proprietário"
                               required>
                        @error('owner_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1 text-green-600"></i>Telefone
                        </label>
                        <input type="text" wire:model="owner_phone" 
                               class="w-full px-4 py-2.5 border @error('owner_phone') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="+244 900 000 000">
                        @error('owner_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1 text-purple-600"></i>Email
                        </label>
                        <input type="email" wire:model="owner_email" 
                               class="w-full px-4 py-2.5 border @error('owner_email') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="email@example.com">
                        @error('owner_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1 text-orange-600"></i>NIF
                        </label>
                        <input type="text" wire:model="owner_nif" 
                               class="w-full px-4 py-2.5 border @error('owner_nif') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Número de identificação fiscal">
                        @error('owner_nif') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>Endereço
                        </label>
                        <input type="text" wire:model="owner_address" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Endereço completo">
                    </div>
                </div>
            </div>

            {{-- Tab: Veículo --}}
            <div x-show="activeTab === 'vehicle'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-blue-600"></i>Matrícula *
                        </label>
                        <input type="text" wire:model="plate" 
                               class="w-full px-4 py-2.5 border @error('plate') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="LD-12-34-AB"
                               required>
                        @error('plate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-car mr-1 text-indigo-600"></i>Marca *
                        </label>
                        <input type="text" wire:model="brand" 
                               class="w-full px-4 py-2.5 border @error('brand') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Ex: Toyota"
                               required>
                        @error('brand') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-car-side mr-1 text-purple-600"></i>Modelo *
                        </label>
                        <input type="text" wire:model="model" 
                               class="w-full px-4 py-2.5 border @error('model') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Ex: Corolla"
                               required>
                        @error('model') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Ano
                        </label>
                        <input type="number" wire:model="year" 
                               class="w-full px-4 py-2.5 border @error('year') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="2020">
                        @error('year') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-palette mr-1 text-pink-600"></i>Cor
                        </label>
                        <input type="text" wire:model="color" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Branco">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-gas-pump mr-1 text-orange-600"></i>Combustível
                        </label>
                        <select wire:model="fuel_type" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="Gasolina">Gasolina</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Elétrico">Elétrico</option>
                            <option value="Híbrido">Híbrido</option>
                            <option value="GPL">GPL</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tachometer-alt mr-1 text-cyan-600"></i>Quilometragem
                        </label>
                        <input type="number" wire:model="mileage" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="50000">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1 text-gray-600"></i>Nº Chassis (VIN)
                        </label>
                        <input type="text" wire:model="vin" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="1HGBH41JXMN109186">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-cog mr-1 text-red-600"></i>Nº Motor
                        </label>
                        <input type="text" wire:model="engine_number" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="ABC123456">
                    </div>
                </div>
            </div>

            {{-- Tab: Documentação --}}
            <div x-show="activeTab === 'documents'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1 text-blue-600"></i>Validade do Livrete
                        </label>
                        <input type="date" wire:model="registration_expiry" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-shield-alt mr-1 text-green-600"></i>Seguradora
                        </label>
                        <input type="text" wire:model="insurance_company" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Nome da seguradora">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-contract mr-1 text-purple-600"></i>Apólice de Seguro
                        </label>
                        <input type="text" wire:model="insurance_policy" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Número da apólice">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-check mr-1 text-green-600"></i>Validade do Seguro
                        </label>
                        <input type="date" wire:model="insurance_expiry" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clipboard-check mr-1 text-cyan-600"></i>Validade da Inspeção
                        </label>
                        <input type="date" wire:model="inspection_expiry" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-toggle-on mr-1 text-indigo-600"></i>Status
                        </label>
                        <select wire:model="status" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="active">✅ Ativo</option>
                            <option value="in_service">🔧 Em Serviço</option>
                            <option value="completed">✔️ Concluído</option>
                            <option value="inactive">❌ Inativo</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-yellow-600"></i>Notas / Observações
                        </label>
                        <textarea wire:model="notes" 
                                  rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                  placeholder="Observações adicionais sobre o veículo..."></textarea>
                    </div>
                </div>
            </div>
        </form>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button type="button" wire:click="closeModal" 
                    class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="submit" wire:click="save" wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-semibold shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>Salvar Veículo
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
    
    {{-- Script para scroll automático para primeiro erro --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('message.processed', (message, component) => {
                // Aguardar renderização do DOM
                setTimeout(() => {
                    const firstError = document.querySelector('.border-red-500');
                    if (firstError) {
                        // Scroll suave para o campo com erro
                        firstError.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        // Focar no campo
                        firstError.focus();
                    }
                }, 100);
            });
        });
    </script>
</div>

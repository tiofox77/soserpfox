@if($showClientModal)
<div class="fixed inset-0 z-[60] overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeClientModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-user-plus mr-3"></i>Novo Cliente
                    </h3>
                    <button wire:click="closeClientModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <p class="text-blue-100 text-sm mt-1">Criar cliente rapidamente para o agendamento</p>
            </div>

            <form wire:submit.prevent="createQuickClient" class="p-6">
                <div class="space-y-4">
                    <!-- Nome -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-blue-500 mr-1"></i>Nome Completo *
                        </label>
                        <input wire:model="quickClientName" type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="Nome do cliente">
                        @error('quickClientName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Telefone e Email -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-green-500 mr-1"></i>Telefone
                            </label>
                            <input wire:model="quickClientPhone" type="text" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="900 000 000">
                            @error('quickClientPhone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-purple-500 mr-1"></i>Email
                            </label>
                            <input wire:model="quickClientEmail" type="email" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="email@exemplo.com">
                            @error('quickClientEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- NIF -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-orange-500 mr-1"></i>NIF
                        </label>
                        <input wire:model="quickClientNif" type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="999999999">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Deixe 999999999 para consumidor final
                        </p>
                    </div>

                    <!-- Morada -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>Morada
                        </label>
                        <input wire:model="quickClientAddress" type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="Rua, número, bairro...">
                    </div>

                    <!-- Cidade e País -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-city text-cyan-500 mr-1"></i>Cidade
                            </label>
                            <input wire:model="quickClientCity" type="text" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="Luanda">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-globe-africa text-green-500 mr-1"></i>País <span class="text-xs text-gray-400">(ISO)</span>
                            </label>
                            <select wire:model="quickClientCountry" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <option value="AO">Angola (AO)</option>
                                <option value="PT">Portugal (PT)</option>
                                <option value="BR">Brasil (BR)</option>
                                <option value="MZ">Moçambique (MZ)</option>
                                <option value="CV">Cabo Verde (CV)</option>
                                <option value="ST">São Tomé e Príncipe (ST)</option>
                                <option value="GW">Guiné-Bissau (GW)</option>
                                <option value="TL">Timor-Leste (TL)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeClientModal" class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-indigo-600 shadow-lg transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
                        <span wire:loading.remove wire:target="createQuickClient">
                            <i class="fas fa-check mr-2"></i>Criar Cliente
                        </span>
                        <span wire:loading wire:target="createQuickClient">
                            <i class="fas fa-spinner fa-spin mr-2"></i>A criar...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

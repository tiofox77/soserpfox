@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-user mr-3"></i>{{ $editingId ? 'Editar' : 'Novo' }} Cliente
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
                                <i class="fas fa-phone text-green-500 mr-2"></i>Telefone
                            </label>
                            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fab fa-whatsapp text-green-600 mr-2"></i>WhatsApp
                            </label>
                            <input wire:model="whatsapp" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
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
                                <i class="fas fa-birthday-cake text-pink-400 mr-2"></i>Data de Nascimento
                            </label>
                            <input wire:model="birth_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-venus-mars text-indigo-500 mr-2"></i>Género
                            </label>
                            <select wire:model="gender" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <option value="">Selecione...</option>
                                <option value="feminino">Feminino</option>
                                <option value="masculino">Masculino</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-blue-500 mr-2"></i>Indicado por
                            </label>
                            <input wire:model="referred_by" type="text" placeholder="Nome de quem indicou" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>Morada
                            </label>
                            <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-globe text-blue-500 mr-2"></i>País <span class="text-xs text-gray-500">(ISO)</span>
                            </label>
                            <select wire:model.live="country" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <option value="AO">Angola (AO)</option>
                                <option value="PT">Portugal (PT)</option>
                                <option value="MZ">Moçambique (MZ)</option>
                                <option value="BR">Brasil (BR)</option>
                                <option value="CV">Cabo Verde (CV)</option>
                                <option value="GW">Guiné-Bissau (GW)</option>
                                <option value="ST">São Tomé e Príncipe (ST)</option>
                            </select>
                        </div>
                        
                        @if($country === 'AO')
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marked-alt text-red-500 mr-2"></i>Província
                            </label>
                            <select wire:model="province" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\Client::PROVINCIAS_ANGOLA as $provincia)
                                    <option value="{{ $provincia }}">{{ $provincia }}</option>
                                @endforeach
                            </select>
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
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-notes-medical text-orange-500 mr-2"></i>Notas / Observações
                            </label>
                            <textarea wire:model="notes" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" placeholder="Observações sobre o cliente..."></textarea>
                        </div>
                        
                        <!-- Preferências -->
                        <div class="col-span-2 bg-gray-50 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-cog text-gray-500 mr-2"></i>Preferências de Comunicação
                            </h4>
                            <div class="flex flex-wrap gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="receives_sms" type="checkbox" class="w-5 h-5 text-pink-500 border-gray-300 rounded focus:ring-pink-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-sms text-blue-500 mr-1"></i> Receber SMS</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="receives_email" type="checkbox" class="w-5 h-5 text-pink-500 border-gray-300 rounded focus:ring-pink-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-envelope text-purple-500 mr-1"></i> Receber Email</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="is_vip" type="checkbox" class="w-5 h-5 text-yellow-500 border-gray-300 rounded focus:ring-yellow-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-crown text-yellow-500 mr-1"></i> Cliente VIP</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition" wire:loading.attr="disabled">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-pink-600 to-purple-600 text-white rounded-xl font-semibold hover:from-pink-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
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

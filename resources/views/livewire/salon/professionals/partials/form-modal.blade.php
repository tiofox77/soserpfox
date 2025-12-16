@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-user-tie mr-3"></i>{{ $editingId ? 'Editar' : 'Novo' }} Profissional
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Dados Pessoais -->
                    <div class="col-span-2 bg-orange-50 rounded-xl p-4 mb-2">
                        <h4 class="text-sm font-bold text-orange-700 mb-3 flex items-center">
                            <i class="fas fa-user mr-2"></i>Dados Pessoais
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome Completo *</label>
                                <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Apelido</label>
                                <input wire:model="nickname" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" placeholder="Como é conhecido">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Documento (BI/NIF)</label>
                                <input wire:model="document" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                                <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Nascimento</label>
                                <input wire:model="birth_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Especialização</label>
                                <input wire:model="specialization" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" placeholder="Cabeleireiro, Manicure...">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nível</label>
                                <select wire:model="level" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                    <option value="">Selecione...</option>
                                    <option value="junior">Júnior</option>
                                    <option value="pleno">Pleno</option>
                                    <option value="senior">Sénior</option>
                                    <option value="master">Master</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Contratação</label>
                                <input wire:model="hire_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div class="col-span-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Morada</label>
                                <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" placeholder="Endereço completo">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Horário de Trabalho -->
                    <div class="bg-green-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-green-700 mb-3 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Horário de Trabalho
                        </h4>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Entrada *</label>
                                <input wire:model="work_start" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Saída *</label>
                                <input wire:model="work_end" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Início Almoço</label>
                                <input wire:model="lunch_start" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Fim Almoço</label>
                                <input wire:model="lunch_end" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                        </div>
                        
                        <label class="block text-xs font-semibold text-gray-700 mb-2">Dias de Trabalho</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($weekDays as $day => $name)
                                <label class="flex items-center gap-1 px-3 py-1.5 bg-white border rounded-lg cursor-pointer hover:bg-green-100 transition {{ in_array($day, $working_days) ? 'border-green-500 bg-green-100' : 'border-gray-300' }}">
                                    <input type="checkbox" wire:model="working_days" value="{{ $day }}" class="hidden">
                                    <span class="text-xs font-semibold {{ in_array($day, $working_days) ? 'text-green-700' : 'text-gray-600' }}">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Comissão e Opções -->
                    <div class="bg-blue-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-blue-700 mb-3 flex items-center">
                            <i class="fas fa-cog mr-2"></i>Remuneração e Configurações
                        </h4>
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-2">Comissão (%)</label>
                                    <input wire:model="commission_percent" type="number" min="0" max="100" step="0.5" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-2">Valor/Hora (Kz)</label>
                                    <input wire:model="hourly_rate" type="number" min="0" step="100" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-2">Valor/Dia (Kz)</label>
                                    <input wire:model="daily_rate" type="number" min="0" step="100" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
                                </div>
                            </div>
                            <div class="space-y-3 pt-2 border-t border-blue-200">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="is_active" type="checkbox" class="w-5 h-5 text-green-500 border-gray-300 rounded focus:ring-green-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-check-circle text-green-500 mr-1"></i> Profissional Ativo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="is_available" type="checkbox" class="w-5 h-5 text-cyan-500 border-gray-300 rounded focus:ring-cyan-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-user-check text-cyan-500 mr-1"></i> Disponível para Agendamento</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="accepts_online_booking" type="checkbox" class="w-5 h-5 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm text-gray-700"><i class="fas fa-globe text-blue-500 mr-1"></i> Aceita Agendamento Online</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Serviços -->
                    <div class="col-span-2 bg-purple-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-purple-700 mb-3 flex items-center">
                            <i class="fas fa-spa mr-2"></i>Serviços que Realiza
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-40 overflow-y-auto">
                            @foreach($services as $service)
                                <label class="flex items-center gap-2 px-3 py-2 bg-white border rounded-lg cursor-pointer hover:bg-purple-100 transition {{ in_array($service->id, $selected_services) ? 'border-purple-500 bg-purple-100' : 'border-gray-300' }}">
                                    <input type="checkbox" wire:model="selected_services" value="{{ $service->id }}" class="w-4 h-4 text-purple-500 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="text-xs font-medium {{ in_array($service->id, $selected_services) ? 'text-purple-700' : 'text-gray-600' }}">{{ $service->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if($services->isEmpty())
                            <p class="text-sm text-gray-500 text-center py-4">Nenhum serviço cadastrado. <a href="{{ route('salon.services') }}" class="text-purple-600 hover:underline">Criar serviços</a></p>
                        @endif
                    </div>
                    
                    <!-- Bio -->
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left text-gray-500 mr-2"></i>Biografia / Apresentação
                        </label>
                        <textarea wire:model="bio" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" placeholder="Breve descrição do profissional..."></textarea>
                    </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition" wire:loading.attr="disabled">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl font-semibold hover:from-orange-600 hover:to-amber-600 shadow-lg hover:shadow-xl transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
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

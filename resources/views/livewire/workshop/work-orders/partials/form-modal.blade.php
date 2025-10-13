<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data="{ activeTab: 'basic' }"
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
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-clipboard-list text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar OS' : 'Nova Ordem de Serviço' }}
                    </h3>
                    <p class="text-orange-100 text-sm">Registre os detalhes completos da ordem de serviço</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-orange-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Tabs Navigation --}}
        <div class="bg-gray-50 border-b border-gray-200 px-6">
            <div class="flex space-x-1 -mb-px">
                <button @click="activeTab = 'basic'" 
                        :class="activeTab === 'basic' ? 'border-orange-600 text-orange-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-info-circle mr-2"></i>Dados Básicos
                </button>
                <button @click="activeTab = 'service'" 
                        :class="activeTab === 'service' ? 'border-orange-600 text-orange-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-wrench mr-2"></i>Serviço
                </button>
                <button @click="activeTab = 'status'" 
                        :class="activeTab === 'status' ? 'border-orange-600 text-orange-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-flag mr-2"></i>Status
                </button>
            </div>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-200px)]">
            
            {{-- Tab: Dados Básicos --}}
            <div x-show="activeTab === 'basic'" class="p-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-car mr-1 text-blue-600"></i>Veículo *
                            </label>
                            <select wire:model="vehicle_id" 
                                    class="w-full px-4 py-2.5 border @error('vehicle_id') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                    required>
                                <option value="">Selecione o veículo...</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">
                                        {{ $vehicle->plate }} - {{ $vehicle->brand }} {{ $vehicle->model }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-user-cog mr-1 text-indigo-600"></i>Mecânico Responsável
                            </label>
                            <select wire:model="mechanic_id" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                <option value="">Não atribuído</option>
                                @foreach($mechanics as $mechanic)
                                    <option value="{{ $mechanic->id }}">{{ $mechanic->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-1 text-green-600"></i>Data Entrada *
                            </label>
                            <input type="datetime-local" wire:model="received_at" 
                                   class="w-full px-4 py-2.5 border @error('received_at') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                   required>
                            @error('received_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-calendar-check mr-1 text-purple-600"></i>Data Agendada
                            </label>
                            <input type="datetime-local" wire:model="scheduled_for" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-tachometer-alt mr-1 text-cyan-600"></i>Quilometragem
                            </label>
                            <input type="number" wire:model="mileage_in" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                   placeholder="KM atual do veículo">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab: Serviço --}}
            <div x-show="activeTab === 'service'" class="p-6">
                <div class="space-y-4">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-exclamation-circle mr-1 text-red-600"></i>Descrição do Problema *
                        </label>
                        <textarea wire:model="problem_description" 
                                  rows="3"
                                  class="w-full px-4 py-2.5 border @error('problem_description') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Descreva detalhadamente o problema relatado pelo cliente..."
                                  required></textarea>
                        @error('problem_description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-stethoscope mr-1 text-blue-600"></i>Diagnóstico Técnico
                        </label>
                        <textarea wire:model="diagnosis" 
                                  rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Diagnóstico técnico realizado pelo mecânico..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-wrench mr-1 text-green-600"></i>Trabalho Realizado
                        </label>
                        <textarea wire:model="work_performed" 
                                  rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Descreva os serviços e reparos executados..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-lightbulb mr-1 text-yellow-600"></i>Recomendações
                        </label>
                        <textarea wire:model="recommendations" 
                                  rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Recomendações futuras para o cliente..."></textarea>
                    </div>
                </div>
            </div>

            {{-- Tab: Status --}}
            <div x-show="activeTab === 'status'" class="p-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-flag mr-1 text-blue-600"></i>Status
                            </label>
                            <select wire:model="status" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                <option value="pending">🕒 Pendente</option>
                                <option value="scheduled">📅 Agendada</option>
                                <option value="in_progress">🛠️ Em Andamento</option>
                                <option value="waiting_parts">⏸️ Aguardando Peças</option>
                                <option value="completed">✅ Concluída</option>
                                <option value="delivered">🏁 Entregue</option>
                                <option value="cancelled">❌ Cancelada</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-exclamation-triangle mr-1 text-red-600"></i>Prioridade
                            </label>
                            <select wire:model="priority" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                <option value="low">🔽 Baixa</option>
                                <option value="normal">➡️ Normal</option>
                                <option value="high">🔼 Alta</option>
                                <option value="urgent">⚠️ Urgente</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-purple-600"></i>Notas Internas
                        </label>
                        <textarea wire:model="notes" 
                                  rows="4"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                                  placeholder="Observações internas, anotações da equipe, etc..."></textarea>
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
            <button type="submit" wire:click="save"
                    class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-semibold shadow-lg transition-all">
                <i class="fas fa-save mr-2"></i>Salvar OS
            </button>
        </div>
    </div>
</div>

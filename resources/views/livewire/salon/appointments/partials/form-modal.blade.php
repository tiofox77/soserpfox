@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-gradient-to-r from-pink-500 to-purple-500 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-calendar-plus mr-3"></i>{{ $editingId ? 'Editar' : 'Novo' }} Agendamento
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Cliente -->
                <div class="bg-blue-50 rounded-xl p-4 mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-blue-700 flex items-center">
                            <i class="fas fa-user mr-2"></i>Cliente
                        </h4>
                        <button type="button" wire:click="openClientModal" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg font-semibold transition shadow-md">
                            <i class="fas fa-plus mr-1"></i>Novo Cliente
                        </button>
                    </div>
                    
                    <!-- Campo de Pesquisa -->
                    <div class="relative" x-data="{ open: @entangle('showClientDropdown') }">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="clientSearch"
                                   wire:keydown.enter.prevent="$set('showClientDropdown', false)"
                                   @focus="open = true"
                                   @click.away="open = false"
                                   class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="Pesquisar cliente por nome, telefone ou email...">
                            @if($client_id)
                                <button type="button" wire:click="clearClient" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition">
                                    <i class="fas fa-times"></i>
                                </button>
                            @endif
                        </div>
                        
                        <!-- Dropdown de Resultados -->
                        <div x-show="open" 
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white rounded-xl shadow-lg border border-gray-200 max-h-60 overflow-y-auto">
                            @if($this->filteredClients->count() > 0)
                                @foreach($this->filteredClients as $client)
                                    <div wire:click="selectClient({{ $client->id }})" 
                                         wire:key="client-{{ $client->id }}"
                                         class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition flex items-center gap-3 border-b border-gray-100 last:border-0">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                            {{ strtoupper(substr($client->name, 0, 1)) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">{{ $client->name }}</p>
                                            <p class="text-xs text-gray-500">
                                                @if($client->phone) <i class="fas fa-phone mr-1"></i>{{ $client->phone }} @endif
                                                @if($client->email) <i class="fas fa-envelope ml-2 mr-1"></i>{{ Str::limit($client->email, 20) }} @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="px-4 py-6 text-center text-gray-500">
                                    <i class="fas fa-user-slash text-2xl mb-2"></i>
                                    <p class="text-sm">Nenhum cliente encontrado</p>
                                    <button type="button" wire:click="openClientModal" class="mt-2 text-blue-600 hover:text-blue-700 text-sm font-semibold">
                                        <i class="fas fa-plus mr-1"></i>Criar novo cliente
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Cliente Selecionado -->
                    @if($client_id)
                        @php $selectedClient = \App\Models\Salon\Client::find($client_id); @endphp
                        @if($selectedClient)
                            <div class="mt-3 p-3 bg-white rounded-xl border-2 border-blue-300 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                                    {{ strtoupper(substr($selectedClient->name, 0, 1)) }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-gray-900">{{ $selectedClient->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if($selectedClient->phone) <i class="fas fa-phone mr-1"></i>{{ $selectedClient->phone }} @endif
                                        @if($selectedClient->email) <span class="ml-2"><i class="fas fa-envelope mr-1"></i>{{ $selectedClient->email }}</span> @endif
                                    </p>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-bold">
                                    <i class="fas fa-check mr-1"></i>Selecionado
                                </span>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Profissional, Data e Hora -->
                <div class="bg-green-50 rounded-xl p-4 mb-6">
                    <h4 class="font-bold text-green-700 mb-3 flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>Agendamento
                    </h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Profissional *</label>
                            <select wire:model="professional_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                                <option value="">Selecione...</option>
                                @foreach($professionals as $prof)
                                    <option value="{{ $prof->id }}">{{ $prof->display_name }}</option>
                                @endforeach
                            </select>
                            @error('professional_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Data *</label>
                            <input wire:model="date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @error('date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Horário *</label>
                            <input wire:model="start_time" type="time" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @error('start_time') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Serviços -->
                <div class="bg-purple-50 rounded-xl p-4 mb-6">
                    <h4 class="font-bold text-purple-700 mb-3 flex items-center">
                        <i class="fas fa-spa mr-2"></i>Serviços *
                    </h4>
                    <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                        @foreach($services as $service)
                            <label class="flex items-center gap-2 p-3 rounded-xl cursor-pointer transition {{ in_array($service->id, $selected_services) ? 'bg-purple-200 border-2 border-purple-400' : 'bg-white border border-gray-200 hover:bg-purple-100' }}">
                                <input type="checkbox" wire:model="selected_services" value="{{ $service->id }}" class="rounded text-purple-600 focus:ring-purple-500">
                                <div class="flex-1">
                                    <span class="text-sm font-semibold">{{ $service->name }}</span>
                                    <span class="text-xs text-gray-500 block">{{ $service->duration }}min - {{ number_format($service->price, 0, ',', '.') }} Kz</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('selected_services') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                </div>

                <!-- Fonte e Observações -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-phone-alt text-gray-500 mr-1"></i>Fonte
                        </label>
                        <select wire:model="source" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @foreach(\App\Models\Salon\Appointment::SOURCES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note text-gray-500 mr-1"></i>Observações
                        </label>
                        <input wire:model="notes" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition" placeholder="Notas adicionais...">
                    </div>
                </div>
                
                <div class="pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition" wire:loading.attr="disabled">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-pink-500 to-purple-500 text-white rounded-xl font-semibold hover:from-pink-600 hover:to-purple-600 shadow-lg hover:shadow-xl transition disabled:opacity-50" wire:loading.attr="disabled" wire:loading.class="cursor-wait">
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

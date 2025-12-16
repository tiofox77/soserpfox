{{-- Modal de Nova/Editar Reserva --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl p-6 m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-blue-600"></i>
                {{ $editingId ? 'Editar Reserva' : 'Nova Reserva' }}
            </h3>
            <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form wire:submit="save" class="space-y-6">
            {{-- Cliente --}}
            <div class="bg-blue-50 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-bold text-blue-700 flex items-center">
                        <i class="fas fa-user mr-2"></i>Cliente
                    </h4>
                    <button type="button" wire:click="openClientModal" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg font-semibold transition shadow-md">
                        <i class="fas fa-plus mr-1"></i>Novo Cliente
                    </button>
                </div>
                
                {{-- Campo de Pesquisa --}}
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
                               placeholder="Pesquisar cliente por nome, telefone ou NIF...">
                        @if($client_id)
                            <button type="button" wire:click="clearClient" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition">
                                <i class="fas fa-times"></i>
                            </button>
                        @endif
                    </div>
                    
                    {{-- Dropdown de Resultados --}}
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
                                            @if($client->nif) <span class="ml-2"><i class="fas fa-id-card mr-1"></i>{{ $client->nif }}</span> @endif
                                        </p>
                                    </div>
                                    @if($client->type === 'company')
                                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs font-bold">Empresa</span>
                                    @endif
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

                {{-- Cliente Selecionado --}}
                @if($client_id)
                    @php $selectedClient = \App\Models\Client::find($client_id); @endphp
                    @if($selectedClient)
                        <div class="mt-3 p-3 bg-white rounded-xl border-2 border-blue-300 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                                {{ strtoupper(substr($selectedClient->name, 0, 1)) }}
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-gray-900">{{ $selectedClient->name }}</p>
                                <p class="text-xs text-gray-500">
                                    @if($selectedClient->phone) <i class="fas fa-phone mr-1"></i>{{ $selectedClient->phone }} @endif
                                    @if($selectedClient->nif) <span class="ml-2"><i class="fas fa-id-card mr-1"></i>{{ $selectedClient->nif }}</span> @endif
                                </p>
                            </div>
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-bold">
                                <i class="fas fa-check mr-1"></i>Selecionado
                            </span>
                        </div>
                    @endif
                @endif
                @error('client_id') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
            </div>

            {{-- Quarto e Datas --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Tipo de Quarto *</label>
                    <select wire:model.live="room_type_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Selecione...</option>
                        @foreach($roomTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} - {{ number_format($type->base_price, 0, ',', '.') }} Kz/noite</option>
                        @endforeach
                    </select>
                    @error('room_type_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Quarto Específico</label>
                    <select wire:model="room_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Atribuir depois...</option>
                        @foreach($rooms->where('room_type_id', $room_type_id) as $room)
                            <option value="{{ $room->id }}">{{ $room->number }} - {{ $room->status_label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Check-in *</label>
                    <input wire:model.live="check_in_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    @error('check_in_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Check-out *</label>
                    <input wire:model.live="check_out_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    @error('check_out_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Adultos</label>
                    <input wire:model="adults" type="number" min="1" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Crianças</label>
                    <input wire:model="children" type="number" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Camas Extra</label>
                    <input wire:model="extra_beds" type="number" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fonte</label>
                    <select wire:model="source" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                        @foreach(\App\Models\Hotel\Reservation::SOURCES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Valores --}}
            <div class="bg-amber-50 rounded-xl p-4">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-calculator text-amber-600"></i> Valores
                </h4>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Taxa/Noite (Kz)</label>
                        <input wire:model="room_rate" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Desconto (Kz)</label>
                        <input wire:model="discount" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Valor Pago (Kz)</label>
                        <input wire:model="paid_amount" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>
                @if($room_rate && $check_in_date && $check_out_date)
                    @php $nights = $this->calculateNights(); @endphp
                    <div class="mt-3 pt-3 border-t border-amber-200">
                        <p class="text-sm text-gray-600">
                            {{ $nights }} noite(s) × {{ number_format($room_rate, 0, ',', '.') }} Kz = 
                            <span class="font-bold text-amber-700 text-lg">{{ number_format($nights * $room_rate, 0, ',', '.') }} Kz</span>
                        </p>
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Pedidos Especiais</label>
                <textarea wire:model="special_requests" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Preferências do hóspede..."></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                    Cancelar
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar Reserva' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

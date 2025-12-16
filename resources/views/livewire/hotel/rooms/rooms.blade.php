<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-door-open text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Quartos</h2>
                    <p class="text-indigo-100 text-sm">Gestão de quartos do hotel</p>
                </div>
            </div>
            <button wire:click="openModal" wire:loading.attr="disabled" 
                    class="bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl disabled:opacity-50">
                <span wire:loading.remove wire:target="openModal">
                    <i class="fas fa-plus mr-2"></i>Novo Quarto
                </span>
                <span wire:loading wire:target="openModal">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Aguarde...
                </span>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <p class="text-xs text-green-600 font-semibold uppercase">Disponíveis</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $rooms->where('status', 'available')->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-red-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/30">
                    <i class="fas fa-user text-white"></i>
                </div>
                <p class="text-xs text-red-600 font-semibold uppercase">Ocupados</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $rooms->where('status', 'occupied')->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-broom text-white"></i>
                </div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Limpeza</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $rooms->where('status', 'cleaning')->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-yellow-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30">
                    <i class="fas fa-tools text-white"></i>
                </div>
                <p class="text-xs text-yellow-600 font-semibold uppercase">Manutenção</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $rooms->where('status', 'maintenance')->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-indigo-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-door-closed text-white"></i>
                </div>
                <p class="text-xs text-indigo-600 font-semibold uppercase">Total</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $rooms->total() }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Pesquisa --}}
            <div class="flex-1 min-w-[200px]">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Número do quarto..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            {{-- Filtro Status --}}
            <select wire:model.live="statusFilter" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Hotel\Room::STATUSES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Filtro Tipo --}}
            <select wire:model.live="typeFilter" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                <option value="">Todos os tipos</option>
                @foreach($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>

            {{-- Filtro Andar --}}
            <select wire:model.live="floorFilter" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                <option value="">Todos os andares</option>
                @foreach($floors as $floor)
                    <option value="{{ $floor }}">{{ $floor }}º Andar</option>
                @endforeach
            </select>

            {{-- Toggle Grid/List --}}
            <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                <button wire:click="setViewMode('grid')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'grid' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-th-large"></i>
                </button>
                <button wire:click="setViewMode('list')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-list"></i>
                </button>
            </div>

            @if($search || $statusFilter || $typeFilter || $floorFilter)
                <button wire:click="$set('search', ''); $set('statusFilter', ''); $set('typeFilter', ''); $set('floorFilter', '')" 
                        class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold">
                    <i class="fas fa-times mr-1"></i>Limpar
                </button>
            @endif
        </div>
    </div>

    {{-- Grid View --}}
    @if($viewMode === 'grid')
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($rooms as $room)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 group {{ !$room->is_active ? 'opacity-60' : '' }}">
                {{-- Header colorido --}}
                <div class="h-24 flex items-center justify-center relative
                    @if($room->status === 'available') bg-gradient-to-br from-green-400 to-green-600
                    @elseif($room->status === 'occupied') bg-gradient-to-br from-red-400 to-red-600
                    @elseif($room->status === 'maintenance') bg-gradient-to-br from-yellow-400 to-yellow-600
                    @elseif($room->status === 'cleaning') bg-gradient-to-br from-blue-400 to-blue-600
                    @else bg-gradient-to-br from-purple-400 to-purple-600
                    @endif">
                    <span class="text-4xl font-bold text-white drop-shadow-lg">{{ $room->number }}</span>
                    @if($room->floor)
                        <span class="absolute top-2 right-2 px-2 py-1 bg-white/20 rounded-lg text-xs text-white font-semibold">{{ $room->floor }}º</span>
                    @endif
                    @if(!$room->is_active)
                        <span class="absolute top-2 left-2 px-2 py-1 bg-black/30 rounded-lg text-xs text-white">Inativo</span>
                    @endif
                </div>

                {{-- Conteúdo --}}
                <div class="p-4">
                    <p class="text-sm font-semibold text-gray-700 truncate mb-2">{{ $room->roomType->name }}</p>
                    
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg
                            @if($room->status === 'available') bg-green-100 text-green-700
                            @elseif($room->status === 'occupied') bg-red-100 text-red-700
                            @elseif($room->status === 'maintenance') bg-yellow-100 text-yellow-700
                            @elseif($room->status === 'cleaning') bg-blue-100 text-blue-700
                            @else bg-purple-100 text-purple-700
                            @endif">
                            {{ $room->status_label }}
                        </span>
                        <span class="text-xs text-gray-500">{{ number_format($room->roomType->base_price, 0, ',', '.') }} Kz</span>
                    </div>
                    
                    @if($room->currentReservation)
                        <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-600 truncate flex items-center">
                                <i class="fas fa-user mr-1 text-indigo-500"></i>
                                {{ $room->currentReservation->client->name ?? 'Hóspede' }}
                            </p>
                        </div>
                    @endif

                    {{-- Ações --}}
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $room->id }})" 
                                wire:loading.attr="disabled"
                                class="flex-1 px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-xs font-semibold transition disabled:opacity-50">
                            <i class="fas fa-edit mr-1"></i>Editar
                        </button>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak x-transition
                                 class="absolute right-0 bottom-full mb-1 w-40 bg-white rounded-xl shadow-lg border z-20">
                                @foreach(\App\Models\Hotel\Room::STATUSES as $key => $label)
                                    @if($key !== $room->status)
                                        <button wire:click="updateStatus({{ $room->id }}, '{{ $key }}')" 
                                                @click="open = false"
                                                class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 first:rounded-t-xl last:rounded-b-xl transition">
                                            {{ $label }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-lg">
                <div class="w-20 h-20 mx-auto mb-4 bg-indigo-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-door-closed text-4xl text-indigo-400"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum quarto encontrado</h3>
                <p class="text-gray-500 mb-4">Crie tipos de quarto primeiro e depois adicione quartos</p>
                <button wire:click="openModal" class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold">
                    <i class="fas fa-plus mr-2"></i>Adicionar Quarto
                </button>
            </div>
        @endforelse
    </div>
    @endif

    {{-- List View --}}
    @if($viewMode === 'list')
    <div class="bg-white rounded-2xl shadow-lg overflow-visible">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Quarto</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Tipo</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Andar</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Hóspede Atual</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-600 uppercase">Preço/Noite</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rooms as $room)
                    <tr class="hover:bg-gray-50 transition {{ !$room->is_active ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg
                                    @if($room->status === 'available') bg-gradient-to-br from-green-400 to-green-600
                                    @elseif($room->status === 'occupied') bg-gradient-to-br from-red-400 to-red-600
                                    @elseif($room->status === 'maintenance') bg-gradient-to-br from-yellow-400 to-yellow-600
                                    @elseif($room->status === 'cleaning') bg-gradient-to-br from-blue-400 to-blue-600
                                    @else bg-gradient-to-br from-purple-400 to-purple-600
                                    @endif">
                                    {{ $room->number }}
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Quarto {{ $room->number }}</p>
                                    @if(!$room->is_active)
                                        <span class="text-xs text-red-500">Inativo</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-700">{{ $room->roomType->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">{{ $room->floor ?? '-' }}º</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 text-xs font-bold rounded-full
                                @if($room->status === 'available') bg-green-100 text-green-700
                                @elseif($room->status === 'occupied') bg-red-100 text-red-700
                                @elseif($room->status === 'maintenance') bg-yellow-100 text-yellow-700
                                @elseif($room->status === 'cleaning') bg-blue-100 text-blue-700
                                @else bg-purple-100 text-purple-700
                                @endif">
                                {{ $room->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($room->currentReservation)
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user text-indigo-500"></i>
                                    <span class="text-sm text-gray-700">{{ $room->currentReservation->client->name ?? 'Hóspede' }}</span>
                                </div>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-lg font-bold text-gray-900">{{ number_format($room->roomType->base_price, 0, ',', '.') }}</span>
                            <span class="text-xs text-gray-500">Kz</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="edit({{ $room->id }})" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Alterar Status">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-cloak x-transition
                                         class="absolute right-0 top-full mt-1 w-40 bg-white rounded-xl shadow-xl border z-50">
                                        @foreach(\App\Models\Hotel\Room::STATUSES as $key => $label)
                                            @if($key !== $room->status)
                                                <button wire:click="updateStatus({{ $room->id }}, '{{ $key }}')" 
                                                        @click="open = false"
                                                        class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 first:rounded-t-xl last:rounded-b-xl transition">
                                                    {{ $label }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-door-closed text-3xl text-indigo-400"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum quarto encontrado</h3>
                            <p class="text-gray-500 mb-4">Crie tipos de quarto primeiro e depois adicione quartos</p>
                            <button wire:click="openModal" class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold">
                                <i class="fas fa-plus mr-2"></i>Adicionar Quarto
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $rooms->links() }}
    </div>

    {{-- Modais --}}
    @include('livewire.hotel.rooms.partials.form-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.rooms.partials.toast')
</div>

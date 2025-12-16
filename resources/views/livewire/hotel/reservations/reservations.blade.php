<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Reservas</h2>
                    <p class="text-blue-100 text-sm">Gestão de reservas do hotel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('hotel.calendar') }}" class="px-4 py-3 bg-white/20 hover:bg-white/30 rounded-xl font-semibold transition flex items-center gap-2">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendário</span>
                </a>
                <button wire:click="openModal" wire:loading.attr="disabled" 
                        class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl disabled:opacity-50">
                    <span wire:loading.remove wire:target="openModal">
                        <i class="fas fa-plus mr-2"></i>Nova Reserva
                    </span>
                    <span wire:loading wire:target="openModal">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Aguarde...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Check-ins Hoje --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-sign-in-alt text-white"></i>
                </div>
                <p class="text-xs text-green-600 font-semibold uppercase">Check-ins Hoje</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['today_checkins'] }}</p>
        </div>

        {{-- Check-outs Hoje --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-orange-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/30">
                    <i class="fas fa-sign-out-alt text-white"></i>
                </div>
                <p class="text-xs text-orange-600 font-semibold uppercase">Check-outs Hoje</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['today_checkouts'] }}</p>
        </div>

        {{-- Hóspedes Atuais --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-bed text-white"></i>
                </div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Hospedados</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['current_guests'] }}</p>
        </div>

        {{-- Pendentes --}}
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-yellow-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30">
                    <i class="fas fa-clock text-white"></i>
                </div>
                <p class="text-xs text-yellow-600 font-semibold uppercase">Pendentes</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['pending'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-blue-600"></i>
                Filtros
            </h3>
            @if($search || $statusFilter || $dateFilter || $sourceFilter)
                <button wire:click="clearFilters" class="text-sm text-blue-600 hover:text-blue-700 font-semibold">
                    <i class="fas fa-times mr-1"></i>Limpar Filtros
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Pesquisa --}}
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Reserva, hóspede..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-flag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Hotel\Reservation::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Período --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar mr-1"></i>Período
                </label>
                <select wire:model.live="dateFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    <option value="today">Hoje</option>
                    <option value="checkin_today">Check-in Hoje</option>
                    <option value="checkout_today">Check-out Hoje</option>
                    <option value="current">Hospedados</option>
                </select>
            </div>

            {{-- Fonte --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-share-alt mr-1"></i>Origem
                </label>
                <select wire:model.live="sourceFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    @foreach(\App\Models\Hotel\Reservation::SOURCES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Header da Lista --}}
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Lista de Reservas
                </h3>
                <div class="flex items-center gap-4">
                    <select wire:model.live="perPage" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm text-gray-600 font-semibold">
                        <i class="fas fa-calendar-check mr-1"></i>{{ $reservations->total() }} Total
                    </span>
                </div>
            </div>
        </div>

        {{-- Cabeçalho da Tabela --}}
        <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-2 flex items-center">
                <i class="fas fa-hashtag mr-2 text-blue-500"></i>Reserva
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-user mr-2 text-indigo-500"></i>Hóspede
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-door-open mr-2 text-purple-500"></i>Quarto
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-calendar mr-2 text-green-500"></i>Período
            </div>
            <div class="col-span-1 flex items-center">
                <i class="fas fa-coins mr-2 text-yellow-500"></i>Valor
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-flag mr-2 text-cyan-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>

        {{-- Corpo da Tabela --}}
        <div class="divide-y divide-gray-100">
            @forelse($reservations as $reservation)
                <div class="group grid grid-cols-1 md:grid-cols-12 gap-4 px-6 py-4 hover:bg-blue-50/50 transition-all duration-300 items-center">
                    {{-- Reserva --}}
                    <div class="col-span-2">
                        <p class="font-bold text-blue-600">{{ $reservation->reservation_number }}</p>
                        <p class="text-xs text-gray-500">{{ $reservation->source_label }}</p>
                    </div>

                    {{-- Cliente --}}
                    <div class="col-span-2 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            {{ strtoupper(substr($reservation->client->name ?? 'C', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $reservation->client->name ?? 'Sem cliente' }}</p>
                            <p class="text-xs text-gray-500">{{ $reservation->client->phone ?? '' }}</p>
                        </div>
                    </div>

                    {{-- Quarto --}}
                    <div class="col-span-2">
                        <p class="text-sm font-semibold text-gray-700">{{ $reservation->roomType->name ?? '-' }}</p>
                        @if($reservation->room)
                            <p class="text-xs text-green-600"><i class="fas fa-door-open mr-1"></i>Quarto {{ $reservation->room->number }}</p>
                        @else
                            <p class="text-xs text-orange-500"><i class="fas fa-exclamation-circle mr-1"></i>Sem quarto</p>
                        @endif
                    </div>

                    {{-- Período --}}
                    <div class="col-span-2 flex items-center gap-3">
                        <div class="text-center bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-xl p-2 min-w-[50px] shadow-lg">
                            <p class="text-lg font-bold">{{ $reservation->check_in_date->format('d') }}</p>
                            <p class="text-[10px] uppercase">{{ $reservation->check_in_date->locale('pt')->shortMonthName }}</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ $reservation->nights }} noite(s)</p>
                            <p class="text-xs text-gray-500">→ {{ $reservation->check_out_date->format('d/m') }}</p>
                        </div>
                    </div>

                    {{-- Valor --}}
                    <div class="col-span-1">
                        <p class="font-bold text-blue-600">{{ number_format($reservation->total, 0, ',', '.') }}</p>
                        <p class="text-xs {{ $reservation->payment_status === 'paid' ? 'text-green-600' : 'text-orange-600' }}">
                            {{ $reservation->payment_status_label }}
                        </p>
                    </div>

                    {{-- Status --}}
                    <div class="col-span-2">
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'checked_in' => 'bg-green-100 text-green-700',
                                'checked_out' => 'bg-gray-100 text-gray-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                'no_show' => 'bg-orange-100 text-orange-700',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $statusColors[$reservation->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $reservation->status_label }}
                        </span>
                    </div>

                    {{-- Ações --}}
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="view({{ $reservation->id }})" 
                                wire:loading.attr="disabled"
                                class="w-8 h-8 flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                title="Ver">
                            <i class="fas fa-eye text-xs"></i>
                        </button>

                        @if($reservation->status === 'pending')
                            <button wire:click="confirm({{ $reservation->id }})" 
                                    wire:loading.attr="disabled"
                                    class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                    title="Confirmar">
                                <i class="fas fa-check text-xs"></i>
                            </button>
                        @endif

                        @if(in_array($reservation->status, ['pending', 'confirmed']))
                            <button wire:click="openCheckInModal({{ $reservation->id }})" 
                                    wire:loading.attr="disabled"
                                    class="w-8 h-8 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                    title="Check-in">
                                <i class="fas fa-sign-in-alt text-xs"></i>
                            </button>
                        @endif

                        @if($reservation->status === 'checked_in')
                            <button wire:click="checkOut({{ $reservation->id }})" 
                                    wire:loading.attr="disabled"
                                    class="w-8 h-8 flex items-center justify-center bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                    title="Check-out">
                                <i class="fas fa-sign-out-alt text-xs"></i>
                            </button>
                        @endif

                        @if(in_array($reservation->status, ['pending', 'confirmed']))
                            <button wire:click="edit({{ $reservation->id }})" 
                                    wire:loading.attr="disabled"
                                    class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                    title="Editar">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button wire:click="cancel({{ $reservation->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:confirm="Tem certeza que deseja cancelar esta reserva?"
                                    class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg disabled:opacity-50" 
                                    title="Cancelar">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="w-20 h-20 mx-auto mb-4 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-4xl text-blue-400"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma reserva encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie a primeira reserva para começar</p>
                    <button wire:click="openModal" class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold">
                        <i class="fas fa-plus mr-2"></i>Nova Reserva
                    </button>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $reservations->links() }}
    </div>

    {{-- Modais --}}
    @include('livewire.hotel.reservations.partials.form-modal')
    @include('livewire.hotel.reservations.partials.view-modal')
    @include('livewire.hotel.reservations.partials.checkin-modal')
    @include('livewire.hotel.reservations.partials.client-modal')
    @include('livewire.hotel.reservations.partials.payment-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.reservations.partials.toast')
</div>

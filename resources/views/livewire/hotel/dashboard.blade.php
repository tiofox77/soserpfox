<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Gestão de Hotel</h1>
                <p class="text-indigo-100 mt-1">Painel de controle e monitorização</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('hotel.reservations') }}" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-xl font-semibold transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nova Reserva
                </a>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        {{-- Quartos Disponíveis --}}
        <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Disponíveis</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $availableRooms }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-door-open text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        {{-- Quartos Ocupados --}}
        <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Ocupados</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $occupiedRooms }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bed text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        {{-- Taxa de Ocupação --}}
        <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Ocupação</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $occupancyRate }}%</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        {{-- Check-ins Hoje --}}
        <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Check-ins Hoje</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $todayCheckIns->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-sign-in-alt text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        {{-- Check-outs Hoje --}}
        <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Check-outs Hoje</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $todayCheckOuts->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-sign-out-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna Esquerda: Check-ins e Check-outs --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Check-ins de Hoje --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fas fa-sign-in-alt"></i> Check-ins de Hoje
                    </h3>
                </div>
                <div class="p-4">
                    @forelse($todayCheckIns as $reservation)
                        <div class="flex items-center justify-between py-3 border-b last:border-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $reservation->guest->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $reservation->roomType->name }} • {{ $reservation->nights }} noite(s)</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">{{ $reservation->reservation_number }}</span>
                                <button wire:click="quickCheckIn({{ $reservation->id }})" 
                                        class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition">
                                    Check-in
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-calendar-check text-4xl mb-3 text-gray-300"></i>
                            <p>Nenhum check-in agendado para hoje</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Check-outs de Hoje --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-orange-500 to-red-500 text-white">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fas fa-sign-out-alt"></i> Check-outs de Hoje
                    </h3>
                </div>
                <div class="p-4">
                    @forelse($todayCheckOuts as $reservation)
                        <div class="flex items-center justify-between py-3 border-b last:border-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-orange-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $reservation->guest->name }}</p>
                                    <p class="text-sm text-gray-500">Quarto {{ $reservation->room?->number ?? 'N/A' }} • {{ number_format($reservation->balance_due, 2, ',', '.') }} Kz pendente</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="quickCheckOut({{ $reservation->id }})" 
                                        class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white text-sm rounded-lg transition">
                                    Check-out
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-door-open text-4xl mb-3 text-gray-300"></i>
                            <p>Nenhum check-out agendado para hoje</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Mapa de Quartos --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-th-large text-indigo-600"></i> Mapa de Quartos
                    </h3>
                </div>
                <div class="p-4">
                    @forelse($roomsMap as $floor => $rooms)
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-600 mb-2">{{ $floor ? $floor . 'º Andar' : 'Térreo' }}</h4>
                            <div class="grid grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
                                @foreach($rooms as $room)
                                    <div class="relative group">
                                        <div class="w-full aspect-square rounded-lg flex items-center justify-center text-xs font-bold cursor-pointer transition
                                            @if($room->status === 'available') bg-green-100 text-green-700 hover:bg-green-200
                                            @elseif($room->status === 'occupied') bg-red-100 text-red-700 hover:bg-red-200
                                            @elseif($room->status === 'maintenance') bg-yellow-100 text-yellow-700 hover:bg-yellow-200
                                            @elseif($room->status === 'cleaning') bg-blue-100 text-blue-700 hover:bg-blue-200
                                            @else bg-purple-100 text-purple-700 hover:bg-purple-200
                                            @endif">
                                            {{ $room->number }}
                                        </div>
                                        {{-- Tooltip --}}
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10">
                                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap">
                                                <p class="font-bold">Quarto {{ $room->number }}</p>
                                                <p>{{ $room->roomType->name }}</p>
                                                <p>{{ $room->status_label }}</p>
                                                @if($room->currentReservation)
                                                    <p class="mt-1 border-t border-gray-700 pt-1">{{ $room->currentReservation->guest->name }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <p>Nenhum quarto cadastrado</p>
                        </div>
                    @endforelse

                    {{-- Legenda --}}
                    <div class="flex flex-wrap gap-4 mt-4 pt-4 border-t">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-4 h-4 bg-green-100 rounded"></div>
                            <span>Disponível</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-4 h-4 bg-red-100 rounded"></div>
                            <span>Ocupado</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-4 h-4 bg-yellow-100 rounded"></div>
                            <span>Manutenção</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-4 h-4 bg-blue-100 rounded"></div>
                            <span>Limpeza</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-4 h-4 bg-purple-100 rounded"></div>
                            <span>Reservado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Coluna Direita: Próximas chegadas e Receita --}}
        <div class="space-y-6">
            {{-- Receita do Mês --}}
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900">Receita do Mês</h3>
                    <i class="fas fa-chart-line text-green-600"></i>
                </div>
                <p class="text-3xl font-bold text-green-600">{{ number_format($monthlyRevenue, 2, ',', '.') }} Kz</p>
                <p class="text-sm text-gray-500 mt-1">{{ now()->format('F Y') }}</p>
            </div>

            {{-- Hóspedes Atuais --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fas fa-users"></i> Hóspedes Atuais ({{ $currentGuests->count() }})
                    </h3>
                </div>
                <div class="p-4 max-h-80 overflow-y-auto">
                    @forelse($currentGuests as $reservation)
                        <div class="flex items-center gap-3 py-2 border-b last:border-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-sm">
                                {{ $reservation->room?->number ?? '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $reservation->guest->name }}</p>
                                <p class="text-xs text-gray-500">Saída: {{ $reservation->check_out_date->format('d/m') }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500 text-sm">
                            Nenhum hóspede no momento
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Próximas Chegadas --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-purple-600"></i> Próximas Chegadas
                    </h3>
                </div>
                <div class="p-4 max-h-80 overflow-y-auto">
                    @forelse($upcomingArrivals as $reservation)
                        <div class="flex items-center justify-between py-2 border-b last:border-0">
                            <div>
                                <p class="font-medium text-gray-900">{{ $reservation->guest->name }}</p>
                                <p class="text-xs text-gray-500">{{ $reservation->roomType->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-purple-600">{{ $reservation->check_in_date->format('d/m') }}</p>
                                <p class="text-xs text-gray-500">{{ $reservation->nights }} noite(s)</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500 text-sm">
                            Sem chegadas nos próximos 7 dias
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Reservas Pendentes --}}
            @if($pendingReservations->count() > 0)
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-100">
                    <h3 class="font-bold text-yellow-800 flex items-center gap-2">
                        <i class="fas fa-clock"></i> Aguardando Confirmação ({{ $pendingReservations->count() }})
                    </h3>
                </div>
                <div class="p-4 max-h-60 overflow-y-auto">
                    @foreach($pendingReservations as $reservation)
                        <div class="flex items-center justify-between py-2 border-b last:border-0">
                            <div>
                                <p class="font-medium text-gray-900 text-sm">{{ $reservation->guest->name }}</p>
                                <p class="text-xs text-gray-500">{{ $reservation->check_in_date->format('d/m') }} - {{ $reservation->check_out_date->format('d/m') }}</p>
                            </div>
                            <a href="{{ route('hotel.reservations') }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

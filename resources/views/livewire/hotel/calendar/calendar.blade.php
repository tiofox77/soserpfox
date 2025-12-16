<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Calendario de Reservas</h2>
                    <p class="text-blue-100 text-sm">Vista {{ $viewType === 'week' ? 'semanal' : 'mensal' }} das reservas</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-white/20 rounded-xl p-1">
                    <button wire:click="previousPeriod" class="px-3 py-2 hover:bg-white/20 rounded-lg transition"><i class="fas fa-chevron-left"></i></button>
                    <button wire:click="goToToday" class="px-4 py-2 hover:bg-white/20 rounded-lg transition font-semibold">Hoje</button>
                    <button wire:click="nextPeriod" class="px-3 py-2 hover:bg-white/20 rounded-lg transition"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="flex items-center gap-1 bg-white/20 rounded-xl p-1">
                    <button wire:click="setViewType('week')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewType === 'week' ? 'bg-white text-blue-600' : 'text-white hover:bg-white/20' }}"><i class="fas fa-calendar-week mr-2"></i>Semana</button>
                    <button wire:click="setViewType('month')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewType === 'month' ? 'bg-white text-blue-600' : 'text-white hover:bg-white/20' }}"><i class="fas fa-calendar mr-2"></i>Mes</button>
                </div>
                <a href="{{ route('hotel.reservations') }}" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold shadow-lg"><i class="fas fa-list mr-2"></i>Lista</a>
            </div>
        </div>
        <div class="mt-4 text-center">
            <h3 class="text-3xl font-bold">{{ Carbon\Carbon::parse($currentDate)->locale('pt')->isoFormat('MMMM [de] YYYY') }}</h3>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-blue-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center"><i class="fas fa-door-open text-white"></i></div>
                <div><p class="text-2xl font-bold text-gray-900">{{ $stats['total_rooms'] }}</p><p class="text-xs text-gray-500">Quartos</p></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-green-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center"><i class="fas fa-sign-in-alt text-white"></i></div>
                <div><p class="text-2xl font-bold text-gray-900">{{ $stats['check_ins_today'] }}</p><p class="text-xs text-gray-500">Check-ins</p></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-orange-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center"><i class="fas fa-sign-out-alt text-white"></i></div>
                <div><p class="text-2xl font-bold text-gray-900">{{ $stats['check_outs_today'] }}</p><p class="text-xs text-gray-500">Check-outs</p></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-purple-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center"><i class="fas fa-bed text-white"></i></div>
                <div><p class="text-2xl font-bold text-gray-900">{{ $stats['occupied_today'] }}</p><p class="text-xs text-gray-500">Ocupados</p></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-cyan-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-teal-600 rounded-xl flex items-center justify-center"><i class="fas fa-percentage text-white"></i></div>
                <div><p class="text-2xl font-bold text-gray-900">{{ $stats['occupancy_rate'] }}%</p><p class="text-xs text-gray-500">Ocupacao</p></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-amber-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center"><i class="fas fa-coins text-white"></i></div>
                <div><p class="text-xl font-bold text-gray-900">{{ number_format($stats['revenue_period'], 0, ',', '.') }}</p><p class="text-xs text-gray-500">Receita</p></div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 bg-white rounded-2xl shadow-lg p-4 flex items-center gap-4">
        <div class="flex items-center gap-2"><i class="fas fa-filter text-blue-600"></i><span class="font-semibold text-gray-700">Filtros:</span></div>
        <select wire:model.live="roomTypeFilter" class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
            <option value="">Todos os tipos</option>
            @foreach($roomTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
        </select>
        <select wire:model.live="statusFilter" class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-sm">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Hotel\Reservation::STATUSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <div class="ml-auto flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-yellow-400"></span> Pendente</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-500"></span> Confirmada</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-500"></span> Check-in</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-400"></span> Check-out</span>
        </div>
    </div>

    {{-- Calendario Timeline --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="flex border-b border-gray-200 bg-gray-50 sticky top-0 z-10">
            <div class="w-40 min-w-[160px] px-4 py-3 border-r border-gray-200 font-bold text-gray-700 bg-gray-100"><i class="fas fa-door-open mr-2 text-blue-600"></i>Quarto</div>
            <div class="flex-1 flex">
                @foreach($dates as $date)
                <div class="flex-1 min-w-[40px] px-1 py-2 text-center border-r border-gray-100 last:border-r-0 {{ $date['isToday'] ? 'bg-blue-100' : ($date['isWeekend'] ? 'bg-gray-50' : '') }}">
                    <p class="text-[10px] uppercase text-gray-500">{{ $date['dayName'] }}</p>
                    <p class="text-sm font-bold {{ $date['isToday'] ? 'text-blue-600' : 'text-gray-700' }}">{{ $date['day'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
        <div class="divide-y divide-gray-100 max-h-[60vh] overflow-y-auto">
            @foreach($roomsWithReservations as $room)
            <div class="flex group hover:bg-blue-50/30 transition">
                <div class="w-40 min-w-[160px] px-4 py-3 border-r border-gray-200 flex items-center gap-2 bg-white group-hover:bg-blue-50/50">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold" style="background-color: {{ $room['type_color'] }}">{{ $room['floor'] }}</div>
                    <div><p class="font-bold text-gray-900">{{ $room['number'] }}</p><p class="text-[10px] text-gray-500">{{ $room['type_name'] }}</p></div>
                </div>
                <div class="flex-1 relative py-1" style="min-height: 50px;">
                    <div class="absolute inset-0 flex">
                        @foreach($dates as $index => $date)
                        <div wire:click="openQuickReservation({{ $room['id'] }}, '{{ $date['date'] }}')" class="flex-1 min-w-[40px] border-r border-gray-100 last:border-r-0 cursor-pointer hover:bg-blue-100/50 transition {{ $date['isToday'] ? 'bg-blue-50/50' : ($date['isWeekend'] ? 'bg-gray-50/50' : '') }}"></div>
                        @endforeach
                    </div>
                    @foreach($room['reservations'] as $reservation)
                    @php
                        $statusColors = ['pending'=>'bg-yellow-400 hover:bg-yellow-500','confirmed'=>'bg-blue-500 hover:bg-blue-600','checked_in'=>'bg-green-500 hover:bg-green-600','checked_out'=>'bg-gray-400 hover:bg-gray-500','no_show'=>'bg-red-400 hover:bg-red-500'];
                        $color = $statusColors[$reservation['status']] ?? 'bg-gray-300';
                        $widthPercent = ($reservation['width'] / count($dates)) * 100;
                        $leftPercent = ($reservation['start_offset'] / count($dates)) * 100;
                    @endphp
                    <div wire:click="viewReservation({{ $reservation['id'] }})" class="absolute top-1 bottom-1 {{ $color }} text-white text-[10px] font-semibold rounded-lg px-2 py-1 cursor-pointer shadow-md hover:shadow-lg transition-all overflow-hidden flex items-center gap-1 z-10 {{ $reservation['starts_before'] ? 'rounded-l-none' : '' }} {{ $reservation['ends_after'] ? 'rounded-r-none' : '' }}" style="left: {{ $leftPercent }}%; width: {{ $widthPercent }}%;" title="{{ $reservation['guest_name'] }} | {{ $reservation['check_in'] }} - {{ $reservation['check_out'] }}">
                        <i class="fas fa-user text-[8px]"></i><span class="truncate">{{ $reservation['guest_name'] }}</span>
                        @if($reservation['width'] > 2)<span class="ml-auto opacity-75">{{ $reservation['nights'] }}n</span>@endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
            @if($roomsWithReservations->isEmpty())
            <div class="p-12 text-center"><i class="fas fa-calendar-times text-6xl text-gray-200 mb-4"></i><p class="text-gray-500">Nenhum quarto encontrado</p></div>
            @endif
        </div>
    </div>

    {{-- Modais --}}
    @include('livewire.hotel.calendar.partials.quick-modal')
    @include('livewire.hotel.calendar.partials.view-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.calendar.partials.toast')
</div>

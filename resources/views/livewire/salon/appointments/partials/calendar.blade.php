@php
    $calendarData = $this->calendarData;
    $currentDate = $calendarData['currentDate'];
    $appointments = $calendarData['appointments'];
    $start = $calendarData['start'];
    $end = $calendarData['end'];
@endphp

<div class="bg-white rounded-2xl shadow-lg overflow-hidden">
    <!-- Calendar Header -->
    <div class="px-6 py-4 bg-gradient-to-r from-pink-500 to-purple-500">
        <div class="flex items-center justify-between">
            <!-- Navigation -->
            <div class="flex items-center gap-2">
                <button wire:click="previousPeriod" class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center text-white transition">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button wire:click="goToToday" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-xl text-white font-semibold transition text-sm">
                    Hoje
                </button>
                <button wire:click="nextPeriod" class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center text-white transition">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Current Period Title -->
            <h3 class="text-xl font-bold text-white">
                @if($calendarView === 'day')
                    {{ $currentDate->locale('pt')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                @elseif($calendarView === 'week')
                    {{ $start->format('d') }} - {{ $end->format('d') }} de {{ $currentDate->locale('pt')->isoFormat('MMMM YYYY') }}
                @else
                    {{ $currentDate->locale('pt')->isoFormat('MMMM [de] YYYY') }}
                @endif
            </h3>

            <!-- View Toggle -->
            <div class="flex items-center gap-1 bg-white/20 rounded-xl p-1">
                <button wire:click="setCalendarView('day')" class="px-3 py-1.5 rounded-lg text-sm font-semibold transition {{ $calendarView === 'day' ? 'bg-white text-pink-600' : 'text-white hover:bg-white/20' }}">
                    Dia
                </button>
                <button wire:click="setCalendarView('week')" class="px-3 py-1.5 rounded-lg text-sm font-semibold transition {{ $calendarView === 'week' ? 'bg-white text-pink-600' : 'text-white hover:bg-white/20' }}">
                    Semana
                </button>
                <button wire:click="setCalendarView('month')" class="px-3 py-1.5 rounded-lg text-sm font-semibold transition {{ $calendarView === 'month' ? 'bg-white text-pink-600' : 'text-white hover:bg-white/20' }}">
                    Mês
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Content -->
    @if($calendarView === 'month')
        <!-- Month View -->
        <div class="p-4">
            <!-- Weekday Headers -->
            <div class="grid grid-cols-7 gap-1 mb-2">
                @foreach(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $day)
                    <div class="text-center text-xs font-bold text-gray-500 uppercase py-2">{{ $day }}</div>
                @endforeach
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7 gap-1">
                @php
                    $day = $start->copy();
                @endphp
                @while($day <= $end)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $dayAppointments = $appointments->get($dateKey, collect());
                        $isToday = $day->isToday();
                        $isCurrentMonth = $day->month === $currentDate->month;
                    @endphp
                    <div wire:click="selectDate('{{ $dateKey }}')" 
                         class="min-h-[100px] p-2 border rounded-xl cursor-pointer transition hover:border-pink-300 hover:bg-pink-50 {{ $isToday ? 'bg-pink-100 border-pink-400' : ($isCurrentMonth ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-100') }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-bold {{ $isToday ? 'text-pink-600' : ($isCurrentMonth ? 'text-gray-900' : 'text-gray-400') }}">
                                {{ $day->format('d') }}
                            </span>
                            @if($dayAppointments->count() > 0)
                                <span class="w-6 h-6 bg-pink-500 text-white rounded-full text-xs font-bold flex items-center justify-center">
                                    {{ $dayAppointments->count() }}
                                </span>
                            @endif
                        </div>
                        <div class="space-y-1">
                            @foreach($dayAppointments->take(3) as $apt)
                                <div class="text-xs p-1 rounded bg-gradient-to-r from-pink-100 to-purple-100 truncate">
                                    <span class="font-semibold text-pink-700">{{ \Carbon\Carbon::parse($apt->start_time)->format('H:i') }}</span>
                                    <span class="text-gray-600">{{ Str::limit($apt->client->name ?? 'Cliente', 10) }}</span>
                                </div>
                            @endforeach
                            @if($dayAppointments->count() > 3)
                                <div class="text-xs text-gray-500 text-center">+{{ $dayAppointments->count() - 3 }} mais</div>
                            @endif
                        </div>
                    </div>
                    @php $day->addDay(); @endphp
                @endwhile
            </div>
        </div>

    @elseif($calendarView === 'week')
        <!-- Week View -->
        <div class="p-4">
            <div class="grid grid-cols-7 gap-2">
                @php
                    $day = $start->copy();
                @endphp
                @while($day <= $end)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $dayAppointments = $appointments->get($dateKey, collect());
                        $isToday = $day->isToday();
                    @endphp
                    <div class="min-h-[400px] border rounded-xl {{ $isToday ? 'border-pink-400 bg-pink-50' : 'border-gray-200' }}">
                        <div class="p-2 text-center border-b {{ $isToday ? 'bg-pink-500 text-white' : 'bg-gray-50' }}">
                            <p class="text-xs font-semibold uppercase">{{ $day->locale('pt')->shortDayName }}</p>
                            <p class="text-lg font-bold">{{ $day->format('d') }}</p>
                        </div>
                        <div class="p-2 space-y-2 overflow-y-auto max-h-[350px]">
                            @forelse($dayAppointments as $apt)
                                <div wire:click="view({{ $apt->id }})" class="p-2 bg-gradient-to-r from-pink-100 to-purple-100 rounded-lg cursor-pointer hover:shadow-md transition">
                                    <p class="text-xs font-bold text-pink-700">{{ \Carbon\Carbon::parse($apt->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($apt->end_time)->format('H:i') }}</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $apt->client->name ?? 'Cliente' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $apt->professional->display_name }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 text-center py-4">Sem agendamentos</p>
                            @endforelse
                        </div>
                    </div>
                    @php $day->addDay(); @endphp
                @endwhile
            </div>
        </div>

    @else
        <!-- Day View -->
        <div class="p-4">
            @php
                $dateKey = $currentDate->format('Y-m-d');
                $dayAppointments = $appointments->get($dateKey, collect());
            @endphp

            <!-- Time Grid -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Time Column -->
                <div class="col-span-2">
                    @for($hour = 8; $hour <= 20; $hour++)
                        <div class="h-16 border-b border-gray-100 flex items-start justify-end pr-2">
                            <span class="text-xs font-semibold text-gray-500">{{ sprintf('%02d:00', $hour) }}</span>
                        </div>
                    @endfor
                </div>

                <!-- Appointments Column -->
                <div class="col-span-10 relative">
                    @for($hour = 8; $hour <= 20; $hour++)
                        <div class="h-16 border-b border-gray-100 border-l"></div>
                    @endfor

                    <!-- Appointments Overlay -->
                    @foreach($dayAppointments as $apt)
                        @php
                            $startTime = \Carbon\Carbon::parse($apt->start_time);
                            $endTime = \Carbon\Carbon::parse($apt->end_time);
                            $topPosition = (($startTime->hour - 8) * 64) + (($startTime->minute / 60) * 64);
                            $height = (($endTime->diffInMinutes($startTime)) / 60) * 64;
                        @endphp
                        <div wire:click="view({{ $apt->id }})" 
                             class="absolute left-1 right-1 bg-gradient-to-r from-pink-500 to-purple-500 rounded-xl p-3 text-white cursor-pointer hover:shadow-lg transition overflow-hidden"
                             style="top: {{ $topPosition }}px; height: {{ max($height, 48) }}px;">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm">{{ $startTime->format('H:i') }} - {{ $endTime->format('H:i') }}</span>
                                @php
                                    $statusColors = [
                                        'scheduled' => 'bg-blue-200 text-blue-800',
                                        'confirmed' => 'bg-indigo-200 text-indigo-800',
                                        'in_progress' => 'bg-yellow-200 text-yellow-800',
                                        'completed' => 'bg-green-200 text-green-800',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xs font-bold {{ $statusColors[$apt->status] ?? 'bg-gray-200 text-gray-800' }}">
                                    {{ $apt->status_label }}
                                </span>
                            </div>
                            <p class="font-semibold truncate">{{ $apt->client->name ?? 'Cliente' }}</p>
                            <p class="text-xs text-white/80 truncate">{{ $apt->professional->display_name }} • {{ $apt->services->pluck('service.name')->implode(', ') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($dayAppointments->isEmpty())
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-day text-gray-400 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-900 mb-2">Sem agendamentos</h4>
                    <p class="text-sm text-gray-500 mb-4">Não há agendamentos para este dia</p>
                    <button wire:click="openModal" class="px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white rounded-xl font-semibold transition">
                        <i class="fas fa-plus mr-2"></i>Criar Agendamento
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>

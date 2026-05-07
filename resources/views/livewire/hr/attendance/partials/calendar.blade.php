@php
    use Carbon\Carbon;
    $firstDay = Carbon::create($selectedYear, $selectedMonth, 1);
    $lastDay  = $firstDay->copy()->endOfMonth();
    $monthNames = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $dayNames   = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
    $today = Carbon::today()->format('Y-m-d');

    // Build calendar grid: start on Monday
    $startOfGrid = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
    $endOfGrid   = $lastDay->copy()->endOfWeek(Carbon::SUNDAY);

    $weeks = [];
    $current = $startOfGrid->copy();
    while ($current->lte($endOfGrid)) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = $current->copy();
            $current->addDay();
        }
        $weeks[] = $week;
    }
@endphp

{{-- Month Navigation + Stats --}}
<div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5">
    <div class="flex items-center justify-between">
        <button wire:click="changeMonth('prev')" class="w-10 h-10 flex items-center justify-center bg-white/20 hover:bg-white/30 text-white rounded-xl transition-all">
            <i class="fas fa-chevron-left"></i>
        </button>
        <div class="text-center">
            <h3 class="text-white font-bold text-xl">{{ $monthNames[(int)$selectedMonth] }} {{ $selectedYear }}</h3>
            <p class="text-green-100 text-xs mt-1">
                @if($employeeFilter)
                    @php $empName = $employees->firstWhere('id', $employeeFilter); @endphp
                    <i class="fas fa-user mr-1"></i>{{ $empName ? $empName->full_name : 'Funcionário' }}
                @else
                    <i class="fas fa-users mr-1"></i>Todos os Funcionários
                @endif
            </p>
        </div>
        <button wire:click="changeMonth('next')" class="w-10 h-10 flex items-center justify-center bg-white/20 hover:bg-white/30 text-white rounded-xl transition-all">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    {{-- Mini stats bar --}}
    <div class="flex items-center justify-center gap-6 mt-4">
        <div class="flex items-center gap-1.5 text-white/90 text-xs font-semibold">
            <span class="w-2.5 h-2.5 rounded-full bg-green-300"></span>
            {{ $calendarStats['present'] ?? 0 }} Presenças
        </div>
        <div class="flex items-center gap-1.5 text-white/90 text-xs font-semibold">
            <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
            {{ $calendarStats['absent'] ?? 0 }} Faltas
        </div>
        <div class="flex items-center gap-1.5 text-white/90 text-xs font-semibold">
            <span class="w-2.5 h-2.5 rounded-full bg-yellow-300"></span>
            {{ $calendarStats['late'] ?? 0 }} Atrasos
        </div>
        <div class="flex items-center gap-1.5 text-white/90 text-xs font-semibold">
            <i class="fas fa-clock text-green-200"></i>
            {{ $calendarStats['total_hours'] ?? 0 }}h Total
        </div>
    </div>
</div>

{{-- Calendar Grid --}}
<div class="p-4">
    {{-- Day Headers --}}
    <div class="grid grid-cols-7 mb-2">
        @foreach($dayNames as $i => $dayName)
            <div class="text-center py-2 text-xs font-bold uppercase tracking-wider {{ $i >= 5 ? 'text-red-400' : 'text-gray-500' }}">
                {{ $dayName }}
            </div>
        @endforeach
    </div>

    {{-- Weeks --}}
    <div class="grid grid-cols-7 border border-gray-200 rounded-xl overflow-hidden">
        @foreach($weeks as $weekIndex => $week)
            @foreach($week as $dayIndex => $day)
                @php
                    $dateStr = $day->format('Y-m-d');
                    $isCurrentMonth = (int)$day->month === (int)$selectedMonth;
                    $isToday = $dateStr === $today;
                    $isWeekend = $day->isSaturday() || $day->isSunday();
                    $dayRecords = $calendarData[$dateStr] ?? collect();
                    $presentCount = $dayRecords->where('status', 'present')->count();
                    $absentCount  = $dayRecords->where('status', 'absent')->count();
                    $lateCount    = $dayRecords->where('is_late', true)->count();
                    $halfCount    = $dayRecords->where('status', 'half_day')->count();
                    $totalHours   = round($dayRecords->sum('hours_worked'), 1);
                    $hasRecords   = $dayRecords->isNotEmpty();

                    // Determine cell background
                    $cellBg = 'bg-white';
                    if (!$isCurrentMonth) {
                        $cellBg = 'bg-gray-50';
                    } elseif ($isToday) {
                        $cellBg = 'bg-green-50 ring-2 ring-green-400 ring-inset';
                    } elseif ($isWeekend) {
                        $cellBg = 'bg-gray-50/70';
                    }

                    // For single-employee view, determine dominant status
                    $dominantStatus = null;
                    if ($employeeFilter && $hasRecords) {
                        $rec = $dayRecords->first();
                        if ($rec->status === 'absent') $dominantStatus = 'absent';
                        elseif ($rec->is_late) $dominantStatus = 'late';
                        elseif ($rec->status === 'half_day') $dominantStatus = 'half_day';
                        elseif ($rec->status === 'present') $dominantStatus = 'present';
                    }
                @endphp
                <div class="relative min-h-[90px] border-r border-b border-gray-100 last:border-r-0 {{ $cellBg }} transition-colors hover:bg-gray-50/50 group"
                     @if($isCurrentMonth) wire:click="createForDate('{{ $dateStr }}')" style="cursor:pointer;" @endif>

                    {{-- Day Number --}}
                    <div class="flex items-center justify-between px-2 pt-1.5">
                        <span class="text-sm font-bold {{ !$isCurrentMonth ? 'text-gray-300' : ($isToday ? 'text-green-700' : ($isWeekend ? 'text-red-400' : 'text-gray-700')) }}">
                            @if($isToday)
                                <span class="inline-flex items-center justify-center w-7 h-7 bg-green-600 text-white rounded-full text-xs">
                                    {{ $day->day }}
                                </span>
                            @else
                                {{ $day->day }}
                            @endif
                        </span>
                        @if($hasRecords && $totalHours > 0 && $isCurrentMonth)
                            <span class="text-[10px] font-semibold text-gray-400">{{ $totalHours }}h</span>
                        @endif
                    </div>

                    @if($isCurrentMonth && $hasRecords)
                        {{-- Single employee: show status badge --}}
                        @if($employeeFilter)
                            <div class="px-2 mt-1">
                                @if($dominantStatus === 'present' && !$lateCount)
                                    <div class="flex items-center gap-1 px-2 py-1 bg-green-100 rounded-lg">
                                        <i class="fas fa-check-circle text-green-600 text-[10px]"></i>
                                        <span class="text-[10px] font-bold text-green-700">Presente</span>
                                    </div>
                                    @php $rec = $dayRecords->first(); @endphp
                                    @if($rec->check_in)
                                        <div class="mt-1 flex items-center gap-2 text-[10px] text-gray-500 px-1">
                                            <span><i class="fas fa-sign-in-alt text-blue-400"></i> {{ substr($rec->check_in, 0, 5) }}</span>
                                            @if($rec->check_out)
                                                <span><i class="fas fa-sign-out-alt text-red-400"></i> {{ substr($rec->check_out, 0, 5) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                @elseif($dominantStatus === 'absent')
                                    <div class="flex items-center gap-1 px-2 py-1 bg-red-100 rounded-lg">
                                        <i class="fas fa-times-circle text-red-500 text-[10px]"></i>
                                        <span class="text-[10px] font-bold text-red-700">Falta</span>
                                    </div>
                                @elseif($lateCount > 0)
                                    <div class="flex items-center gap-1 px-2 py-1 bg-yellow-100 rounded-lg">
                                        <i class="fas fa-clock text-yellow-600 text-[10px]"></i>
                                        <span class="text-[10px] font-bold text-yellow-700">Atraso</span>
                                    </div>
                                    @php $rec = $dayRecords->first(); @endphp
                                    @if($rec->late_minutes)
                                        <div class="mt-0.5 text-[9px] text-yellow-600 font-semibold px-1">
                                            +{{ $rec->late_minutes }} min
                                        </div>
                                    @endif
                                    @if($rec->check_in)
                                        <div class="mt-0.5 flex items-center gap-2 text-[10px] text-gray-500 px-1">
                                            <span><i class="fas fa-sign-in-alt text-blue-400"></i> {{ substr($rec->check_in, 0, 5) }}</span>
                                            @if($rec->check_out)
                                                <span><i class="fas fa-sign-out-alt text-red-400"></i> {{ substr($rec->check_out, 0, 5) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                @elseif($dominantStatus === 'half_day')
                                    <div class="flex items-center gap-1 px-2 py-1 bg-blue-100 rounded-lg">
                                        <i class="fas fa-adjust text-blue-500 text-[10px]"></i>
                                        <span class="text-[10px] font-bold text-blue-700">Meio Dia</span>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- All employees: show count dots --}}
                            <div class="px-2 mt-1 space-y-0.5">
                                @if($presentCount > 0)
                                    <div class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                                        <span class="text-[10px] font-semibold text-green-700">{{ $presentCount }} presente{{ $presentCount > 1 ? 's' : '' }}</span>
                                    </div>
                                @endif
                                @if($absentCount > 0)
                                    <div class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                                        <span class="text-[10px] font-semibold text-red-600">{{ $absentCount }} falta{{ $absentCount > 1 ? 's' : '' }}</span>
                                    </div>
                                @endif
                                @if($lateCount > 0)
                                    <div class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-yellow-500 flex-shrink-0"></span>
                                        <span class="text-[10px] font-semibold text-yellow-600">{{ $lateCount }} atraso{{ $lateCount > 1 ? 's' : '' }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @elseif($isCurrentMonth && !$hasRecords && !$isWeekend && $day->lte(Carbon::today()))
                        {{-- Working day with no records (past) --}}
                        <div class="px-2 mt-2">
                            <span class="text-[10px] text-gray-300 italic">Sem registo</span>
                        </div>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</div>

{{-- Legend --}}
<div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex flex-wrap gap-4">
            <span class="inline-flex items-center text-xs font-medium text-gray-600">
                <span class="w-3 h-3 rounded-full bg-green-500 mr-1.5"></span>Presente
            </span>
            <span class="inline-flex items-center text-xs font-medium text-gray-600">
                <span class="w-3 h-3 rounded-full bg-red-500 mr-1.5"></span>Falta
            </span>
            <span class="inline-flex items-center text-xs font-medium text-gray-600">
                <span class="w-3 h-3 rounded-full bg-yellow-500 mr-1.5"></span>Atraso
            </span>
            <span class="inline-flex items-center text-xs font-medium text-gray-600">
                <span class="w-3 h-3 rounded-full bg-blue-500 mr-1.5"></span>Meio Período
            </span>
            <span class="inline-flex items-center text-xs font-medium text-gray-600">
                <span class="inline-flex items-center justify-center w-5 h-5 bg-green-600 text-white rounded-full text-[9px] font-bold mr-1.5">H</span>Hoje
            </span>
        </div>
        <span class="text-[10px] text-gray-400">Clique num dia para registar presença</span>
    </div>
</div>

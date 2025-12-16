<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-spa text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Dashboard do Salão</h2>
                    <p class="text-pink-100 text-sm">Visão geral dos agendamentos</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                <button wire:click="previousDay" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="text-center min-w-[120px]">
                    <p class="text-lg font-bold">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</p>
                    <p class="text-xs text-pink-200">{{ \Carbon\Carbon::parse($selectedDate)->locale('pt')->dayName }}</p>
                </div>
                <button wire:click="nextDay" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <i class="fas fa-chevron-right"></i>
                </button>
                @if($selectedDate !== today()->toDateString())
                    <button wire:click="goToToday" class="ml-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-semibold transition">
                        Hoje
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-gray-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['today_appointments'] }}</p>
                    <p class="text-xs text-gray-500">Agendamentos</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check text-blue-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['confirmed'] }}</p>
                    <p class="text-xs text-gray-500">Confirmados</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-cut text-indigo-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-indigo-600">{{ $stats['in_progress'] }}</p>
                    <p class="text-xs text-gray-500">Em Atendimento</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</p>
                    <p class="text-xs text-gray-500">Concluídos</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-pink-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-coins text-pink-600"></i>
                </div>
                <div>
                    <p class="text-lg font-bold text-pink-600">{{ number_format($stats['revenue_today'], 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500">Receita Hoje (Kz)</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-line text-purple-600"></i>
                </div>
                <div>
                    <p class="text-lg font-bold text-purple-600">{{ number_format($stats['revenue_month'], 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500">Receita Mês (Kz)</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-cyan-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-stopwatch text-cyan-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-cyan-600">{{ $stats['avg_duration'] }}<span class="text-sm">min</span></p>
                    <p class="text-xs text-gray-500">Tempo Médio</p>
                </div>
            </div>
        </div>
        <a href="{{ route('salon.reports.time') }}" class="bg-gradient-to-br from-pink-500 to-purple-600 rounded-2xl shadow-lg p-4 hover:shadow-xl transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-bar text-white"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-white">Relatório</p>
                    <p class="text-xs text-white/70">Ver Tempos <i class="fas fa-arrow-right ml-1 group-hover:translate-x-1 transition"></i></p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <!-- Próximos Clientes -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-pink-600 to-purple-600">
                <h3 class="font-bold text-white flex items-center">
                    <i class="fas fa-clock mr-2"></i> Próximos Atendimentos
                </h3>
            </div>
            <div class="p-4 space-y-3">
                @forelse($nextClients as $appointment)
                    <div class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-pink-50 rounded-xl transition">
                        <div class="w-10 h-10 bg-gradient-to-br from-pink-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold shadow">
                            {{ substr($appointment->client->name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $appointment->client->name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }} - {{ $appointment->professional->display_name }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            @if($appointment->status === 'scheduled')
                                <button wire:click="quickConfirm({{ $appointment->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Confirmar">
                                    <i class="fas fa-check"></i>
                                </button>
                            @elseif($appointment->status === 'confirmed')
                                <button wire:click="quickStart({{ $appointment->id }})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Iniciar">
                                    <i class="fas fa-play"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-8">
                        <i class="fas fa-calendar-check text-3xl text-gray-300 mb-2 block"></i>
                        Nenhum agendamento pendente
                    </p>
                @endforelse
            </div>
        </div>

        <!-- Agenda por Profissional -->
        <div class="col-span-2 bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="font-bold text-gray-900 flex items-center">
                    <i class="fas fa-calendar-alt text-purple-600 mr-2"></i> Agenda do Dia
                </h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-{{ min(count($professionals), 4) }} gap-4">
                    @foreach($professionals as $professional)
                        <div class="border rounded-xl overflow-hidden">
                            <div class="p-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center text-xs font-bold">
                                        {{ $professional->initials }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-sm">{{ $professional->display_name }}</p>
                                        <p class="text-xs text-pink-200">{{ $schedule[$professional->id]['appointments']->count() }} agendamentos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-2 space-y-2 max-h-64 overflow-y-auto">
                                @forelse($schedule[$professional->id]['appointments'] as $apt)
                                    <div class="p-2 rounded-lg text-xs border-l-4
                                        @if($apt->status === 'completed') bg-green-50 border-green-500
                                        @elseif($apt->status === 'in_progress') bg-indigo-50 border-indigo-500
                                        @elseif($apt->status === 'confirmed') bg-blue-50 border-blue-500
                                        @elseif($apt->status === 'cancelled') bg-red-50 border-red-500 opacity-50
                                        @else bg-yellow-50 border-yellow-500
                                        @endif">
                                        <p class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($apt->start_time)->format('H:i') }} - {{ $apt->client->first_name }}</p>
                                        <p class="text-gray-500 truncate">{{ $apt->services->pluck('service.name')->implode(', ') }}</p>
                                    </div>
                                @empty
                                    <p class="text-gray-400 text-center py-4 text-xs">Sem agendamentos</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Lista Completa -->
    <div class="mt-6 bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-pink-600"></i>Todos os Agendamentos do Dia
            </h3>
            <a href="{{ route('salon.appointments') }}" class="text-pink-600 hover:text-pink-700 text-sm font-semibold">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="divide-y divide-gray-100">
            @forelse($appointments as $appointment)
                <div class="p-4 hover:bg-pink-50 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="text-center min-w-[60px]">
                                <p class="font-bold text-gray-900">{{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }}</p>
                                <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($appointment->end_time)->format('H:i') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $appointment->client->name }}</p>
                                <p class="text-sm text-gray-500">{{ $appointment->professional->display_name }} • {{ $appointment->services->pluck('service.name')->implode(', ') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <p class="font-bold text-pink-600">{{ number_format($appointment->total, 0, ',', '.') }} Kz</p>
                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-{{ $appointment->status_color }}-100 text-{{ $appointment->status_color }}-700">
                                {{ $appointment->status_label }}
                            </span>
                            @if($appointment->status === 'in_progress')
                                <button wire:click="quickComplete({{ $appointment->id }})" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-check mr-1"></i>Concluir
                                </button>
                            @elseif($appointment->status === 'confirmed' || $appointment->status === 'arrived')
                                <button wire:click="quickStart({{ $appointment->id }})" class="px-3 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-play mr-1"></i>Iniciar
                                </button>
                            @elseif($appointment->status === 'scheduled')
                                <button wire:click="quickConfirm({{ $appointment->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-check mr-1"></i>Confirmar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum agendamento para este dia</h3>
                    <p class="text-gray-500">Selecione outra data ou crie um novo agendamento</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

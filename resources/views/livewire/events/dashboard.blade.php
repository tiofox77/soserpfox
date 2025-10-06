<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">📅 Dashboard - Gestão de Eventos</h1>
        <p class="text-gray-600 mt-1">Visão geral dos eventos e equipamentos</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Eventos deste Mês -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Eventos Este Mês</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['events_month'] }}</p>
                </div>
                <div class="bg-white/20 rounded-full p-4">
                    <i class="fas fa-calendar-alt text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Eventos Confirmados -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Confirmados</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['confirmed_events'] }}</p>
                </div>
                <div class="bg-white/20 rounded-full p-4">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Em Progresso -->
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm">Em Progresso</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="bg-white/20 rounded-full p-4">
                    <i class="fas fa-cog fa-spin text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Equipamentos em Uso -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">Equipamentos em Uso</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['equipment_in_use'] }}</p>
                </div>
                <div class="bg-white/20 rounded-full p-4">
                    <i class="fas fa-tools text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Próximos Eventos -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                Próximos Eventos
            </h2>
            <a href="{{ route('events.events') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        @if($upcomingEvents->isEmpty())
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-calendar-times text-5xl mb-4"></i>
                <p class="text-lg">Nenhum evento próximo</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($upcomingEvents as $event)
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-bold text-gray-900">{{ $event->name }}</h3>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $event->status_color }}-100 text-{{ $event->status_color }}-800">
                                    {{ $event->status_label }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar text-blue-500 w-5"></i>
                                    <span>{{ $event->start_date->format('d/m/Y H:i') }}</span>
                                </div>
                                @if($event->client)
                                <div class="flex items-center">
                                    <i class="fas fa-user text-green-500 w-5"></i>
                                    <span>{{ $event->client->name }}</span>
                                </div>
                                @endif
                                @if($event->venue)
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-red-500 w-5"></i>
                                    <span>{{ $event->venue->name }}</span>
                                </div>
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('events.events') }}" class="ml-4 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

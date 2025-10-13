<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Meus Eventos</h2>
                    <p class="text-indigo-100 text-sm">Acompanhe o status e detalhes dos seus eventos</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/50 icon-float">
                    <i class="fas fa-calendar-alt text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-indigo-600 font-semibold mb-2">Total de Eventos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['total'] }}</p>
            <p class="text-xs text-gray-500">Eventos cadastrados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Confirmados</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['confirmados'] }}</p>
            <p class="text-xs text-gray-500">Prontos para execução</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-play-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Em Andamento</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['em_andamento'] }}</p>
            <p class="text-xs text-gray-500">Acontecendo agora</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-slate-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/50 icon-float">
                    <i class="fas fa-check-double text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Concluídos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['concluidos'] }}</p>
            <p class="text-xs text-gray-500">Finalizados</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filtros Avançados
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Número ou nome do evento..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-tag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
                    <option value="">Todos os status</option>
                    <option value="orcamento">📋 Orçamento</option>
                    <option value="confirmado">✅ Confirmado</option>
                    <option value="em_montagem">🔨 Em Montagem</option>
                    <option value="em_andamento">▶️ Em Andamento</option>
                    <option value="concluido">✔️ Concluído</option>
                    <option value="cancelado">❌ Cancelado</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Eventos --}}
    <div class="space-y-4">
        @forelse($events as $event)
            <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 card-hover border border-gray-100">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        {{-- Info Principal --}}
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-sm font-mono text-gray-500">{{ $event->event_number }}</span>
                                
                                {{-- Status Badge --}}
                                <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                    @if($event->status === 'confirmado') bg-blue-100 text-blue-800
                                    @elseif($event->status === 'em_andamento') bg-green-100 text-green-800
                                    @elseif($event->status === 'concluido') bg-gray-100 text-gray-800
                                    @elseif($event->status === 'cancelado') bg-red-100 text-red-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ $event->status_label }}
                                </span>

                                {{-- Phase Badge --}}
                                @if($event->phase)
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <i class="{{ $event->phase_icon }} mr-1"></i>{{ $event->phase_label }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $event->name }}</h3>

                            {{-- Detalhes --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar text-blue-500 w-5 mr-2"></i>
                                    <span>{{ $event->start_date->format('d/m/Y H:i') }}</span>
                                    @if($event->end_date)
                                        <span class="mx-1">→</span>
                                        <span>{{ $event->end_date->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>

                                @if($event->venue)
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt text-red-500 w-5 mr-2"></i>
                                        <span>{{ $event->venue->name }}</span>
                                    </div>
                                @endif

                                @if($event->type)
                                    <div class="flex items-center">
                                        <i class="fas fa-tag text-purple-500 w-5 mr-2"></i>
                                        <span>{{ $event->type->name }}</span>
                                    </div>
                                @endif

                                @if($event->expected_attendees)
                                    <div class="flex items-center">
                                        <i class="fas fa-users text-green-500 w-5 mr-2"></i>
                                        <span>{{ $event->expected_attendees }} participantes</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Progress Bar --}}
                            @if($event->checklist_progress > 0)
                                <div class="mt-4">
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                        <span>Progresso do Checklist</span>
                                        <span class="font-semibold">{{ $event->checklist_progress }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all" style="width: {{ $event->checklist_progress }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Valor --}}
                        @if($event->total_value)
                            <div class="mt-4 md:mt-0 md:ml-6 text-right">
                                <p class="text-sm text-gray-500">Valor Total</p>
                                <p class="text-2xl font-bold text-indigo-600">{{ number_format($event->total_value, 2, ',', '.') }} Kz</p>
                            </div>
                        @endif
                    </div>

                    {{-- Descrição --}}
                    @if($event->description)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-600">{{ Str::limit($event->description, 200) }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-calendar-times text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">Nenhum evento encontrado</p>
                <p class="text-gray-400 text-sm">Entre em contato conosco para agendar seu próximo evento</p>
            </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    @if($events->hasPages())
        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @endif
</div>

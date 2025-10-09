<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-calendar-alt text-purple-600 mr-3"></i>
                Calendário de Eventos
            </h2>
            <p class="text-gray-600">Visualização interativa e gestão de fases</p>
        </div>
        <div class="flex items-center space-x-3">
            {{-- Abas de Visualização --}}
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button wire:click="switchView('calendar')" 
                        class="px-4 py-2 rounded-md font-semibold transition-all duration-300 flex items-center {{ $viewMode === 'calendar' ? 'bg-white text-purple-600 shadow-md' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Calendário
                </button>
                <button wire:click="switchView('list')" 
                        class="px-4 py-2 rounded-md font-semibold transition-all duration-300 flex items-center {{ $viewMode === 'list' ? 'bg-white text-purple-600 shadow-md' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="fas fa-list mr-2"></i>
                    Lista
                </button>
            </div>
            
            <button wire:click="openQuickCreate" 
                    class="group bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
                <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                Criar Evento
                <i class="fas fa-arrow-right ml-2 opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 hover:shadow-2xl transition duration-300 cursor-pointer group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-sm font-medium flex items-center">
                        <i class="fas fa-chart-line mr-2 group-hover:animate-pulse"></i>
                        Total de Eventos
                    </p>
                    <p class="text-3xl font-bold mt-1 group-hover:scale-110 transition-transform">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full group-hover:rotate-12 transition-transform">
                    <i class="fas fa-calendar-check text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 hover:shadow-2xl transition duration-300 cursor-pointer group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-sm font-medium flex items-center">
                        <i class="fas fa-file-alt mr-2 group-hover:animate-pulse"></i>
                        Orçamentos
                    </p>
                    <p class="text-3xl font-bold mt-1 group-hover:scale-110 transition-transform">{{ $stats['orcamento'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full group-hover:rotate-12 transition-transform">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 hover:shadow-2xl transition duration-300 cursor-pointer group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-sm font-medium flex items-center">
                        <i class="fas fa-check mr-2 group-hover:animate-pulse"></i>
                        Confirmados
                    </p>
                    <p class="text-3xl font-bold mt-1 group-hover:scale-110 transition-transform">{{ $stats['confirmados'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full group-hover:rotate-12 transition-transform">
                    <i class="fas fa-check-double text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 hover:shadow-2xl transition duration-300 cursor-pointer group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-sm font-medium flex items-center">
                        <i class="fas fa-play mr-2 group-hover:animate-pulse"></i>
                        Em Andamento
                    </p>
                    <p class="text-3xl font-bold mt-1 group-hover:scale-110 transition-transform">{{ $stats['em_andamento'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full group-hover:rotate-12 transition-transform">
                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 hover:shadow-2xl transition duration-300 cursor-pointer group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-sm font-medium flex items-center">
                        <i class="fas fa-award mr-2 group-hover:animate-pulse"></i>
                        Concluídos (mês)
                    </p>
                    <p class="text-3xl font-bold mt-1 group-hover:scale-110 transition-transform">{{ $stats['concluidos_mes'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full group-hover:rotate-12 transition-transform">
                    <i class="fas fa-trophy text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 border-l-4 border-purple-600">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-filter text-blue-600 mr-2"></i>
                    Filtrar por Status
                </label>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center bg-blue-50 px-3 py-1.5 rounded-full hover:bg-blue-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="statusFilter" value="orcamento" class="rounded text-blue-600">
                        <i class="fas fa-file-alt ml-2 mr-1 text-blue-600 text-xs"></i>
                        <span class="text-sm">Orçamento</span>
                    </label>
                    <label class="inline-flex items-center bg-green-50 px-3 py-1.5 rounded-full hover:bg-green-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="statusFilter" value="confirmado" class="rounded text-green-600">
                        <i class="fas fa-check ml-2 mr-1 text-green-600 text-xs"></i>
                        <span class="text-sm">Confirmado</span>
                    </label>
                    <label class="inline-flex items-center bg-orange-50 px-3 py-1.5 rounded-full hover:bg-orange-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="statusFilter" value="em_andamento" class="rounded text-orange-600">
                        <i class="fas fa-play ml-2 mr-1 text-orange-600 text-xs"></i>
                        <span class="text-sm">Em Andamento</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-tasks text-purple-600 mr-2"></i>
                    Filtrar por Fase
                </label>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center bg-indigo-50 px-3 py-1.5 rounded-full hover:bg-indigo-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="phaseFilter" value="planejamento" class="rounded text-indigo-600">
                        <i class="fas fa-clipboard-list ml-2 mr-1 text-indigo-600 text-xs"></i>
                        <span class="text-sm">Planejamento</span>
                    </label>
                    <label class="inline-flex items-center bg-yellow-50 px-3 py-1.5 rounded-full hover:bg-yellow-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="phaseFilter" value="montagem" class="rounded text-yellow-600">
                        <i class="fas fa-hammer ml-2 mr-1 text-yellow-600 text-xs"></i>
                        <span class="text-sm">Montagem</span>
                    </label>
                    <label class="inline-flex items-center bg-green-50 px-3 py-1.5 rounded-full hover:bg-green-100 cursor-pointer transition">
                        <input type="checkbox" wire:model.live="phaseFilter" value="operacao" class="rounded text-green-600">
                        <i class="fas fa-play-circle ml-2 mr-1 text-green-600 text-xs"></i>
                        <span class="text-sm">Operação</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                    Legendas
                </label>
                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="inline-flex items-center bg-gray-100 px-2 py-1 rounded-full font-semibold">Status:</span>
                        <span class="inline-flex items-center bg-blue-100 px-2 py-1 rounded-full">
                            📄 Orçamento
                        </span>
                        <span class="inline-flex items-center bg-green-100 px-2 py-1 rounded-full">
                            ✅ Confirmado
                        </span>
                        <span class="inline-flex items-center bg-yellow-100 px-2 py-1 rounded-full">
                            🔨 Em Montagem
                        </span>
                        <span class="inline-flex items-center bg-orange-100 px-2 py-1 rounded-full">
                            ▶️ Em Andamento
                        </span>
                        <span class="inline-flex items-center bg-purple-100 px-2 py-1 rounded-full">
                            🏆 Concluído
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="inline-flex items-center bg-gray-100 px-2 py-1 rounded-full font-semibold">Fases:</span>
                        <span class="inline-flex items-center bg-indigo-100 px-2 py-1 rounded-full">
                            <i class="fas fa-clipboard-list mr-1 text-indigo-700"></i>
                            Planejamento
                        </span>
                        <span class="inline-flex items-center bg-blue-100 px-2 py-1 rounded-full">
                            <i class="fas fa-tasks mr-1 text-blue-700"></i>
                            Pré-Produção
                        </span>
                        <span class="inline-flex items-center bg-yellow-100 px-2 py-1 rounded-full">
                            <i class="fas fa-hammer mr-1 text-yellow-700"></i>
                            Montagem
                        </span>
                        <span class="inline-flex items-center bg-green-100 px-2 py-1 rounded-full">
                            <i class="fas fa-play-circle mr-1 text-green-700"></i>
                            Operação
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visualização Dinâmica --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden" 
         x-data="{ currentView: @entangle('viewMode') }"
         x-effect="if (currentView === 'calendar') { 
             setTimeout(() => { 
                 window.dispatchEvent(new CustomEvent('render-calendar'));
                 console.log('🔄 Alpine detectou mudança para calendário');
             }, 200);
         }">
        {{-- Calendário --}}
        <div x-show="currentView === 'calendar'" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Calendário de Eventos
                </h3>
            </div>
            <div class="p-6">
                <div id="calendar" wire:ignore></div>
            </div>
        </div>
        
        {{-- Lista de Eventos --}}
        <div x-show="currentView === 'list'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <i class="fas fa-list mr-2"></i>
                    Lista de Eventos ({{ $eventsList->count() }})
                </h3>
            </div>
            <div class="p-6">
                @if($eventsList->count() > 0)
                    <div class="space-y-4">
                        @foreach($eventsList as $event)
                            <div wire:click="viewEvent({{ $event->id }})" 
                                 class="group border-2 border-gray-200 rounded-xl p-5 hover:border-purple-400 hover:shadow-lg transition-all duration-300 cursor-pointer bg-gradient-to-r hover:from-purple-50 hover:to-indigo-50">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="text-2xl">
                                                @if($event->status === 'orcamento') 📄
                                                @elseif($event->status === 'confirmado') ✅
                                                @elseif($event->status === 'em_montagem') 🔨
                                                @elseif($event->status === 'em_andamento') ▶️
                                                @elseif($event->status === 'concluido') 🏆
                                                @else 📌
                                                @endif
                                            </span>
                                            <div>
                                                <h4 class="text-lg font-bold text-gray-900 group-hover:text-purple-700 transition">
                                                    {{ $event->name }}
                                                </h4>
                                                <p class="text-sm text-gray-500">{{ $event->event_number }}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-calendar text-purple-600 mr-2"></i>
                                                {{ $event->start_date->format('d/m/Y H:i') }}
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-user text-indigo-600 mr-2"></i>
                                                {{ $event->client?->name ?? 'Sem cliente' }}
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                                {{ $event->venue?->name ?? 'Sem local' }}
                                            </div>
                                            <div class="flex items-center text-sm">
                                                <i class="{{ $event->phase_icon }} text-green-600 mr-2"></i>
                                                <span class="font-semibold">{{ $event->phase_label }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 flex items-center gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs font-semibold text-gray-600">Progresso</span>
                                                    <span class="text-xs font-bold text-purple-600">{{ $event->checklist_progress }}%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 h-2 rounded-full transition-all duration-300" 
                                                         style="width: {{ $event->checklist_progress }}%"></div>
                                                </div>
                                            </div>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white" 
                                                  style="background-color: {{ $event->calendar_color }}">
                                                {{ $event->status_label }}
                                            </span>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-purple-600 group-hover:translate-x-1 transition-all ml-4"></i>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                        <p class="text-gray-500 text-lg">Nenhum evento encontrado</p>
                        <p class="text-gray-400 text-sm mt-2">Ajuste os filtros ou crie um novo evento</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal de Visualização do Evento --}}
    @if($showEventModal && $selectedEvent)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4 animate-fade-in-up" 
         style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto animate-scale-in">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">{{ $selectedEvent->name }}</h3>
                    <p class="text-purple-100 text-sm">{{ $selectedEvent->event_number }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="editEvent({{ $selectedEvent->id }})" 
                            class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg font-semibold transition">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                        <p class="text-sm text-blue-700 font-semibold">Status</p>
                        <p class="text-xl font-bold text-blue-900">{{ $selectedEvent->status_label }}</p>
                    </div>
                    <div class="bg-purple-50 border-2 border-purple-300 rounded-lg p-4">
                        <p class="text-sm text-purple-700 font-semibold">Fase</p>
                        <p class="text-xl font-bold text-purple-900">
                            <i class="{{ $selectedEvent->phase_icon }} mr-2"></i>
                            {{ $selectedEvent->phase_label }}
                        </p>
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">Progresso do Checklist</span>
                        <span class="text-sm font-bold text-purple-600">{{ $selectedEvent->checklist_progress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 h-3 rounded-full" 
                             style="width: {{ $selectedEvent->checklist_progress }}%"></div>
                    </div>
                </div>

                @if($selectedEvent->phase !== 'concluido')
                <button wire:click="advancePhase({{ $selectedEvent->id }})"
                        class="w-full bg-gradient-to-r from-green-600 to-teal-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition mb-6
                               {{ !$selectedEvent->canAdvancePhase() ? 'opacity-50 cursor-not-allowed' : '' }}">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Avançar para Próxima Fase
                </button>
                @endif

                <div class="mb-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-3">
                        <i class="fas fa-tasks text-purple-600 mr-2"></i>
                        Checklist - {{ $selectedEvent->phase_label }}
                    </h4>
                    
                    @php
                        $checklistsByPhase = $selectedEvent->checklists->where('phase', $selectedEvent->phase);
                    @endphp

                    @if($checklistsByPhase->count() > 0)
                        <div class="space-y-2">
                            @foreach($checklistsByPhase->sortBy('order') as $item)
                            <label class="flex items-center p-3 rounded-lg border-2 cursor-pointer
                                        {{ $item->status === 'concluido' ? 'bg-green-50 border-green-300' : 'bg-white border-gray-200' }} hover:shadow-md transition">
                                <input type="checkbox" 
                                       wire:click="toggleChecklistItem({{ $item->id }})"
                                       {{ $item->status === 'concluido' ? 'checked' : '' }}
                                       class="w-5 h-5 text-green-600 rounded">
                                <span class="ml-3 flex-1 {{ $item->status === 'concluido' ? 'line-through text-gray-500' : 'text-gray-900' }}">
                                    {{ $item->task }}
                                    @if($item->is_required)
                                        <span class="text-red-500">*</span>
                                    @endif
                                </span>
                                @if($item->status === 'concluido')
                                    <i class="fas fa-check-circle text-green-600"></i>
                                @endif
                            </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">Nenhuma tarefa nesta fase</p>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm border-t pt-4">
                    <div>
                        <p class="text-gray-600 font-semibold">Cliente</p>
                        <p class="text-gray-900">{{ $selectedEvent->client?->name ?? 'Não definido' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-semibold">Local</p>
                        <p class="text-gray-900">{{ $selectedEvent->venue?->name ?? 'Não definido' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-semibold">Início</p>
                        <p class="text-gray-900">{{ $selectedEvent->start_date->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-semibold">Término</p>
                        <p class="text-gray-900">{{ $selectedEvent->end_date->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Quick Create Event (Componente Separado) --}}
    @include('livewire.events.partials.quick-create-modal')
    
    {{-- Modal Edit Event (Componente Separado) --}}
    @include('livewire.events.partials.edit-event-modal')
</div>

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<style>
    /* FullCalendar Customizado */
    .fc .fc-button {
        background: linear-gradient(135deg, #9333ea 0%, #4f46e5 100%) !important;
        border: none !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .fc .fc-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 15px -3px rgba(147, 51, 234, 0.3) !important;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #6b21a8 !important;
    }
    
    .fc-event {
        border-radius: 0.5rem !important;
        padding: 0.375rem 0.625rem !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        border: none !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15) !important;
    }
    
    .fc-event:hover {
        transform: translateY(-2px) scale(1.03) !important;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25) !important;
        filter: brightness(1.15) !important;
        z-index: 10 !important;
    }
    
    .fc-event-title {
        font-weight: 700 !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.25rem !important;
    }
    
    .fc-daygrid-day:hover {
        background-color: rgba(147, 51, 234, 0.05) !important;
    }
    
    .fc-day-today {
        background-color: rgba(147, 51, 234, 0.1) !important;
    }
    
    .fc-day-today .fc-daygrid-day-number {
        background: linear-gradient(135deg, #9333ea 0%, #4f46e5 100%);
        color: white !important;
        border-radius: 50%;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }
    
    .fc-col-header-cell {
        background: linear-gradient(135deg, rgba(147, 51, 234, 0.1) 0%, rgba(79, 70, 229, 0.1) 100%) !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        font-size: 0.75rem !important;
        color: #6b21a8 !important;
        padding: 1rem !important;
    }
    
    /* Animações */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }
    
    .animate-scale-in {
        animation: scaleIn 0.3s ease-out;
    }
</style>

<script>
document.addEventListener('livewire:initialized', () => {
    let calendar;
    
    function initCalendar() {
        const calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'pt-br',
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia'
            },
            height: 'auto',
            editable: true,
            events: @json($events),
            eventDidMount: function(info) {
                // Adicionar tooltip com mais informações
                const props = info.event.extendedProps;
                info.el.title = `${info.event.title}\n` +
                               `📋 ${props.event_number}\n` +
                               `👤 ${props.client_name}\n` +
                               `📍 ${props.venue_name}\n` +
                               `📊 Progresso: ${props.progress}%\n` +
                               `🔖 Fase: ${props.phase_label}`;
                
                // Adicionar badge de progresso se tiver
                if (props.progress > 0) {
                    const progressBadge = document.createElement('span');
                    progressBadge.style.cssText = 'background: rgba(255,255,255,0.3); padding: 1px 4px; border-radius: 3px; font-size: 10px; margin-left: 4px;';
                    progressBadge.textContent = props.progress + '%';
                    info.el.querySelector('.fc-event-title').appendChild(progressBadge);
                }
            },
            eventClick: function(info) {
                @this.viewEvent(info.event.id);
            },
            eventDrop: function(info) {
                @this.updateEventDate(
                    info.event.id,
                    info.event.start.toISOString(),
                    info.event.end ? info.event.end.toISOString() : info.event.start.toISOString()
                );
            },
            dateClick: function(info) {
                @this.openQuickCreate(info.dateStr);
            }
        });

        calendar.render();
    }

    setTimeout(() => {
        initCalendar();
    }, 100);

    // Listener para refresh de eventos
    Livewire.on('refreshCalendar', () => {
        if (calendar) {
            calendar.refetchEvents();
        }
    });
    
    // Listener para evento customizado do Alpine (disparado pelo x-effect)
    window.addEventListener('render-calendar', () => {
        setTimeout(() => {
            const calendarEl = document.getElementById('calendar');
            if (calendarEl && calendarEl.offsetParent !== null) {
                if (calendar) {
                    calendar.render();
                    console.log('📅 Calendário re-renderizado!');
                } else {
                    initCalendar();
                    console.log('📅 Calendário inicializado!');
                }
            }
        }, 100);
    });
});
</script>
@endpush

<?php

namespace App\Livewire\Events;

use App\Models\Events\Event;
use App\Models\Events\EventType;
use App\Models\Client;
use App\Models\Events\Venue;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Calendário de Eventos')]
class EventCalendar extends Component
{
    public $selectedEvent = null;
    public $showEventModal = false;
    public $showQuickCreateModal = false;
    public $showEditModal = false;
    
    // Mini Modals
    public $showQuickClientModal = false;
    public $showQuickVenueModal = false;
    public $showQuickEventTypeModal = false;
    
    // Edit Event
    public $editingEventId = null;
    public $editName = '';
    public $editStartDate = '';
    public $editEndDate = '';
    public $editClientId = null;
    public $editVenueId = null;
    public $editType = '';
    public $editAttendees = null;
    public $editDescription = '';
    public $editStatus = 'orcamento';
    
    // View mode
    public $viewMode = 'calendar'; // 'calendar' or 'list'
    
    // Filtros
    public $statusFilter = [];
    public $phaseFilter = [];
    public $typeFilter = [];
    
    // Quick Create Event
    public $quickName = '';
    public $quickStartDate = '';
    public $quickEndDate = '';
    public $quickClientId = null;
    public $quickVenueId = null;
    public $quickType = '';
    public $quickAttendees = null;
    public $quickDescription = '';
    
    // Quick Create Client
    public $newClientName = '';
    public $newClientNif = '';
    public $newClientCountry = 'Angola';
    public $newClientEmail = '';
    public $newClientPhone = '';
    
    // Quick Create Venue
    public $newVenueName = '';
    public $newVenueAddress = '';
    public $newVenueCapacity = null;
    
    // Quick Create Event Type
    public $newEventTypeName = '';
    public $newEventTypeIcon = '📅';
    public $newEventTypeColor = '#8b5cf6';

    public function mount()
    {
        // Inicializar filtros com todos marcados
        $this->statusFilter = ['orcamento', 'confirmado', 'em_montagem', 'em_andamento'];
    }

    public function render()
    {
        $events = $this->getEventsForCalendar();
        $eventsList = $this->getEventsList();
        $clients = Client::where('tenant_id', activeTenantId())->where('is_active', true)->get();
        $venues = Venue::where('tenant_id', activeTenantId())->where('is_active', true)->get();
        $eventTypes = EventType::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('order')->get();
        
        // Estatísticas
        $stats = $this->getStatistics();
        
        return view('livewire.events.event-calendar', compact('events', 'eventsList', 'clients', 'venues', 'eventTypes', 'stats'));
    }
    
    /**
     * Retorna eventos para lista
     */
    public function getEventsList()
    {
        $query = Event::where('tenant_id', activeTenantId())
            ->with(['client', 'venue', 'responsible']);

        // Aplicar filtros
        if (!empty($this->statusFilter)) {
            $query->whereIn('status', $this->statusFilter);
        }

        if (!empty($this->phaseFilter)) {
            $query->whereIn('phase', $this->phaseFilter);
        }

        if (!empty($this->typeFilter)) {
            $query->whereIn('type_id', $this->typeFilter);
        }

        return $query->orderBy('start_date', 'asc')->get();
    }
    
    /**
     * Alterna entre calendário e lista
     */
    public function switchView($mode)
    {
        $this->viewMode = $mode;
        // O Alpine.js x-effect cuidará da renderização
    }

    /**
     * Retorna eventos formatados para FullCalendar
     */
    public function getEventsForCalendar()
    {
        $query = Event::where('tenant_id', activeTenantId())
            ->with(['client', 'venue', 'responsible']);

        // Aplicar filtros
        if (!empty($this->statusFilter)) {
            $query->whereIn('status', $this->statusFilter);
        }

        if (!empty($this->phaseFilter)) {
            $query->whereIn('phase', $this->phaseFilter);
        }

        if (!empty($this->typeFilter)) {
            $query->whereIn('type_id', $this->typeFilter);
        }

        return $query->get()->map(function ($event) {
            // Ícones baseados no status
            $statusIcons = [
                'orcamento' => '📄',
                'confirmado' => '✅',
                'em_montagem' => '🔨',
                'em_andamento' => '▶️',
                'concluido' => '🏆',
                'cancelado' => '❌',
            ];
            
            $statusIcon = $statusIcons[$event->status] ?? '📌';
            
            // Verificar se o evento começa e termina no mesmo dia
            $isSameDay = $event->start_date->isSameDay($event->end_date);
            
            return [
                'id' => $event->id,
                'title' => $statusIcon . ' ' . $event->name,
                'start' => $event->start_date->format('Y-m-d\TH:i:s'),
                'end' => $event->end_date->format('Y-m-d\TH:i:s'),
                'allDay' => false,
                'backgroundColor' => $event->calendar_color,
                'borderColor' => $event->calendar_color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'event_number' => $event->event_number,
                    'status' => $event->status,
                    'status_label' => $event->status_label,
                    'status_icon' => $statusIcon,
                    'phase' => $event->phase,
                    'phase_label' => $event->phase_label,
                    'phase_icon' => $event->phase_icon,
                    'client_name' => $event->client?->name ?? 'Sem cliente',
                    'venue_name' => $event->venue?->name ?? 'Sem local',
                    'progress' => $event->checklist_progress,
                    'type' => $event->type?->name ?? 'N/A',
                ],
            ];
        })->toArray();
    }

    /**
     * Estatísticas para dashboard
     */
    private function getStatistics()
    {
        $baseQuery = Event::where('tenant_id', activeTenantId());
        
        return [
            'total' => $baseQuery->count(),
            'orcamento' => (clone $baseQuery)->where('status', 'orcamento')->count(),
            'confirmados' => (clone $baseQuery)->where('status', 'confirmado')->count(),
            'em_andamento' => (clone $baseQuery)->where('status', 'em_andamento')->count(),
            'concluidos_mes' => (clone $baseQuery)
                ->where('status', 'concluido')
                ->whereMonth('end_date', now()->month)
                ->count(),
            
            // Por fase
            'planejamento' => (clone $baseQuery)->where('phase', 'planejamento')->count(),
            'pre_producao' => (clone $baseQuery)->where('phase', 'pre_producao')->count(),
            'montagem' => (clone $baseQuery)->where('phase', 'montagem')->count(),
            'operacao' => (clone $baseQuery)->where('phase', 'operacao')->count(),
            'desmontagem' => (clone $baseQuery)->where('phase', 'desmontagem')->count(),
        ];
    }

    /**
     * Atualiza data do evento (drag and drop)
     */
    public function updateEventDate($eventId, $newStart, $newEnd)
    {
        $event = Event::where('tenant_id', activeTenantId())->findOrFail($eventId);
        
        $event->update([
            'start_date' => $newStart,
            'end_date' => $newEnd,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Data do evento atualizada!'
        ]);

        $this->dispatch('refreshCalendar');
    }

    /**
     * Abre modal de visualização do evento
     */
    public function viewEvent($eventId)
    {
        $this->selectedEvent = Event::with(['client', 'venue', 'responsible', 'checklists', 'equipment', 'staff'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($eventId);
        
        $this->showEventModal = true;
    }

    /**
     * Fecha modal
     */
    public function closeModal()
    {
        $this->showEventModal = false;
        $this->showQuickCreateModal = false;
        $this->showEditModal = false;
        $this->selectedEvent = null;
        $this->reset(['quickName', 'quickStartDate', 'quickEndDate', 'quickClientId', 'quickVenueId', 'quickType', 'quickAttendees', 'quickDescription']);
        $this->reset(['editingEventId', 'editName', 'editStartDate', 'editEndDate', 'editClientId', 'editVenueId', 'editType', 'editAttendees', 'editDescription', 'editStatus']);
    }
    
    /**
     * Abrir modal de edição de evento
     */
    public function editEvent($eventId)
    {
        $event = Event::where('tenant_id', activeTenantId())->findOrFail($eventId);
        
        $this->editingEventId = $event->id;
        $this->editName = $event->name;
        $this->editStartDate = $event->start_date->format('Y-m-d\TH:i');
        $this->editEndDate = $event->end_date->format('Y-m-d\TH:i');
        $this->editClientId = $event->client_id;
        $this->editVenueId = $event->venue_id;
        $this->editType = $event->type_id ?? $event->type;
        $this->editAttendees = $event->expected_attendees;
        $this->editDescription = $event->description;
        $this->editStatus = $event->status;
        
        $this->showEditModal = true;
    }
    
    /**
     * Atualizar evento
     */
    public function updateEvent()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editStartDate' => 'required|date',
            'editEndDate' => 'required|date|after:editStartDate',
            'editType' => 'required',
            'editAttendees' => 'nullable|integer|min:1',
            'editStatus' => 'required|string',
        ], [
            'editName.required' => 'O nome do evento é obrigatório',
            'editStartDate.required' => 'A data de início é obrigatória',
            'editEndDate.required' => 'A data de fim é obrigatória',
            'editEndDate.after' => 'A data de fim deve ser após a data de início',
            'editType.required' => 'Selecione o tipo de evento',
            'editStatus.required' => 'Selecione o status',
        ]);
        
        $event = Event::where('tenant_id', activeTenantId())->findOrFail($this->editingEventId);
        
        $event->update([
            'name' => $this->editName,
            'start_date' => $this->editStartDate,
            'end_date' => $this->editEndDate,
            'client_id' => $this->editClientId,
            'venue_id' => $this->editVenueId,
            'type_id' => $this->editType,
            'expected_attendees' => $this->editAttendees,
            'description' => $this->editDescription,
            'status' => $this->editStatus,
        ]);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Evento atualizado com sucesso!'
        ]);
        
        $this->closeModal();
        $this->dispatch('refreshCalendar');
    }

    /**
     * Quick create - criar evento rapidamente
     */
    public function openQuickCreate($startDate = null)
    {
        // Formato 24h (H:i) ao invés de 12h (h:i A)
        $this->quickStartDate = $startDate ?? now()->format('Y-m-d\TH:i');
        $this->quickEndDate = $startDate ? date('Y-m-d\TH:i', strtotime($startDate . ' +2 hours')) : now()->addHours(2)->format('Y-m-d\TH:i');
        $this->showQuickCreateModal = true;
    }

    /**
     * Salvar quick create
     */
    public function saveQuickEvent()
    {
        $this->validate([
            'quickName' => 'required|string|max:255',
            'quickStartDate' => 'required|date',
            'quickEndDate' => 'required|date|after:quickStartDate',
            'quickType' => 'required|string',
            'quickAttendees' => 'nullable|integer|min:1',
        ], [
            'quickName.required' => 'O nome do evento é obrigatório',
            'quickStartDate.required' => 'A data de início é obrigatória',
            'quickEndDate.required' => 'A data de fim é obrigatória',
            'quickEndDate.after' => 'A data de fim deve ser após a data de início',
            'quickType.required' => 'Selecione o tipo de evento',
            'quickAttendees.min' => 'O número de participantes deve ser no mínimo 1',
        ]);

        $event = Event::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->quickName,
            'start_date' => $this->quickStartDate,
            'end_date' => $this->quickEndDate,
            'client_id' => $this->quickClientId,
            'venue_id' => $this->quickVenueId,
            'type_id' => $this->quickType,
            'description' => $this->quickDescription,
            'expected_attendees' => $this->quickAttendees,
            'status' => 'orcamento',
            'phase' => 'planejamento',
            'responsible_user_id' => auth()->id(),
        ]);

        // Criar checklist inicial
        $event->createDefaultChecklistForPhase('planejamento');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Evento criado! Número: ' . $event->event_number
        ]);

        $this->closeModal();
        $this->dispatch('refreshCalendar');
    }

    /**
     * Avançar fase do evento
     */
    public function advancePhase($eventId)
    {
        $event = Event::where('tenant_id', activeTenantId())->findOrFail($eventId);
        
        if (!$event->canAdvancePhase()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Complete todas as tarefas obrigatórias antes de avançar!'
            ]);
            return;
        }

        if ($event->advanceToNextPhase()) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fase avançada para: ' . $event->phase_label
            ]);
            
            $this->selectedEvent = $event->fresh(['client', 'venue', 'responsible', 'checklists', 'equipment', 'staff']);
            $this->dispatch('refreshCalendar');
        } else {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Evento já está na última fase!'
            ]);
        }
    }

    /**
     * Marca tarefa como concluída
     */
    public function toggleChecklistItem($checklistId)
    {
        $checklist = \App\Models\Events\Checklist::findOrFail($checklistId);
        
        if ($checklist->status === 'concluido') {
            $checklist->status = 'pendente';
            $checklist->completed_at = null;
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => '⬜ Tarefa desmarcada: ' . $checklist->task
            ]);
        } else {
            $checklist->markAsCompleted();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Tarefa concluída: ' . $checklist->task
            ]);
        }
        
        $checklist->save();
        
        // Recarregar evento
        if ($this->selectedEvent) {
            $this->selectedEvent = $this->selectedEvent->fresh(['checklists']);
        }
    }

    /**
     * Atualizar filtros
     */
    public function updatedStatusFilter()
    {
        $this->dispatch('refreshCalendar');
    }

    public function updatedPhaseFilter()
    {
        $this->dispatch('refreshCalendar');
    }

    public function updatedTypeFilter()
    {
        $this->dispatch('refreshCalendar');
    }
    
    /**
     * Abrir modal de criar cliente rápido
     */
    public function openQuickClientModal()
    {
        $this->reset(['newClientName', 'newClientNif', 'newClientEmail', 'newClientPhone']);
        $this->newClientCountry = 'Angola'; // Default
        $this->showQuickClientModal = true;
    }
    
    /**
     * Fechar modal de criar cliente
     */
    public function closeQuickClientModal()
    {
        $this->showQuickClientModal = false;
        $this->reset(['newClientName', 'newClientNif', 'newClientCountry', 'newClientEmail', 'newClientPhone']);
    }
    
    /**
     * Salvar cliente rápido
     */
    public function saveQuickClient()
    {
        $this->validate([
            'newClientName' => 'required|string|max:255',
            'newClientNif' => 'required|string|max:20|unique:invoicing_clients,nif',
            'newClientCountry' => 'required|string|max:100',
            'newClientEmail' => 'nullable|email|max:255',
            'newClientPhone' => 'nullable|string|max:20',
        ], [
            'newClientName.required' => 'O nome do cliente é obrigatório',
            'newClientNif.required' => 'O NIF é obrigatório (SAFT-AO)',
            'newClientNif.unique' => 'Este NIF já está registrado',
            'newClientCountry.required' => 'O país é obrigatório',
            'newClientEmail.email' => 'Email inválido',
        ]);
        
        $client = Client::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->newClientName,
            'nif' => $this->newClientNif,
            'country' => $this->newClientCountry,
            'email' => $this->newClientEmail,
            'phone' => $this->newClientPhone,
            'type' => 'pessoa_fisica',
            'is_active' => true,
        ]);
        
        $this->quickClientId = $client->id;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Cliente criado: ' . $client->name . ' (NIF: ' . $client->nif . ')'
        ]);
        
        $this->closeQuickClientModal();
    }
    
    /**
     * Abrir modal de criar local rápido
     */
    public function openQuickVenueModal()
    {
        $this->reset(['newVenueName', 'newVenueAddress', 'newVenueCapacity']);
        $this->showQuickVenueModal = true;
    }
    
    /**
     * Fechar modal de criar local
     */
    public function closeQuickVenueModal()
    {
        $this->showQuickVenueModal = false;
        $this->reset(['newVenueName', 'newVenueAddress', 'newVenueCapacity']);
    }
    
    /**
     * Salvar local rápido
     */
    public function saveQuickVenue()
    {
        $this->validate([
            'newVenueName' => 'required|string|max:255',
            'newVenueAddress' => 'nullable|string|max:500',
            'newVenueCapacity' => 'nullable|integer|min:1',
        ], [
            'newVenueName.required' => 'O nome do local é obrigatório',
        ]);
        
        $venue = Venue::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->newVenueName,
            'address' => $this->newVenueAddress,
            'capacity' => $this->newVenueCapacity,
            'is_active' => true,
        ]);
        
        $this->quickVenueId = $venue->id;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Local criado: ' . $venue->name
        ]);
        
        $this->closeQuickVenueModal();
    }
    
    /**
     * Abrir modal de criar tipo de evento rápido
     */
    public function openQuickEventTypeModal()
    {
        $this->reset(['newEventTypeName', 'newEventTypeIcon', 'newEventTypeColor']);
        $this->newEventTypeIcon = '📅';
        $this->newEventTypeColor = '#8b5cf6';
        $this->showQuickEventTypeModal = true;
    }
    
    /**
     * Fechar modal de criar tipo de evento
     */
    public function closeQuickEventTypeModal()
    {
        $this->showQuickEventTypeModal = false;
        $this->reset(['newEventTypeName', 'newEventTypeIcon', 'newEventTypeColor']);
    }
    
    /**
     * Salvar tipo de evento rápido
     */
    public function saveQuickEventType()
    {
        $this->validate([
            'newEventTypeName' => 'required|string|max:100',
            'newEventTypeIcon' => 'required|string|max:10',
            'newEventTypeColor' => 'required|string|max:7',
        ], [
            'newEventTypeName.required' => 'O nome do tipo de evento é obrigatório',
            'newEventTypeIcon.required' => 'Selecione um ícone',
            'newEventTypeColor.required' => 'Selecione uma cor',
        ]);
        
        $eventType = EventType::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->newEventTypeName,
            'icon' => $this->newEventTypeIcon,
            'color' => $this->newEventTypeColor,
            'order' => EventType::where('tenant_id', activeTenantId())->max('order') + 1,
            'is_active' => true,
        ]);
        
        $this->quickType = $eventType->id;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Tipo criado: ' . $eventType->icon . ' ' . $eventType->name
        ]);
        
        $this->closeQuickEventTypeModal();
    }
}

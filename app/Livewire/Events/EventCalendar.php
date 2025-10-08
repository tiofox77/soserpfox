<?php

namespace App\Livewire\Events;

use App\Models\Events\Event;
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
    
    // View mode
    public $viewMode = 'calendar'; // 'calendar' or 'list'
    
    // Filtros
    public $statusFilter = [];
    public $phaseFilter = [];
    public $typeFilter = [];
    
    // Quick Create
    public $quickName = '';
    public $quickStartDate = '';
    public $quickEndDate = '';
    public $quickClientId = null;
    public $quickVenueId = null;
    public $quickType = '';
    public $quickAttendees = null;
    public $quickDescription = '';

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
        
        // Estatísticas
        $stats = $this->getStatistics();
        
        return view('livewire.events.event-calendar', compact('events', 'eventsList', 'clients', 'venues', 'stats'));
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
            $query->whereIn('type', $this->typeFilter);
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
            $query->whereIn('type', $this->typeFilter);
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
            
            return [
                'id' => $event->id,
                'title' => $statusIcon . ' ' . $event->name,
                'start' => $event->start_date->toIso8601String(),
                'end' => $event->end_date->toIso8601String(),
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
                    'type' => $event->type,
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
        $this->selectedEvent = null;
        $this->reset(['quickName', 'quickStartDate', 'quickEndDate', 'quickClientId', 'quickVenueId', 'quickType', 'quickAttendees', 'quickDescription']);
    }

    /**
     * Quick create - criar evento rapidamente
     */
    public function openQuickCreate($startDate = null)
    {
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
        ]);

        $event = Event::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->quickName,
            'start_date' => $this->quickStartDate,
            'end_date' => $this->quickEndDate,
            'client_id' => $this->quickClientId,
            'venue_id' => $this->quickVenueId,
            'type' => $this->quickType,
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
}

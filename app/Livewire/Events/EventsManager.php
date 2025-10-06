<?php

namespace App\Livewire\Events;

use App\Models\Events\Event;
use App\Models\Client;
use App\Models\Events\Venue;
use Livewire\Component;
use Livewire\WithPagination;

class EventsManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $dateFilter = 'all';

    public $showModal = false;
    public $editMode = false;
    public $selectedEvent;

    public $event_id, $client_id, $venue_id, $name, $description, $type, $start_date, $end_date;
    public $expected_attendees, $status, $notes;

    protected $rules = [
        'name' => 'required|string|max:255',
        'client_id' => 'nullable|exists:clients,id',
        'venue_id' => 'nullable|exists:events_venues,id',
        'type' => 'required|in:corporativo,casamento,conferencia,show,streaming,outros',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required',
    ];

    public function render()
    {
        $events = Event::where('tenant_id', activeTenantId())
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                                             ->orWhere('event_number', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter != 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->with(['client', 'venue', 'responsible'])
            ->orderBy('start_date', 'desc')
            ->paginate(15);

        $clients = Client::where('tenant_id', activeTenantId())->where('is_active', true)->get();
        $venues = Venue::where('tenant_id', activeTenantId())->where('is_active', true)->get();

        return view('livewire.events.events-manager', compact('events', 'clients', 'venues'));
    }

    public function create()
    {
        $this->reset(['event_id', 'client_id', 'venue_id', 'name', 'description', 'type', 'start_date', 'end_date', 'expected_attendees', 'status', 'notes']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $event = Event::findOrFail($id);
        $this->event_id = $event->id;
        $this->client_id = $event->client_id;
        $this->venue_id = $event->venue_id;
        $this->name = $event->name;
        $this->description = $event->description;
        $this->type = $event->type;
        $this->start_date = $event->start_date->format('Y-m-d\TH:i');
        $this->end_date = $event->end_date->format('Y-m-d\TH:i');
        $this->expected_attendees = $event->expected_attendees;
        $this->status = $event->status;
        $this->notes = $event->notes;
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'client_id' => $this->client_id,
            'venue_id' => $this->venue_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'expected_attendees' => $this->expected_attendees,
            'status' => $this->status ?? 'orcamento',
            'notes' => $this->notes,
            'responsible_user_id' => auth()->id(),
        ];

        if ($this->editMode) {
            Event::find($this->event_id)->update($data);
            session()->flash('message', 'Evento atualizado com sucesso!');
        } else {
            Event::create($data);
            session()->flash('message', 'Evento criado com sucesso!');
        }

        $this->showModal = false;
        $this->reset();
    }

    public function delete($id)
    {
        Event::find($id)->delete();
        session()->flash('message', 'Evento excluído com sucesso!');
    }
}

<?php

namespace App\Livewire\Events\Venues;

use App\Models\Events\Venue;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Locais - Eventos')]
class VenuesManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editMode = false;

    public $venue_id, $name, $address, $city, $phone, $contact_person;
    public $capacity, $notes, $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'address' => 'nullable|string',
        'city' => 'nullable|string|max:100',
        'phone' => 'nullable|string|max:20',
        'contact_person' => 'nullable|string|max:100',
        'capacity' => 'nullable|integer|min:0',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        $venues = Venue::where('tenant_id', activeTenantId())
            ->withCount('events')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                                             ->orWhere('city', 'like', '%' . $this->search . '%')
                                             ->orWhere('address', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.events.venues.venues-manager', compact('venues'));
    }

    public function create()
    {
        $this->reset(['venue_id', 'name', 'address', 'city', 'phone', 'contact_person', 'capacity', 'notes']);
        $this->is_active = true;
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $venue = Venue::findOrFail($id);
        $this->venue_id = $venue->id;
        $this->name = $venue->name;
        $this->address = $venue->address;
        $this->city = $venue->city;
        $this->phone = $venue->phone;
        $this->contact_person = $venue->contact_person;
        $this->capacity = $venue->capacity;
        $this->notes = $venue->notes;
        $this->is_active = $venue->is_active;
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'contact_person' => $this->contact_person,
            'capacity' => $this->capacity,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];

        if ($this->editMode) {
            Venue::find($this->venue_id)->update($data);
            session()->flash('message', 'Local atualizado com sucesso!');
        } else {
            Venue::create($data);
            session()->flash('message', 'Local criado com sucesso!');
        }

        $this->showModal = false;
        $this->reset();
    }

    public function delete($id)
    {
        $venue = Venue::findOrFail($id);
        
        // Verificar se pode ser deletado
        if (!$venue->canBeDeleted()) {
            session()->flash('error', 'Não é possível excluir este local pois existem eventos associados!');
            return;
        }
        
        $venue->delete();
        session()->flash('message', 'Local excluído com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['venue_id', 'name', 'address', 'city', 'phone', 'contact_person', 'capacity', 'notes']);
        $this->is_active = true;
        $this->editMode = false;
    }
}

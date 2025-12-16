<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;

class RoomManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $floorFilter = '';
    public $showModal = false;
    public $editingId = null;
    public $confirmingDelete = false;
    public $deleteId = null;
    public $viewMode = 'grid';

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    // Form fields
    public $room_type_id = '';
    public $number = '';
    public $floor = '';
    public $status = 'available';
    public $notes = '';
    public $features = [];
    public $is_active = true;

    protected $rules = [
        'room_type_id' => 'required|exists:hotel_room_types,id',
        'number' => 'required|string|max:20',
        'floor' => 'nullable|string|max:10',
        'status' => 'required|in:available,occupied,maintenance,cleaning,reserved',
        'notes' => 'nullable|string',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['editingId', 'room_type_id', 'number', 'floor', 'status', 'notes', 'features', 'is_active']);
        $this->status = 'available';
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $room = Room::forTenant()->findOrFail($id);
        
        $this->editingId = $id;
        $this->room_type_id = $room->room_type_id;
        $this->number = $room->number;
        $this->floor = $room->floor;
        $this->status = $room->status;
        $this->notes = $room->notes;
        $this->features = $room->features ?? [];
        $this->is_active = $room->is_active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'room_type_id' => $this->room_type_id,
            'number' => $this->number,
            'floor' => $this->floor,
            'status' => $this->status,
            'notes' => $this->notes,
            'features' => $this->features,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Room::forTenant()->findOrFail($this->editingId)->update($data);
            $this->dispatch('success', message: 'Quarto atualizado com sucesso!');
        } else {
            // Verificar se número já existe
            if (Room::forTenant()->where('number', $this->number)->exists()) {
                $this->addError('number', 'Este número de quarto já existe.');
                return;
            }
            Room::create($data);
            $this->dispatch('success', message: 'Quarto criado com sucesso!');
        }

        $this->showModal = false;
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $room = Room::forTenant()->findOrFail($this->deleteId);
        
        if ($room->reservations()->whereIn('status', ['pending', 'confirmed', 'checked_in'])->count() > 0) {
            $this->dispatch('error', message: 'Não é possível excluir. Existem reservas ativas para este quarto.');
            $this->confirmingDelete = false;
            return;
        }

        $room->delete();
        $this->dispatch('success', message: 'Quarto excluído com sucesso!');
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function updateStatus($id, $status)
    {
        $room = Room::forTenant()->findOrFail($id);
        $room->update(['status' => $status]);
        
        $this->dispatch('success', message: 'Status atualizado com sucesso!');
    }

    public function toggleActive($id)
    {
        $room = Room::forTenant()->findOrFail($id);
        $room->update(['is_active' => !$room->is_active]);
        
        $this->dispatch('success', message: 'Status atualizado com sucesso!');
    }

    public function render()
    {
        $rooms = Room::forTenant()
            ->with(['roomType', 'currentReservation.guest'])
            ->when($this->search, function ($query) {
                $query->where('number', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('room_type_id', $this->typeFilter);
            })
            ->when($this->floorFilter, function ($query) {
                $query->where('floor', $this->floorFilter);
            })
            ->orderBy('floor')
            ->orderBy('number')
            ->paginate(20);

        $roomTypes = RoomType::forTenant()->active()->orderBy('name')->get();
        $floors = Room::forTenant()->distinct()->pluck('floor')->filter()->sort();

        return view('livewire.hotel.rooms.rooms', compact('rooms', 'roomTypes', 'floors'))
            ->layout('layouts.app');
    }
}

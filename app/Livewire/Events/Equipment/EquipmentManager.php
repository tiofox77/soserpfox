<?php

namespace App\Livewire\Events\Equipment;

use App\Models\Events\Equipment;
use Livewire\Component;
use Livewire\WithPagination;

class EquipmentManager extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = 'all';
    public $statusFilter = 'all';

    public $showModal = false;
    public $editMode = false;

    public $equipment_id, $name, $code, $category, $specifications;
    public $daily_price, $quantity, $quantity_available, $status, $notes;

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:50',
        'category' => 'required',
        'daily_price' => 'required|numeric|min:0',
        'quantity' => 'required|integer|min:1',
        'quantity_available' => 'required|integer|min:0',
        'status' => 'required',
    ];

    public function render()
    {
        $equipment = Equipment::where('tenant_id', activeTenantId())
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                                             ->orWhere('code', 'like', '%' . $this->search . '%'))
            ->when($this->categoryFilter != 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->statusFilter != 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.events.equipment.equipment-manager', compact('equipment'));
    }

    public function create()
    {
        $this->reset(['equipment_id', 'name', 'code', 'category', 'specifications', 'daily_price', 'quantity', 'quantity_available', 'status', 'notes']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $equipment = Equipment::findOrFail($id);
        $this->equipment_id = $equipment->id;
        $this->name = $equipment->name;
        $this->code = $equipment->code;
        $this->category = $equipment->category;
        $this->specifications = $equipment->specifications;
        $this->daily_price = $equipment->daily_price;
        $this->quantity = $equipment->quantity;
        $this->quantity_available = $equipment->quantity_available;
        $this->status = $equipment->status;
        $this->notes = $equipment->notes;
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category,
            'specifications' => $this->specifications,
            'daily_price' => $this->daily_price,
            'quantity' => $this->quantity,
            'quantity_available' => $this->quantity_available,
            'status' => $this->status ?? 'disponivel',
            'notes' => $this->notes,
        ];

        if ($this->editMode) {
            Equipment::find($this->equipment_id)->update($data);
            session()->flash('message', 'Equipamento atualizado com sucesso!');
        } else {
            Equipment::create($data);
            session()->flash('message', 'Equipamento criado com sucesso!');
        }

        $this->showModal = false;
        $this->reset();
    }

    public function delete($id)
    {
        Equipment::find($id)->delete();
        session()->flash('message', 'Equipamento excluído com sucesso!');
    }
}

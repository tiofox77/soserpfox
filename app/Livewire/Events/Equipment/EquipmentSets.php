<?php

namespace App\Livewire\Events\Equipment;

use App\Models\EquipmentSet;
use App\Models\EquipmentCategory;
use App\Models\Equipment;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Conjuntos de Equipamentos')]
class EquipmentSets extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editMode = false;
    public $setId = null;
    
    // Formulário
    public $name = '';
    public $description = '';
    public $category = '';
    
    // Gerenciar itens do SET
    public $showItemsModal = false;
    public $currentSetId = null;
    public $selectedEquipment = '';
    public $quantity = 1;

    public function render()
    {
        $sets = EquipmentSet::where('tenant_id', activeTenantId())
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->withCount('equipments')
            ->with(['equipments' => function($q) {
                $q->limit(5);
            }, 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $categories = EquipmentCategory::where('tenant_id', activeTenantId())->active()->ordered()->get();
        
        $availableEquipments = Equipment::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.events.equipment.equipment-sets', compact('sets', 'categories', 'availableEquipments'));
    }

    public function openModal()
    {
        $this->reset(['name', 'description', 'category', 'setId']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $set = EquipmentSet::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->setId = $set->id;
        $this->name = $set->name;
        $this->description = $set->description;
        $this->category = $set->category_id;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
        ]);

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category,
            'created_by' => auth()->id(),
        ];

        if ($this->editMode) {
            EquipmentSet::findOrFail($this->setId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'SET atualizado com sucesso!']);
        } else {
            EquipmentSet::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'SET criado com sucesso!']);
        }

        $this->closeModal();
    }

    public function openItemsModal($setId)
    {
        $this->currentSetId = $setId;
        $this->reset(['selectedEquipment', 'quantity']);
        $this->quantity = 1;
        $this->showItemsModal = true;
    }

    public function addEquipment()
    {
        $this->validate([
            'selectedEquipment' => 'required|exists:equipment,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $set = EquipmentSet::findOrFail($this->currentSetId);
        
        $set->equipments()->syncWithoutDetaching([
            $this->selectedEquipment => ['quantity' => $this->quantity]
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Equipamento adicionado ao SET!']);
        $this->reset(['selectedEquipment', 'quantity']);
        $this->quantity = 1;
    }

    public function removeEquipment($setId, $equipmentId)
    {
        $set = EquipmentSet::findOrFail($setId);
        $set->equipments()->detach($equipmentId);
        
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Equipamento removido do SET!']);
    }

    public function delete($id)
    {
        EquipmentSet::where('tenant_id', activeTenantId())->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'SET excluído!']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showItemsModal = false;
    }
}

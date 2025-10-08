<?php

namespace App\Livewire\Events\Equipment;

use App\Models\EquipmentCategory;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Categorias de Equipamentos')]
class EquipmentCategories extends Component
{
    public $showModal = false;
    public $editMode = false;
    public $categoryId = null;
    
    public $name = '';
    public $icon = '';
    public $color = '#6366f1';
    public $sort_order = 0;

    public function render()
    {
        $categories = EquipmentCategory::where('tenant_id', activeTenantId())
            ->withCount('equipments')
            ->ordered()
            ->get();

        return view('livewire.events.equipment.equipment-categories', compact('categories'));
    }

    public function openModal()
    {
        $this->reset(['name', 'icon', 'color', 'sort_order', 'categoryId']);
        $this->color = '#6366f1';
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $category = EquipmentCategory::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->icon = $category->icon;
        $this->color = $category->color;
        $this->sort_order = $category->sort_order;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'color' => 'required|string|max:7',
            'sort_order' => 'required|integer|min:0',
        ]);

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editMode) {
            EquipmentCategory::findOrFail($this->categoryId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria atualizada!']);
        } else {
            EquipmentCategory::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria criada!']);
        }

        $this->closeModal();
    }

    public function toggleActive($id)
    {
        $category = EquipmentCategory::where('tenant_id', activeTenantId())->findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);
        
        $status = $category->is_active ? 'ativada' : 'desativada';
        $this->dispatch('notify', ['type' => 'success', 'message' => "Categoria {$status}!"]);
    }

    public function delete($id)
    {
        $category = EquipmentCategory::where('tenant_id', activeTenantId())->findOrFail($id);
        
        if ($category->equipments()->count() > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Não pode excluir! Existem equipamentos nesta categoria.']);
            return;
        }
        
        $category->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria excluída!']);
    }

    public function closeModal()
    {
        $this->showModal = false;
    }
}

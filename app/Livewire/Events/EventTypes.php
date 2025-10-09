<?php

namespace App\Livewire\Events;

use App\Models\Events\EventType;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Tipos de Eventos')]
class EventTypes extends Component
{
    public $showModal = false;
    public $editingId = null;
    
    // Form fields
    public $name = '';
    public $icon = '📅';
    public $color = '#8b5cf6';
    public $description = '';
    public $order = 0;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:100',
        'icon' => 'required|string|max:10',
        'color' => 'required|string|max:7',
        'description' => 'nullable|string|max:500',
        'order' => 'required|integer|min:0',
        'is_active' => 'boolean',
    ];

    protected $messages = [
        'name.required' => 'O nome do tipo é obrigatório',
        'icon.required' => 'Selecione um ícone',
        'color.required' => 'Selecione uma cor',
    ];

    public function render()
    {
        $types = EventType::where('tenant_id', activeTenantId())
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return view('livewire.events.event-types', compact('types'));
    }

    public function create()
    {
        $this->resetForm();
        $this->order = EventType::where('tenant_id', activeTenantId())->max('order') + 1;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $type = EventType::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $type->name;
        $this->icon = $type->icon;
        $this->color = $type->color;
        $this->description = $type->description;
        $this->order = $type->order;
        $this->is_active = $type->is_active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingId) {
            $type = EventType::where('tenant_id', activeTenantId())->findOrFail($this->editingId);
            $type->update([
                'name' => $this->name,
                'icon' => $this->icon,
                'color' => $this->color,
                'description' => $this->description,
                'order' => $this->order,
                'is_active' => $this->is_active,
            ]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Tipo de evento atualizado!'
            ]);
        } else {
            EventType::create([
                'tenant_id' => activeTenantId(),
                'name' => $this->name,
                'icon' => $this->icon,
                'color' => $this->color,
                'description' => $this->description,
                'order' => $this->order,
                'is_active' => $this->is_active,
            ]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Tipo de evento criado!'
            ]);
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        $type = EventType::where('tenant_id', activeTenantId())->findOrFail($id);
        
        // Verificar se há eventos usando este tipo
        if ($type->events()->count() > 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Não é possível excluir! Existem eventos usando este tipo.'
            ]);
            return;
        }
        
        $type->delete();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Tipo de evento excluído!'
        ]);
    }

    public function toggleStatus($id)
    {
        $type = EventType::where('tenant_id', activeTenantId())->findOrFail($id);
        $type->update(['is_active' => !$type->is_active]);
        
        $status = $type->is_active ? 'ativado' : 'desativado';
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "✅ Tipo {$status}!"
        ]);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'description', 'editingId']);
        $this->icon = '📅';
        $this->color = '#8b5cf6';
        $this->order = 0;
        $this->is_active = true;
    }
}

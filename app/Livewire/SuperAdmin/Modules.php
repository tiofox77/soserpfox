<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Module;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Gestão de Módulos')]
class Modules extends Component
{
    public $showModal = false;
    public $editingModuleId = null;
    
    // Form fields
    public $name, $slug, $description, $icon = 'puzzle-piece';
    public $version = '1.0.0', $order = 0;
    public $is_active = true, $is_core = false;
    public $dependencies = [];

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:modules,slug',
        'description' => 'required',
        'icon' => 'required',
        'version' => 'required',
        'order' => 'required|integer',
    ];

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $module = Module::findOrFail($id);
        $this->editingModuleId = $id;
        $this->name = $module->name;
        $this->slug = $module->slug;
        $this->description = $module->description;
        $this->icon = $module->icon;
        $this->version = $module->version;
        $this->order = $module->order;
        $this->is_active = $module->is_active;
        $this->is_core = $module->is_core;
        $this->dependencies = $module->dependencies ?? [];
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editingModuleId) {
            $this->rules['slug'] = 'required|unique:modules,slug,' . $this->editingModuleId;
        }

        $this->validate();

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'version' => $this->version,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'is_core' => $this->is_core,
            'dependencies' => $this->dependencies,
        ];

        if ($this->editingModuleId) {
            Module::find($this->editingModuleId)->update($data);
            $this->dispatch('success', message: 'Módulo atualizado com sucesso!');
        } else {
            Module::create($data);
            $this->dispatch('success', message: 'Módulo criado com sucesso!');
        }

        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $module = Module::findOrFail($id);
        $module->update(['is_active' => !$module->is_active]);
        $status = $module->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Módulo {$status} com sucesso!");
    }

    public function delete($id)
    {
        try {
            $module = Module::findOrFail($id);
            if ($module->is_core) {
                $this->dispatch('error', message: 'Não é possível excluir um módulo core!');
                return;
            }
            $module->delete();
            $this->dispatch('success', message: 'Módulo excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir módulo!');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'slug', 'description', 'icon', 'version', 'order', 'editingModuleId', 'dependencies']);
        $this->is_active = true;
        $this->is_core = false;
        $this->icon = 'puzzle-piece';
        $this->version = '1.0.0';
        $this->order = 0;
    }

    public function render()
    {
        $modules = Module::orderBy('order')->get();

        return view('livewire.super-admin.modules.modules', compact('modules'));
    }
}

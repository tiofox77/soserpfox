<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\ServiceCategory;

#[Layout('layouts.app')]
#[Title('Categorias de Serviços - Salão de Beleza')]
class ServiceCategoryManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $showModal = false;
    public $showDeleteModal = false;
    public $editingId = null;
    public $deletingId = null;
    public $deletingName = '';

    // Form fields
    public $name, $icon = 'fas fa-spa', $color = '#6366f1', $description;
    public $order = 0;

    protected function rules()
    {
        return [
            'name' => 'required|min:2',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $category = ServiceCategory::find($id);
            $this->editingId = $id;
            $this->name = $category->name;
            $this->icon = $category->icon ?? 'fas fa-spa';
            $this->color = $category->color ?? '#6366f1';
            $this->description = $category->description;
            $this->order = $category->order ?? 0;
        }
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'description' => $this->description,
            'order' => $this->order,
        ];

        if ($this->editingId) {
            ServiceCategory::find($this->editingId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria atualizada!']);
        } else {
            $data['tenant_id'] = activeTenantId();
            ServiceCategory::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria criada!']);
        }

        $this->closeModal();
    }

    public function openDeleteModal($id)
    {
        $category = ServiceCategory::find($id);
        $this->deletingId = $id;
        $this->deletingName = $category->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $category = ServiceCategory::withCount('services')->find($this->deletingId);
        
        if ($category->services_count > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Categoria tem serviços vinculados! Mova-os primeiro.']);
            $this->cancelDelete();
            return;
        }
        
        $category->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Categoria removida!']);
        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function moveUp($id)
    {
        $category = ServiceCategory::find($id);
        if ($category->order > 0) {
            $category->update(['order' => $category->order - 1]);
        }
    }

    public function moveDown($id)
    {
        $category = ServiceCategory::find($id);
        $category->update(['order' => $category->order + 1]);
    }

    private function resetForm()
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->icon = 'fas fa-spa';
        $this->color = '#6366f1';
        $this->order = 0;
    }

    public function render()
    {
        $categories = ServiceCategory::forTenant()
            ->withCount('services')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.salon.service-categories.categories', compact('categories'));
    }
}

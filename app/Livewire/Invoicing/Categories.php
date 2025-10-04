<?php

namespace App\Livewire\Invoicing;

use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Categorias')]
class Categories extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingCategoryId = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingCategoryId = null;
    public $deletingCategoryName = '';
    
    // Filters
    public $typeFilter = ''; // main or subcategory
    public $perPage = 15;
    
    // Form fields
    public $parent_id = null;
    public $name, $description, $icon = 'fa-folder', $color = '#3B82F6', $order = 0;

    protected $rules = [
        'name' => 'required|min:2',
        'parent_id' => 'nullable|exists:invoicing_categories,id',
        'description' => 'nullable|string',
        'icon' => 'required|string',
        'color' => 'required|string',
        'order' => 'nullable|integer',
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['typeFilter', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        
        if ($category->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->editingCategoryId = $id;
        $this->parent_id = $category->parent_id;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->icon = $category->icon;
        $this->color = $category->color;
        $this->order = $category->order;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'order' => $this->order,
        ];

        if ($this->editingCategoryId) {
            $category = Category::findOrFail($this->editingCategoryId);
            
            if ($category->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $category->update($data);
            $this->dispatch('success', message: 'Categoria atualizada com sucesso!');
        } else {
            Category::create($data);
            $this->dispatch('success', message: 'Categoria criada com sucesso!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $category = Category::findOrFail($id);
        
        if ($category->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->deletingCategoryId = $id;
        $this->deletingCategoryName = $category->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        try {
            $category = Category::findOrFail($this->deletingCategoryId);
            
            if ($category->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $category->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingCategoryId', 'deletingCategoryName']);
            $this->dispatch('success', message: 'Categoria excluída com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir categoria!');
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingCategoryId', 'deletingCategoryName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['parent_id', 'name', 'description', 'editingCategoryId']);
        $this->icon = 'fa-folder';
        $this->color = '#3B82F6';
        $this->order = 0;
    }

    public function render()
    {
        $categories = Category::where('tenant_id', activeTenantId())
            ->with(['parent', 'children'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter === 'main', function ($query) {
                $query->whereNull('parent_id');
            })
            ->when($this->typeFilter === 'sub', function ($query) {
                $query->whereNotNull('parent_id');
            })
            ->orderBy('order')
            ->latest()
            ->paginate($this->perPage);

        $mainCategories = Category::where('tenant_id', activeTenantId())
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.categories.categories', compact('categories', 'mainCategories'));
    }
}

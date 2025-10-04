<?php

namespace App\Livewire\Invoicing;

use App\Models\Brand;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Marcas')]
class Brands extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingBrandId = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingBrandId = null;
    public $deletingBrandName = '';
    
    // Filters
    public $perPage = 15;
    
    // Form fields
    public $name, $description, $icon = 'fa-tag', $logo, $website, $order = 0;

    protected $rules = [
        'name' => 'required|min:2',
        'description' => 'nullable|string',
        'icon' => 'required|string',
        'logo' => 'nullable|url',
        'website' => 'nullable|url',
        'order' => 'nullable|integer',
    ];

    public function updatingSearch() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        
        if ($brand->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->editingBrandId = $id;
        $this->name = $brand->name;
        $this->description = $brand->description;
        $this->icon = $brand->icon ?? 'fa-tag';
        $this->logo = $brand->logo;
        $this->website = $brand->website;
        $this->order = $brand->order;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'logo' => $this->logo,
            'website' => $this->website,
            'order' => $this->order,
        ];

        if ($this->editingBrandId) {
            $brand = Brand::findOrFail($this->editingBrandId);
            
            if ($brand->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $brand->update($data);
            $this->dispatch('success', message: 'Marca atualizada com sucesso!');
        } else {
            Brand::create($data);
            $this->dispatch('success', message: 'Marca criada com sucesso!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $brand = Brand::findOrFail($id);
        
        if ($brand->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->deletingBrandId = $id;
        $this->deletingBrandName = $brand->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        try {
            $brand = Brand::findOrFail($this->deletingBrandId);
            
            if ($brand->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $brand->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingBrandId', 'deletingBrandName']);
            $this->dispatch('success', message: 'Marca excluída com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir marca!');
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingBrandId', 'deletingBrandName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'description', 'logo', 'website', 'editingBrandId']);
        $this->icon = 'fa-tag';
        $this->order = 0;
    }

    public function render()
    {
        $brands = Brand::where('tenant_id', activeTenantId())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('order')
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.invoicing.brands.brands', compact('brands'));
    }
}

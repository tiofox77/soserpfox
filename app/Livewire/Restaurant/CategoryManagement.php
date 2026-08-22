<?php

namespace App\Livewire\Restaurant;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Categorias - Restaurante')]
class CategoryManagement extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '', $description = '', $icon = 'fa-utensils', $color = '#EA580C';
    public bool $isActive = true;

    public function mount(): void { Category::seedRestaurantDefaults(activeTenantId()); }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->icon = 'fa-utensils'; $this->color = '#EA580C'; $this->isActive = true; $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = Category::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->editingId = $category->id; $this->name = $category->name; $this->description = (string) $category->description;
        $this->icon = $category->icon ?: 'fa-utensils'; $this->color = $category->color ?: '#EA580C'; $this->isActive = (bool) $category->is_active; $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required','string','min:2','max:100', Rule::unique('invoicing_categories','name')->where('tenant_id',activeTenantId())->ignore($this->editingId)],
            'description' => ['nullable','string','max:500'], 'icon' => ['required','string','max:60'],
            'color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'], 'isActive' => ['boolean'],
        ]);
        $values = ['tenant_id'=>activeTenantId(),'name'=>trim($data['name']),'slug'=>Str::slug($data['name']),'description'=>$data['description'] ?: null,'icon'=>$data['icon'],'color'=>strtoupper($data['color']),'is_active'=>$data['isActive']];
        if ($this->editingId) Category::where('tenant_id', activeTenantId())->findOrFail($this->editingId)->update($values);
        else { $values['order'] = (int) Category::where('tenant_id',activeTenantId())->max('order') + 1; Category::create($values); }
        $this->showForm = false; $this->dispatch('notify', type:'success', message:'Categoria guardada e disponível no POS e na Faturação.');
    }

    public function toggle(int $id): void
    {
        $category = Category::where('tenant_id', activeTenantId())->findOrFail($id);
        if ($category->slug === 'geral' && $category->is_active) { $this->dispatch('notify',type:'error',message:'A categoria Geral deve permanecer ativa.'); return; }
        $category->update(['is_active'=>!$category->is_active]);
    }

    public function delete(int $id): void
    {
        $category = Category::where('tenant_id', activeTenantId())->findOrFail($id);
        if ($category->slug === 'geral') { $this->dispatch('notify',type:'error',message:'A categoria Geral é padrão e não pode ser eliminada.'); return; }
        if ($category->products()->exists()) { $this->dispatch('notify',type:'error',message:'Esta categoria possui produtos. Mova-os antes de eliminar.'); return; }
        $category->delete(); $this->dispatch('notify',type:'success',message:'Categoria eliminada.');
    }

    public function render()
    {
        return view('livewire.restaurant.category-management', ['categories'=>Category::where('tenant_id',activeTenantId())->withCount('products')->orderBy('order')->orderBy('name')->get()]);
    }
}

<?php

namespace App\Livewire\SuperAdmin;

use App\Models\{Plan, Module};
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Gestão de Planos')]
class Plans extends Component
{
    public $showModal = false;
    public $editingPlanId = null;
    
    // Form fields
    public $name, $slug, $description;
    public $price_monthly = 0, $price_yearly = 0;
    public $max_users = 5, $max_companies = 1, $max_storage_mb = 1000;
    public $trial_days = 30, $order = 0;
    public $is_active = true, $is_featured = false;
    public $features = [];
    public $newFeature = '';
    public $selectedModules = [];

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:plans,slug',
        'description' => 'required',
        'price_monthly' => 'required|numeric|min:0',
        'price_yearly' => 'required|numeric|min:0',
        'max_users' => 'required|integer|min:1',
        'max_companies' => 'required|integer|min:1',
        'max_storage_mb' => 'required|integer|min:100',
        'trial_days' => 'required|integer|min:0',
        'order' => 'required|integer',
    ];

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $plan = Plan::findOrFail($id);
        $this->editingPlanId = $id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description;
        $this->price_monthly = $plan->price_monthly;
        $this->price_yearly = $plan->price_yearly;
        $this->max_users = $plan->max_users;
        $this->max_companies = $plan->max_companies ?? 1;
        $this->max_storage_mb = $plan->max_storage_mb;
        $this->trial_days = $plan->trial_days;
        $this->order = $plan->order;
        $this->is_active = $plan->is_active;
        $this->is_featured = $plan->is_featured;
        $this->features = $plan->features ?? [];
        $this->selectedModules = $plan->modules->pluck('id')->toArray();
        $this->showModal = true;
    }

    public function addFeature()
    {
        if (trim($this->newFeature)) {
            $this->features[] = trim($this->newFeature);
            $this->newFeature = '';
        }
    }

    public function removeFeature($index)
    {
        unset($this->features[$index]);
        $this->features = array_values($this->features);
    }

    public function save()
    {
        if ($this->editingPlanId) {
            $this->rules['slug'] = 'required|unique:plans,slug,' . $this->editingPlanId;
        }

        $this->validate();

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'max_users' => $this->max_users,
            'max_companies' => $this->max_companies,
            'max_storage_mb' => $this->max_storage_mb,
            'trial_days' => $this->trial_days,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'features' => $this->features,
        ];

        if ($this->editingPlanId) {
            $plan = Plan::find($this->editingPlanId);
            $plan->update($data);
            $plan->modules()->sync($this->selectedModules);
            $this->dispatch('success', message: 'Plano atualizado com sucesso!');
        } else {
            $plan = Plan::create($data);
            $plan->modules()->sync($this->selectedModules);
            $this->dispatch('success', message: 'Plano criado com sucesso!');
        }

        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);
        $status = $plan->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Plano {$status} com sucesso!");
    }

    public function delete($id)
    {
        try {
            $plan = Plan::findOrFail($id);
            // Verificar se tem subscrições ativas
            if ($plan->subscriptions()->where('status', 'active')->count() > 0) {
                $this->dispatch('error', message: 'Não é possível excluir um plano com subscrições ativas!');
                return;
            }
            $plan->delete();
            $this->dispatch('success', message: 'Plano excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir plano!');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'slug', 'description', 'editingPlanId', 'features', 'newFeature', 'selectedModules']);
        $this->price_monthly = 0;
        $this->price_yearly = 0;
        $this->max_users = 5;
        $this->max_companies = 1;
        $this->max_storage_mb = 1000;
        $this->trial_days = 30;
        $this->order = 0;
        $this->is_active = true;
        $this->is_featured = false;
    }

    public function render()
    {
        $plans = Plan::with('modules')->orderBy('order')->get();
        $modules = Module::active()->orderBy('order')->get();

        return view('livewire.super-admin.plans.plans', compact('plans', 'modules'));
    }
}

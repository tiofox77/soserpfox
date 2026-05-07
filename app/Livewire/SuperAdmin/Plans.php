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
    public $search = '';
    public $showModal = false;
    public $editingPlanId = null;
    
    // Form fields
    public $name, $slug, $description;
    public $price_monthly = 0, $price_yearly = 0;
    public $max_users = 5, $max_companies = 1, $max_storage_mb = 1000;
    public $trial_days = 30, $order = 0;
    public $is_active = true, $is_featured = false, $auto_activate = false;
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
        $this->auto_activate = $plan->auto_activate;
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
            'auto_activate' => $this->auto_activate,
            'features' => $this->features,
        ];

        if ($this->editingPlanId) {
            $plan = Plan::find($this->editingPlanId);
            
            // Pegar módulos antes da atualização
            $oldModuleIds = $plan->modules->pluck('id')->toArray();
            
            $plan->update($data);
            $plan->modules()->sync($this->selectedModules);
            
            // 1. Verificar módulos ADICIONADOS
            $addedModuleIds = array_diff($this->selectedModules, $oldModuleIds);
            
            // 2. Verificar módulos REMOVIDOS
            $removedModuleIds = array_diff($oldModuleIds, $this->selectedModules);
            
            $changes = [];
            
            // Adicionar novos módulos aos tenants
            if (!empty($addedModuleIds)) {
                $addedCount = 0;
                foreach ($addedModuleIds as $moduleId) {
                    $addedCount += $plan->addModuleToTenants($moduleId);
                }
                if ($addedCount > 0) {
                    $changes[] = "{$addedCount} tenant(s) receberam " . count($addedModuleIds) . " novo(s) módulo(s)";
                }
            }
            
            // Remover módulos desvinculados dos tenants
            if (!empty($removedModuleIds)) {
                $removedCount = 0;
                foreach ($removedModuleIds as $moduleId) {
                    $removedCount += $plan->removeModuleFromTenants($moduleId);
                }
                if ($removedCount > 0) {
                    $changes[] = "{$removedCount} tenant(s) perderam " . count($removedModuleIds) . " módulo(s)";
                }
            }
            
            // Log das alterações
            if (!empty($changes)) {
                \Log::info("📦 SINCRONIZAÇÃO AUTOMÁTICA - Plano '{$plan->name}':", $changes);
            }
            
            $this->dispatch('success', message: 'Plano atualizado com sucesso!');
        } else {
            $plan = Plan::create($data);
            $plan->modules()->sync($this->selectedModules);
            
            // Para novos planos, sincronizar todos os módulos (se houver tenants)
            $syncedCount = $plan->syncModulesToTenants();
            if ($syncedCount > 0) {
                \Log::info("Módulos do novo plano '{$plan->name}' sincronizados com {$syncedCount} tenant(s)");
            }
            
            $this->dispatch('success', message: 'Plano criado com sucesso!');
        }

        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $plan = Plan::findOrFail($id);
        $activeSubsCount = $plan->subscriptions()->where('status', 'active')->count();
        
        if ($plan->is_active && $activeSubsCount > 0) {
            $plan->update(['is_active' => !$plan->is_active]);
            $this->dispatch('warning', message: "Plano '{$plan->name}' desativado. Atenção: {$activeSubsCount} subscrição(ões) ativa(s) continuarão a funcionar, mas não será possível novos registos.");
            return;
        }
        
        $plan->update(['is_active' => !$plan->is_active]);
        $status = $plan->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Plano '{$plan->name}' {$status} com sucesso!");
    }

    public function delete($id)
    {
        try {
            $plan = Plan::findOrFail($id);
            
            // Verificar se tem subscrições ativas ou em trial
            $subsCount = $plan->subscriptions()->whereIn('status', ['active', 'trial', 'pending'])->count();
            if ($subsCount > 0) {
                $this->dispatch('error', message: "Não é possível excluir o plano '{$plan->name}'. Possui {$subsCount} subscrição(ões) ativa(s)/em trial. Cancele-as primeiro.");
                return;
            }
            
            // Desvincular módulos antes de excluir
            $plan->modules()->detach();
            $plan->delete();
            $this->dispatch('success', message: "Plano '{$plan->name}' excluído com sucesso!");
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir plano: ' . $e->getMessage());
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
        $this->auto_activate = false;
    }

    public function render()
    {
        $plans = Plan::with('modules')
            ->withCount(['subscriptions as active_subscriptions_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('slug', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('order')
            ->get();
        $modules = Module::active()->orderBy('order')->get();

        return view('livewire.super-admin.plans.plans', compact('plans', 'modules'));
    }
}

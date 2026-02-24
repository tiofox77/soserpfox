<?php

namespace App\Livewire;

use Livewire\Component;

class TenantSwitcher extends Component
{
    public $tenants = [];
    public $activeTenantId;
    public $activeTenantName;
    public $hasExceededLimit = false;
    public $currentCount = 0;
    public $maxAllowed = 1;
    
    public function mount()
    {
        $this->loadTenants();
    }
    
    public function loadTenants()
    {
        $user = auth()->user();
        $this->tenants = $user->tenants;
        
        $activeTenant = $user->activeTenant();
        if ($activeTenant) {
            $this->activeTenantId = $activeTenant->id;
            $this->activeTenantName = $activeTenant->name;
        }
        
        // Verificar limite
        if (!$user->is_super_admin) {
            $this->currentCount = $user->tenants()->count();
            $this->maxAllowed = $user->getMaxCompaniesLimit();
            $this->hasExceededLimit = $this->currentCount > $this->maxAllowed;
        }
    }
    
    public function switchTenant($tenantId)
    {
        $user = auth()->user();
        
        // BUG-07 FIX: Verificar limite por contagem total vs max_companies do plano
        // (não mais por índice da collection)
        if (!$user->is_super_admin && $this->hasExceededLimit) {
            $this->dispatch('error', message: 
                "🔒 Limite excedido! Seu plano permite {$this->maxAllowed} empresa(s) mas tem {$this->currentCount}. " .
                "Faça upgrade ou remova uma empresa para alternar."
            );
            return;
        }
        
        if ($user->switchTenant($tenantId)) {
            $this->loadTenants();
            $this->dispatch('tenant-switched-reload');
            $this->dispatch('success', message: 'Empresa alterada com sucesso!');
        } else {
            $this->dispatch('error', message: 'Você não tem permissão para acessar esta empresa!');
        }
    }
    
    public function render()
    {
        return view('livewire.tenant-switcher');
    }
}

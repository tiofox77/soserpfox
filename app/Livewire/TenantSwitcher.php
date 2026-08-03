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

        // O limite do plano controla a criacao de novas empresas. Empresas que
        // ja pertencem ao utilizador devem continuar acessiveis para consulta.
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

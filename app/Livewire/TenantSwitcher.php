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
        \Log::info('TenantSwitcher: Iniciando troca de tenant', [
            'from' => auth()->user()->activeTenantId(),
            'to' => $tenantId,
            'user_id' => auth()->id(),
        ]);
        
        $user = auth()->user();
        
        // Verificar se a empresa está bloqueada pelo limite do plano
        if (!$user->is_super_admin && $this->hasExceededLimit) {
            // Verificar qual índice da empresa
            $tenants = $user->tenants;
            $index = $tenants->search(fn($t) => $t->id == $tenantId);
            
            if ($index !== false && $index >= $this->maxAllowed) {
                \Log::warning('TenantSwitcher: Empresa bloqueada por limite de plano', [
                    'tenant_id' => $tenantId,
                    'index' => $index,
                    'max_allowed' => $this->maxAllowed,
                ]);
                
                $this->dispatch('error', message: 
                    "🔒 Empresa bloqueada! Você excedeu o limite de {$this->maxAllowed} empresa(s) do seu plano. " .
                    "Faça upgrade para acessar esta empresa."
                );
                return;
            }
        }
        
        if ($user->switchTenant($tenantId)) {
            \Log::info('TenantSwitcher: Troca bem-sucedida', [
                'new_tenant' => $tenantId,
                'session' => session('active_tenant_id'),
            ]);
            
            $this->loadTenants();
            
            \Log::info('TenantSwitcher: Despachando evento para reload');
            
            // Despachar evento para JavaScript fazer o reload
            $this->dispatch('tenant-switched-reload');
            $this->dispatch('success', message: 'Empresa alterada com sucesso!');
        } else {
            \Log::warning('TenantSwitcher: Falha na troca de tenant', [
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
            ]);
            
            $this->dispatch('error', message: 'Você não tem permissão para acessar esta empresa!');
        }
    }
    
    public function render()
    {
        return view('livewire.tenant-switcher');
    }
}

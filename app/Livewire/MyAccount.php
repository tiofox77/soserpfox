<?php

namespace App\Livewire;

use App\Models\Tenant;
use App\Models\Order;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Minha Conta')]
class MyAccount extends Component
{
    use WithFileUploads;
    
    public $activeTab = 'companies'; // companies, plan, profile
    
    // Dados do usuário
    public $currentCount = 0;
    public $maxAllowed = 1;
    public $hasExceededLimit = false;
    public $planName = '';
    public $planPrice = 0;
    
    // Modal criar empresa
    public $showCreateCompanyModal = false;
    public $newCompanyName = '';
    public $newCompanyNif = '';
    public $newCompanyRegime = 'regime_geral';
    public $newCompanyAddress = '';
    public $newCompanyPhone = '';
    public $newCompanyEmail = '';
    
    // Modal deletar empresa
    public $showDeleteModal = false;
    public $companyToDelete = null;
    public $companyToDeleteName = '';
    
    // Modal editar empresa
    public $showEditCompanyModal = false;
    public $editCompanyId = null;
    public $editCompanyName = '';
    public $editCompanyNif = '';
    public $editCompanyRegime = 'regime_geral';
    public $editCompanyAddress = '';
    public $editCompanyPhone = '';
    public $editCompanyEmail = '';
    public $editCompanyLogo = null; // Upload file
    public $currentLogo = null; // Logo atual
    
    public function mount()
    {
        // Aceitar tab via query string
        if (request()->has('tab')) {
            $tab = request()->get('tab');
            if (in_array($tab, ['companies', 'plan', 'profile'])) {
                $this->activeTab = $tab;
            }
        }
        
        $this->loadAccountData();
    }
    
    public function loadAccountData()
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin) {
            $this->currentCount = $user->tenants()->count();
            $this->maxAllowed = $user->getMaxCompaniesLimit();
            $this->hasExceededLimit = $this->currentCount > $this->maxAllowed;
            
            $activeTenant = $user->activeTenant();
            if ($activeTenant) {
                $subscription = $activeTenant->activeSubscription;
                if ($subscription && $subscription->plan) {
                    $this->planName = $subscription->plan->name;
                    $this->planPrice = $subscription->plan->price_monthly;
                }
            }
        }
    }
    
    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }
    
    public function openCreateCompanyModal()
    {
        // Verificar se pode criar mais empresas
        if ($this->currentCount >= $this->maxAllowed) {
            $this->dispatch('error', message: 
                "🔒 Limite atingido! Você já tem {$this->currentCount} empresa(s) e seu plano permite no máximo {$this->maxAllowed}. " .
                "Faça upgrade do seu plano para criar mais empresas."
            );
            return;
        }
        
        $this->showCreateCompanyModal = true;
        $this->reset(['newCompanyName', 'newCompanyNif', 'newCompanyRegime', 'newCompanyAddress', 'newCompanyPhone', 'newCompanyEmail']);
        $this->newCompanyRegime = 'regime_geral'; // Default
    }
    
    public function closeCreateCompanyModal()
    {
        $this->showCreateCompanyModal = false;
    }
    
    public function createCompany()
    {
        $user = auth()->user();
        
        // Validar limite novamente
        if ($user->tenants()->count() >= $this->maxAllowed) {
            $this->dispatch('error', message: 'Limite de empresas atingido!');
            return;
        }
        
        // Validação
        $this->validate([
            'newCompanyName' => 'required|min:3|max:255',
            'newCompanyNif' => 'required|min:9|max:14',
            'newCompanyRegime' => 'required|in:regime_geral,regime_simplificado,regime_isencao,regime_nao_sujeicao,regime_misto',
            'newCompanyAddress' => 'nullable|max:255',
            'newCompanyPhone' => 'nullable|max:20',
            'newCompanyEmail' => 'nullable|email|max:255',
        ], [
            'newCompanyName.required' => 'Nome da empresa é obrigatório',
            'newCompanyName.min' => 'Nome deve ter no mínimo 3 caracteres',
            'newCompanyNif.required' => 'NIF é obrigatório',
            'newCompanyNif.min' => 'NIF deve ter no mínimo 9 caracteres',
            'newCompanyRegime.required' => 'Regime fiscal é obrigatório',
            'newCompanyRegime.in' => 'Regime fiscal inválido',
            'newCompanyEmail.email' => 'Email inválido',
        ]);
        
        \DB::beginTransaction();
        try {
            // Buscar tenant e subscription atuais para replicar
            $currentTenant = $user->activeTenant();
            $currentSubscription = null;
            $activeModules = collect([]);
            
            if ($currentTenant) {
                $currentSubscription = $currentTenant->activeSubscription;
                $activeModules = $currentTenant->modules()->wherePivot('is_active', true)->get();
            }
            
            // Criar novo tenant
            $tenant = Tenant::create([
                'name' => $this->newCompanyName,
                'company_name' => $this->newCompanyName,
                'nif' => $this->newCompanyNif,
                'regime' => $this->newCompanyRegime,
                'address' => $this->newCompanyAddress,
                'phone' => $this->newCompanyPhone,
                'email' => $this->newCompanyEmail ?: $user->email,
                'is_active' => true,
            ]);
            
            // Vincular usuário ao novo tenant como Admin
            $user->tenants()->attach($tenant->id, [
                'role_id' => 2, // Admin
                'is_active' => true,
                'joined_at' => now(),
            ]);
            
            // Replicar subscription do tenant atual (se existir)
            if ($currentSubscription && $currentSubscription->plan) {
                $now = now();
                $nextBillingDate = $currentSubscription->billing_cycle === 'yearly' 
                    ? $now->copy()->addYear() 
                    : $now->copy()->addMonth();
                
                $tenant->subscriptions()->create([
                    'plan_id' => $currentSubscription->plan_id,
                    'status' => $currentSubscription->status,
                    'billing_cycle' => $currentSubscription->billing_cycle,
                    'amount' => $currentSubscription->amount,
                    'current_period_start' => $now,
                    'current_period_end' => $nextBillingDate,
                    'ends_at' => $nextBillingDate,
                    'trial_ends_at' => null,
                ]);
                
                \Log::info('Subscription replicada para nova empresa', [
                    'new_tenant_id' => $tenant->id,
                    'plan_id' => $currentSubscription->plan_id
                ]);
            }
            
            // Replicar módulos ativos
            if ($activeModules->count() > 0) {
                foreach ($activeModules as $module) {
                    $tenant->modules()->attach($module->id, [
                        'is_active' => true,
                        'activated_at' => now(),
                    ]);
                }
                
                \Log::info('Módulos replicados para nova empresa', [
                    'new_tenant_id' => $tenant->id,
                    'modules_count' => $activeModules->count()
                ]);
            }
            
            \DB::commit();
            
            $this->showCreateCompanyModal = false;
            $this->loadAccountData();
            $this->dispatch('success', message: 'Empresa criada com sucesso com o mesmo plano e módulos!');
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Erro ao criar empresa', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao criar empresa: ' . $e->getMessage());
        }
    }
    
    public function confirmDeleteCompany($tenantId)
    {
        $user = auth()->user();
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->dispatch('error', message: 'Empresa não encontrada.');
            return;
        }
        
        // Verificar se usuário tem permissão (é admin da empresa)
        $pivot = $user->tenants()->where('tenant_id', $tenantId)->first()?->pivot;
        if (!$pivot || $pivot->role_id != 2) {
            $this->dispatch('error', message: 'Você não tem permissão para eliminar esta empresa.');
            return;
        }
        
        // Não permitir deletar se for a única empresa
        if ($user->tenants()->count() <= 1) {
            $this->dispatch('error', message: 'Você não pode eliminar sua única empresa.');
            return;
        }
        
        // Não permitir deletar se for a empresa ativa
        if ($user->tenant_id == $tenantId) {
            $this->dispatch('error', message: 'Não pode eliminar a empresa ativa. Troque para outra empresa primeiro.');
            return;
        }
        
        // Verificar se tem faturas registradas
        $invoicesCount = $tenant->invoices()->count();
        if ($invoicesCount > 0) {
            $this->dispatch('error', message: "Não é possível eliminar esta empresa. Existem {$invoicesCount} fatura(s) registrada(s). Por motivos legais e de auditoria, empresas com faturas não podem ser eliminadas.");
            return;
        }
        
        // Verificar se tem clientes
        $clientsCount = \DB::table('invoicing_clients')->where('tenant_id', $tenantId)->count();
        if ($clientsCount > 0) {
            $this->dispatch('warning', message: "Esta empresa possui {$clientsCount} cliente(s) cadastrado(s). Ao eliminar, todos os dados serão perdidos permanentemente.");
        }
        
        // Abrir modal de confirmação
        $this->companyToDelete = $tenantId;
        $this->companyToDeleteName = $tenant->name;
        $this->showDeleteModal = true;
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->companyToDelete = null;
        $this->companyToDeleteName = '';
    }
    
    public function deleteCompany()
    {
        if (!$this->companyToDelete) {
            $this->dispatch('error', message: 'Nenhuma empresa selecionada para eliminar.');
            return;
        }
        
        $user = auth()->user();
        $tenant = Tenant::find($this->companyToDelete);
        
        if (!$tenant) {
            $this->dispatch('error', message: 'Empresa não encontrada.');
            $this->closeDeleteModal();
            return;
        }
        
        \DB::beginTransaction();
        try {
            // Deletar tenant (cascade vai deletar subscriptions, modules, etc)
            $tenant->delete();
            
            \DB::commit();
            
            $this->closeDeleteModal();
            $this->loadAccountData();
            $this->dispatch('success', message: 'Empresa eliminada com sucesso!');
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Erro ao eliminar empresa', [
                'user_id' => $user->id,
                'tenant_id' => $this->companyToDelete,
                'error' => $e->getMessage()
            ]);
            $this->closeDeleteModal();
            $this->dispatch('error', message: 'Erro ao eliminar empresa: ' . $e->getMessage());
        }
    }
    
    public function openEditCompanyModal($tenantId)
    {
        $user = auth()->user();
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->dispatch('error', message: 'Empresa não encontrada.');
            return;
        }
        
        // Verificar se usuário tem permissão (é admin da empresa)
        $pivot = $user->tenants()->where('tenant_id', $tenantId)->first()?->pivot;
        if (!$pivot || $pivot->role_id != 2) {
            $this->dispatch('error', message: 'Você não tem permissão para editar esta empresa.');
            return;
        }
        
        // Carregar dados da empresa
        $this->editCompanyId = $tenant->id;
        $this->editCompanyName = $tenant->name;
        $this->editCompanyNif = $tenant->nif;
        $this->editCompanyRegime = $tenant->regime ?? 'regime_geral';
        $this->editCompanyAddress = $tenant->address;
        $this->editCompanyPhone = $tenant->phone;
        $this->editCompanyEmail = $tenant->email;
        $this->currentLogo = $tenant->logo;
        $this->editCompanyLogo = null;
        
        $this->showEditCompanyModal = true;
    }
    
    public function closeEditCompanyModal()
    {
        $this->showEditCompanyModal = false;
        $this->reset(['editCompanyId', 'editCompanyName', 'editCompanyNif', 'editCompanyRegime', 'editCompanyAddress', 'editCompanyPhone', 'editCompanyEmail']);
    }
    
    public function updateCompany()
    {
        $user = auth()->user();
        
        // Validação
        $this->validate([
            'editCompanyName' => 'required|min:3|max:255',
            'editCompanyNif' => 'required|min:9|max:14',
            'editCompanyRegime' => 'required|in:regime_geral,regime_simplificado,regime_isencao,regime_nao_sujeicao,regime_misto',
            'editCompanyAddress' => 'nullable|max:255',
            'editCompanyPhone' => 'nullable|max:20',
            'editCompanyEmail' => 'nullable|email|max:255',
            'editCompanyLogo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ], [
            'editCompanyName.required' => 'Nome da empresa é obrigatório',
            'editCompanyName.min' => 'Nome deve ter no mínimo 3 caracteres',
            'editCompanyNif.required' => 'NIF é obrigatório',
            'editCompanyNif.min' => 'NIF deve ter no mínimo 9 caracteres',
            'editCompanyRegime.required' => 'Regime fiscal é obrigatório',
            'editCompanyEmail.email' => 'Email inválido',
            'editCompanyLogo.image' => 'O arquivo deve ser uma imagem',
            'editCompanyLogo.mimes' => 'Formatos aceitos: jpeg, jpg, png, gif, svg',
            'editCompanyLogo.max' => 'Imagem não pode ser maior que 2MB',
        ]);
        
        \DB::beginTransaction();
        try {
            $tenant = Tenant::findOrFail($this->editCompanyId);
            
            // Verificar permissão novamente
            $pivot = $user->tenants()->where('tenant_id', $tenant->id)->first()?->pivot;
            if (!$pivot || $pivot->role_id != 2) {
                throw new \Exception('Você não tem permissão para editar esta empresa.');
            }
            
            $dataToUpdate = [
                'name' => $this->editCompanyName,
                'company_name' => $this->editCompanyName,
                'nif' => $this->editCompanyNif,
                'regime' => $this->editCompanyRegime,
                'address' => $this->editCompanyAddress,
                'phone' => $this->editCompanyPhone,
                'email' => $this->editCompanyEmail ?: $user->email,
            ];
            
            // Processar upload da logo se existir
            if ($this->editCompanyLogo) {
                // Deletar logo antiga se existir
                if ($tenant->logo && \Storage::disk('public')->exists($tenant->logo)) {
                    \Storage::disk('public')->delete($tenant->logo);
                }
                
                // Salvar nova logo
                $logoPath = $this->editCompanyLogo->store('logos', 'public');
                $dataToUpdate['logo'] = $logoPath;
            }
            
            $tenant->update($dataToUpdate);
            
            \DB::commit();
            
            $this->closeEditCompanyModal();
            $this->loadAccountData();
            $this->dispatch('success', message: 'Empresa atualizada com sucesso!');
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Erro ao atualizar empresa', [
                'user_id' => $user->id,
                'tenant_id' => $this->editCompanyId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao atualizar empresa: ' . $e->getMessage());
        }
    }
    
    public function removeLogo()
    {
        $user = auth()->user();
        
        try {
            $tenant = Tenant::findOrFail($this->editCompanyId);
            
            // Verificar permissão
            $pivot = $user->tenants()->where('tenant_id', $tenant->id)->first()?->pivot;
            if (!$pivot || $pivot->role_id != 2) {
                throw new \Exception('Você não tem permissão para editar esta empresa.');
            }
            
            // Deletar logo do storage
            if ($tenant->logo && \Storage::disk('public')->exists($tenant->logo)) {
                \Storage::disk('public')->delete($tenant->logo);
            }
            
            // Atualizar banco de dados
            $tenant->update(['logo' => null]);
            
            $this->currentLogo = null;
            $this->dispatch('success', message: 'Logo removida com sucesso!');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao remover logo', [
                'user_id' => $user->id,
                'tenant_id' => $this->editCompanyId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao remover logo: ' . $e->getMessage());
        }
    }
    
    public function switchToTenant($tenantId)
    {
        $user = auth()->user();
        
        // Verificar se a empresa está bloqueada pelo limite do plano
        if (!$user->is_super_admin && $this->hasExceededLimit) {
            // Verificar qual índice da empresa
            $tenants = $user->tenants;
            $index = $tenants->search(fn($t) => $t->id == $tenantId);
            
            if ($index !== false && $index >= $this->maxAllowed) {
                $this->dispatch('error', message: 
                    "🔒 Empresa bloqueada! Você excedeu o limite de {$this->maxAllowed} empresa(s) do seu plano. " .
                    "Faça upgrade para acessar esta empresa."
                );
                return;
            }
        }
        
        if ($user->switchTenant($tenantId)) {
            $this->dispatch('success', message: 'Empresa alterada com sucesso!');
            $this->loadAccountData();
            
            // Recarregar página
            return redirect()->to(route('my-account') . '?tab=companies');
        }
        
        $this->dispatch('error', message: 'Não foi possível alternar para esta empresa.');
    }
    
    public function render()
    {
        $user = auth()->user();
        $myTenants = $user->tenants()->withPivot('role_id', 'is_active', 'joined_at')->get();
        
        // Pegar plano atual
        $currentPlan = null;
        $currentSubscription = null;
        $activeTenant = $user->activeTenant();
        if ($activeTenant) {
            $subscription = $activeTenant->activeSubscription;
            if ($subscription) {
                $currentPlan = $subscription->plan;
                $currentSubscription = $subscription;
            }
        }
        
        // Buscar pedidos pendentes do usuário
        $pendingOrders = Order::with(['tenant', 'plan'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending'])
            ->latest()
            ->get();
        
        // Buscar subscriptions pendentes
        $pendingSubscriptions = collect([]); // Inicializar como Collection vazia
        if ($activeTenant) {
            $pendingSubscriptions = $activeTenant->subscriptions()
                ->with('plan')
                ->where('status', 'pending')
                ->latest()
                ->get();
        }
        
        return view('livewire.my-account', compact('myTenants', 'currentPlan', 'currentSubscription', 'pendingOrders', 'pendingSubscriptions'));
    }
}

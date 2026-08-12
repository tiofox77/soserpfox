<?php

namespace App\Livewire;

use App\Models\Tenant;
use App\Models\Order;
use App\Services\Subscriptions\DireitoACortesia;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Minha Conta')]
class MyAccount extends Component
{
    use WithFileUploads;
    
    public $activeTab = 'companies'; // companies, plan, billing, profile, security
    
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
    
    // Profile fields
    public $userName = '';
    public $userEmail = '';
    public $userPhone = '';
    public $userBio = '';
    public $userAvatar = null;
    public $currentAvatar = null;
    
    // Security fields
    public $currentPassword = '';
    public $newPassword = '';
    public $confirmPassword = '';
    public $showChangePasswordForm = false;
    
    // Upgrade Modal
    public $showUpgradeModal = false;
    public $selectedPlanForUpgrade = null;
    public $upgradeBillingCycle = 'monthly';
    public $upgradeStep = 1; // 1 = Selecionar, 2 = Pagamento
    public $paymentProof = null;
    
    // Order Modals
    public $showOrderViewModal = false;
    public $showOrderPaymentModal = false;
    public $viewingOrder = null;
    public $payingOrder = null;
    public $newPaymentProof = null;
    
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
        $user = auth()->user();

        // Áreas administrativas (empresas/plano/faturação) só para quem pode gerir a conta.
        // Utilizadores de nível baixo entram diretamente no perfil.
        $canManage = $user->canManageAccount();
        $restricted = ['companies', 'plan', 'billing'];
        $this->activeTab = $canManage ? 'companies' : 'profile';

        // Aceitar tab via query string
        if (request()->has('tab')) {
            $tab = request()->get('tab');
            if (in_array($tab, ['companies', 'plan', 'billing', 'profile', 'security'])) {
                // Bloquear acesso direto por URL a áreas restritas
                $this->activeTab = (in_array($tab, $restricted) && !$canManage) ? 'profile' : $tab;
            }
        }

        // Carregar dados do perfil
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userPhone = $user->phone ?? '';
        $this->userBio = $user->bio ?? '';
        $this->currentAvatar = $user->avatar;
        
        $this->loadAccountData();

        // Auto-abrir modal de upgrade quando vem da página /subscription-expired
        // ou de qualquer link com ?select={planId} (ex.: emails de renovação).
        if (request()->filled('select')) {
            $planId = (int) request()->get('select');
            if ($planId > 0) {
                $this->activeTab = 'plan';
                $this->openUpgradeModal($planId);

                // Se já existe uma Order PENDING recente para este plano deste user/tenant,
                // saltar directo para o step 2 (pagamento) — o user já escolheu antes.
                $activeTenant = $user->activeTenant();
                if ($activeTenant) {
                    $existingPending = \App\Models\Order::where('tenant_id', $activeTenant->id)
                        ->where('user_id', $user->id)
                        ->where('plan_id', $planId)
                        ->where('status', 'pending')
                        ->where('created_at', '>=', now()->subDays(7))
                        ->latest()
                        ->first();
                    if ($existingPending) {
                        $this->upgradeStep = 2;
                    }
                }
            }
        }
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
        // Bloquear áreas administrativas para utilizadores sem permissão
        if (in_array($tab, ['companies', 'plan', 'billing']) && !auth()->user()->canManageAccount()) {
            $this->activeTab = 'profile';
            return;
        }
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
            'newCompanyRegime' => 'required|in:' . implode(',', array_merge(array_keys(\App\Models\Tenant::REGIMES), array_keys(\App\Models\Tenant::REGIME_ALIASES))),
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
            
            // Criar roles padrão para o novo tenant
            createDefaultRolesForTenant($tenant->id);
            
            // Criar dados contabilísticos padrão
            initializeAccountingDataForTenant($tenant->id);
            
            // Buscar o role "Super Admin" criado
            $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'Super Admin')
                ->where('tenant_id', $tenant->id)
                ->first();
            
            // Vincular usuário ao novo tenant como Super Admin
            $user->tenants()->attach($tenant->id, [
                'role_id' => $superAdminRole ? $superAdminRole->id : null,
                'is_active' => true,
                'joined_at' => now(),
            ]);
            
            // Atribuir role "Super Admin" ao usuário para este tenant
            if ($superAdminRole) {
                setPermissionsTeamId($tenant->id);
                $user->assignRole($superAdminRole);
                
                \Log::info('Role Super Admin atribuído ao usuário', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'role_id' => $superAdminRole->id,
                ]);
            }
            
            // BUG-03 FIX: Replicar subscription com MESMAS datas (sincronizada)
            if ($currentSubscription && $currentSubscription->plan) {
                $tenant->subscriptions()->create([
                    'plan_id'              => $currentSubscription->plan_id,
                    'status'               => $currentSubscription->status,
                    'billing_cycle'        => $currentSubscription->billing_cycle,
                    'amount'               => $currentSubscription->amount,
                    'current_period_start' => $currentSubscription->current_period_start,
                    'current_period_end'   => $currentSubscription->current_period_end,
                    'ends_at'              => $currentSubscription->ends_at,
                    'trial_ends_at'        => $currentSubscription->trial_ends_at,
                ]);
                
                \Log::info('Subscription replicada para nova empresa (sincronizada)', [
                    'new_tenant_id' => $tenant->id,
                    'plan_id' => $currentSubscription->plan_id,
                    'period_end' => $currentSubscription->current_period_end,
                ]);
            }
            
            // Replicar módulos ativos — via serviço, para a nova empresa nascer
            // com as dependências (Faturação ⇒ Tesouraria), os pré-requisitos
            // (métodos de pagamento, impostos, armazém) e as permissões.
            if ($activeModules->count() > 0) {
                $sync = new \App\Services\Tenant\TenantModuleSyncService();
                foreach ($activeModules as $module) {
                    $sync->activateModule($tenant, $module->slug);
                }

                \Log::info('Módulos replicados para nova empresa (com dependências e seeds)', [
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
        
        // Verificar se pode deletar (sem faturas)
        $canDelete = $tenant->canBeDeleted();
        
        if (!$canDelete['can_delete']) {
            $this->dispatch('error', message: $canDelete['reason'] . ' (' . $canDelete['invoices_count'] . ' fatura(s) emitida(s))');
            return;
        }
        
        // Verificar se tem clientes
        if (class_exists('\App\Models\Invoicing\Client')) {
            $clientsCount = \App\Models\Invoicing\Client::where('tenant_id', $tenantId)->count();
            if ($clientsCount > 0) {
                $this->dispatch('warning', message: "Esta empresa possui {$clientsCount} cliente(s) cadastrado(s). Ao eliminar, todos os dados serão perdidos PERMANENTEMENTE da base de dados.");
            }
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
        
        // Verificar se pode ser deletada (sem faturas)
        $canDelete = $tenant->canBeDeleted();
        
        if (!$canDelete['can_delete']) {
            $this->closeDeleteModal();
            $this->dispatch('error', message: $canDelete['reason'] . ' (' . $canDelete['invoices_count'] . ' fatura(s) emitida(s))');
            return;
        }
        
        \DB::beginTransaction();
        try {
            \Log::info('🗑️ Deletando empresa permanentemente', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);
            
            // Deletar PERMANENTEMENTE (forceDelete irá acionar o evento deleting do boot)
            $tenant->forceDelete();
            
            \DB::commit();
            
            \Log::info('✅ Empresa deletada com sucesso', [
                'tenant_id' => $tenant->id,
            ]);
            
            $this->closeDeleteModal();
            $this->loadAccountData();
            $this->dispatch('success', message: 'Empresa eliminada permanentemente com sucesso!');
            
            // Se deletou a empresa ativa, trocar para outra
            if (activeTenantId() == $this->companyToDelete) {
                $firstTenant = $user->tenants()->first();
                if ($firstTenant) {
                    $user->switchTenant($firstTenant->id);
                    // Recarregar página para atualizar contexto
                    $this->dispatch('tenant-switched-reload');
                }
            }
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('❌ Erro ao eliminar empresa', [
                'user_id' => $user->id,
                'tenant_id' => $this->companyToDelete,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            'editCompanyRegime' => 'required|in:' . implode(',', array_merge(array_keys(\App\Models\Tenant::REGIMES), array_keys(\App\Models\Tenant::REGIME_ALIASES))),
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
            
            $previousRegime = $tenant->regime;
            
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
            
            // Sincronizar taxes / settings / produtos com o novo regime fiscal
            $syncResult = ['changed' => false];
            if ($previousRegime !== $this->editCompanyRegime) {
                $syncResult = (new \App\Services\Tenant\TaxRegimeSyncer())
                    ->sync($tenant->fresh(), $previousRegime);
            }
            
            \DB::commit();
            
            $this->closeEditCompanyModal();
            $this->loadAccountData();
            
            $msg = 'Empresa atualizada com sucesso!';
            if (!empty($syncResult['changed'])) {
                if (($syncResult['mode'] ?? '') === 'apply-exempt') {
                    $count = $syncResult['products_count'] ?? 0;
                    $msg .= " Regime de isenção aplicado: tax default ISE marcada e {$count} produto(s) atualizado(s).";
                } elseif (($syncResult['mode'] ?? '') === 'revert-exempt') {
                    $msg .= ' Saída do regime isento: tax default ISE removida.';
                }
            }
            $this->dispatch('success', message: $msg);
            
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
        
        // BUG-07 FIX: Verificar limite por contagem total (não por índice)
        if (!$user->is_super_admin && $this->hasExceededLimit) {
            $this->dispatch('error', message: 
                "🔒 Limite excedido! Seu plano permite {$this->maxAllowed} empresa(s) mas tem {$this->currentCount}. " .
                "Faça upgrade ou remova uma empresa."
            );
            return;
        }
        
        if ($user->switchTenant($tenantId)) {
            $this->dispatch('success', message: 'Empresa alterada com sucesso!');
            $this->loadAccountData();
            
            return redirect()->to(route('my-account') . '?tab=companies');
        }
        
        $this->dispatch('error', message: 'Não foi possível alternar para esta empresa.');
    }
    
    // ==================== PERFIL ====================
    
    public function updateProfile()
    {
        $user = auth()->user();
        
        $this->validate([
            'userName' => 'required|min:3|max:255',
            'userEmail' => 'required|email|unique:users,email,' . $user->id,
            'userPhone' => 'nullable|max:20',
            'userBio' => 'nullable|max:500',
            'userAvatar' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
        ], [
            'userName.required' => 'Nome é obrigatório',
            'userName.min' => 'Nome deve ter no mínimo 3 caracteres',
            'userEmail.required' => 'Email é obrigatório',
            'userEmail.email' => 'Email inválido',
            'userEmail.unique' => 'Este email já está em uso',
            'userBio.max' => 'Biografia não pode ter mais de 500 caracteres',
            'userAvatar.image' => 'O arquivo deve ser uma imagem',
            'userAvatar.max' => 'Imagem não pode ser maior que 2MB',
        ]);
        
        try {
            $dataToUpdate = [
                'name' => $this->userName,
                'email' => $this->userEmail,
                'phone' => $this->userPhone,
                'bio' => $this->userBio,
            ];
            
            // Upload de avatar
            if ($this->userAvatar) {
                // Deletar avatar antigo
                if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                    \Storage::disk('public')->delete($user->avatar);
                }
                
                $avatarPath = $this->userAvatar->store('avatars', 'public');
                $dataToUpdate['avatar'] = $avatarPath;
                $this->currentAvatar = $avatarPath;
            }
            
            $user->update($dataToUpdate);
            
            $this->userAvatar = null;
            $this->dispatch('success', message: 'Perfil atualizado com sucesso!');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar perfil', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao atualizar perfil: ' . $e->getMessage());
        }
    }
    
    public function removeAvatar()
    {
        $user = auth()->user();
        
        try {
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }
            
            $user->update(['avatar' => null]);
            $this->currentAvatar = null;
            $this->dispatch('success', message: 'Avatar removido com sucesso!');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao remover avatar', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao remover avatar: ' . $e->getMessage());
        }
    }
    
    // ==================== UPGRADE ====================

    /**
     * O que esta conta ainda pode receber de graça.
     *
     * A regra vive em DireitoACortesia, partilhada com o registo: uma cortesia
     * por cliente, para sempre — plano gratuito ou período de teste, o
     * primeiro que se usar gasta o direito ao outro.
     */
    public function getDireitoACortesiaProperty(): DireitoACortesia
    {
        $empresa = auth()->user()?->activeTenant();

        return $empresa
            ? DireitoACortesia::daEmpresa($empresa, auth()->user())
            : DireitoACortesia::de(auth()->user());
    }

    /**
     * Porque é que este plano não pode ser escolhido — ou null se puder.
     *
     * Antes olhava só para a bandeira `is_promotional` e só para o MESMO
     * plano na MESMA empresa: bastava criar outra empresa, ou saltar do FOX
     * Friendly para os 30 dias de teste do Business e daí para os do
     * Enterprise, e a casa continuava a pagar.
     */
    private function motivoParaRecusarPlano($plan, $tenant = null): ?string
    {
        if (!$plan) {
            return null;
        }

        $tenant = $tenant ?: auth()->user()?->activeTenant();

        $direito = $tenant
            ? DireitoACortesia::daEmpresa($tenant, auth()->user())
            : DireitoACortesia::de(auth()->user());

        return $direito->motivoParaRecusar($plan);
    }

    /**
     * Só quem gere a conta (dono/Admin) pode mexer no plano e na faturação.
     * Antes, qualquer utilizador da empresa (caixa, vendedor) podia trocar o plano.
     */
    private function guardCanManage(): bool
    {
        if (auth()->user()?->canManageAccount()) {
            return true;
        }

        $this->dispatch('error', message: 'Sem permissão para gerir o plano/faturação desta conta.');
        return false;
    }

    public function openUpgradeModal($planId)
    {
        if (!$this->guardCanManage()) {
            return;
        }

        $plan = \App\Models\Plan::with('modules')->find($planId);

        if (!$plan) {
            $this->dispatch('error', message: 'Plano não encontrado!');
            return;
        }

        // Cortesia é uma só, para sempre: gratuito ou teste, o primeiro gasta o outro.
        if ($recusa = $this->motivoParaRecusarPlano($plan)) {
            $this->dispatch('error', message: $recusa);
            return;
        }

        $this->selectedPlanForUpgrade = $plan;
        // Planos com trial e auto-ativação ficam fixos no ciclo equivalente ao trial
        // (ex.: FOX Friendly com 90 dias → 'quarterly'). O utilizador não pode escolher outro.
        if ($plan->auto_activate && (int) $plan->trial_days > 0) {
            $this->upgradeBillingCycle = match (true) {
                $plan->trial_days >= 360 => 'yearly',
                $plan->trial_days >= 150 => 'semiannual',
                $plan->trial_days >= 80  => 'quarterly',
                default => 'monthly',
            };
        } else {
            $this->upgradeBillingCycle = 'monthly';
        }
        $this->showUpgradeModal = true;
    }
    
    public function closeUpgradeModal()
    {
        $this->showUpgradeModal = false;
        $this->selectedPlanForUpgrade = null;
        $this->upgradeBillingCycle = 'monthly';
        $this->upgradeStep = 1;
        $this->paymentProof = null;
    }
    
    public function goToPaymentStep()
    {
        $this->upgradeStep = 2;
    }
    
    public function backToSelectPlan()
    {
        $this->upgradeStep = 1;
    }
    
    public function processUpgrade()
    {
        if (!$this->guardCanManage()) {
            return;
        }

        if (!$this->selectedPlanForUpgrade) {
            $this->dispatch('error', message: 'Nenhum plano selecionado!');
            return;
        }
        
        $user = auth()->user();
        $activeTenant = $user->activeTenant();
        
        if (!$activeTenant) {
            $this->dispatch('error', message: 'Nenhuma empresa ativa encontrada!');
            return;
        }

        // Defesa dupla — o modal pode ser contornado, o pedido vem de fora.
        if ($recusa = $this->motivoParaRecusarPlano($this->selectedPlanForUpgrade, $activeTenant)) {
            $this->dispatch('error', message: $recusa);
            return;
        }

        \DB::beginTransaction();
        try {
            $plan = $this->selectedPlanForUpgrade;
            
            // Calcular valor baseado no ciclo
            $amount = match($this->upgradeBillingCycle) {
                'yearly' => $plan->price_yearly,
                'semiannual' => $plan->price_semiannual,
                'quarterly' => $plan->price_quarterly,
                default => $plan->price_monthly,
            };
            
            // Upload do comprovativo
            $proofPath = null;
            if ($this->paymentProof) {
                $proofPath = $this->paymentProof->store('payment-proofs', 'public');
            }
            
            // Determinar se este plano tem auto-activação (ex.: FOX Friendly trial)
            // O período de teste só se dá a quem ainda o tem por gastar. A
            // quem já o usou, o pedido segue o caminho normal: transferência,
            // comprovativo, aprovação.
            $direito = DireitoACortesia::daEmpresa($activeTenant, $user);

            $autoActivate = (bool) $plan->auto_activate
                && (int) $plan->trial_days > 0
                && !$proofPath
                && $direito->temDireitoATeste($plan);

            // BUG-05 FIX: Criar pedido. Se tem auto-activação, criar como pending e
            // imediatamente actualizar para 'approved' — o OrderObserver despacha
            // toda a lógica de cancelar subscription antiga + criar nova + sync módulos.
            $order = \App\Models\Order::create([
                'tenant_id' => $activeTenant->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $amount,
                'billing_cycle' => $this->upgradeBillingCycle,
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'payment_proof' => $proofPath,
                'notes' => $autoActivate
                    ? "Pedido auto-aprovado. Plano '{$plan->name}' com {$plan->trial_days} dias de trial gratuito (auto_activate=true)."
                    : null,
            ]);

            if ($autoActivate) {
                // Disparar OrderObserver::updated → processApproval (renova subscription)
                $order->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => null, // Sistema
                ]);
                \Log::info('✅ Order auto-aprovada (plano com auto_activate)', [
                    'order_id' => $order->id,
                    'plan' => $plan->name,
                    'trial_days' => $plan->trial_days,
                ]);
            }

            \DB::commit();

            $this->closeUpgradeModal();
            $this->loadAccountData();

            if ($autoActivate) {
                $message = "🎉 Plano '{$plan->name}' activado com {$plan->trial_days} dias gratuitos! Bem-vindo de volta.";
                $this->dispatch('success', message: $message);
                // Redirect para home — subscription já renovada
                return redirect()->route('home');
            }

            $message = $proofPath
                ? '✅ Pedido criado! Comprovativo anexado. Aguarde a validação da nossa equipe (até 24h úteis).'
                : '✅ Pedido criado! Efetue a transferência e anexe o comprovativo clicando em "Pagar".';

            $this->dispatch('success', message: $message);

            // Mudar para tab de faturas
            $this->activeTab = 'billing';
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Erro ao processar upgrade', [
                'user_id' => $user->id,
                'plan_id' => $this->selectedPlanForUpgrade->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao processar upgrade: ' . $e->getMessage());
        }
    }
    
    // ==================== PEDIDOS/FATURAS ====================
    
    public function viewOrder($orderId)
    {
        $order = \App\Models\Order::with(['plan', 'tenant'])->find($orderId);
        
        if (!$order || $order->user_id !== auth()->id()) {
            $this->dispatch('error', message: 'Pedido não encontrado!');
            return;
        }
        
        $this->viewingOrder = $order;
        $this->showOrderViewModal = true;
    }
    
    public function closeOrderViewModal()
    {
        $this->showOrderViewModal = false;
        $this->viewingOrder = null;
    }
    
    public function openPaymentModal($orderId)
    {
        $order = \App\Models\Order::with(['plan', 'tenant'])->find($orderId);
        
        if (!$order || $order->user_id !== auth()->id()) {
            $this->dispatch('error', message: 'Pedido não encontrado!');
            return;
        }
        
        $this->payingOrder = $order;
        $this->showOrderPaymentModal = true;
    }
    
    public function closePaymentModal()
    {
        $this->showOrderPaymentModal = false;
        $this->payingOrder = null;
        $this->newPaymentProof = null;
    }
    
    public function uploadPaymentProof()
    {
        if (!$this->payingOrder || !$this->newPaymentProof) {
            $this->dispatch('error', message: 'Nenhum comprovativo selecionado!');
            return;
        }

        // O ficheiro ia para o disco PÚBLICO sem qualquer validação de tipo ou
        // tamanho — qualquer ficheiro (incluindo executável) ficava acessível
        // por URL. Mesmas regras do wizard de registo.
        $this->validate([
            'newPaymentProof' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'newPaymentProof.mimes' => 'O comprovativo deve ser PDF, JPG ou PNG.',
            'newPaymentProof.max'   => 'O comprovativo não pode exceder 5 MB.',
        ]);
        
        try {
            // Upload do arquivo
            $proofPath = $this->newPaymentProof->store('payment-proofs', 'public');
            
            // Atualizar pedido
            $this->payingOrder->update([
                'payment_proof' => $proofPath,
            ]);
            
            $this->closePaymentModal();
            $this->dispatch('success', message: '✅ Comprovativo anexado com sucesso! Aguarde a validação da nossa equipe.');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao anexar comprovativo', [
                'order_id' => $this->payingOrder->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao anexar comprovativo: ' . $e->getMessage());
        }
    }
    
    // ==================== SEGURANÇA ====================
    
    public function toggleChangePasswordForm()
    {
        $this->showChangePasswordForm = !$this->showChangePasswordForm;
        $this->reset(['currentPassword', 'newPassword', 'confirmPassword']);
    }
    
    public function changePassword()
    {
        $user = auth()->user();
        
        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|min:8|different:currentPassword',
            'confirmPassword' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Senha atual é obrigatória',
            'newPassword.required' => 'Nova senha é obrigatória',
            'newPassword.min' => 'Nova senha deve ter no mínimo 8 caracteres',
            'newPassword.different' => 'Nova senha deve ser diferente da atual',
            'confirmPassword.required' => 'Confirmação de senha é obrigatória',
            'confirmPassword.same' => 'As senhas não coincidem',
        ]);
        
        try {
            // Verificar senha atual
            if (!\Hash::check($this->currentPassword, $user->password)) {
                $this->addError('currentPassword', 'Senha atual incorreta');
                return;
            }
            
            // Atualizar senha
            $user->update([
                'password' => \Hash::make($this->newPassword),
            ]);
            
            // Atualizar last_password_changed
            $user->update(['last_password_changed' => now()]);
            
            $this->reset(['currentPassword', 'newPassword', 'confirmPassword']);
            $this->showChangePasswordForm = false;
            $this->dispatch('success', message: 'Senha alterada com sucesso!');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao alterar senha', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao alterar senha: ' . $e->getMessage());
        }
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
        
        // Buscar todos os planos disponíveis para upgrade
        $availablePlans = \App\Models\Plan::where('is_active', true)
            ->with('modules')
            ->orderBy('price_monthly')
            ->get();
        
        // Buscar histórico de pedidos/faturas
        $orders = Order::with(['tenant', 'plan'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);
        
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
        
        return view('livewire.my-account', compact('myTenants', 'currentPlan', 'currentSubscription', 'availablePlans', 'orders', 'pendingOrders', 'pendingSubscriptions'));
    }
    
    /**
     * Criar dados padrão de contabilidade para novo tenant
     */
    private function createDefaultAccountingData($tenantId)
    {
        try {
            // Criar Plano de Contas (71 contas SNC Angola)
            $accountSeeder = new \Database\Seeders\Accounting\AccountSeeder();
            $accountSeeder->runForTenant($tenantId);
            
            // Criar Diários padrão (6 diários)
            $journalSeeder = new \Database\Seeders\Accounting\JournalSeeder();
            $journalSeeder->runForTenant($tenantId);
            
            // Criar Períodos para o ano atual
            $periodSeeder = new \Database\Seeders\Accounting\PeriodSeeder();
            $periodSeeder->runForTenant($tenantId);
            
            \Log::info('✅ Dados de contabilidade criados', [
                'tenant_id' => $tenantId,
                'contas' => 71,
                'diarios' => 6,
                'periodos' => 12
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar dados de contabilidade', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            // Não lançar exceção para não quebrar a criação da empresa
        }
    }
}

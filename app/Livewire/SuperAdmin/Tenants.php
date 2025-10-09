<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Gestão de Tenants')]
class Tenants extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingTenantId = null;
    
    // Delete modal
    public $showDeleteModal = false;
    public $deletingTenantId = null;
    public $deletingTenantName = '';
    
    // View modal
    public $showViewModal = false;
    public $viewingTenant = null;
    
    // Users management
    public $showUsersModal = false;
    public $managingTenantId = null;
    public $showAddUserModal = false;
    public $selectedUserId = null;
    public $selectedRoleId = null;
    public $newUserName = '';
    public $newUserEmail = '';
    public $newUserPassword = '';
    public $createNewUser = 0; // 0 = existente, 1 = novo
    
    // Plan management
    public $showPlanModal = false;
    public $managingPlanTenantId = null;
    public $selectedPlanId = null;
    public $billingCycle = 'monthly';
    
    // Deactivation modal
    public $showDeactivationModal = false;
    public $deactivatingTenantId = null;
    public $deactivatingTenantName = '';
    public $deactivationReason = '';
    
    // Loading states
    public $activatingTenantId = null;
    public $deactivatingLoadingId = null;
    
    // Form fields
    public $name, $slug, $email, $phone, $company_name, $nif;
    public $address, $city, $postal_code, $country = 'Portugal';
    public $max_users = 5, $max_storage_mb = 1000;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:tenants,slug',
        'email' => 'required|email',
        'company_name' => 'nullable',
        'nif' => 'nullable',
        'phone' => 'nullable',
        'max_users' => 'required|integer|min:1',
        'max_storage_mb' => 'required|integer|min:100',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $tenant = Tenant::findOrFail($id);
        $this->editingTenantId = $id;
        $this->fill($tenant->toArray());
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editingTenantId) {
            $this->rules['slug'] = 'required|unique:tenants,slug,' . $this->editingTenantId;
        }

        $this->validate();

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'nif' => $this->nif,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'max_users' => $this->max_users,
            'max_storage_mb' => $this->max_storage_mb,
            'is_active' => $this->is_active,
        ];
        if ($this->editingTenantId) {
            Tenant::find($this->editingTenantId)->update($data);
            $this->dispatch('success', message: 'Tenant atualizado com sucesso!');
        } else {
            Tenant::create($data);
            $this->dispatch('success', message: 'Tenant criado com sucesso!');
        }

        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        if ($tenant->is_active) {
            // Vai desativar - abrir modal para motivo
            $this->deactivatingTenantId = $id;
            $this->deactivatingTenantName = $tenant->name;
            $this->showDeactivationModal = true;
        } else {
            // Vai ativar - fazer direto
            $this->activateTenant($id);
        }
    }
    
    public function confirmDeactivation()
    {
        $this->validate([
            'deactivationReason' => 'required|min:10',
        ], [
            'deactivationReason.required' => 'Por favor, informe o motivo da desativação.',
            'deactivationReason.min' => 'O motivo deve ter pelo menos 10 caracteres.',
        ]);
        
        $this->deactivatingLoadingId = $this->deactivatingTenantId; // Ativar loading
        
        try {
            $tenant = Tenant::findOrFail($this->deactivatingTenantId);
            $usersCount = $tenant->users()->count();
            
            $tenant->update([
                'is_active' => false,
                'deactivation_reason' => $this->deactivationReason,
                'deactivated_at' => now(),
                'deactivated_by' => auth()->id(),
            ]);
            
            // Enviar notificação para todos os usuários do tenant (PODE DEMORAR)
            $this->sendSuspensionNotification($tenant);
            
            $this->dispatch('warning', message: 
                "⚠️ Tenant '{$tenant->name}' desativado! {$usersCount} usuário(s) notificados por email."
            );
            
            $this->closeDeactivationModal();
            
        } finally {
            $this->deactivatingLoadingId = null; // Desativar loading
        }
    }
    
    public function activateTenant($id)
    {
        $this->activatingTenantId = $id; // Ativar loading
        
        try {
            $tenant = Tenant::findOrFail($id);
            $usersCount = $tenant->users()->count();
            
            $tenant->update([
                'is_active' => true,
                'deactivation_reason' => null,
                'deactivated_at' => null,
                'deactivated_by' => null,
            ]);
            
            // Enviar notificação de reativação para todos os usuários (PODE DEMORAR)
            $this->sendReactivationNotification($tenant);
            
            $this->dispatch('success', message: "✓ Tenant '{$tenant->name}' reativado! {$usersCount} usuário(s) notificados por email.");
            
        } finally {
            $this->activatingTenantId = null; // Desativar loading
        }
    }
    
    public function closeDeactivationModal()
    {
        $this->showDeactivationModal = false;
        $this->deactivatingTenantId = null;
        $this->deactivatingTenantName = '';
        $this->deactivationReason = '';
    }

    public function openDeleteModal($id)
    {
        $tenant = Tenant::findOrFail($id);
        $this->deletingTenantId = $id;
        $this->deletingTenantName = $tenant->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingTenantId = null;
        $this->deletingTenantName = '';
    }

    public function confirmDelete()
    {
        try {
            Tenant::findOrFail($this->deletingTenantId)->delete();
            $this->dispatch('success', message: 'Tenant excluído com sucesso!');
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir tenant!');
        }
    }

    public function viewDetails($id)
    {
        $this->viewingTenant = Tenant::findOrFail($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingTenant = null;
    }

    public function editFromView()
    {
        $this->edit($this->viewingTenant->id);
        $this->showViewModal = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'slug', 'email', 'phone', 'company_name', 'nif', 
                     'address', 'city', 'postal_code', 'editingTenantId']);
        $this->country = 'Portugal';
        $this->max_users = 5;
        $this->max_storage_mb = 1000;
        $this->is_active = true;
    }

    // Gestão de Usuários
    public function manageUsers($tenantId)
    {
        $this->managingTenantId = $tenantId;
        $this->showUsersModal = true;
    }
    
    public function closeUsersModal()
    {
        $this->showUsersModal = false;
        $this->managingTenantId = null;
        $this->resetUserForm();
    }
    
    public function openAddUserModal()
    {
        $this->resetUserForm();
        $this->showAddUserModal = true;
    }
    
    public function closeAddUserModal()
    {
        $this->showAddUserModal = false;
        $this->resetUserForm();
    }
    
    private function resetUserForm()
    {
        $this->selectedUserId = null;
        $this->selectedRoleId = null;
        $this->newUserName = '';
        $this->newUserEmail = '';
        $this->newUserPassword = '';
        $this->createNewUser = 0; // Reset para "existente"
    }
    
    public function addUserToTenant()
    {
        if ($this->createNewUser == 1 || $this->createNewUser === true) {
            // Criar novo usuário
            $this->validate([
                'newUserName' => 'required|min:3',
                'newUserEmail' => 'required|email|unique:users,email',
                'newUserPassword' => 'required|min:6',
                'selectedRoleId' => 'required',
            ]);
            
            $user = \App\Models\User::create([
                'name' => $this->newUserName,
                'email' => $this->newUserEmail,
                'password' => \Hash::make($this->newUserPassword),
                'is_active' => true,
            ]);
            
            $userId = $user->id;
            $message = 'Usuário criado e adicionado ao tenant com sucesso!';
        } else {
            // Adicionar usuário existente
            $this->validate([
                'selectedUserId' => 'required|exists:users,id',
                'selectedRoleId' => 'required',
            ]);
            
            $userId = $this->selectedUserId;
            $message = 'Usuário adicionado ao tenant com sucesso!';
        }
        
        // Verificar se já existe
        $exists = \DB::table('tenant_user')
            ->where('tenant_id', $this->managingTenantId)
            ->where('user_id', $userId)
            ->exists();
            
        if ($exists) {
            $this->dispatch('error', message: 'Usuário já pertence a este tenant!');
            return;
        }
        
        // Verificar limite de empresas do usuário
        $user = \App\Models\User::find($userId);
        if (!$user->is_super_admin && !$user->canAddMoreCompanies()) {
            $maxAllowed = $user->getMaxCompaniesLimit();
            $currentCount = $user->tenants()->count();
            
            $this->dispatch('error', message: 
                "❌ Limite de empresas excedido! " .
                "Este usuário já gerencia {$currentCount} empresa(s), mas seu plano permite apenas {$maxAllowed}. " .
                "Faça upgrade do plano antes de adicionar a mais empresas."
            );
            return;
        }
        
        // Adicionar à tenant_user (sem role_id)
        $user = \App\Models\User::find($userId);
        if (!$user->tenants()->where('tenants.id', $this->managingTenantId)->exists()) {
            $user->tenants()->attach($this->managingTenantId, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }
        
        // Atribuir role usando Spatie Permission
        setPermissionsTeamId($this->managingTenantId);
        $role = \Spatie\Permission\Models\Role::find($this->selectedRoleId);
        if ($role) {
            $user->assignRole($role);
        }
        
        $this->dispatch('success', message: $message);
        $this->closeAddUserModal();
    }
    
    public function removeUserFromTenant($userId)
    {
        $user = \App\Models\User::find($userId);
        
        // Remover roles do tenant
        setPermissionsTeamId($this->managingTenantId);
        $user->roles()->wherePivot('tenant_id', $this->managingTenantId)->detach();
        
        // Remover da pivot table
        $user->tenants()->detach($this->managingTenantId);
            
        $this->dispatch('success', message: 'Usuário removido do tenant com sucesso!');
    }
    
    public function updateUserRole($userId, $roleId)
    {
        $user = \App\Models\User::find($userId);
        
        setPermissionsTeamId($this->managingTenantId);
        
        // Remover roles antigas do tenant
        $user->roles()->wherePivot('tenant_id', $this->managingTenantId)->detach();
        
        // Atribuir nova role
        $role = \Spatie\Permission\Models\Role::find($roleId);
        if ($role) {
            $user->assignRole($role);
        }
            
        $this->dispatch('success', message: 'Role atualizada com sucesso!');
    }
    
    // Plan Management
    public function managePlan($tenantId)
    {
        $this->managingPlanTenantId = $tenantId;
        $tenant = Tenant::with('activeSubscription.plan')->find($tenantId);
        
        if ($tenant->activeSubscription) {
            $this->selectedPlanId = $tenant->activeSubscription->plan_id;
            $this->billingCycle = $tenant->activeSubscription->billing_cycle ?? 'monthly';
        }
        
        $this->showPlanModal = true;
    }
    
    public function closePlanModal()
    {
        $this->showPlanModal = false;
        $this->managingPlanTenantId = null;
        $this->selectedPlanId = null;
        $this->billingCycle = 'monthly';
    }
    
    public function updateTenantPlan()
    {
        $this->validate([
            'selectedPlanId' => 'required|exists:plans,id',
            'billingCycle' => 'required|in:monthly,quarterly,semiannual,yearly',
        ]);
        
        \DB::beginTransaction();
        
        try {
            $tenant = Tenant::find($this->managingPlanTenantId);
            $plan = \App\Models\Plan::find($this->selectedPlanId);
            
            // Atualizar ou criar subscription
            $subscription = $tenant->activeSubscription;
            
            if ($subscription) {
                // Atualizar subscription existente
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $this->billingCycle,
                    'amount' => $plan->getPrice($this->billingCycle),
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                ]);
            } else {
                // Criar nova subscription
                $tenant->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $this->billingCycle,
                    'amount' => $plan->getPrice($this->billingCycle),
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]);
            }
            
            // Atualizar limites do tenant baseado no plano
            $tenant->update([
                'max_users' => $plan->max_users,
                'max_storage_mb' => $plan->max_storage_mb,
            ]);
            
            // Sincronizar módulos do plano com o tenant
            $this->syncPlanModules($tenant, $plan);
            
            \DB::commit();
            
            $this->dispatch('success', message: "Plano alterado para {$plan->name} com sucesso! Limites e módulos sincronizados.");
            $this->closePlanModal();
            
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->dispatch('error', message: 'Erro ao alterar plano: ' . $e->getMessage());
        }
    }
    
    private function syncPlanModules($tenant, $plan)
    {
        // Remover todos os módulos antigos
        $tenant->modules()->detach();
        
        // Adicionar módulos do novo plano
        if ($plan->included_modules && is_array($plan->included_modules)) {
            foreach ($plan->included_modules as $moduleSlug) {
                $module = \App\Models\Module::where('slug', $moduleSlug)->first();
                if ($module) {
                    $tenant->modules()->attach($module->id, [
                        'is_active' => true,
                        'activated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function render()
    {
        $tenants = Tenant::with('activeSubscription.plan')
            ->where('name', 'like', '%' . $this->search . '%')
            ->orWhere('email', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        // Para modal de usuários
        $tenantUsers = [];
        $availableUsers = [];
        $roles = [];
        
        if ($this->managingTenantId) {
            $tenant = \App\Models\Tenant::find($this->managingTenantId);
            $tenantUsers = $tenant->users()->withPivot('is_active', 'joined_at')->get();
            
            // Buscar roles de cada usuário via Spatie
            setPermissionsTeamId($this->managingTenantId);
            foreach ($tenantUsers as $tenantUser) {
                // A tabela model_has_roles usa 'tenant_id' como pivot
                $tenantUser->current_role = $tenantUser->roles()
                    ->wherePivot('tenant_id', $this->managingTenantId)
                    ->first();
            }
            
            // Usuários disponíveis (que não estão neste tenant)
            $userIdsInTenant = $tenantUsers->pluck('id')->toArray();
            $availableUsers = \App\Models\User::whereNotIn('id', $userIdsInTenant)
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get();
            
            // Buscar roles do tenant específico
            $roles = \Spatie\Permission\Models\Role::where('tenant_id', $this->managingTenantId)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.super-admin.tenants.tenants', compact('tenants', 'tenantUsers', 'availableUsers', 'roles'));
    }
    
    protected function sendSuspensionNotification($tenant)
    {
        try {
            \Log::info('📧 Enviando notificação de suspensão para usuários do tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);
            
            // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            if (!$smtpSetting) {
                \Log::error('❌ Configuração SMTP não encontrada no banco');
                return;
            }
            
            \Log::info('📧 Configuração SMTP encontrada', [
                'host' => $smtpSetting->host,
                'port' => $smtpSetting->port,
                'encryption' => $smtpSetting->encryption,
            ]);
            
            // CONFIGURAR SMTP usando método configure() do modelo
            $smtpSetting->configure();
            \Log::info('✅ SMTP configurado do banco de dados');
            
            // BUSCAR TEMPLATE DO BANCO
            $template = \App\Models\EmailTemplate::where('slug', 'account_suspended')->first();
            
            if (!$template) {
                \Log::error('❌ Template account_suspended não encontrado');
                return;
            }
            
            \Log::info('📄 Template account_suspended encontrado', [
                'id' => $template->id,
                'subject' => $template->subject,
            ]);
            
            // Buscar todos os usuários do tenant
            $users = $tenant->users()->get();
            
            foreach ($users as $user) {
                if (!$user->email) {
                    \Log::warning('Usuário sem email', ['user_id' => $user->id]);
                    continue;
                }
                
                // Dados para o template
                $data = [
                    'user_name' => $user->name,
                    'tenant_name' => $tenant->name,
                    'reason' => $tenant->deactivation_reason ?? 'Conta suspensa por motivos administrativos.',
                    'app_name' => config('app.name', 'SOS ERP'),
                    'app_url' => config('app.url'),
                    'support_email' => $smtpSetting->from_email,
                ];
                
                // Renderizar template do BD
                $rendered = $template->render($data);
                
                \Log::info('📧 Template renderizado', [
                    'to' => $user->email,
                    'subject' => $rendered['subject'],
                ]);
                
                // Enviar email usando HTML DO TEMPLATE
                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
                    $message->to($user->email, $user->name)
                            ->subject($rendered['subject'])
                            ->html($rendered['body_html']);
                });
                
                \Log::info('✅ Email de suspensão enviado', ['to' => $user->email]);
            }
            
            \Log::info('✅ Todas as notificações de suspensão foram enviadas');
            
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao enviar notificações de suspensão', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    
    protected function sendReactivationNotification($tenant)
    {
        try {
            \Log::info('📧 Enviando notificação de reativação para usuários do tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);
            
            // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            if (!$smtpSetting) {
                \Log::error('❌ Configuração SMTP não encontrada no banco');
                return;
            }
            
            \Log::info('📧 Configuração SMTP encontrada', [
                'host' => $smtpSetting->host,
                'port' => $smtpSetting->port,
                'encryption' => $smtpSetting->encryption,
            ]);
            
            // CONFIGURAR SMTP usando método configure() do modelo
            $smtpSetting->configure();
            \Log::info('✅ SMTP configurado do banco de dados');
            
            // BUSCAR TEMPLATE DO BANCO
            $template = \App\Models\EmailTemplate::where('slug', 'account_reactivated')->first();
            
            if (!$template) {
                \Log::error('❌ Template account_reactivated não encontrado');
                return;
            }
            
            \Log::info('📄 Template account_reactivated encontrado', [
                'id' => $template->id,
                'subject' => $template->subject,
            ]);
            
            // Buscar todos os usuários do tenant
            $users = $tenant->users()->get();
            
            foreach ($users as $user) {
                if (!$user->email) {
                    \Log::warning('Usuário sem email', ['user_id' => $user->id]);
                    continue;
                }
                
                // Dados para o template
                $data = [
                    'user_name' => $user->name,
                    'tenant_name' => $tenant->name,
                    'app_name' => config('app.name', 'SOS ERP'),
                    'app_url' => config('app.url'),
                    'support_email' => $smtpSetting->from_email,
                    'login_url' => route('login'),
                ];
                
                // Renderizar template do BD
                $rendered = $template->render($data);
                
                \Log::info('📧 Template renderizado', [
                    'to' => $user->email,
                    'subject' => $rendered['subject'],
                ]);
                
                // Enviar email usando HTML DO TEMPLATE
                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
                    $message->to($user->email, $user->name)
                            ->subject($rendered['subject'])
                            ->html($rendered['body_html']);
                });
                
                \Log::info('✅ Email de reativação enviado', ['to' => $user->email]);
            }
            
            \Log::info('✅ Todas as notificações de reativação foram enviadas');
            
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao enviar notificações de reativação', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

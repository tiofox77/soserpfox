<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Gestão de Utilizadores')]
class UserManagement extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingUserId = null;
    public $activeTab = 'create'; // 'create' ou 'invite'
    
    // Form fields
    public $name, $email, $password, $password_confirmation;
    public $is_active = true;
    public $selectedTenants = []; // IDs dos tenants selecionados
    public $selectedRoles = []; // [tenant_id => role_id]
    public $assignToAllTenants = false;
    
    // Invite fields
    public $inviteName = '';
    public $inviteEmail = '';
    public $inviteRole = '';
    public $sending = false;
    
    // Filters
    public $search = '';
    public $filterTenant = '';
    public $filterRole = '';
    
    protected function rules()
    {
        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'is_active' => 'boolean',
        ];
        
        if (!$this->editingUserId) {
            $rules['password'] = 'required|min:6|confirmed';
        } else {
            $rules['email'] = 'required|email|unique:users,email,' . $this->editingUserId;
            if ($this->password) {
                $rules['password'] = 'min:6|confirmed';
            }
        }
        
        return $rules;
    }

    public function create()
    {
        $this->resetForm();
        $this->activeTab = 'create';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::with('roles', 'tenants')->findOrFail($id);
        $currentTenantId = activeTenantId();
        
        // Verificar se o usuário pertence ao tenant atual (via pivot)
        if (!auth()->user()->is_super_admin) {
            $belongsToTenant = $user->tenants()->where('tenants.id', $currentTenantId)->exists();
            if (!$belongsToTenant) {
                $this->dispatch('error', message: 'Sem permissão para editar este usuário');
                return;
            }
        }
        
        $this->editingUserId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_active = $user->is_active;
        
        // Carregar TODAS as empresas que o usuário já tem acesso
        $this->selectedTenants = $user->tenants->pluck('id')->toArray();
        
        // Carregar roles de TODAS as empresas do usuário
        foreach ($user->tenants as $tenant) {
            $userRole = $user->roles()->wherePivot('tenant_id', $tenant->id)->first();
            $this->selectedRoles[$tenant->id] = $userRole ? $userRole->id : null;
        }
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();
        
        // Verificar limite de usuários ao criar novo (não ao editar)
        if (!$this->editingUserId) {
            $tenant = Tenant::find(activeTenantId());
            
            if (!$tenant->canAddUser()) {
                $currentUsers = $tenant->users()->count();
                $maxUsers = $tenant->getMaxUsers();
                
                $this->dispatch('error', message: "Limite de utilizadores atingido! O seu plano permite {$maxUsers} utilizador(es) e já tem {$currentUsers}. Faça upgrade do plano para adicionar mais.");
                return;
            }
        }
        
        DB::beginTransaction();
        try {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'is_active' => $this->is_active,
            ];
            
            if ($this->password) {
                $data['password'] = Hash::make($this->password);
            }
            
            if ($this->editingUserId) {
                $user = User::find($this->editingUserId);
                $user->update($data);
                $message = 'Utilizador atualizado com sucesso!';
            } else {
                $user = User::create($data);
                $message = 'Utilizador criado com sucesso!';
            }
            
            // Sincronizar tenants
            $this->syncUserTenants($user);
            
            DB::commit();
            
            $this->dispatch('success', message: $message);
            $this->closeModal();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Erro ao salvar utilizador: ' . $e->getMessage());
        }
    }
    
    protected function syncUserTenants($user)
    {
        $currentTenantId = activeTenantId();
        
        // Sincronizar TODAS as empresas selecionadas
        foreach ($this->selectedTenants as $tenantId) {
            // Verificar se é uma NOVA empresa sendo adicionada
            $isNewTenant = !$user->tenants()->where('tenants.id', $tenantId)->exists();
            
            // Vincular usuário ao tenant se ainda não estiver vinculado
            if ($isNewTenant) {
                $user->tenants()->attach($tenantId, [
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
                
                // ENVIAR NOTIFICAÇÃO POR EMAIL
                $tenant = Tenant::find($tenantId);
                $roleId = $this->selectedRoles[$tenantId] ?? null;
                $roleName = null;
                
                if ($roleId) {
                    $role = \Spatie\Permission\Models\Role::find($roleId);
                    $roleName = $role ? $role->name : null;
                }
                
                try {
                    $user->notify(new \App\Notifications\UserAddedToTenantNotification(
                        $tenant,
                        auth()->user(),
                        $roleName
                    ));
                    
                    \Log::info('📧 Notificação enviada: Usuário adicionado a empresa', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'tenant_id' => $tenantId,
                        'tenant_name' => $tenant->name,
                        'role' => $roleName,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('❌ Erro ao enviar notificação de adição a empresa', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Remover todos os roles antigos do usuário para este tenant
            $user->roles()->wherePivot('tenant_id', $tenantId)->detach();
            
            // Atribuir role para este tenant
            $roleId = $this->selectedRoles[$tenantId] ?? null;
            
            if ($roleId) {
                // Atribuir role do Spatie com tenant_id
                $this->assignRoleToUserForTenant($user, $roleId, $tenantId);
            }
        }
        
        // Remover tenants que foram desmarcados
        $currentTenantIds = $user->tenants()->pluck('tenants.id')->toArray();
        $tenantsToRemove = array_diff($currentTenantIds, $this->selectedTenants);
        
        if (!empty($tenantsToRemove)) {
            $user->tenants()->detach($tenantsToRemove);
            
            // Remover roles dos tenants removidos
            foreach ($tenantsToRemove as $tenantId) {
                $user->roles()->wherePivot('tenant_id', $tenantId)->detach();
            }
        }
        
        // Garantir que o usuário tem o tenant_id correto (primeira empresa selecionada)
        if (!empty($this->selectedTenants) && $user->tenant_id !== $this->selectedTenants[0]) {
            $user->update(['tenant_id' => $this->selectedTenants[0]]);
        }
        
        // Limpar cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
    
    protected function assignRoleToUserForTenant($user, $roleId, $tenantId)
    {
        // Definir o tenant_id para o Spatie
        setPermissionsTeamId($tenantId);
        
        // Buscar o role
        $role = Role::find($roleId);
        
        if ($role) {
            // Atribuir o role ao usuário
            $user->assignRole($role);
        }
    }

    public function toggleTenant($tenantId)
    {
        if (in_array($tenantId, $this->selectedTenants)) {
            $this->selectedTenants = array_diff($this->selectedTenants, [$tenantId]);
            unset($this->selectedRoles[$tenantId]);
        } else {
            $this->selectedTenants[] = $tenantId;
            // Definir role padrão (primeiro disponível)
            $defaultRole = Role::first();
            if ($defaultRole) {
                $this->selectedRoles[$tenantId] = $defaultRole->id;
            }
        }
    }

    public function selectAllTenants()
    {
        $this->assignToAllTenants = !$this->assignToAllTenants;
        
        if ($this->assignToAllTenants) {
            $allTenants = auth()->user()->tenants;
            $this->selectedTenants = $allTenants->pluck('id')->toArray();
            
            // Definir role padrão para todos
            $defaultRole = Role::first();
            if ($defaultRole) {
                foreach ($this->selectedTenants as $tenantId) {
                    if (!isset($this->selectedRoles[$tenantId])) {
                        $this->selectedRoles[$tenantId] = $defaultRole->id;
                    }
                }
            }
        }
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Utilizador {$status} com sucesso!");
    }

    public $showDeleteModal = false;
    public $deletingUserId = null;
    public $deletingUserName = '';

    public function confirmDelete($id)
    {
        $user = User::findOrFail($id);
        
        // Não permitir deletar super admin
        if ($user->is_super_admin) {
            $this->dispatch('error', message: 'Não é possível excluir um Super Admin!');
            return;
        }
        
        // Não permitir deletar a si mesmo
        if ($user->id == auth()->id()) {
            $this->dispatch('error', message: 'Você não pode excluir sua própria conta!');
            return;
        }
        
        $this->deletingUserId = $id;
        $this->deletingUserName = $user->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        try {
            $user = User::with(['roles', 'tenants'])->findOrFail($this->deletingUserId);
            
            // Verificar se tem documentos criados
            $hasDocuments = DB::table('invoicing_sales_invoices')->where('created_by', $user->id)->exists()
                || DB::table('invoicing_sales_proformas')->where('created_by', $user->id)->exists()
                || DB::table('invoicing_purchase_invoices')->where('created_by', $user->id)->exists();
            
            if ($hasDocuments) {
                $this->dispatch('error', message: 'Não é possível excluir! Este utilizador tem documentos associados.');
                $this->closeDeleteModal();
                return;
            }
            
            // Remover roles e relações
            $user->roles()->detach();
            $user->tenants()->detach();
            
            // Forçar exclusão permanente
            $user->forceDelete();
            
            $this->dispatch('success', message: 'Utilizador excluído com sucesso!');
            $this->closeDeleteModal();
            
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir utilizador: ' . $e->getMessage());
            $this->closeDeleteModal();
        }
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingUserId = null;
        $this->deletingUserName = '';
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function sendInvitation()
    {
        $this->validate([
            'inviteName' => 'required|min:3',
            'inviteEmail' => 'required|email',
            'inviteRole' => 'required|exists:roles,id',
        ]);
        
        $this->sending = true;
        
        try {
            // Verificar se o email já está em uso
            $existingUser = User::where('email', $this->inviteEmail)
                ->whereHas('tenants', function($q) {
                    $q->where('tenants.id', activeTenantId());
                })
                ->first();
                
            if ($existingUser) {
                $this->dispatch('error', message: 'Este email já está em uso por um utilizador existente.');
                $this->sending = false;
                return;
            }
            
            // Verificar se já existe um convite pendente
            $pendingInvitation = \App\Models\UserInvitation::where('email', $this->inviteEmail)
                ->where('tenant_id', activeTenantId())
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();
                
            if ($pendingInvitation) {
                $this->dispatch('error', message: 'Já existe um convite pendente para este email.');
                $this->sending = false;
                return;
            }
            
            DB::beginTransaction();
            
            // Buscar nome do role
            $role = \Spatie\Permission\Models\Role::find($this->inviteRole);
            
            // Criar convite
            $invitation = \App\Models\UserInvitation::create([
                'tenant_id' => activeTenantId(),
                'invited_by' => auth()->id(),
                'email' => $this->inviteEmail,
                'name' => $this->inviteName,
                'role' => $role ? $role->name : 'user',
                'role_id' => $this->inviteRole,
            ]);
            
            // Enviar email
            $invitation->sendInvitationEmail();
            
            DB::commit();
            
            $this->dispatch('success', message: "Convite enviado com sucesso para {$this->inviteEmail}!");
            $this->closeModal();
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao enviar convite', [
                'error' => $e->getMessage(),
                'email' => $this->inviteEmail
            ]);
            
            $this->dispatch('error', message: 'Erro ao enviar convite: ' . $e->getMessage());
        }
        
        $this->sending = false;
    }

    private function resetForm()
    {
        $this->reset([
            'name', 'email', 'password', 'password_confirmation',
            'editingUserId', 'selectedTenants', 'selectedRoles', 'assignToAllTenants',
            'inviteName', 'inviteEmail', 'inviteRole', 'activeTab'
        ]);
        $this->is_active = true;
        $this->activeTab = 'create';
    }

    public function render()
    {
        $currentUser = auth()->user();
        $currentTenantId = activeTenantId();
        
        // Filtrar usuários do mesmo tenant (usando relação many-to-many)
        $users = User::query()
            ->with(['roles', 'tenants'])
            ->when(!$currentUser->is_super_admin, function ($query) use ($currentTenantId) {
                // Apenas usuários que pertencem ao tenant ativo (via pivot)
                $query->whereHas('tenants', function ($q) use ($currentTenantId) {
                    $q->where('tenants.id', $currentTenantId);
                });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        // Pegar TODAS as empresas que o usuário logado tem acesso
        if ($currentUser->is_super_admin) {
            // Super Admin vê todos os tenants
            $myTenants = Tenant::orderBy('name')->get();
        } else {
            // Usuário normal vê apenas suas empresas
            $myTenants = $currentUser->tenants()->orderBy('name')->get();
        }
        
        // Buscar roles de TODOS os tenants disponíveis
        if ($currentUser->is_super_admin) {
            // Super Admin vê roles de todos os tenants
            $roles = Role::orderBy('tenant_id')->orderBy('name')->get();
        } else {
            // Usuário normal vê roles apenas de seus tenants
            $tenantIds = $myTenants->pluck('id')->toArray();
            $roles = Role::whereIn('tenant_id', $tenantIds)
                ->orderBy('tenant_id')
                ->orderBy('name')
                ->get();
        }
        
        // Stats para cards
        $totalUsers = User::query()
            ->when(!$currentUser->is_super_admin, function ($query) use ($currentTenantId) {
                $query->whereHas('tenants', function ($q) use ($currentTenantId) {
                    $q->where('tenants.id', $currentTenantId);
                });
            })
            ->count();
            
        $activeUsers = User::query()
            ->where('is_active', true)
            ->when(!$currentUser->is_super_admin, function ($query) use ($currentTenantId) {
                $query->whereHas('tenants', function ($q) use ($currentTenantId) {
                    $q->where('tenants.id', $currentTenantId);
                });
            })
            ->count();
            
        $inactiveUsers = $totalUsers - $activeUsers;
        
        return view('livewire.users.user-management', compact('users', 'myTenants', 'roles', 'totalUsers', 'activeUsers', 'inactiveUsers'));
    }
}

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
    
    // Form fields
    public $name, $email, $password, $password_confirmation;
    public $is_active = true;
    public $selectedTenants = []; // IDs dos tenants selecionados
    public $selectedRoles = []; // [tenant_id => role_id]
    public $assignToAllTenants = false;
    
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
        
        // Carregar apenas o tenant atual
        $this->selectedTenants = [$currentTenantId];
        
        // Carregar roles do Spatie para o tenant atual (especificar tabela pivot)
        $userRole = $user->roles()->wherePivot('tenant_id', $currentTenantId)->first();
        $this->selectedRoles[$currentTenantId] = $userRole ? $userRole->id : null;
        
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
        
        // Vincular usuário ao tenant se ainda não estiver vinculado
        if (!$user->tenants()->where('tenants.id', $currentTenantId)->exists()) {
            $user->tenants()->attach($currentTenantId, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }
        
        // Remover todos os roles antigos do usuário para este tenant
        $user->roles()->wherePivot('tenant_id', $currentTenantId)->detach();
        
        // Atribuir role para o tenant atual
        $roleId = $this->selectedRoles[$currentTenantId] ?? null;
        
        if ($roleId) {
            // Atribuir role do Spatie com tenant_id
            $this->assignRoleToUserForTenant($user, $roleId, $currentTenantId);
        }
        
        // Garantir que o usuário tem o tenant_id correto
        if ($user->tenant_id !== $currentTenantId) {
            $user->update(['tenant_id' => $currentTenantId]);
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
            $hasDocuments = DB::table('sales_invoices')->where('created_by', $user->id)->exists()
                || DB::table('sales_proformas')->where('created_by', $user->id)->exists()
                || DB::table('purchase_invoices')->where('created_by', $user->id)->exists();
            
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

    private function resetForm()
    {
        $this->reset([
            'name', 'email', 'password', 'password_confirmation',
            'editingUserId', 'selectedTenants', 'selectedRoles', 'assignToAllTenants'
        ]);
        $this->is_active = true;
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
        
        // Pegar apenas o tenant ativo
        $myTenants = [Tenant::find($currentTenantId)];
        
        // Buscar roles do Spatie Permission do tenant atual
        setPermissionsTeamId($currentTenantId);
        $roles = Role::where('tenant_id', $currentTenantId)
            ->orderBy('name')
            ->get();
        
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

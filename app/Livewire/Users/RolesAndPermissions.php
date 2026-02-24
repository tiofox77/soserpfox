<?php

namespace App\Livewire\Users;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

#[Layout('layouts.app')]
#[Title('Roles e Permissões')]
class RolesAndPermissions extends Component
{
    use WithPagination;

    public $activeTab = 'roles'; // roles, permissions, assign
    
    // Role Management
    public $showRoleModal = false;
    public $editingRole = null;
    public $roleName = '';
    public $roleDescription = '';
    public $selectedPermissions = [];
    
    // Permission Management
    public $showPermissionModal = false;
    public $permissionName = '';
    public $permissionDescription = '';
    
    // User Assignment
    public $showAssignModal = false;
    public $selectedUser = null;
    public $selectedRoles = [];
    
    // Delete
    public $showDeleteModal = false;
    public $deletingItem = null;
    public $deletingType = ''; // role or permission

    public function render()
    {
        // Definir contexto do tenant
        $tenantId = activeTenantId();
        setPermissionsTeamId($tenantId);
        
        // Buscar APENAS roles do tenant atual
        $roles = Role::withCount('permissions', 'users')
            ->where('tenant_id', $tenantId)
            ->get();
            
        $permissions = Permission::all()->groupBy(function($permission) {
            return explode('.', $permission->name)[0]; // Group by module
        });
        // BUG-U01 FIX: Usar pivot tenant_user em vez de tenant_id directo
        $users = User::with('roles')
            ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))
            ->get();
        $allPermissions = Permission::orderBy('name')->get();

        return view('livewire.users.roles-and-permissions', [
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
            'allPermissions' => $allPermissions,
        ]);
    }

    // ==========================================
    // ROLE MANAGEMENT
    // ==========================================
    
    public function selectAllPermissions()
    {
        $this->selectedPermissions = Permission::all()->pluck('id')->toArray();
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => count($this->selectedPermissions) . ' permissões selecionadas'
        ]);
    }
    
    public function deselectAllPermissions()
    {
        $this->selectedPermissions = [];
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Todas as permissões desmarcadas'
        ]);
    }
    
    public function toggleModulePermissions($module)
    {
        $modulePermissions = Permission::where('name', 'like', $module . '.%')->pluck('id')->toArray();
        
        // Se todas já estão selecionadas, desmarcar; caso contrário, marcar
        $allSelected = !array_diff($modulePermissions, $this->selectedPermissions);
        
        if ($allSelected) {
            // Remover todas do módulo
            $this->selectedPermissions = array_diff($this->selectedPermissions, $modulePermissions);
        } else {
            // Adicionar todas do módulo
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $modulePermissions));
        }
    }

    public function openRoleModal($roleId = null)
    {
        $this->resetRoleForm();
        
        if ($roleId) {
            // Garantir que a role pertence ao tenant atual
            $tenantId = activeTenantId();
            $role = Role::where('id', $roleId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
                
            $this->editingRole = $roleId;
            $this->roleName = $role->name;
            $this->roleDescription = $role->description ?? '';
            $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        }
        
        $this->showRoleModal = true;
    }

    public function saveRole()
    {
        $this->validate([
            'roleName' => 'required|min:3',
            'roleDescription' => 'nullable',
        ]);
        
        // Definir contexto do tenant
        $tenantId = activeTenantId();
        setPermissionsTeamId($tenantId);

        if ($this->editingRole) {
            $role = Role::findOrFail($this->editingRole);
            $role->update([
                'name' => $this->roleName,
                'description' => $this->roleDescription,
            ]);
            $message = 'Role atualizado com sucesso!';
        } else {
            // Criar role COM tenant_id
            $role = Role::create([
                'name' => $this->roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
                'description' => $this->roleDescription,
            ]);
            $message = 'Role criado com sucesso!';
        }

        // Sync permissions (converter IDs para objetos)
        \Log::info('Saving role permissions', [
            'role' => $role->name,
            'selected_permissions' => $this->selectedPermissions,
            'count' => count($this->selectedPermissions ?? [])
        ]);
        
        if (!empty($this->selectedPermissions) && is_array($this->selectedPermissions)) {
            $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();
            \Log::info('Found permissions', ['count' => $permissions->count()]);
            $role->syncPermissions($permissions);
        } else {
            // Se não há permissões selecionadas, remover todas
            $role->syncPermissions([]);
        }
        
        // Limpar cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->showRoleModal = false;
        $this->resetRoleForm();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }

    public function confirmDeleteRole($roleId)
    {
        $this->deletingItem = $roleId;
        $this->deletingType = 'role';
        $this->showDeleteModal = true;
    }

    public function deleteRole()
    {
        // Garantir que a role pertence ao tenant atual
        $tenantId = activeTenantId();
        $role = Role::where('id', $this->deletingItem)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        // Check if role has users
        if ($role->users()->count() > 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Não é possível eliminar um role com utilizadores atribuídos!'
            ]);
            return;
        }

        $role->delete();
        
        // Limpar cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $this->showDeleteModal = false;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Role eliminado com sucesso!'
        ]);
    }

    private function resetRoleForm()
    {
        $this->editingRole = null;
        $this->roleName = '';
        $this->roleDescription = '';
        $this->selectedPermissions = [];
    }

    // ==========================================
    // PERMISSION MANAGEMENT
    // ==========================================

    public function openPermissionModal()
    {
        $this->resetPermissionForm();
        $this->showPermissionModal = true;
    }

    public function savePermission()
    {
        // BUG-U09 FIX: Validar formato modulo.acção e adicionar guard_name
        $this->validate([
            'permissionName' => ['required', 'unique:permissions,name', 'regex:/^[a-z_]+\.[a-z_]+$/'],
            'permissionDescription' => 'nullable',
        ], [
            'permissionName.regex' => 'Formato inválido. Use: modulo.accao (ex: users.view)',
        ]);

        Permission::create([
            'name' => $this->permissionName,
            'guard_name' => 'web',
            'description' => $this->permissionDescription,
        ]);

        $this->showPermissionModal = false;
        $this->resetPermissionForm();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Permissão criada com sucesso!'
        ]);
    }

    private function resetPermissionForm()
    {
        $this->permissionName = '';
        $this->permissionDescription = '';
    }

    // ==========================================
    // USER ASSIGNMENT
    // ==========================================

    public function openAssignModal($userId)
    {
        $user = User::with('roles')->findOrFail($userId);
        $this->selectedUser = $userId;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
        $this->showAssignModal = true;
    }

    public function assignRoles()
    {
        $tenantId = activeTenantId();
        // BUG-U02 FIX: Usar pivot em vez de tenant_id directo
        $user = User::where('id', $this->selectedUser)
            ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))
            ->firstOrFail();
        
        // Definir o tenant_id para as operações do Spatie Permission
        setPermissionsTeamId($tenantId);
        
        // Converter IDs para objetos Role (APENAS do tenant atual)
        $roles = Role::whereIn('id', $this->selectedRoles)
            ->where('tenant_id', $tenantId)
            ->get();
            
        $user->syncRoles($roles);
        
        // Limpar cache de permissões para aplicar imediatamente
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->showAssignModal = false;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Roles atribuídos com sucesso!'
        ]);
    }
}

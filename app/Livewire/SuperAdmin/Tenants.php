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
        $tenant->update(['is_active' => !$tenant->is_active]);
        $status = $tenant->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Tenant {$status} com sucesso!");
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
                'selectedRoleId' => 'required|exists:roles,id',
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
                'selectedRoleId' => 'required|exists:roles,id',
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
        
        // Adicionar à tenant_user
        \DB::table('tenant_user')->insert([
            'tenant_id' => $this->managingTenantId,
            'user_id' => $userId,
            'role_id' => $this->selectedRoleId,
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->dispatch('success', message: $message);
        $this->closeAddUserModal();
    }
    
    public function removeUserFromTenant($userId)
    {
        \DB::table('tenant_user')
            ->where('tenant_id', $this->managingTenantId)
            ->where('user_id', $userId)
            ->delete();
            
        $this->dispatch('success', message: 'Usuário removido do tenant com sucesso!');
    }
    
    public function updateUserRole($userId, $roleId)
    {
        \DB::table('tenant_user')
            ->where('tenant_id', $this->managingTenantId)
            ->where('user_id', $userId)
            ->update([
                'role_id' => $roleId,
                'updated_at' => now(),
            ]);
            
        $this->dispatch('success', message: 'Role atualizada com sucesso!');
    }

    public function render()
    {
        $tenants = Tenant::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('email', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        // Para modal de usuários
        $tenantUsers = [];
        $availableUsers = [];
        $roles = [];
        
        if ($this->managingTenantId) {
            $tenant = \App\Models\Tenant::find($this->managingTenantId);
            $tenantUsers = $tenant->users()->withPivot('role_id', 'is_active', 'joined_at')->get();
            
            // Usuários disponíveis (que não estão neste tenant)
            $userIdsInTenant = $tenantUsers->pluck('id')->toArray();
            $availableUsers = \App\Models\User::whereNotIn('id', $userIdsInTenant)
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get();
            
            $roles = \Spatie\Permission\Models\Role::all();
        }

        return view('livewire.super-admin.tenants.tenants', compact('tenants', 'tenantUsers', 'availableUsers', 'roles'));
    }
}

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
        $user = User::with('tenants')->findOrFail($id);
        
        $this->editingUserId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_active = $user->is_active;
        
        // Carregar tenants e roles
        $this->selectedTenants = $user->tenants->pluck('id')->toArray();
        foreach ($user->tenants as $tenant) {
            $this->selectedRoles[$tenant->id] = $tenant->pivot->role_id ?? null;
        }
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();
        
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
        $syncData = [];
        $myTenantIds = auth()->user()->tenants->pluck('id')->toArray();
        
        if ($this->assignToAllTenants) {
            // Atribuir a todos os tenants do usuário logado
            $allTenants = auth()->user()->tenants->pluck('id');
            
            foreach ($allTenants as $tenantId) {
                $roleId = $this->selectedRoles[$tenantId] ?? null;
                if ($roleId) {
                    $syncData[$tenantId] = [
                        'role_id' => $roleId,
                        'is_active' => true,
                        'joined_at' => now(),
                    ];
                }
            }
        } else {
            // Atribuir apenas aos tenants selecionados (que pertencem ao usuário logado)
            foreach ($this->selectedTenants as $tenantId) {
                // SEGURANÇA: Verificar se o tenant pertence ao usuário logado
                if (!in_array($tenantId, $myTenantIds)) {
                    continue; // Pular empresas que o usuário não gerencia
                }
                
                $roleId = $this->selectedRoles[$tenantId] ?? null;
                if ($roleId) {
                    $syncData[$tenantId] = [
                        'role_id' => $roleId,
                        'is_active' => true,
                        'joined_at' => now(),
                    ];
                }
            }
        }
        
        $user->tenants()->sync($syncData);
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

    public function delete($id)
    {
        try {
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
            
            $user->delete();
            $this->dispatch('success', message: 'Utilizador excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir utilizador!');
        }
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
        
        // Pegar IDs das empresas do usuário logado
        $myTenantIds = $currentUser->tenants->pluck('id');
        
        // Filtrar apenas usuários que pertencem às MESMAS empresas
        $users = User::query()
            ->when(!$currentUser->is_super_admin, function ($query) use ($myTenantIds) {
                // Apenas usuários que têm pelo menos uma empresa em comum
                $query->whereHas('tenants', function ($q) use ($myTenantIds) {
                    $q->whereIn('tenants.id', $myTenantIds);
                });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with('tenants')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $myTenants = $currentUser->tenants;
        $roles = Role::orderBy('name')->get();
        
        // Stats para cards
        $totalUsers = User::query()
            ->when(!$currentUser->is_super_admin, function ($query) use ($myTenantIds) {
                $query->whereHas('tenants', function ($q) use ($myTenantIds) {
                    $q->whereIn('tenants.id', $myTenantIds);
                });
            })
            ->count();
            
        $activeUsers = User::query()
            ->where('is_active', true)
            ->when(!$currentUser->is_super_admin, function ($query) use ($myTenantIds) {
                $query->whereHas('tenants', function ($q) use ($myTenantIds) {
                    $q->whereIn('tenants.id', $myTenantIds);
                });
            })
            ->count();
            
        $inactiveUsers = $totalUsers - $activeUsers;
        
        return view('livewire.users.user-management', compact('users', 'myTenants', 'roles', 'totalUsers', 'activeUsers', 'inactiveUsers'));
    }
}

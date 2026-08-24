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
        
        // BUG-U08 FIX: Só definir tenant_id se user for NOVO (sem tenant_id prévio)
        if (!empty($this->selectedTenants) && empty($user->tenant_id)) {
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
            // BUG-U06 FIX: Buscar role padrão DESTE tenant (não global)
            $defaultRole = Role::where('tenant_id', $tenantId)->first();
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
            
            // BUG-U07 FIX: Buscar role padrão de CADA tenant (não global)
            foreach ($this->selectedTenants as $tenantId) {
                if (!isset($this->selectedRoles[$tenantId])) {
                    $defaultRole = Role::where('tenant_id', $tenantId)->first();
                    if ($defaultRole) {
                        $this->selectedRoles[$tenantId] = $defaultRole->id;
                    }
                }
            }
        }
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // BUG-U03 FIX: Verificações de segurança
        if ($user->is_super_admin) {
            $this->dispatch('error', message: 'Não é possível alterar o status de um Super Admin!');
            return;
        }
        
        if ($user->id === auth()->id()) {
            $this->dispatch('error', message: 'Não pode desactivar a sua própria conta!');
            return;
        }
        
        // Verificar se user pertence ao tenant activo
        $tenantId = activeTenantId();
        if (!auth()->user()->is_super_admin && !$user->tenants()->where('tenants.id', $tenantId)->exists()) {
            $this->dispatch('error', message: 'Sem permissão para alterar este utilizador.');
            return;
        }
        
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Utilizador {$status} com sucesso!");
    }

    // ── PIN de turno (login offline do POS) ──────────────────────────
    // O admin define ou repõe o PIN de um funcionário — util para o primeiro
    // provisionamento e para quem se esqueceu do PIN. O verificador segue para
    // os tablets na próxima sincronização.
    public $showPinModal = false;
    public $pinUserId = null;
    public $pinUserName = '';
    public $posPin = '';
    public $posPinConfirmation = '';

    public function openPinModal($id)
    {
        $user = User::findOrFail($id);

        // Só funcionários desta empresa (a não ser super admin).
        $tenantId = activeTenantId();
        if (!auth()->user()->is_super_admin && !$user->tenants()->where('tenants.id', $tenantId)->exists()) {
            $this->dispatch('error', message: 'Sem permissão para este utilizador.');
            return;
        }

        $this->pinUserId = $user->id;
        $this->pinUserName = $user->name;
        $this->posPin = '';
        $this->posPinConfirmation = '';
        $this->resetErrorBag(['posPin']);
        $this->showPinModal = true;
    }

    public function savePin()
    {
        $this->validate([
            'posPin' => ['required', 'digits_between:4,6', 'same:posPinConfirmation'],
        ], [
            'posPin.required'       => 'Escreva o PIN.',
            'posPin.digits_between' => 'O PIN tem de ter 4 a 6 dígitos.',
            'posPin.same'           => 'Os dois PIN não coincidem.',
        ]);

        $user = User::findOrFail($this->pinUserId);

        $tenantId = activeTenantId();
        if (!auth()->user()->is_super_admin && !$user->tenants()->where('tenants.id', $tenantId)->exists()) {
            $this->dispatch('error', message: 'Sem permissão para este utilizador.');
            return;
        }

        if (in_array($this->posPin, ['0000', '1111', '1234', '123456', '000000', '111111'], true)) {
            $this->addError('posPin', 'Escolha um PIN menos óbvio.');
            return;
        }

        try {
            $user->definirPinPos($this->posPin);
        } catch (\InvalidArgumentException $e) {
            $this->addError('posPin', $e->getMessage());
            return;
        }

        // Não deixar o PIN no snapshot do componente.
        $this->reset(['posPin', 'posPinConfirmation']);
        $this->showPinModal = false;

        $this->dispatch('success', message:
            "PIN de {$this->pinUserName} definido. Vai para os tablets na próxima sincronização.");
    }

    public function closePinModal()
    {
        $this->reset(['posPin', 'posPinConfirmation', 'pinUserId', 'pinUserName']);
        $this->showPinModal = false;
        $this->resetErrorBag(['posPin']);
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
            
            // BUG-U04 FIX: Verificações de segurança
            if ($user->is_super_admin) {
                $this->dispatch('error', message: 'Não é possível excluir um Super Admin!');
                $this->closeDeleteModal();
                return;
            }
            
            if ($user->id === auth()->id()) {
                $this->dispatch('error', message: 'Não pode excluir a sua própria conta!');
                $this->closeDeleteModal();
                return;
            }
            
            // BUG-U04 FIX: Verificação expandida de documentos associados
            $tablesToCheck = [
                'invoicing_sales_invoices' => 'created_by',
                'invoicing_sales_proformas' => 'created_by',
                'invoicing_purchase_invoices' => 'created_by',
                'invoicing_credit_notes' => 'created_by',
                'invoicing_debit_notes' => 'created_by',
                'invoicing_receipts' => 'created_by',
                'orders' => 'user_id',
            ];
            
            $hasDocuments = false;
            foreach ($tablesToCheck as $table => $column) {
                if (DB::getSchemaBuilder()->hasTable($table) && DB::table($table)->where($column, $user->id)->exists()) {
                    $hasDocuments = true;
                    break;
                }
            }
            
            if ($hasDocuments) {
                $this->dispatch('error', message: 'Não é possível excluir! Este utilizador tem documentos associados. Pode desactivá-lo em vez de excluir.');
                $this->closeDeleteModal();
                return;
            }
            
            // BUG-U04 FIX: Usar soft delete em vez de forceDelete
            $user->roles()->detach();
            $user->tenants()->detach();
            $user->delete(); // Soft delete — mantém dados no sistema
            
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
            
            // Mesmo motivo do InviteUser: não convidar quem não vai caber.
            $empresa = \App\Models\Tenant::find(activeTenantId());
            if ($empresa && !$empresa->cabeMaisUmUtilizador()) {
                $this->dispatch('error', message: 'Limite de utilizadores atingido ('
                    . $empresa->limiteDeUtilizadores() . '). Liberte uma conta ou aumente o plano.');

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
        
        // BUG-U11 FIX: Passar stats do tenant para a view (evitar queries no Blade)
        $tenant = Tenant::find($currentTenantId);
        $currentUsersCount = $tenant ? $tenant->users()->count() : 0;
        $maxUsersCount = $tenant ? $tenant->getMaxUsers() : 3;
        $canAddUser = $tenant ? $tenant->canAddUser() : false;
        
        return view('livewire.users.user-management', compact(
            'users', 'myTenants', 'roles', 'totalUsers', 'activeUsers', 'inactiveUsers',
            'currentUsersCount', 'maxUsersCount', 'canAddUser'
        ));
    }
}

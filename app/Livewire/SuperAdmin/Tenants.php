<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Tenant;
use Livewire\Component;

use function Illuminate\Support\defer;

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
    
    // User management
    public $showUserModal = false;
    public $showUsersModal = false;
    public $showAddUserModal = false;
    public $managingTenantId = null;
    public $selectedUserId = null;
    public $selectedRoleId = null;
    
    // Create new user
    public $newUserName = '';
    public $newUserEmail = '';
    public $newUserPhone = '';
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
    public $address, $city, $postal_code, $country = 'Angola';
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
            $tenant = Tenant::with('activeSubscription.plan')->find($this->editingTenantId);

            // A ficha não pode prometer menos do que o plano dá.
            //
            // Como o limite passou a ser imposto, um número na ficha abaixo do
            // plano era só confusão: mostrava 10 a quem paga por 50, e o plano
            // é que prevalecia na hora de adicionar alguém. Ao gravar, o
            // número sobe para o do plano — nunca desce, porque a ficha serve
            // para conceder MAIS.
            $doPlano = (int) ($tenant->activeSubscription?->plan?->max_users ?? 0);

            if ($doPlano > (int) $data['max_users']) {
                $data['max_users'] = $doPlano;
            }

            $doPlanoStorage = (int) ($tenant->activeSubscription?->plan?->max_storage_mb ?? 0);

            if ($doPlanoStorage > (int) $data['max_storage_mb']) {
                $data['max_storage_mb'] = $doPlanoStorage;
            }

            $tenant->update($data);

            $this->dispatch('success', message: 'Empresa actualizada.');
            $this->closeModal();

            return;
        }

        try {
            // Tudo ou nada.
            //
            // Sem transacção, uma falha a criar os papéis ou o plano de contas
            // deixava a empresa GRAVADA e meio montada: aparecia na lista, e
            // quem lá entrasse não tinha permissões nem contabilidade. Não
            // havia forma de perceber que estava incompleta a não ser tropeçar
            // nisso.
            $tenant = \DB::transaction(function () use ($data) {
                $tenant = Tenant::create($data);

                createDefaultRolesForTenant($tenant->id);
                initializeAccountingDataForTenant($tenant->id);

                $this->provisionar($tenant);

                return $tenant;
            });

            \Log::info('Tenant criado pelo Super Admin', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);

            $this->dispatch('success', message: "Empresa '{$tenant->name}' criada e configurada!");
            $this->closeModal();
        } catch (\Throwable $e) {
            \Log::error('Falha ao criar tenant', ['erro' => $e->getMessage()]);

            $this->dispatch('error', message: 'Não foi possível criar a empresa: ' . $e->getMessage());
        }
    }

    /**
     * O que uma empresa nova precisa para funcionar.
     *
     * Faltava aqui, e o efeito só aparecia mais tarde, ecrã a ecrã: as
     * definições de RH e os modelos de notificação existiam agarrados à empresa
     * 1 e nenhuma empresa nova os recebia — quem abrisse esses ecrãs via uma
     * página em branco. Ambos foram corrigidos com catálogos próprios; aqui
     * chamam-se para a empresa já nascer completa em vez de ser remendada na
     * primeira visita.
     *
     * Cada passo falha em silêncio: uma empresa não pode deixar de ser criada
     * porque um catálogo acessório teve um problema.
     */
    private function provisionar(Tenant $tenant): void
    {
        $passos = [
            'definições de RH'         => fn () => \App\Services\HR\DefinicoesRH::garantirPara($tenant->id),
            'modelos de notificação'   => fn () => \App\Services\Notifications\ModelosPadrao::garantirPara($tenant->id),
        ];

        foreach ($passos as $nome => $passo) {
            try {
                $passo();
            } catch (\Throwable $e) {
                \Log::warning("Provisionamento parcial da empresa: {$nome}", [
                    'tenant_id' => $tenant->id,
                    'erro'      => $e->getMessage(),
                ]);
            }
        }
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
            
            // Os emails saem DEPOIS da resposta.
            //
            // Isto era um ciclo síncrono com uma ligação SMTP por utilizador,
            // dentro do pedido — o comentário original já dizia "PODE DEMORAR".
            // Com trinta pessoas numa empresa, o pedido excedia o tempo e o
            // administrador via um erro apesar de a desactivação ter sido
            // gravada (o `update` acontece antes do envio).
            //
            // `defer` corre depois de a resposta seguir para o browser, no
            // mesmo processo — não precisa de fila, que este alojamento não
            // tem. O ecrã responde de imediato.
            defer(fn () => $this->sendSuspensionNotification($tenant));

            $this->dispatch('warning', message:
                "⚠️ Empresa '{$tenant->name}' desactivada. A notificar {$usersCount} utilizador(es) por email."
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
            
            // Depois da resposta, pela mesma razão da desactivação.
            defer(fn () => $this->sendReactivationNotification($tenant));

            $this->dispatch('success', message: "✓ Empresa '{$tenant->name}' reactivada. A notificar {$usersCount} utilizador(es) por email.");
            
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
            $tenant = Tenant::findOrFail($this->deletingTenantId);
            
            $check = $tenant->canBeDeleted();
            if (!$check['can_delete']) {
                $this->dispatch('error', message: $check['reason']);
                $this->closeDeleteModal();
                return;
            }
            
            $tenant->delete();
            $this->dispatch('success', message: 'Tenant excluído com sucesso!');
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir tenant: ' . $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->viewingTenant = Tenant::with(['modules', 'users', 'activeSubscription.plan'])->findOrFail($id);
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
        $this->country = 'Angola';
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
    
    /**
     * O papel indicado pertence mesmo a esta empresa?
     *
     * O `$roleId` vem do browser. O menu do ecrã só mostra papéis desta
     * empresa, mas quem monte o pedido à mão podia indicar o de OUTRA — e o
     * `Role::find()` aceitava-o sem perguntar nada. O resultado era dar a
     * alguém as permissões definidas por uma empresa diferente.
     *
     * É a mesma lição que este sistema já aprendeu com os artigos no POS: o
     * que vem do browser filtra-se sempre pela empresa.
     */
    private function papelDaEmpresa($roleId, int $tenantId): ?\Spatie\Permission\Models\Role
    {
        if (!$roleId) {
            return null;
        }

        return \Spatie\Permission\Models\Role::where('id', $roleId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /*
     * A regra do limite vive no MODELO — Tenant::limiteDeUtilizadores() e
     * Tenant::cabeMaisUmUtilizador(). Estava aqui, mas então só este ecrã a
     * conhecia: qualquer outro sítio que perguntasse "cabe mais um?"
     * responderia outra coisa.
     */

    /**
     * O plano da empresa que está a ser editada, para o formulário poder
     * mostrar o que ela de facto tem direito.
     */
    public function getPlanoDoTenantEmEdicaoProperty(): ?\App\Models\Plan
    {
        if (!$this->editingTenantId) {
            return null;
        }

        return Tenant::with('activeSubscription.plan')
            ->find($this->editingTenantId)
            ?->activeSubscription?->plan;
    }

    public function addUserToTenant()
    {
        $tenant = Tenant::find($this->managingTenantId);

        if (!$tenant) {
            $this->dispatch('error', message: 'Empresa não encontrada.');

            return;
        }

        if (!$tenant->cabeMaisUmUtilizador()) {
            $this->dispatch('error', message: sprintf(
                'Esta empresa já tem %d utilizador(es) e o limite é %d. Aumente o limite na ficha da empresa ou mude o plano.',
                $tenant->users()->count(),
                $tenant->limiteDeUtilizadores()
            ));

            return;
        }

        // O papel é validado ANTES de se criar seja o que for: recusar depois
        // de o utilizador existir deixava uma conta órfã sem permissões.
        if (!$this->papelDaEmpresa($this->selectedRoleId, $tenant->id)) {
            $this->dispatch('error', message: 'Escolha um papel válido para esta empresa.');

            return;
        }

        if ($this->createNewUser == 1 || $this->createNewUser === true) {
            // Criar novo usuário
            $this->validate([
                'newUserName' => 'required|min:3',
                'newUserEmail' => 'required|email|unique:users,email',
                'newUserPhone' => 'nullable|string',
                'newUserPassword' => 'required|min:6',
                'selectedRoleId' => 'required',
            ]);
            
            $user = \App\Models\User::create([
                'name' => $this->newUserName,
                'email' => $this->newUserEmail,
                'phone' => $this->newUserPhone,
                'password' => \Hash::make($this->newUserPassword),
                'is_active' => true,
            ]);

            // Email e SMS DEPOIS da resposta.
            //
            // Eram síncronos: uma ligação SMTP e uma chamada à operadora dentro
            // do pedido. O ecrã ficava parado à espera, e um SMTP lento fazia
            // parecer que a criação tinha falhado — quando o utilizador já
            // estava criado.
            //
            // A senha é passada por valor ao fecho e não fica em lado nenhum
            // depois disto.
            $senha  = $this->newUserPassword;
            $phone  = $this->newUserPhone;

            defer(function () use ($user, $senha, $phone, $tenant) {
                try {
                    $this->sendNewUserWelcomeEmail($user, $senha, $tenant);
                } catch (\Throwable $e) {
                    \Log::error('Email de boas-vindas não enviado', ['user_id' => $user->id, 'erro' => $e->getMessage()]);
                }

                if ($phone) {
                    try {
                        (new \App\Services\SmsService())->sendNewAccountSms($user, $senha, $tenant);
                    } catch (\Throwable $e) {
                        \Log::error('SMS de boas-vindas não enviado', ['user_id' => $user->id, 'erro' => $e->getMessage()]);
                    }
                }
            });

            $userId  = $user->id;
            $message = '✅ Utilizador criado. A enviar as credenciais por email' . ($phone ? ' e SMS' : '') . '.';
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
        
        // Atribuir papel. Já foi validado como sendo desta empresa lá em cima.
        setPermissionsTeamId($this->managingTenantId);
        $user->assignRole($this->papelDaEmpresa($this->selectedRoleId, $this->managingTenantId));

        // A senha escrita não fica na memória do componente — e portanto não
        // volta a viajar para o browser no render seguinte.
        $this->newUserPassword = '';

        $this->dispatch('success', message: $message);
        $this->closeAddUserModal();
    }

    public function removeUserFromTenant($userId)
    {
        $tenant = Tenant::find($this->managingTenantId);
        $user   = \App\Models\User::find($userId);

        if (!$tenant || !$user) {
            return;
        }

        // Não deixar a empresa sem ninguém.
        //
        // Não havia guarda nenhuma: dava para tirar o último utilizador e a
        // empresa ficava sem forma de lá entrar — e sem forma de voltar atrás
        // a partir deste ecrã, porque adicionar exige escolher um papel que
        // alguém tem de conseguir gerir.
        if ($tenant->users()->count() <= 1) {
            $this->dispatch('error', message:
                'Esta é a última pessoa com acesso a esta empresa. Adicione outra antes de a remover, '
                . 'ou desactive a empresa.'
            );

            return;
        }

        setPermissionsTeamId($this->managingTenantId);
        $user->roles()->wherePivot('tenant_id', $this->managingTenantId)->detach();
        $user->tenants()->detach($this->managingTenantId);

        $this->dispatch('success', message: 'Utilizador removido desta empresa.');
    }

    public function updateUserRole($userId, $roleId)
    {
        $user = \App\Models\User::find($userId);

        if (!$user || !$this->managingTenantId) {
            return;
        }

        // O papel TEM de ser desta empresa. O `Role::find()` que aqui estava
        // aceitava qualquer id vindo do browser, incluindo o de outra empresa.
        $papel = $this->papelDaEmpresa($roleId, $this->managingTenantId);

        if (!$papel) {
            $this->dispatch('error', message: 'Esse papel não pertence a esta empresa.');

            return;
        }

        setPermissionsTeamId($this->managingTenantId);

        $user->roles()->wherePivot('tenant_id', $this->managingTenantId)->detach();
        $user->assignRole($papel);

        $this->dispatch('success', message: "Papel alterado para {$papel->name}.");
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
            
            $periodEnd = match($this->billingCycle) {
                'yearly' => now()->addMonths(14),
                'semiannual' => now()->addMonths(6),
                'quarterly' => now()->addMonths(3),
                default => now()->addMonth(),
            };
            
            if ($subscription) {
                // Atualizar subscription existente
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $this->billingCycle,
                    'amount' => $plan->getPrice($this->billingCycle),
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                ]);
            } else {
                // Criar nova subscription
                $tenant->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $this->billingCycle,
                    'amount' => $plan->getPrice($this->billingCycle),
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
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
        // Módulos do plano JÁ COM dependências (Faturação ⇒ Tesouraria).
        // O detach()+attach() anterior destruía o pivot inteiro e, nos planos
        // que não listam a Tesouraria (Business/Enterprise), deixava o cliente
        // sem métodos de pagamento — sem forma de gravar uma Fatura-Recibo.
        $slugsDoPlano = $plan->moduleSlugsWithDependencies();
        $sync = new \App\Services\Tenant\TenantModuleSyncService();

        // 1) Desactivar (sem apagar histórico) o que já não pertence ao plano.
        //    O serviço recusa desactivar módulos de que outro activo dependa.
        $activos = $tenant->modules()->wherePivot('is_active', true)->pluck('modules.slug')->toArray();
        foreach (array_diff($activos, $slugsDoPlano) as $slug) {
            $sync->deactivateModule($tenant, $slug);
        }

        // 2) Activar os do plano (idempotente: pivot + pré-requisitos + permissões)
        foreach ($slugsDoPlano as $slug) {
            $sync->activateModule($tenant, $slug);
        }
    }

    public function render()
    {
        $tenants = Tenant::with(['activeSubscription.plan', 'modules'])
            ->withCount('users')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('nif', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);

        // Está viva ou é só uma linha na base?
        //
        // A lista mostrava nome, plano e número de utilizadores — e com isso
        // não se distingue um cliente que factura todos os dias de um que se
        // registou, abriu duas páginas e nunca mais voltou.
        //
        // Seis consultas fixas para a página inteira, e não seis por empresa.
        $sinais = \App\Services\Tenants\SinaisDeVida::para($tenants->getCollection());

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

        // Para modal de plano
        $allPlans = collect();
        $managingPlanTenant = null;
        if ($this->managingPlanTenantId) {
            $allPlans = \App\Models\Plan::where('is_active', true)->orderBy('order')->get();
            $managingPlanTenant = Tenant::with('activeSubscription.plan')->find($this->managingPlanTenantId);
        }

        return view('livewire.super-admin.tenants.tenants', compact('tenants', 'sinais', 'tenantUsers', 'availableUsers', 'roles', 'allPlans', 'managingPlanTenant'));
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
    
    /**
     * Enviar email de boas-vindas para novo usuário
     * Usa SMTP e Template do banco de dados (mesma lógica do RegisterWizard)
     */
    private function sendNewUserWelcomeEmail($user, $password, $tenant)
    {
        \Log::info('📧 Iniciando envio de email para novo usuário', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        
        // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (mesma lógica do RegisterWizard)
        $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
        
        if (!$smtpSetting) {
            \Log::error('❌ Configuração SMTP não encontrada no banco');
            throw new \Exception('Configuração SMTP não encontrada');
        }
        
        \Log::info('📧 Configuração SMTP encontrada', [
            'host' => $smtpSetting->host,
            'port' => $smtpSetting->port,
            'encryption' => $smtpSetting->encryption,
        ]);
        
        // CONFIGURAR SMTP do banco de dados
        $smtpSetting->configure();
        
        // BUSCAR TEMPLATE 'new-user' DO BANCO (criar se não existir)
        $template = \App\Models\EmailTemplate::where('slug', 'new-user')->first();
        
        if (!$template) {
            \Log::warning('⚠️ Template new-user não encontrado, usando template welcome');
            $template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();
        }
        
        if (!$template) {
            \Log::error('❌ Nenhum template encontrado');
            throw new \Exception('Template de email não encontrado');
        }
        
        \Log::info('📄 Template encontrado', [
            'id' => $template->id,
            'slug' => $template->slug,
            'subject' => $template->subject,
        ]);
        
        // Dados para o template
        $data = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_password' => $password, // Senha em texto plano (apenas neste email)
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'tenant_domain' => $tenant->domain,
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'login_url' => route('login'),
            'support_email' => 'sos@soserp.vip',
        ];
        
        // Renderizar template do BD
        $rendered = $template->render($data);
        
        \Log::info('📧 Template renderizado', [
            'subject' => $rendered['subject'],
            'body_length' => strlen($rendered['body_html']),
        ]);
        
        // Enviar email usando HTML DO TEMPLATE
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
            $message->to($user->email, $user->name)
                    ->subject($rendered['subject'])
                    ->html($rendered['body_html']);
        });
        
        \Log::info('✅ Email de boas-vindas enviado com sucesso', [
            'to' => $user->email,
            'subject' => $rendered['subject'],
        ]);
    }
}

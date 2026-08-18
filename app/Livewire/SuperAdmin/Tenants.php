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

    // Filtros. A lista serve para responder a perguntas — "quem está
    // adormecido?", "quem está no Business?", "quem desactivámos?" — e sem
    // filtros a única resposta possível era ler as páginas todas à mão.
    public $filtroEstado = '';      // '' | activa | a_usar | a_montar | adormecida | vazia
    public $filtroPlano  = '';      // '' | id do plano
    public $filtroActivo = '';      // '' | '1' | '0'
    public $ordenar      = 'recentes';
    public $porPagina    = 10;

    public const ORDENACOES = [
        'recentes' => 'Mais recentes',
        'antigas'  => 'Mais antigas',
        'nome'     => 'Nome (A–Z)',
        'entrada'  => 'Última entrada',
        'facturas' => 'Mais facturas (30d)',
        'artigos'  => 'Maior catálogo',
    ];

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
        'nif' => 'nullable|nif_empresa',
        'phone' => 'nullable',
        'max_users' => 'required|integer|min:1',
        'max_storage_mb' => 'required|integer|min:100',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // Qualquer filtro volta à primeira página — filtrar na página 3 mostrava
    // "nenhum resultado" com resultados a existir na primeira.
    public function updatingFiltroEstado()
    {
        $this->resetPage();
    }

    public function updatingFiltroPlano()
    {
        $this->resetPage();
    }

    public function updatingFiltroActivo()
    {
        $this->resetPage();
    }

    public function updatingOrdenar()
    {
        $this->resetPage();
    }

    public function updatingPorPagina()
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->reset(['search', 'filtroEstado', 'filtroPlano', 'filtroActivo']);
        $this->ordenar = 'recentes';
        $this->resetPage();
    }

    public function getTemFiltrosProperty(): bool
    {
        return $this->search !== '' || $this->filtroEstado !== ''
            || $this->filtroPlano !== '' || $this->filtroActivo !== '';
    }

    /** Um clique num cartão de estado liga (ou desliga) esse filtro. */
    public function filtrarPorEstado(string $estado): void
    {
        $this->filtroEstado = $this->filtroEstado === $estado ? '' : $estado;
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

    /** Estado do apagar definitivo: id da empresa e o que se perde com ela. */
    public $apagarDefinitivoId = null;
    public $apagarDefinitivoNome = '';
    public array $apagarDefinitivoPerdas = [];
    public $apagarDefinitivoImpedido = null;
    public $apagarDefinitivoConfirmacao = '';

    /**
     * Abre a confirmação do apagar DEFINITIVO — o que tira mesmo da base.
     *
     * O botão "Excluir" que já existia chama o delete() do modelo, e como o
     * Tenant usa SoftDeletes isso é uma suspensão: a empresa sai da lista e
     * fica na base, recuperável. Só que o rótulo dizia "Excluir", pelo que
     * ninguém sabia disso e ninguém tinha como limpar as empresas de lixo.
     */
    public function abrirApagarDefinitivo($id)
    {
        $this->apenasDonoDaPlataforma();

        $empresa = Tenant::withTrashed()->findOrFail($id);
        $servico = app(\App\Services\Plataforma\EliminarEmpresa::class);

        $this->apagarDefinitivoId = $id;
        $this->apagarDefinitivoNome = $empresa->name;
        $this->apagarDefinitivoConfirmacao = '';
        $this->apagarDefinitivoPerdas = $servico->oQueSePerde($empresa);
        $this->apagarDefinitivoImpedido = $servico->comunicouAAgt($empresa)
            ? 'Esta empresa já comunicou documentos à AGT. Não pode ser apagada — suspenda-a.'
            : null;
    }

    public function fecharApagarDefinitivo()
    {
        $this->reset([
            'apagarDefinitivoId', 'apagarDefinitivoNome', 'apagarDefinitivoPerdas',
            'apagarDefinitivoImpedido', 'apagarDefinitivoConfirmacao',
        ]);
    }

    /**
     * Apaga mesmo, e só depois de o nome ser escrito à mão.
     *
     * Escrever o nome não é burocracia: é a diferença entre carregar num botão
     * por engano e decidir. Isto não se desfaz.
     */
    public function confirmarApagarDefinitivo()
    {
        $this->apenasDonoDaPlataforma();

        $empresa = Tenant::withTrashed()->findOrFail($this->apagarDefinitivoId);

        if (trim($this->apagarDefinitivoConfirmacao) !== trim($empresa->name)) {
            $this->addError('apagarDefinitivoConfirmacao', 'Escreva o nome da empresa exactamente como está.');

            return;
        }

        try {
            $linhas = app(\App\Services\Plataforma\EliminarEmpresa::class)->eliminar($empresa);

            $this->fecharApagarDefinitivo();
            $this->dispatch('success', message: "Empresa apagada em definitivo ({$linhas} registos).");
        } catch (\DomainException $e) {
            $this->addError('apagarDefinitivoConfirmacao', $e->getMessage());
        }
    }

    private function apenasDonoDaPlataforma(): void
    {
        abort_unless(auth()->check() && auth()->user()->is_super_admin, 403);
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
            
            // A troca de plano vive no TrocarDePlano: o antigo e cancelado
            // com tudo a zero e nasce um novo, com dias novos. Aqui
            // reaproveitava-se a subscricao existente e so se lhe trocava o
            // plano — ficava la o fim do teste e as datas do plano anterior,
            // e a empresa acabava com um plano novo a correr com as contas do
            // velho.
            app(\App\Services\Plataforma\TrocarDePlano::class)
                ->aplicar($tenant, $plan, $this->billingCycle);

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
        // A lista constrói-se em três tempos:
        //
        //   1. os ids que passam nos filtros de base (pesquisa, plano, activo)
        //      — uma consulta leve, só id/nome/data;
        //   2. os sinais de vida de TODOS esses ids — seis consultas fixas,
        //      seja qual for o número de empresas, e é isso que torna barato
        //      filtrar e ordenar por vitalidade, que a base de dados não sabe
        //      calcular;
        //   3. os modelos completos só da página que se vai mostrar.
        //
        // O total é 8-9 consultas por render, constante com o tamanho da lista.
        $porPagina = in_array((int) $this->porPagina, [10, 25, 50], true) ? (int) $this->porPagina : 10;

        $base = Tenant::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('nif', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filtroActivo !== '', fn ($q) => $q->where('is_active', $this->filtroActivo === '1'))
            ->when($this->filtroPlano !== '', function ($q) {
                $q->whereHas('subscriptions', fn ($s) => $s
                    ->where('plan_id', (int) $this->filtroPlano)
                    ->whereIn('status', ['active', 'trial']));
            })
            ->get(['id', 'name', 'created_at']);

        $sinais = \App\Services\Tenants\SinaisDeVida::para($base->pluck('id'));

        // As contagens por estado — os cartões do topo — contam-se ANTES do
        // filtro de estado, senão clicar num cartão zerava todos os outros.
        $contagens = collect($sinais)
            ->groupBy(fn ($s) => $s->estado['chave'])
            ->map->count();

        $filtrados = $this->filtroEstado === ''
            ? $base
            : $base->filter(fn ($t) => ($sinais[$t->id]->estado['chave'] ?? '') === $this->filtroEstado);

        $ordenados = match ($this->ordenar) {
            'antigas'  => $filtrados->sortBy('created_at'),
            'nome'     => $filtrados->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            // Datas nulas para o fim: quem nunca entrou não pode aparecer à
            // frente de quem entrou ontem.
            'entrada'  => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->ultima_entrada?->timestamp ?? -1),
            'facturas' => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->facturas_30d ?? 0),
            'artigos'  => $filtrados->sortByDesc(fn ($t) => $sinais[$t->id]->artigos ?? 0),
            default    => $filtrados->sortByDesc('created_at'),
        };

        $pagina  = max(1, (int) $this->getPage());
        $doPagia = $ordenados->slice(($pagina - 1) * $porPagina, $porPagina)->pluck('id')->values();

        // Só agora os modelos completos, e só os da página.
        $modelos = Tenant::with(['activeSubscription.plan', 'modules'])
            ->withCount('users')
            ->whereIn('id', $doPagia)
            ->get()
            ->sortBy(fn ($t) => $doPagia->search($t->id))
            ->values();

        $tenants = new \Illuminate\Pagination\LengthAwarePaginator(
            $modelos,
            $ordenados->count(),
            $porPagina,
            $pagina,
            ['path' => request()->url(), 'pageName' => 'page']
        );

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

        // Para o filtro por plano — sempre, e não só com a modal aberta.
        $planosParaFiltro = \App\Models\Plan::where('is_active', true)->orderBy('order')->get(['id', 'name']);

        return view('livewire.super-admin.tenants.tenants', compact('tenants', 'sinais', 'contagens', 'planosParaFiltro', 'tenantUsers', 'availableUsers', 'roles', 'allPlans', 'managingPlanTenant'));
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

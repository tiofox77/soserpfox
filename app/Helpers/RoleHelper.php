<?php

if (!function_exists('getDefaultRolePermissionMap')) {
    /**
     * Devolve o mapa canónico de role → array de permission names esperadas,
     * dado um conjunto (Collection) de permissões disponíveis no sistema.
     *
     * Esta função é a fonte única de verdade do "que cada role deve ter por defeito"
     * e é usada tanto pela criação inicial de roles do tenant
     * (createDefaultRolesForTenant) como pelo TenantModuleSyncService
     * (sincronização de permissões em upgrade/downgrade de plano).
     *
     * @param  \Illuminate\Support\Collection $allPermissions Collection<Permission>
     * @return array<string, string[]>  ['Super Admin' => ['p1', 'p2', ...], ...]
     */
    function getDefaultRolePermissionMap($allPermissions): array
    {
        return [
            'Super Admin' => $allPermissions->pluck('name')->toArray(), // TODAS
            'Admin' => $allPermissions->filter(function ($perm) {
                // Admin tem tudo EXCETO gestão de sistema
                return !str_contains($perm->name, 'system.');
            })->pluck('name')->toArray(),
            'Gestor' => $allPermissions->filter(function ($perm) {
                // Gestor: view, create e edit (sem delete)
                return str_contains($perm->name, '.view')
                    || str_contains($perm->name, '.create')
                    || str_contains($perm->name, '.edit');
            })->pluck('name')->toArray(),
            'Utilizador' => $allPermissions->filter(function ($perm) {
                // Utilizador: apenas view
                return str_contains($perm->name, '.view');
            })->pluck('name')->toArray(),
            // ── Roles especializados ─────────────────────────────
            'Administrador Faturação' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'invoicing.')
                    || str_starts_with($perm->name, 'treasury.')
                    || str_starts_with($perm->name, 'customers.')
                    || str_starts_with($perm->name, 'products.')
                    || $perm->name === 'settings.view';
            })->pluck('name')->toArray(),
            'Vendedor' => $allPermissions->filter(function ($perm) {
                return in_array($perm->name, [
                    'customers.view', 'customers.create', 'customers.edit',
                    'products.view',
                    'invoicing.sales.invoices.create', 'invoicing.sales.invoices.view',
                    'invoicing.sales.proformas.create', 'invoicing.sales.proformas.view', 'invoicing.sales.proformas.edit',
                    'invoicing.pos.access', 'invoicing.pos.sell',
                    'invoicing.receipts.view', 'invoicing.receipts.create',
                    'invoicing.stock.view',
                ]);
            })->pluck('name')->toArray(),
            'Caixa' => $allPermissions->filter(function ($perm) {
                return in_array($perm->name, [
                    'invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.pos.reports',
                    'invoicing.receipts.view', 'invoicing.receipts.create',
                    'invoicing.sales.invoices.view',
                    'customers.view', 'products.view', 'invoicing.stock.view',
                    'treasury.cash-registers.view', 'treasury.transactions.create',
                ]);
            })->pluck('name')->toArray(),
            'Contabilista' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'accounting.')
                    || $perm->name === 'invoicing.saft.view'
                    || $perm->name === 'invoicing.reports.view'
                    || (str_starts_with($perm->name, 'treasury.') && str_contains($perm->name, '.view'));
            })->pluck('name')->toArray(),
            'Operador Stock' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'invoicing.stock.')
                    || str_starts_with($perm->name, 'invoicing.warehouse')
                    || str_starts_with($perm->name, 'invoicing.inter-company-transfer.')
                    || str_starts_with($perm->name, 'invoicing.imports.')
                    || $perm->name === 'products.view'
                    || $perm->name === 'invoicing.suppliers.view';
            })->pluck('name')->toArray(),
            'Administrador Restaurante' => $allPermissions->filter(fn($p)=>str_starts_with($p->name,'restaurant.')||str_starts_with($p->name,'invoicing.')||str_starts_with($p->name,'treasury.'))->pluck('name')->toArray(),
            'Gerente de Sala' => $allPermissions->filter(fn($p)=>in_array($p->name,['restaurant.dashboard.view','restaurant.floor.view','restaurant.floor.manage','restaurant.orders.view','restaurant.orders.create','restaurant.orders.edit','restaurant.orders.transfer','restaurant.orders.split','restaurant.orders.cancel','restaurant.reservations.view','restaurant.reservations.create','restaurant.reservations.edit','restaurant.reservations.cancel','restaurant.checkout.view','restaurant.reports.view']))->pluck('name')->toArray(),
            'Empregado de Mesa' => $allPermissions->filter(fn($p)=>in_array($p->name,['restaurant.dashboard.view','restaurant.floor.view','restaurant.orders.view','restaurant.orders.create','restaurant.orders.edit','restaurant.reservations.view','restaurant.reservations.create']))->pluck('name')->toArray(),
            'Caixa Restaurante' => $allPermissions->filter(fn($p)=>str_starts_with($p->name,'restaurant.checkout.')||in_array($p->name,['restaurant.dashboard.view','restaurant.floor.view','restaurant.orders.view','treasury.transactions.create','treasury.cash-registers.view']))->pluck('name')->toArray(),
            'Cozinha/Bar' => $allPermissions->filter(fn($p)=>in_array($p->name,['restaurant.kitchen.view','restaurant.kitchen.manage','restaurant.orders.view']))->pluck('name')->toArray(),
            'Gestor Stock Restaurante' => $allPermissions->filter(fn($p)=>str_starts_with($p->name,'restaurant.recipes.')||str_starts_with($p->name,'restaurant.stock.')||$p->name==='restaurant.menu.view')->pluck('name')->toArray(),
        ];
    }
}

if (!function_exists('getModulePermissionPrefixes')) {
    /**
     * Mapa canónico módulo → array de prefixos de permissões que pertencem ao módulo.
     * Usado para sincronizar permissões Spatie ao ativar/desativar um módulo.
     *
     * Espelha o mapeamento usado em User::canAccessModuleMenu().
     *
     * @return array<string, string[]>
     */
    function getModulePermissionPrefixes(): array
    {
        return [
            'invoicing'     => ['invoicing.', 'customers.', 'products.'],
            'contabilidade' => ['accounting.'],
            'oficina'       => ['workshop.'],
            'eventos'       => ['events.', 'eventos.'],
            'rh'            => ['hr.', 'rh.', 'attendance.', 'payroll.', 'employees.'],
            'crm'           => ['crm.'],
            'inventario'    => ['inventario.', 'inventory.'],
            'compras'       => ['compras.', 'purchases.'],
            'projetos'      => ['projetos.', 'projects.'],
            'hotel'         => ['hotel.'],
            'salon'         => ['salon.'],
            'restaurant'    => ['restaurant.'],
            'notifications' => ['notifications.'],
            'treasury'      => ['treasury.'],
        ];
    }
}

if (!function_exists('createDefaultRolesForTenant')) {
    /**
     * Criar roles padrão para um novo tenant (Sistema de Níveis)
     *
     * @param int $tenantId
     * @return void
     */
    function createDefaultRolesForTenant($tenantId)
    {
        \Log::info('Criando roles padrão para tenant', ['tenant_id' => $tenantId]);

        // Definir o tenant_id para o Spatie Permission
        setPermissionsTeamId($tenantId);

        // Salvaguarda: garantir que permissoes AGT + product-batches existem globalmente
        $extraPerms = [
            'invoicing.agt.view' => 'Ver Configurações AGT Angola',
            'invoicing.agt.edit' => 'Editar Configurações AGT Angola',
            'invoicing.product-batches.view'   => 'Ver Lotes de Produtos',
            'invoicing.product-batches.create' => 'Criar Lotes de Produtos',
            'invoicing.product-batches.edit'   => 'Editar Lotes de Produtos',
            'invoicing.product-batches.delete' => 'Excluir Lotes de Produtos',
            'invoicing.pos.reports.all'        => 'Ver Relatórios POS de Todos os Caixas',
            'invoicing.reports.view'           => 'Ver Relatórios de Faturação',
            // Gestão de utilizadores e permissões (rota /users/*)
            'users.manage'              => 'Gestão de Utilizadores (acesso total ao módulo)',
            'users.view'                => 'Ver Utilizadores',
            'users.create'              => 'Criar Utilizadores',
            'users.edit'                => 'Editar Utilizadores',
            'users.delete'              => 'Eliminar Utilizadores',
            'users.invite'              => 'Convidar Utilizadores',
            'users.roles.manage'        => 'Gerir Roles e Permissões',
        ];
        foreach ($extraPerms as $permName => $permDesc) {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['description' => $permDesc]
            );
        }

        $allPermissions = \Spatie\Permission\Models\Permission::all();
        $roleMap = getDefaultRolePermissionMap($allPermissions);

        foreach ($roleMap as $roleName => $permNames) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]
            );
            $permissions = \Spatie\Permission\Models\Permission::whereIn('name', $permNames)->get();
            $role->syncPermissions($permissions);

            \Log::info("Role '{$roleName}' criada", [
                'tenant_id' => $tenantId,
                'permissions_count' => $permissions->count(),
            ]);
        }

        \Log::info('Todas as roles padrão criadas para tenant', [
            'tenant_id' => $tenantId,
            'roles' => array_keys($roleMap),
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

if (!function_exists('initializeAccountingDataForTenant')) {
    /**
     * Inicializar dados contabilísticos padrão para novo tenant
     * 
     * @param int $tenantId
     * @return void
     */
    function initializeAccountingDataForTenant($tenantId)
    {
        \Log::info('Inicializando dados contabilísticos para tenant', ['tenant_id' => $tenantId]);
        
        try {
            // 1. Criar plano de contas padrão
            $accountSeeder = new \Database\Seeders\Accounting\AccountSeeder();
            $accountSeeder->runForTenant($tenantId);
            
            \Log::info('✅ Plano de contas criado', ['tenant_id' => $tenantId]);
            
            // 2. Criar diários contabilísticos padrão
            $journalSeeder = new \Database\Seeders\Accounting\JournalSeeder();
            $journalSeeder->runForTenant($tenantId);
            
            \Log::info('✅ Diários contabilísticos criados', ['tenant_id' => $tenantId]);
            
            // 3. Criar impostos padrão (IVA, IRT, etc)
            $taxSeeder = new \Database\Seeders\Accounting\TaxSeeder();
            $taxSeeder->seedForTenant($tenantId);

            // 3b. Criar impostos de FATURAÇÃO (IVA Angola + SAFT) — usados em /invoicing/taxes
            \App\Models\Invoicing\Tax::seedDefaultsForTenant($tenantId);

            \Log::info('✅ Impostos padrão criados', ['tenant_id' => $tenantId]);
            
            // 4. Criar centros de custo padrão
            $ccSeeder = new \Database\Seeders\CostCenterSeeder();
            $ccSeeder->seedForTenant($tenantId);

            \Log::info('✅ Centros de custo criados', ['tenant_id' => $tenantId]);

            // 5. Séries de documentos (SOSFR, SOSFT, SOSNC, SOSND, SOSPROV,
            //    SOSFC, SOSPROC, SOSRC).
            //
            // Faltava por completo: o provisionamento criava contas, diários,
            // impostos e centros de custo, mas nenhuma série — e sem série não
            // se numera um documento. As empresas 17, 18 e 19 ficaram assim,
            // sem série de fatura de venda.
            $seriesCriadas = \App\Services\Invoicing\SeriesCatalog::provisionar($tenantId);

            \Log::info('✅ Séries de documentos criadas', [
                'tenant_id' => $tenantId,
                'criadas'   => $seriesCriadas,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao inicializar dados contabilísticos', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

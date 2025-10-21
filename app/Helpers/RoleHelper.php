<?php

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
        
        // Buscar todas as permissões globais
        $allPermissions = \Spatie\Permission\Models\Permission::all();
        
        // Definir estrutura de roles por nível
        $roleLevels = [
            'Super Admin' => [
                'permissions' => $allPermissions->pluck('name')->toArray(), // TODAS
            ],
            'Admin' => [
                'permissions' => $allPermissions->filter(function($perm) {
                    // Admin tem tudo EXCETO gestão de sistema
                    return !str_contains($perm->name, 'system.');
                })->pluck('name')->toArray(),
            ],
            'Gestor' => [
                'permissions' => $allPermissions->filter(function($perm) {
                    // Gestor: view, create e edit (sem delete)
                    return str_contains($perm->name, '.view') || 
                           str_contains($perm->name, '.create') ||
                           str_contains($perm->name, '.edit');
                })->pluck('name')->toArray(),
            ],
            'Utilizador' => [
                'permissions' => $allPermissions->filter(function($perm) {
                    // Utilizador: apenas view
                    return str_contains($perm->name, '.view');
                })->pluck('name')->toArray(),
            ],
        ];
        
        // Criar cada role com suas permissões
        foreach ($roleLevels as $roleName => $config) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]
            );
            
            // Sincronizar permissões
            $permissions = \Spatie\Permission\Models\Permission::whereIn('name', $config['permissions'])->get();
            $role->syncPermissions($permissions);
            
            \Log::info("Role '{$roleName}' criada", [
                'tenant_id' => $tenantId,
                'permissions_count' => $permissions->count()
            ]);
        }
        
        \Log::info('Todas as roles padrão criadas para tenant', [
            'tenant_id' => $tenantId,
            'roles' => array_keys($roleLevels)
        ]);
        
        // Limpar cache de permissões
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
            
            \Log::info('✅ Impostos padrão criados', ['tenant_id' => $tenantId]);
            
            // 4. Criar centros de custo padrão
            $ccSeeder = new \Database\Seeders\CostCenterSeeder();
            $ccSeeder->seedForTenant($tenantId);
            
            \Log::info('✅ Centros de custo criados', ['tenant_id' => $tenantId]);
            
        } catch (\Exception $e) {
            \Log::error('Erro ao inicializar dados contabilísticos', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

<?php

namespace App\Services\Tenant;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincronização de módulos do tenant com o seu plano, garantindo coerência
 * entre o pivot `tenant_module` e as permissões Spatie nos roles do tenant.
 *
 * Fonte única de verdade para upgrade/downgrade:
 *   - DOWNGRADE: pivot is_active=false  +  revoke das permissões do módulo nos roles
 *   - UPGRADE:   pivot is_active=true   +  grant das permissões do módulo nos roles
 *               (respeitando o mapa canónico em getDefaultRolePermissionMap)
 *
 * O middleware `tenant.module:<slug>` aplica esta coerência em runtime; este
 * serviço garante a coerência em escrita (na mudança de plano).
 */
class TenantModuleSyncService
{
    /**
     * Dependências entre módulos: activar a chave implica activar os valores.
     * A Faturação e a Tesouraria trabalham juntas em todas as vertentes (formas
     * de pagamento, recibos, caixa) — activar uma sem a outra deixa ecrãs
     * inutilizáveis (ex.: seletor de forma de pagamento vazio na Fatura-Recibo).
     */
    public const MODULE_DEPENDENCIES = [
        'invoicing' => ['treasury'],
        'treasury'  => ['invoicing'],
        'compras'   => ['invoicing'],
        'salon'     => ['invoicing'],
        'hotel'     => ['invoicing'],
        'restaurant'=> ['invoicing'],
    ];

    /**
     * Devolve o slug do módulo mais todas as suas dependências (transitivo).
     *
     * @return string[]
     */
    public static function withDependencies(string $slug): array
    {
        $resolved = [];
        $stack = [$slug];

        while ($stack) {
            $current = array_pop($stack);
            if (in_array($current, $resolved, true)) {
                continue;
            }
            $resolved[] = $current;
            foreach (self::MODULE_DEPENDENCIES[$current] ?? [] as $dep) {
                if (!in_array($dep, $resolved, true)) {
                    $stack[] = $dep;
                }
            }
        }

        return $resolved;
    }

    /**
     * Cria os pré-requisitos de dados de um módulo (idempotente).
     *
     * Sem isto, um módulo podia ficar "activo" mas inutilizável — foi o que
     * aconteceu: tenant com Tesouraria por activar ⇒ zero métodos de pagamento
     * ⇒ Fatura-Recibo sem formas de pagamento para escolher.
     */
    public function seedModulePrerequisites(Tenant $tenant, string $moduleSlug): array
    {
        $criado = [];

        try {
            switch ($moduleSlug) {
                case 'treasury':
                    $n = \App\Models\Treasury\PaymentMethod::seedDefaultsForTenant($tenant->id);
                    if ($n > 0) {
                        $criado['metodos_pagamento'] = $n;
                    }
                    break;

                case 'invoicing':
                    $n = \App\Models\Invoicing\Tax::seedDefaultsForTenant($tenant->id);
                    if ($n > 0) {
                        $criado['impostos'] = $n;
                    }

                    // Configurações de faturação (firstOrCreate)
                    \App\Models\Invoicing\InvoicingSettings::forTenant($tenant->id);

                    // Série da Fatura-Recibo/POS. Idempotente e obrigatória para
                    // emissão; o registo fiscal AGT ocorre depois por tenant.
                    $hadFrSeries = \App\Models\Invoicing\InvoicingSeries::where(
                        'tenant_id',
                        $tenant->id
                    )->where('document_type', 'pos')->exists();
                    \App\Models\Invoicing\InvoicingSeries::getDefaultSeries($tenant->id, 'pos');
                    if (!$hadFrSeries) {
                        $criado['serie_fr'] = 1;
                    }

                    // Armazém por omissão (necessário para gravar documentos com stock)
                    if (!\App\Models\Invoicing\Warehouse::getDefault($tenant->id)) {
                        \App\Models\Invoicing\Warehouse::getOrCreateDefault($tenant->id);
                        $criado['armazem_default'] = 1;
                    }
                    break;

                case 'restaurant':
                    $settings = \App\Models\Restaurant\RestaurantSettings::forTenant($tenant->id);
                    $warehouse = \App\Models\Invoicing\Warehouse::getDefault($tenant->id)
                        ?: \App\Models\Invoicing\Warehouse::getOrCreateDefault($tenant->id);
                    if (!$settings->default_warehouse_id) {
                        $settings->update(['default_warehouse_id' => $warehouse->id]);
                    }

                    $venue = \App\Models\Restaurant\Venue::withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => 'PRINCIPAL'],
                        ['name' => 'Restaurante Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]
                    );
                    \App\Models\Restaurant\Area::withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => 'Sala Principal'],
                        ['sort_order' => 1, 'is_active' => true]
                    );
                    $criado['configuracao_restaurante'] = 1;
                    break;
            }
        } catch (\Throwable $e) {
            // Nunca deixar a activação falhar por causa dos seeds — regista e segue.
            Log::error('seedModulePrerequisites falhou', [
                'tenant_id' => $tenant->id,
                'module'    => $moduleSlug,
                'error'     => $e->getMessage(),
            ]);
        }

        return $criado;
    }

    /**
     * Sincroniza os módulos do tenant com os do novo plano.
     * Calcula o diff entre `oldPlan` e `newPlan` (ou entre o estado atual do
     * tenant e o newPlan, se oldPlan for null) e ativa/desativa em conformidade.
     */
    public function syncToPlan(Tenant $tenant, Plan $newPlan, ?Plan $oldPlan = null): array
    {
        $newModuleIds = $newPlan->modules()->pluck('modules.id')->toArray();

        // Regra de negócio: a TESOURARIA acompanha sempre a FATURAÇÃO — os métodos
        // de pagamento dependem da tesouraria, têm de trabalhar em conjunto.
        $invoicingId = Module::where('slug', 'invoicing')->value('id');
        $treasuryId  = Module::where('slug', 'treasury')->value('id');
        if ($invoicingId && $treasuryId
            && in_array($invoicingId, $newModuleIds)
            && !in_array($treasuryId, $newModuleIds)) {
            $newModuleIds[] = $treasuryId;
        }

        if ($oldPlan) {
            $oldModuleIds = $oldPlan->modules()->pluck('modules.id')->toArray();
        } else {
            // Sem oldPlan explícito: usar o estado atual do tenant (pivot ativo)
            $oldModuleIds = $tenant->modules()
                ->wherePivot('is_active', true)
                ->pluck('modules.id')
                ->toArray();
        }

        $toActivate   = array_values(array_diff($newModuleIds, $oldModuleIds));
        $toDeactivate = array_values(array_diff($oldModuleIds, $newModuleIds));
        $toKeep       = array_values(array_intersect($oldModuleIds, $newModuleIds));

        Log::info('TenantModuleSyncService: syncToPlan', [
            'tenant_id'   => $tenant->id,
            'old_plan'    => $oldPlan?->name,
            'new_plan'    => $newPlan->name,
            'activate'    => $toActivate,
            'deactivate'  => $toDeactivate,
            'keep'        => $toKeep,
        ]);

        foreach ($toDeactivate as $moduleId) {
            $module = Module::find($moduleId);
            if ($module) {
                $this->deactivateModule($tenant, $module->slug);
            }
        }

        foreach ($toActivate as $moduleId) {
            $module = Module::find($moduleId);
            if ($module) {
                $this->activateModule($tenant, $module->slug);
            }
        }

        // Garantir que os módulos a manter ficam mesmo ativos. Antes usava-se
        // updateExistingPivot, que é NO-OP quando a linha ainda não existe — um
        // módulo comum ao plano antigo e ao novo podia ficar sem registo nenhum
        // (e sem permissões nem pré-requisitos). activateModule é idempotente.
        foreach ($toKeep as $moduleId) {
            $module = Module::find($moduleId);
            if ($module) {
                $this->activateModule($tenant, $module->slug);
            }
        }

        $this->forgetPermissionCache();

        return [
            'activated'   => $toActivate,
            'deactivated' => $toDeactivate,
            'kept'        => $toKeep,
        ];
    }

    /**
     * Ativa um módulo no tenant E concede as permissões correspondentes aos
     * roles do tenant, segundo o mapa canónico em getDefaultRolePermissionMap.
     */
    public function activateModule(Tenant $tenant, string $moduleSlug, bool $comDependencias = true): void
    {
        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            Log::warning("activateModule: módulo '{$moduleSlug}' não existe.");
            return;
        }

        // 0) Dependências: activar um módulo activa sempre aquilo de que depende
        //    (ex.: Faturação ⇒ Tesouraria). Sem isto o cliente fica com ecrãs
        //    inutilizáveis e "planos a falhar".
        if ($comDependencias) {
            foreach (self::MODULE_DEPENDENCIES[$moduleSlug] ?? [] as $dep) {
                if ($dep !== $moduleSlug) {
                    $this->activateModule($tenant, $dep, false);
                }
            }
        }

        // 1) Pivot tenant_module
        $tenant->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active'       => true,
                'activated_at'    => now(),
                'deactivated_at'  => null,
            ],
        ]);

        // 1.1) Pré-requisitos de dados do módulo (idempotente)
        $semeado = $this->seedModulePrerequisites($tenant, $moduleSlug);
        if (!empty($semeado)) {
            Log::info('activateModule: pré-requisitos criados', [
                'tenant_id' => $tenant->id,
                'module'    => $moduleSlug,
                'criado'    => $semeado,
            ]);
        }

        // 2) Conceder permissões aos roles do tenant (segundo mapa canónico)
        $modulePerms = $this->getModulePermissions($moduleSlug);
        if ($modulePerms->isEmpty()) {
            Log::info("activateModule: nenhuma permissão registada para o módulo '{$moduleSlug}'");
            return;
        }

        $allPerms = Permission::all();
        $roleMap  = getDefaultRolePermissionMap($allPerms);
        $modulePermNames = $modulePerms->pluck('name')->toArray();

        $tenantRoles = Role::where('tenant_id', $tenant->id)->get();
        foreach ($tenantRoles as $role) {
            $expectedNames = $roleMap[$role->name] ?? null;
            if ($expectedNames === null) {
                // Role custom (criada pelo cliente) — ignorar; o admin do tenant atribui depois
                continue;
            }
            // Interseção: o que esta role devia ter ∩ permissões deste módulo
            $toGrant = array_values(array_intersect($expectedNames, $modulePermNames));
            if (!empty($toGrant)) {
                $perms = Permission::whereIn('name', $toGrant)->get();
                $role->givePermissionTo($perms);
            }
        }

        Log::info('activateModule: módulo ativado e permissões concedidas', [
            'tenant_id'  => $tenant->id,
            'module'     => $moduleSlug,
            'perm_count' => $modulePerms->count(),
            'roles'      => $tenantRoles->pluck('name')->toArray(),
        ]);
    }

    /**
     * Desativa um módulo no tenant E revoga as permissões correspondentes
     * de TODOS os roles do tenant (incluindo roles custom).
     */
    public function deactivateModule(Tenant $tenant, string $moduleSlug): void
    {
        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            Log::warning("deactivateModule: módulo '{$moduleSlug}' não existe.");
            return;
        }

        // Não desactivar um módulo do qual outro módulo ACTIVO depende — deixaria
        // o dependente partido (ex.: tirar a Tesouraria com a Faturação activa
        // ⇒ vendas sem forma de pagamento nem registo em caixa).
        foreach (self::MODULE_DEPENDENCIES as $dependente => $requeridos) {
            if ($dependente === $moduleSlug || !in_array($moduleSlug, $requeridos, true)) {
                continue;
            }
            $dependenteActivo = $tenant->modules()
                ->where('slug', $dependente)
                ->wherePivot('is_active', true)
                ->exists();

            if ($dependenteActivo) {
                Log::warning('deactivateModule: ignorado — outro módulo activo depende deste', [
                    'tenant_id'  => $tenant->id,
                    'module'     => $moduleSlug,
                    'dependente' => $dependente,
                ]);
                return;
            }
        }

        // 1) Pivot tenant_module
        if ($tenant->modules()->where('modules.id', $module->id)->exists()) {
            $tenant->modules()->updateExistingPivot($module->id, [
                'is_active'      => false,
                'deactivated_at' => now(),
            ]);
        }

        // 2) Revogar permissões de TODOS os roles do tenant
        $modulePerms = $this->getModulePermissions($moduleSlug);
        if ($modulePerms->isEmpty()) {
            return;
        }

        $tenantRoles = Role::where('tenant_id', $tenant->id)->get();
        foreach ($tenantRoles as $role) {
            $role->revokePermissionTo($modulePerms);
        }

        Log::info('deactivateModule: módulo desativado e permissões revogadas', [
            'tenant_id'  => $tenant->id,
            'module'     => $moduleSlug,
            'perm_count' => $modulePerms->count(),
            'roles'      => $tenantRoles->pluck('name')->toArray(),
        ]);
    }

    /**
     * Reconcilia o estado atual do tenant (pivot + permissões) com o seu plano
     * ativo. Percorre TODOS os módulos do sistema:
     *   - se está no plano → activateModule (idempotente: pivot ON + grant perms)
     *   - se não está no plano → deactivateModule (idempotente: pivot OFF + revoke perms)
     *
     * Útil para hot-fix de tenants que ficaram com permissões residuais de planos
     * anteriores, antes do sistema de sync existir, mesmo que o pivot já esteja
     * consistente.
     */
    public function reconcile(Tenant $tenant): array
    {
        $sub = $tenant->activeSubscription()->first();
        if (!$sub || !$sub->plan) {
            Log::warning("reconcile: tenant {$tenant->id} sem subscription ativa — nada a fazer.");
            return ['activated' => [], 'deactivated' => [], 'kept' => []];
        }

        $planModuleSlugs = $sub->plan->modules()->pluck('modules.slug')->toArray();

        // Regra de negócio: a TESOURARIA acompanha sempre a FATURAÇÃO.
        if (in_array('invoicing', $planModuleSlugs, true)
            && !in_array('treasury', $planModuleSlugs, true)) {
            $planModuleSlugs[] = 'treasury';
        }

        $allModules = Module::all();

        $activated = [];
        $deactivated = [];

        foreach ($allModules as $module) {
            if (in_array($module->slug, $planModuleSlugs, true)) {
                $this->activateModule($tenant, $module->slug);
                $activated[] = $module->slug;
            } else {
                $this->deactivateModule($tenant, $module->slug);
                $deactivated[] = $module->slug;
            }
        }

        $this->forgetPermissionCache();

        return [
            'activated'   => $activated,
            'deactivated' => $deactivated,
            'kept'        => [],
        ];
    }

    /**
     * Devolve as permissões Spatie que pertencem a um módulo, com base no mapa
     * canónico de prefixos em getModulePermissionPrefixes().
     */
    protected function getModulePermissions(string $moduleSlug): Collection
    {
        $prefixMap = getModulePermissionPrefixes();
        $prefixes  = $prefixMap[$moduleSlug] ?? [$moduleSlug . '.'];

        return Permission::query()
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('name', 'like', $prefix . '%');
                }
            })
            ->get();
    }

    protected function forgetPermissionCache(): void
    {
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            Log::warning('forgetPermissionCache falhou: ' . $e->getMessage());
        }
    }
}

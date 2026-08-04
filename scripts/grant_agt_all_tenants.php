<?php
/**
 * Garante que TODOS os tenants tenham as permissoes 'invoicing.agt.view' / 'edit'
 * atribuidas a todas as roles que ja tenham 'invoicing.settings.view' / 'edit'.
 *
 * Usa Spatie + teams (tenant_id como team_foreign_key).
 *
 * Uso (local):    php scripts/grant_agt_all_tenants.php
 * Uso (servidor): php scripts/grant_agt_all_tenants.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$pairs = [
    'invoicing.settings.view' => ['invoicing.agt.view',  'Ver Configurações AGT Angola'],
    'invoicing.settings.edit' => ['invoicing.agt.edit',  'Editar Configurações AGT Angola'],
];

// 1. Garantir que as permissoes existem (globalmente)
foreach ($pairs as [$targetName, $targetLabel]) {
    Permission::firstOrCreate(
        ['name' => $targetName, 'guard_name' => 'web'],
        ['description' => $targetLabel]
    );
}

$tenants = Tenant::all();
echo "Total tenants: " . $tenants->count() . "\n\n";

$totalGranted = 0;
foreach ($tenants as $tenant) {
    // Aplicar contexto de team do Spatie
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    echo "── Tenant #{$tenant->id} ({$tenant->name}) ──\n";

    foreach ($pairs as $sourceName => [$targetName, $targetLabel]) {
        // Roles deste tenant que tem a permissao source
        $roles = Role::where('roles.tenant_id', $tenant->id)
            ->whereHas('permissions', fn($q) => $q->where('name', $sourceName))
            ->get();

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($targetName)) {
                $role->givePermissionTo($targetName);
                echo "  + role '{$role->name}' → {$targetName}\n";
                $totalGranted++;
            }
        }
    }
}

// Limpar cache final
app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "\n────────────────────────────────────────\n";
echo "Total novas atribuicoes: {$totalGranted}\n";
echo "Cache de permissoes limpo. ✅\n";

<?php
/**
 * Forca atribuicao de invoicing.agt.view/edit a TODAS as roles
 * de TODOS os tenants (independente das permissoes que ja tenham).
 *
 * Uso: php scripts/force_agt_permissions.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// 1. Garantir permissoes globais
$permsToEnsure = [
    'invoicing.agt.view'  => 'Ver Configuracoes AGT Angola',
    'invoicing.agt.edit'  => 'Editar Configuracoes AGT Angola',
];

foreach ($permsToEnsure as $name => $desc) {
    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $desc]);
}

// 2. Roles a quem queremos garantir as 2 permissoes
$rolesAlvo = ['Super Admin', 'Admin', 'Gestor', 'Administrador Faturacao', 'Administrador Faturação'];

$tenants = Tenant::all();
$total = 0;

foreach ($tenants as $tenant) {
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    echo "── Tenant #{$tenant->id} ({$tenant->name}) ──\n";

    $roles = Role::where('roles.tenant_id', $tenant->id)
        ->whereIn('name', $rolesAlvo)
        ->get();

    foreach ($roles as $role) {
        foreach (array_keys($permsToEnsure) as $perm) {
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
                echo "  + {$role->name} → {$perm}\n";
                $total++;
            }
        }
    }
}

app(PermissionRegistrar::class)->forgetCachedPermissions();
echo "\nTotal atribuicoes novas: $total\n";
echo "Cache limpo. ✅\n";
echo "\nProximos passos:\n";
echo "  php artisan permission:cache-reset\n";
echo "  php artisan view:clear\n";

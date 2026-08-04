<?php
/**
 * Garante que todas as roles com permissao 'invoicing.settings.view'
 * tambem tenham 'invoicing.agt.view' (e idem para .edit).
 *
 * Uso: php scripts/grant_agt_permissions.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$pairs = [
    'invoicing.settings.view' => ['invoicing.agt.view', 'Ver Configurações AGT Angola'],
    'invoicing.settings.edit' => ['invoicing.agt.edit', 'Editar Configurações AGT Angola'],
];

foreach ($pairs as $sourcePermName => [$targetPermName, $targetLabel]) {
    // Garantir que existe
    $target = Permission::firstOrCreate(
        ['name' => $targetPermName, 'guard_name' => 'web'],
        ['description' => $targetLabel ?? $targetPermName]
    );

    $rolesWithSource = Role::whereHas('permissions', fn($q) => $q->where('name', $sourcePermName))->get();

    echo "Permissao '$targetPermName' (id={$target->id}) — atribuir a " . $rolesWithSource->count() . " role(s):\n";
    foreach ($rolesWithSource as $role) {
        if (!$role->hasPermissionTo($targetPermName)) {
            $role->givePermissionTo($targetPermName);
            echo "  + concedido a role: {$role->name}\n";
        } else {
            echo "  = ja tinha: {$role->name}\n";
        }
    }
}

// Limpar cache de permissoes
app()->make(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
echo "\nCache de permissoes limpo. ✅\n";

<?php
/**
 * Diagnostico: porque o menu 'Facturacao Electronica AGT' nao aparece para um utilizador.
 *
 * Uso: php scripts/diagnose_agt_menu.php email@exemplo.com
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Module;
use Spatie\Permission\PermissionRegistrar;

$email = $argv[1] ?? 'softecangola@gmail.com';
echo "=== DIAGNOSTICO: {$email} ===\n\n";

$user = User::where('email', $email)->first();
if (!$user) { echo "❌ Utilizador NAO existe.\n"; exit(1); }
echo "✅ User #{$user->id} ({$user->name})\n";
echo "   is_super_admin: " . ($user->is_super_admin ? 'YES' : 'NO') . "\n";
echo "   tenant_id (default): {$user->tenant_id}\n\n";

$tenants = $user->tenants()->get();
echo "Tenants associados: " . $tenants->count() . "\n";
foreach ($tenants as $t) {
    echo "  - #{$t->id} '{$t->name}' (active: " . ($t->is_active ? 'Y' : 'N') . ")\n";
}
echo "\n";

$active = $user->activeTenant();
if (!$active) { echo "❌ Sem tenant activo.\n"; exit(1); }
echo "Tenant activo: #{$active->id} '{$active->name}'\n\n";

// Modulo invoicing
$invoicingModule = Module::where('slug', 'invoicing')->first();
if (!$invoicingModule) { echo "❌ Modulo 'invoicing' NAO existe na tabela modules.\n"; exit(1); }
echo "✅ Modulo invoicing existe (id={$invoicingModule->id})\n";

$hasModule = $active->hasModule('invoicing');
echo "Tenant tem 'invoicing' activo? " . ($hasModule ? '✅ YES' : '❌ NO') . "\n";

if (!$hasModule) {
    echo "\n>>> SOLUCAO: ativar modulo invoicing para tenant #{$active->id}\n";
    echo ">>> Codigo SQL: INSERT INTO module_tenant (tenant_id, module_id, is_active, created_at, updated_at) VALUES ({$active->id}, {$invoicingModule->id}, 1, NOW(), NOW());\n";
    echo ">>> ou via tinker: \$tenant->modules()->syncWithoutDetaching([{$invoicingModule->id} => ['is_active' => true]]);\n\n";
}

// Permissoes scoped ao tenant activo
app(PermissionRegistrar::class)->setPermissionsTeamId($active->id);

$roles = $user->roles()->where('roles.tenant_id', $active->id)->get();
echo "\nRoles no tenant #{$active->id}: " . $roles->count() . "\n";
foreach ($roles as $r) {
    $perms = $r->permissions->pluck('name')->toArray();
    $hasView = in_array('invoicing.agt.view', $perms);
    $hasSettings = in_array('invoicing.settings.view', $perms);
    echo "  - {$r->name} (id={$r->id})\n";
    echo "      invoicing.settings.view: " . ($hasSettings ? '✅' : '❌') . "\n";
    echo "      invoicing.agt.view:      " . ($hasView ? '✅' : '❌') . "\n";
}

echo "\n--- Resumo ---\n";
echo "User can('invoicing.agt.view'): " . ($user->can('invoicing.agt.view') ? '✅ YES' : '❌ NO') . "\n";
echo "User hasActiveModule('invoicing'): " . ($hasModule ? '✅ YES' : '❌ NO') . "\n";

echo "\nMenu visivel se: hasActiveModule('invoicing') AND can('invoicing.agt.view') == ambos YES\n";

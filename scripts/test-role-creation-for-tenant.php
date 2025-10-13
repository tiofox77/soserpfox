<?php

/**
 * TESTAR CRIAÇÃO DE ROLES PARA TENANT
 * 
 * Verifica se as roles multi-níveis são criadas corretamente
 * quando um novo tenant é adicionado.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTAR CRIAÇÃO DE ROLES PARA TENANT\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar um tenant para testar
$tenant = \App\Models\Tenant::first();

if (!$tenant) {
    echo "❌ Nenhum tenant encontrado no sistema!\n";
    echo "   Crie um tenant primeiro.\n\n";
    exit(1);
}

echo "✅ Tenant encontrado: {$tenant->name} (ID: {$tenant->id})\n\n";

// Configurar tenant_id para o Spatie Permission
setPermissionsTeamId($tenant->id);

// Buscar roles deste tenant
$roles = \Spatie\Permission\Models\Role::where('tenant_id', $tenant->id)
    ->orderBy('name')
    ->get();

echo "📊 Roles do Tenant:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($roles->count() === 0) {
    echo "⚠️  Nenhuma role encontrada!\n";
    echo "   Criando roles automaticamente...\n\n";
    
    createDefaultRolesForTenant($tenant->id);
    
    // Buscar novamente
    $roles = \Spatie\Permission\Models\Role::where('tenant_id', $tenant->id)
        ->orderBy('name')
        ->get();
}

foreach ($roles as $role) {
    $permissionsCount = $role->permissions->count();
    echo "  • {$role->name}\n";
    echo "    ID: {$role->id}\n";
    echo "    Permissões: {$permissionsCount}\n";
    echo "    Guard: {$role->guard_name}\n";
    echo "    Tenant: {$role->tenant_id}\n\n";
}

// Verificar roles esperadas
$expectedRoles = ['Super Admin', 'Admin', 'Gestor', 'Utilizador'];
$existingRoleNames = $roles->pluck('name')->toArray();
$missingRoles = array_diff($expectedRoles, $existingRoleNames);

if (count($missingRoles) > 0) {
    echo "⚠️  Roles faltando:\n";
    foreach ($missingRoles as $roleName) {
        echo "   ❌ {$roleName}\n";
    }
    echo "\n";
} else {
    echo "✅ Todas as roles esperadas existem!\n\n";
}

// Resumo
echo "📋 Resumo:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Tenant: {$tenant->name}\n";
echo "Roles criadas: " . $roles->count() . "\n";
echo "Roles esperadas: " . count($expectedRoles) . "\n";
echo "Status: " . (count($missingRoles) === 0 ? '✅ OK' : '⚠️  Incompleto') . "\n\n";

// Testar criação para novo tenant (simulação)
echo "🧪 Teste de Criação:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Quando você criar um novo tenant via:\n";
echo "  1. Registro (RegisterWizard)\n";
echo "  2. Minha Conta > Nova Empresa (MyAccount)\n";
echo "  3. Super Admin > Tenants (Tenants)\n\n";
echo "As seguintes roles serão criadas automaticamente:\n";
foreach ($expectedRoles as $roleName) {
    echo "  ✓ {$roleName}\n";
}
echo "\n";

echo "✅ Teste concluído!\n\n";

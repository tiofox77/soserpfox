<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;

echo "═══════════════════════════════════════════════\n";
echo "ROLES NO SISTEMA\n";
echo "═══════════════════════════════════════════════\n\n";

$roles = Role::whereIn('name', ['Super Admin', 'Admin', 'Gestor', 'Utilizador'])
    ->orderBy('tenant_id')
    ->orderBy('name')
    ->get();

foreach ($roles as $role) {
    echo "ID: {$role->id}\n";
    echo "Nome: {$role->name}\n";
    echo "Tenant ID: " . ($role->tenant_id ?? 'NULL') . "\n";
    echo "Guard: {$role->guard_name}\n";
    echo "Usuários: " . $role->users()->count() . "\n";
    echo "---\n";
}

// Verificar usuário específico
echo "\n═══════════════════════════════════════════════\n";
echo "USUÁRIO: softecangola@gmail.com\n";
echo "═══════════════════════════════════════════════\n\n";

$user = \App\Models\User::where('email', 'softecangola@gmail.com')->first();

if ($user) {
    echo "ID: {$user->id}\n";
    echo "Nome: {$user->name}\n";
    echo "Tenant ID: {$user->tenant_id}\n";
    echo "is_super_admin: " . ($user->is_super_admin ? 'TRUE' : 'FALSE') . "\n";
    
    setPermissionsTeamId($user->tenant_id);
    
    $roles = $user->getRoleNames();
    echo "Roles: " . $roles->implode(', ') . "\n";
    
    $permissions = $user->getAllPermissions()->pluck('name');
    echo "Permissões: " . $permissions->count() . " permissões\n";
}

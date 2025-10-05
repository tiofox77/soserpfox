<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CleanOldPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🧹 Limpando permissões antigas...');

        // Prefixos de permissões antigas que devem ser removidas
        $oldPrefixes = [
            'accounting.',
            'appointments.',
            'users.',      // Sistema antigo
            'customers.',  // Agora é invoicing.clients
            'products.',   // Agora é invoicing.products (sem prefixo antigo)
            'invoices.',   // Agora é invoicing.sales.invoices
            'payments.',   // Agora é treasury
            'employees.',
            'vehicles.',
            'repairs.',
            'settings.',
            'reports.',
        ];

        $deleted = 0;
        
        foreach ($oldPrefixes as $prefix) {
            $count = Permission::where('name', 'like', $prefix . '%')->count();
            if ($count > 0) {
                $this->command->info("  🗑️  Removendo {$count} permissões com prefixo '{$prefix}'");
                Permission::where('name', 'like', $prefix . '%')->delete();
                $deleted += $count;
            }
        }

        // Remover permissões órfãs específicas
        $orphanPermissions = [
            'tenants.view',
            'tenants.create',
            'tenants.edit',
            'tenants.delete',
        ];

        foreach ($orphanPermissions as $perm) {
            if (Permission::where('name', $perm)->exists()) {
                $this->command->info("  🗑️  Removendo permissão órfã: {$perm}");
                Permission::where('name', $perm)->delete();
                $deleted++;
            }
        }

        $this->command->info("✅ {$deleted} permissões antigas removidas!");
        
        // Mostrar permissões restantes
        $remaining = Permission::count();
        $this->command->info("📊 Permissões restantes no sistema: {$remaining}");
        
        // Mostrar módulos atuais
        $this->command->info("\n📦 Módulos de permissões atuais:");
        $modules = Permission::all()->groupBy(function($p) {
            return explode('.', $p->name)[0];
        });
        
        foreach ($modules as $module => $perms) {
            $this->command->info("  - {$module}: {$perms->count()} permissões");
        }
    }
}

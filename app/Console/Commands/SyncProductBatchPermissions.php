<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncProductBatchPermissions extends Command
{
    protected $signature = 'permissions:sync-product-batches';
    protected $description = 'Cria permissões invoicing.product-batches.* e sincroniza com roles existentes (não atribui a Caixa)';

    public function handle(): int
    {
        $permissions = [
            'invoicing.product-batches.view'   => 'Ver Lotes de Produtos',
            'invoicing.product-batches.create' => 'Criar Lotes de Produtos',
            'invoicing.product-batches.edit'   => 'Editar Lotes de Produtos',
            'invoicing.product-batches.delete' => 'Excluir Lotes de Produtos',
        ];

        $this->info('Criando permissões product-batches…');
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
            $this->line(" ✓ {$name}");
        }

        // Atribuir a roles relevantes (em TODOS os tenants)
        $rolesWithFullAccess = ['Super Admin', 'Admin', 'Administrador Faturação'];
        $rolesWithCRUDExceptDelete = ['Gestor', 'Operador Stock'];
        $rolesWithViewOnly = ['Utilizador', 'Vendedor', 'Contabilista'];

        $allPerms = array_keys($permissions);
        $crudExceptDelete = array_filter($allPerms, fn($p) => !str_ends_with($p, '.delete'));
        $viewOnly = ['invoicing.product-batches.view'];

        $assigned = 0;
        foreach (Role::all() as $role) {
            // CAIXA explicitamente NÃO recebe — não pode mexer em lotes
            if ($role->name === 'Caixa') {
                $role->revokePermissionTo($allPerms);
                continue;
            }

            if (in_array($role->name, $rolesWithFullAccess)) {
                $role->givePermissionTo($allPerms);
                $assigned++;
            } elseif (in_array($role->name, $rolesWithCRUDExceptDelete)) {
                $role->givePermissionTo($crudExceptDelete);
                $assigned++;
            } elseif (in_array($role->name, $rolesWithViewOnly)) {
                $role->givePermissionTo($viewOnly);
                $assigned++;
            }
        }

        $this->info("✅ {$assigned} role(s) actualizada(s).");
        $this->info('🚫 Role "Caixa" NÃO recebe permissões de lotes.');

        return self::SUCCESS;
    }
}

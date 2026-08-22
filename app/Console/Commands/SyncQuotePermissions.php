<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria as permissões invoicing.sales.quotes.* e atribui-as às roles
 * existentes em TODOS os tenants.
 *
 * Uma permissão nova não pertence a role nenhuma: sem isto, ao publicar os
 * Orçamentos ninguém — nem o dono da empresa — via o menu nem entrava na
 * página. Espelha a distribuição das proformas de venda. Mesmo padrão do
 * permissions:sync-product-batches.
 */
class SyncQuotePermissions extends Command
{
    protected $signature = 'permissions:sync-quotes';
    protected $description = 'Cria permissões invoicing.sales.quotes.* e sincroniza com as roles existentes de todos os tenants';

    public function handle(): int
    {
        $permissions = [
            'invoicing.sales.quotes.view'    => 'Ver Orçamentos',
            'invoicing.sales.quotes.create'  => 'Criar Orçamentos',
            'invoicing.sales.quotes.edit'    => 'Editar Orçamentos',
            'invoicing.sales.quotes.delete'  => 'Eliminar Orçamentos',
            'invoicing.sales.quotes.convert' => 'Converter Orçamentos em Faturas',
        ];

        $this->info('Criando permissões de orçamentos…');
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
            $this->line(" ✓ {$name}");
        }

        $allPerms = array_keys($permissions);
        // Gerir = tudo menos apagar (view/create/edit/convert)
        $manage = array_filter($allPerms, fn($p) => !str_ends_with($p, '.delete'));
        $viewOnly = ['invoicing.sales.quotes.view'];

        // Quem faz proformas faz orçamentos.
        $rolesWithFullAccess = ['Super Admin', 'Admin', 'Administrador Faturação'];
        $rolesWithManage     = ['Gestor', 'Vendedor'];
        $rolesWithViewOnly   = ['Utilizador', 'Contabilista'];

        $assigned = 0;
        foreach (Role::all() as $role) {
            if (in_array($role->name, $rolesWithFullAccess, true)) {
                $role->givePermissionTo($allPerms);
                $assigned++;
            } elseif (in_array($role->name, $rolesWithManage, true)) {
                $role->givePermissionTo($manage);
                $assigned++;
            } elseif (in_array($role->name, $rolesWithViewOnly, true)) {
                $role->givePermissionTo($viewOnly);
                $assigned++;
            }
        }

        // Limpar a cache de permissões, senão o menu só aparece no próximo deploy.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("✅ {$assigned} role(s) actualizada(s).");

        return self::SUCCESS;
    }
}

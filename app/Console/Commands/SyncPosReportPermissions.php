<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncPosReportPermissions extends Command
{
    protected $signature = 'permissions:sync-pos-reports';
    protected $description = 'Cria invoicing.pos.reports.all e atribui invoicing.pos.reports à role Caixa em todos os tenants';

    public function handle(): int
    {
        // Garantir que as duas permissões existem globalmente
        $perms = [
            'invoicing.pos.reports'     => 'Ver Relatórios POS',
            'invoicing.pos.reports.all' => 'Ver Relatórios POS de Todos os Caixas',
        ];
        foreach ($perms as $name => $desc) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $desc]
            );
            $this->line(" ✓ {$name}");
        }

        $caixaCount = 0;
        $allCount   = 0;

        foreach (Role::all() as $role) {
            // Caixa: apenas pode ver os PRÓPRIOS relatórios
            if ($role->name === 'Caixa') {
                $role->givePermissionTo('invoicing.pos.reports');
                $role->revokePermissionTo('invoicing.pos.reports.all');
                $caixaCount++;
                continue;
            }

            // Admin / Super Admin / Administrador Faturação / Gestor: tudo
            if (in_array($role->name, ['Super Admin', 'Admin', 'Administrador Faturação', 'Gestor'])) {
                $role->givePermissionTo(['invoicing.pos.reports', 'invoicing.pos.reports.all']);
                $allCount++;
            }
        }

        $this->info("");
        $this->info("✅ Caixa configurado em {$caixaCount} role(s) — vê apenas as próprias vendas.");
        $this->info("✅ {$allCount} role(s) com acesso total a relatórios POS.");

        return self::SUCCESS;
    }
}

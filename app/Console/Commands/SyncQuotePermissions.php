<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria as permissões invoicing.sales.quotes.* e atribui-as às roles
 * existentes em TODOS os tenants.
 *
 * Uma permissão nova não pertence a role nenhuma: sem isto, ao publicar os
 * Orçamentos ninguém — nem o dono da empresa — via o menu nem entrava na
 * página. Espelha a distribuição das proformas de venda.
 *
 * O trabalho é feito em INSERÇÃO EM MASSA no pivot role_has_permissions
 * (colunas permission_id, role_id — sem coluna de equipa) em vez de um
 * givePermissionTo por role. Com muitos tenants, o laço role-a-role
 * ultrapassava o timeout HTTP da rota de manutenção e nunca terminava; assim
 * são meia dúzia de queries e acaba em segundos. É idempotente
 * (insertOrIgnore), por isso pode correr as vezes que forem precisas.
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
        $ids = [];
        foreach ($permissions as $name => $description) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
            $ids[$name] = $perm->id;
            $this->line(" ✓ {$name}");
        }

        $all     = array_values($ids);
        $manage  = [$ids['invoicing.sales.quotes.view'], $ids['invoicing.sales.quotes.create'], $ids['invoicing.sales.quotes.edit'], $ids['invoicing.sales.quotes.convert']];
        $view    = [$ids['invoicing.sales.quotes.view']];

        // Quem faz proformas faz orçamentos.
        $mapa = [
            'Super Admin'             => $all,
            'Admin'                   => $all,
            'Administrador Faturação' => $all,
            'Gestor'                  => $manage,
            'Vendedor'                => $manage,
            'Utilizador'              => $view,
            'Contabilista'            => $view,
        ];

        // Uma query por NOME de role (não por role): traz os ids de todas as
        // roles com esse nome em todos os tenants de uma vez.
        $linhas = [];
        foreach ($mapa as $nome => $perms) {
            $roleIds = Role::where('name', $nome)->pluck('id');
            foreach ($roleIds as $rid) {
                foreach ($perms as $pid) {
                    $linhas[] = ['permission_id' => $pid, 'role_id' => $rid];
                }
            }
        }

        $inseridas = 0;
        foreach (array_chunk($linhas, 1000) as $bloco) {
            // insertOrIgnore: não duplica o que já lá esteja de uma corrida anterior.
            $inseridas += DB::table('role_has_permissions')->insertOrIgnore($bloco);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("✅ " . count($linhas) . " ligação(ões) processada(s), {$inseridas} nova(s).");

        return self::SUCCESS;
    }
}

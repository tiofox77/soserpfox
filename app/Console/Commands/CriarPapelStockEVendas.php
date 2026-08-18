<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cria o papel "Stock e Vendas" numa empresa.
 *
 * Quem o tem gere stock, vende, e cria e edita artigos.
 *
 * APAGAR artigos fica de fora, e essa é a linha que se mantém: um artigo
 * apagado leva consigo a ligação ao que já foi vendido com ele, e isso não
 * se desfaz. Editar um preço vê-se e corrige-se; apagar não.
 *
 * SIMULAÇÃO POR OMISSÃO. É idempotente: correr outra vez só acerta as
 * permissões, não duplica o papel.
 *
 *   php artisan papel:stock-vendas --tenant=57
 *   php artisan papel:stock-vendas --tenant=57 --aplicar
 */
class CriarPapelStockEVendas extends Command
{
    protected $signature = 'papel:stock-vendas
                            {--tenant= : id da empresa}
                            {--nome=Stock e Vendas : nome do papel}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Cria o papel "Stock e Vendas" numa empresa (simulação por omissão)';

    /** O que o papel PODE. O que não está aqui, não pode. */
    private const PERMISSOES = [
        // Vender
        'invoicing.pos.access',
        'invoicing.pos.sell',
        'invoicing.sales.invoices.view',
        'invoicing.sales.invoices.create',
        'invoicing.sales.invoices.pdf',
        'invoicing.clients.view',
        'invoicing.clients.create',

        // Gerir stock
        'invoicing.stock.view',
        'invoicing.stock.edit',
        'invoicing.warehouses.view',
        'invoicing.product-batches.view',
        'invoicing.product-batches.create',
        'invoicing.product-batches.edit',

        // Artigos: ver, criar e editar. APAGAR fica de fora, de propósito:
        // um artigo apagado leva consigo a ligação ao que já foi vendido.
        'invoicing.products.view',
        'invoicing.products.create',
        'invoicing.products.edit',
    ];

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $nome = trim((string) $this->option('nome'));
        $aplicar = (bool) $this->option('aplicar');

        $existem = Permission::whereIn('name', self::PERMISSOES)->pluck('id', 'name');
        $faltam = array_diff(self::PERMISSOES, $existem->keys()->all());

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(" Papel: {$nome}");
        $this->line('  permissões a dar:  ' . $existem->count());
        $this->line('  não existem:       ' . (count($faltam) ? implode(', ', $faltam) : 'nenhuma'));

        $papel = Role::where('name', $nome)->where('tenant_id', $empresa->id)->first();
        $this->line('  papel: ' . ($papel ? "já existe (#{$papel->id})" : 'a criar'));

        // Estas ficam explicitamente DE FORA — é o que distingue este papel.
        $this->newLine();
        $this->warn('  NÃO pode: apagar artigos, anular facturas, definições.');

        if (!$aplicar) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $papel = Role::firstOrCreate(
            ['name' => $nome, 'guard_name' => 'web', 'tenant_id' => $empresa->id]
        );

        // O Spatie é multi-empresa: sem dizer de que equipa se trata, as
        // permissões iam parar à equipa errada ou a nenhuma.
        setPermissionsTeamId($empresa->id);
        $papel->syncPermissions($existem->keys()->all());

        // O Spatie guarda as permissões em cache. Sem esvaziar, o papel novo
        // ficava sem efeito até a cache expirar — alguém entrava, não via o
        // POS, e ninguém percebia porquê.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info("  ✓ Papel \"{$nome}\" (#{$papel->id}) com {$existem->count()} permissões.");

        return self::SUCCESS;
    }
}

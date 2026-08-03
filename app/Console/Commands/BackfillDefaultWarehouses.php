<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Invoicing\Warehouse;
use Illuminate\Console\Command;

/**
 * Garante que cada tenant tem um armazém principal/default.
 * Idempotente: cria o "Armazém Principal" quando não existe nenhum,
 * ou promove o primeiro armazém activo a default quando há armazéns
 * mas nenhum marcado como default.
 *
 *   php artisan warehouses:backfill [--tenant=ID]
 */
class BackfillDefaultWarehouses extends Command
{
    protected $signature = 'warehouses:backfill {--tenant= : ID de um tenant específico}';

    protected $description = 'Cria/garante o armazém principal (default) em falta nos tenants';

    public function handle(): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }
        $tenants = $query->get(['id', 'name']);

        $createdCount = 0;
        foreach ($tenants as $tenant) {
            $before = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $wh = Warehouse::ensureDefaultForTenant($tenant->id);
            $after = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            if ($after > $before) { $createdCount++; }
            $this->info("✓ #{$tenant->id} {$tenant->name} — default: {$wh->name} (#{$wh->id}), armazéns: {$after}");
        }

        $this->newLine();
        $this->info("Backfill de armazéns concluído. Tenants processados: {$tenants->count()}, novos armazéns: {$createdCount}.");
        return self::SUCCESS;
    }
}

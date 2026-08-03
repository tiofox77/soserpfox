<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Treasury\PaymentMethod;
use Illuminate\Console\Command;

/**
 * Garante que cada tenant tem os métodos de pagamento padrão de Angola
 * (Dinheiro, Multicaixa Express, TPA, Transferência, Cheque, Débito, MB Way).
 * Idempotente por código — tenants antigos (criados antes do seeding) recebem
 * os que faltam.
 *
 *   php artisan payment-methods:backfill [--tenant=ID]
 */
class BackfillPaymentMethods extends Command
{
    protected $signature = 'payment-methods:backfill {--tenant= : ID de um tenant específico}';

    protected $description = 'Cria/garante os métodos de pagamento padrão (Tesouraria) em falta nos tenants';

    public function handle(): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }
        $tenants = $query->get(['id', 'name']);
        $expected = count(PaymentMethod::defaultSet());

        foreach ($tenants as $tenant) {
            $before = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $created = PaymentMethod::seedDefaultsForTenant($tenant->id);
            $after = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $this->info("✓ #{$tenant->id} {$tenant->name} — antes: {$before}, criados: {$created}, agora: {$after} (esperado ≥ {$expected})");
        }

        $this->newLine();
        $this->info('Backfill de métodos de pagamento concluído.');
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia o agregado `invoicing_products.stock_quantity` com a soma real
 * das linhas em `invoicing_stocks` para todos os tenants (ou um específico).
 *
 * Uso:
 *   php artisan stock:reconcile           # todos os tenants
 *   php artisan stock:reconcile --tenant=17
 *   php artisan stock:reconcile --dry-run # apenas mostra divergências
 */
class StockReconcile extends Command
{
    protected $signature = 'stock:reconcile
                            {--tenant= : ID do tenant (opcional, todos se omitido)}
                            {--dry-run : Apenas mostra divergências, não corrige}
                            {--fix-negatives : Zera linhas de invoicing_stocks com quantidade negativa (com movimento de ajuste registado) antes de reconciliar}';

    protected $description = 'Reconcilia invoicing_products.stock_quantity com a soma de invoicing_stocks';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $this->info('=== Stock Reconcile ===');
        $this->info($dryRun ? '(modo dry-run — nenhuma alteração será feita)' : '(modo REAL — dados serão corrigidos)');
        $this->newLine();

        // ── Passo 0 (opcional): zerar linhas negativas ANTES de reconciliar,
        // para a soma por produto não ficar poluída por valores impossíveis.
        // Via Eloquent save() → StockObserver ressincroniza o agregado; movimento
        // de ajuste registado semAplicarStock (o stock já foi corrigido aqui).
        if ($this->option('fix-negatives')) {
            $negQuery = \App\Models\Invoicing\Stock::withoutGlobalScopes()->where('quantity', '<', 0);
            if ($tenantId) {
                $negQuery->where('tenant_id', $tenantId);
            }
            $negatives = $negQuery->get();
            $this->warn("Linhas negativas encontradas: {$negatives->count()}");
            foreach ($negatives as $row) {
                $old = (float) $row->quantity;
                $this->line("  tenant {$row->tenant_id} | produto {$row->product_id} | armazém {$row->warehouse_id} | {$old} → 0");
                if ($dryRun) {
                    continue;
                }
                $row->quantity = 0;
                $row->save(); // dispara StockObserver → agregado ressincronizado

                $userId = DB::table('users')->where('tenant_id', $row->tenant_id)->min('id');
                if ($userId) {
                    \App\Models\Invoicing\StockMovement::semAplicarStock(function () use ($row, $old, $userId) {
                        \App\Models\Invoicing\StockMovement::create([
                            'tenant_id'    => $row->tenant_id,
                            'warehouse_id' => $row->warehouse_id,
                            'product_id'   => $row->product_id,
                            'type'         => 'adjustment',
                            'quantity'     => 0,
                            'user_id'      => $userId,
                            'notes'        => "Correção automática: quantidade negativa ({$old}) zerada — reparação de dados",
                        ]);
                    });
                }
            }
            $this->newLine();
        }

        // Buscar todos os produtos que têm pelo menos uma linha em invoicing_stocks
        // e cuja soma difere do stock_quantity actual
        $query = DB::table('invoicing_products as p')
            ->join(DB::raw('(
                SELECT tenant_id, product_id, SUM(quantity) AS sum_qty
                FROM invoicing_stocks
                GROUP BY tenant_id, product_id
            ) AS agg'), function ($j) {
                $j->on('agg.tenant_id', '=', 'p.tenant_id')
                  ->on('agg.product_id', '=', 'p.id');
            })
            ->whereRaw('ROUND(p.stock_quantity, 3) != ROUND(agg.sum_qty, 3)')
            ->select('p.id', 'p.tenant_id', 'p.name', 'p.stock_quantity', 'agg.sum_qty');

        if ($tenantId) {
            $query->where('p.tenant_id', $tenantId);
        }

        $divergent = $query->get();

        if ($divergent->isEmpty()) {
            $this->info('✓ Nenhuma divergência encontrada. Tudo sincronizado.');
            return self::SUCCESS;
        }

        $this->warn("Encontradas {$divergent->count()} divergências:");
        $this->newLine();

        $headers = ['Tenant', 'Product ID', 'Nome', 'Agregado Atual', 'Soma Real', 'Diff'];
        $rows = $divergent->map(fn($r) => [
            $r->tenant_id,
            $r->id,
            mb_substr($r->name, 0, 40),
            number_format((float) $r->stock_quantity, 3),
            number_format((float) $r->sum_qty, 3),
            number_format((float) $r->sum_qty - (float) $r->stock_quantity, 3),
        ])->toArray();

        $this->table($headers, $rows);

        if ($dryRun) {
            $this->newLine();
            $this->info('(dry-run) Nenhuma alteração feita. Remova --dry-run para corrigir.');
            return self::SUCCESS;
        }

        // Corrigir em batch
        $this->newLine();
        $this->info('A corrigir...');

        $fixed = 0;
        foreach ($divergent as $r) {
            DB::table('invoicing_products')
                ->where('id', $r->id)
                ->where('tenant_id', $r->tenant_id)
                ->update(['stock_quantity' => $r->sum_qty]);
            $fixed++;
        }

        $this->info("✓ Corrigidos {$fixed} produtos.");

        return self::SUCCESS;
    }
}

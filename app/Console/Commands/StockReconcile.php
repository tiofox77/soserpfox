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
                            {--fix : Corrige mesmo. Sem esta opção o comando apenas SIMULA — e é assim de propósito, porque se alcança por URL}
                            {--dry-run : Mantido por compatibilidade; simular já é o comportamento por omissão}
                            {--fix-negatives : Zera linhas de invoicing_stocks com quantidade negativa (com movimento de ajuste registado) antes de reconciliar}
                            {--vendas : Só diagnostica: quantas linhas de venda não deixaram baixa de stock, e desde quando}';

    protected $description = 'Reconcilia invoicing_products.stock_quantity com a soma de invoicing_stocks';

    /**
     * Vendas que não deixaram baixa de stock.
     *
     * Responde à pergunta que o rastreio de um artigo levanta e não explica:
     * "foram vendidas 12 e só saíram 10 — isto é de agora ou é herança?".
     *
     * Só CONTA. Não devolve números de documento, clientes nem valores — pode
     * correr em produção sem expor nada.
     *
     * As linhas importadas do sistema anterior (source_billing 'P') contam-se à
     * parte: nunca passaram por esta aplicação, por isso é natural não terem
     * movimento e não são defeito nenhum.
     */
    private function diagnosticarVendasSemBaixa($tenantId): int
    {
        $this->info('=== Vendas sem baixa de stock ===');
        $this->newLine();

        $base = DB::table('invoicing_sales_invoice_items as it')
            ->join('invoicing_sales_invoices as i', 'i.id', '=', 'it.sales_invoice_id')
            ->join('invoicing_products as p', 'p.id', '=', 'it.product_id')
            ->whereIn('i.status', ['sent', 'paid', 'partially_paid', 'overdue'])
            ->whereNull('i.deleted_at')
            // Serviços e artigos sem gestão de stock não têm baixa a fazer.
            ->where('p.manage_stock', 1)
            ->where('p.type', 'produto');

        if ($tenantId) {
            $base->where('i.tenant_id', $tenantId);
        }

        $semBaixa = fn () => (clone $base)->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('invoicing_stock_movements as m')
              ->whereColumn('m.reference_id', 'i.id')
              ->whereColumn('m.product_id', 'it.product_id')
              ->where('m.type', 'out');
        });

        $linhas = [];

        foreach ([
            ['Linhas de venda com gestão de stock', (clone $base)->count()],
            ['  sem baixa registada',               $semBaixa()->count()],
            ['    importadas (nunca passaram aqui)', $semBaixa()->where('i.source_billing', 'P')->count()],
            ['    feitas NESTA aplicação',           $semBaixa()->where(fn ($q) => $q->where('i.source_billing', '<>', 'P')->orWhereNull('i.source_billing'))->count()],
        ] as [$rotulo, $valor]) {
            $linhas[] = [$rotulo, number_format($valor, 0, ',', '.')];
        }

        $this->table(['', 'Linhas'], $linhas);

        // O que interessa mesmo: continua a acontecer HOJE?
        $recentes = $semBaixa()
            ->where(fn ($q) => $q->where('i.source_billing', '<>', 'P')->orWhereNull('i.source_billing'));

        $this->newLine();
        $this->info('Das feitas nesta aplicação, por antiguidade:');

        $porPeriodo = [];

        foreach ([7 => 'últimos 7 dias', 30 => 'últimos 30 dias', 90 => 'últimos 90 dias'] as $dias => $rotulo) {
            $porPeriodo[] = [$rotulo, (clone $recentes)->where('i.invoice_date', '>=', now()->subDays($dias))->count()];
        }

        $porPeriodo[] = ['mais antigas', (clone $recentes)->where('i.invoice_date', '<', now()->subDays(90))->count()];

        $this->table(['Período', 'Linhas'], $porPeriodo);

        $ultimas7 = (clone $recentes)->where('i.invoice_date', '>=', now()->subDays(7))->count();

        $this->newLine();

        if ($ultimas7 > 0) {
            $this->error("ATENÇÃO: {$ultimas7} venda(s) da última semana sem baixa de stock. O problema é ACTUAL.");
        } else {
            $this->info('Nenhuma venda da última semana sem baixa. O que existe é herança, não um defeito activo.');
        }

        return self::SUCCESS;
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        // SIMULAÇÃO por omissão. Escrever só com --fix.
        //
        // Este comando está na whitelist da rota de manutenção, e essa rota não
        // passa argumentos: bastava abrir o URL para ele reescrever o agregado
        // de todos os produtos de todas as empresas. Aconteceu — uma consulta
        // que se queria de diagnóstico corrigiu 335 produtos em produção sem
        // ninguém o ter pedido. Um comando que se alcança por URL não pode ter
        // a escrita como omissão.
        $dryRun = !$this->option('fix');

        if ($this->option('vendas')) {
            return $this->diagnosticarVendasSemBaixa($tenantId);
        }

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

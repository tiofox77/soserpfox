<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** A demonstração de resultados: receita, devoluções, CMV, lucro bruto. */
class LucrosEPerdas extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'profit-loss',
            'titulo' => 'Lucros e Perdas (DRE)',
            'descricao' => 'Demonstração de resultados do período, e a evolução dos últimos seis meses.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('Receita líquida', 'netRevenue', 'dinheiro', 'blue'),
                self::cartao('CMV', 'cogs', 'dinheiro', 'orange'),
                self::cartao('Lucro bruto', 'grossProfit', 'dinheiro', 'green'),
                self::cartao('Margem bruta', 'grossMargin', 'percentagem', 'purple'),
            ],
            'tabelas' => [
                ['titulo' => 'Demonstração de resultados', 'chave' => 'linhas', 'colunas' => [self::col('Rubrica', 'rotulo'), self::col('Valor', 'valor', 'dinheiro')]],
                ['titulo' => 'Últimos seis meses', 'chave' => 'monthly', 'colunas' => [self::col('Mês', 'label'), self::col('Receita', 'revenue', 'dinheiro'), self::col('CMV', 'cogs', 'dinheiro'), self::col('Lucro', 'profit', 'dinheiro')]],
            ],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        // Receita Bruta (Faturas de Venda)
        $grossRevenue = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');

        $discounts = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum(DB::raw('COALESCE(discount_amount, 0)'));

        // Notas de Crédito (devoluções)
        $returns = (float) DB::table('invoicing_credit_notes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');

        $netRevenue = $grossRevenue - $returns;

        // Custo dos Produtos Vendidos (CMV) — cost × quantity
        $cogs = $this->cmv($tenantId, $de, $ate);

        $grossProfit = $netRevenue - $cogs;
        $grossMargin = $netRevenue > 0 ? ($grossProfit / $netRevenue * 100) : 0;

        // Despesas operacionais (proxy: compras no período)
        $expenses = (float) DB::table('invoicing_purchase_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        $netResult = $grossProfit; // sem despesas indirectas modeladas

        // Evolução mensal (últimos 6 meses)
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            $rev = (float) DB::table('invoicing_sales_invoices')
                ->where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$from, $to])
                ->where('status', '!=', 'cancelled')
                ->sum('subtotal');
            $cmv = $this->cmv($tenantId, $from, $to);

            $monthly[] = [
                'label' => $month->translatedFormat('M/Y'),
                'revenue' => $rev,
                'cogs' => $cmv,
                'profit' => $rev - $cmv,
            ];
        }

        $linhas = [
            ['rotulo' => 'Receita bruta', 'valor' => $grossRevenue],
            ['rotulo' => 'Descontos', 'valor' => $discounts],
            ['rotulo' => 'Devoluções (notas de crédito)', 'valor' => $returns],
            ['rotulo' => 'Receita líquida', 'valor' => $netRevenue],
            ['rotulo' => 'Custo dos produtos vendidos (CMV)', 'valor' => $cogs],
            ['rotulo' => 'Lucro bruto', 'valor' => $grossProfit],
            ['rotulo' => 'Compras do período', 'valor' => $expenses],
            ['rotulo' => 'Resultado', 'valor' => $netResult],
        ];

        return compact('grossRevenue', 'discounts', 'returns', 'netRevenue', 'cogs', 'grossProfit', 'grossMargin', 'expenses', 'netResult', 'monthly', 'linhas');
    }

    private function cmv(int $tenantId, $de, $ate): float
    {
        return (float) DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->sum(DB::raw('items.quantity * COALESCE(p.cost, 0)'));
    }
}

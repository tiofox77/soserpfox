<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** O mapa de IVA: liquidado nas vendas, dedutível nas compras, a pagar. */
class Iva extends Base
{
    public function esquema(): array
    {
        $colunas = [self::col('Taxa', 'tax_rate', 'percentagem'), self::col('Base', 'base', 'dinheiro'), self::col('IVA', 'tax', 'dinheiro')];

        return [
            'slug' => 'vat',
            'titulo' => 'Mapa de IVA',
            'descricao' => 'IVA liquidado, dedutível e a pagar no período.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('IVA liquidado (vendas)', 'totals.sales_tax', 'dinheiro', 'green'),
                self::cartao('IVA dedutível (compras)', 'totals.purchases_tax', 'dinheiro', 'orange'),
                self::cartao('IVA a pagar', 'totals.tax_payable', 'dinheiro', 'red'),
            ],
            'tabelas' => [
                ['titulo' => 'IVA liquidado — vendas por taxa', 'chave' => 'salesByRate', 'colunas' => $colunas, 'rodape' => ['base' => 'totals.sales_base', 'tax' => 'totals.sales_tax']],
                ['titulo' => 'IVA dedutível — compras por taxa', 'chave' => 'purchasesByRate', 'colunas' => $colunas, 'rodape' => ['base' => 'totals.purchases_base', 'tax' => 'totals.purchases_tax']],
            ],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        // IVA Liquidado (vendas)
        $salesByRate = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.tax_rate, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as base, SUM(items.tax_amount) as tax')
            ->groupBy('items.tax_rate')
            ->orderBy('items.tax_rate')
            ->get();

        // IVA Dedutível (compras)
        $purchasesByRate = DB::table('invoicing_purchase_invoice_items as items')
            ->join('invoicing_purchase_invoices as inv', 'inv.id', '=', 'items.purchase_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.tax_rate, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as base, SUM(items.tax_amount) as tax')
            ->groupBy('items.tax_rate')
            ->orderBy('items.tax_rate')
            ->get();

        $totals = [
            'sales_base' => $salesByRate->sum('base'),
            'sales_tax' => $salesByRate->sum('tax'),
            'purchases_base' => $purchasesByRate->sum('base'),
            'purchases_tax' => $purchasesByRate->sum('tax'),
        ];
        $totals['tax_payable'] = $totals['sales_tax'] - $totals['purchases_tax'];

        return compact('salesByRate', 'purchasesByRate', 'totals');
    }
}

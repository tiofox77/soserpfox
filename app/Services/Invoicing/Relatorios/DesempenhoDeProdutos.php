<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** O desempenho de cada produto: vendido, comprado, lucro e rotação. */
class DesempenhoDeProdutos extends Base
{
    public const TIPOS = ['produto' => 'Produtos', 'servico' => 'Serviços', 'all' => 'Todos'];

    public const ORDENS = ['profit_desc' => 'Maior lucro', 'revenue_desc' => 'Maior receita', 'qty_sold_desc' => 'Mais vendidos', 'margin_desc' => 'Maior margem', 'rotation_desc' => 'Maior rotação', 'stock_asc' => 'Menor stock'];

    public function esquema(): array
    {
        return [
            'slug' => 'product-performance',
            'titulo' => 'Desempenho de Produtos',
            'descricao' => 'Vendas, compras, stock, lucro e rotação de cada produto com movimento no período.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'typeFilter', 'rotulo' => 'Tipo', 'tipo' => 'select', 'opcoes' => self::opcoes(self::TIPOS), 'omissao' => 'produto'],
                ['nome' => 'sortBy', 'rotulo' => 'Ordenar por', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ORDENS), 'omissao' => 'profit_desc'],
                ['nome' => 'limit', 'rotulo' => 'Quantos', 'tipo' => 'number', 'omissao' => 100],
            ],
            'cartoes' => [
                self::cartao('Produtos com movimento', 'totals.products_count', 'inteiro', 'blue'),
                self::cartao('Receita', 'totals.revenue', 'dinheiro', 'green'),
                self::cartao('CMV', 'totals.cogs', 'dinheiro', 'orange'),
                self::cartao('Lucro', 'totals.profit', 'dinheiro', 'purple'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'colunas' => [
                    self::col('Produto', 'name'), self::col('Tipo', 'type'), self::col('Preço', 'price', 'dinheiro'), self::col('Custo', 'cost', 'dinheiro'),
                    self::col('Stock', 'stock', 'numero'), self::col('Vendido', 'qty_sold', 'numero'), self::col('Comprado', 'qty_bought', 'numero'),
                    self::col('Receita', 'revenue', 'dinheiro'), self::col('Lucro', 'profit', 'dinheiro'), self::col('Margem', 'margin', 'percentagem'), self::col('Rotação', 'rotation', 'numero'),
                ],
                'rodape' => ['qty_sold' => 'totals.qty_sold', 'qty_bought' => 'totals.qty_bought', 'revenue' => 'totals.revenue', 'profit' => 'totals.profit'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');
        $typeFilter = $this->filtro($f, 'typeFilter') ?? 'produto';
        $sortBy = $this->filtro($f, 'sortBy') ?? 'profit_desc';
        $limit = max(1, (int) ($this->filtro($f, 'limit') ?? 100));

        // Vendas por produto
        $sales = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.product_id, SUM(items.quantity) as qty_sold, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue, COUNT(DISTINCT inv.id) as sales_count')
            ->groupBy('items.product_id')
            ->get()
            ->keyBy('product_id');

        // Compras por produto
        $purchases = DB::table('invoicing_purchase_invoice_items as items')
            ->join('invoicing_purchase_invoices as inv', 'inv.id', '=', 'items.purchase_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.product_id, SUM(items.quantity) as qty_bought, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as total_cost')
            ->groupBy('items.product_id')
            ->get()
            ->keyBy('product_id');

        $productsQuery = DB::table('invoicing_products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);
        if ($typeFilter !== 'all') {
            $productsQuery->where('type', $typeFilter);
        }
        $products = $productsQuery->get();

        $rows = $products->map(function ($p) use ($sales, $purchases) {
            $s = $sales->get($p->id);
            $b = $purchases->get($p->id);
            $qtySold = $s->qty_sold ?? 0;
            $revenue = $s->revenue ?? 0;
            $qtyBought = $b->qty_bought ?? 0;
            $estimatedCogs = $qtySold * ($p->cost ?? 0);
            $profit = $revenue - $estimatedCogs;

            return (object) [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'type' => $p->type,
                'price' => $p->price,
                'cost' => $p->cost,
                'stock' => $p->stock_quantity ?? 0,
                'qty_sold' => $qtySold,
                'qty_bought' => $qtyBought,
                'revenue' => $revenue,
                'cogs' => $estimatedCogs,
                'profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue * 100) : 0,
                'sales_count' => $s->sales_count ?? 0,
                'rotation' => ($p->stock_quantity ?? 0) > 0 ? ($qtySold / $p->stock_quantity) : ($qtySold > 0 ? 999 : 0),
            ];
        })->filter(fn ($r) => $r->qty_sold > 0 || $r->qty_bought > 0); // só produtos com movimento

        $rows = match ($sortBy) {
            'revenue_desc' => $rows->sortByDesc('revenue'),
            'qty_sold_desc' => $rows->sortByDesc('qty_sold'),
            'margin_desc' => $rows->sortByDesc('margin'),
            'rotation_desc' => $rows->sortByDesc('rotation'),
            'stock_asc' => $rows->sortBy('stock'),
            default => $rows->sortByDesc('profit'),
        };

        $rows = $rows->take($limit)->values();

        $totals = [
            'qty_sold' => $rows->sum('qty_sold'),
            'qty_bought' => $rows->sum('qty_bought'),
            'revenue' => $rows->sum('revenue'),
            'cogs' => $rows->sum('cogs'),
            'profit' => $rows->sum('profit'),
            'products_count' => $rows->count(),
        ];

        return compact('rows', 'totals');
    }
}

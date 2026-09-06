<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** Só os serviços: quanto renderam, e que peso têm nas vendas. */
class Servicos extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'services',
            'titulo' => 'Mapa de Serviços',
            'descricao' => 'Análise dos serviços prestados no período e do seu peso na facturação.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('Serviços', 'totals.services_count', 'inteiro', 'blue'),
                self::cartao('Receita', 'totals.revenue', 'dinheiro', 'green'),
                self::cartao('Lucro', 'totals.profit', 'dinheiro', 'purple'),
                self::cartao('Margem', 'totals.margin', 'percentagem', 'orange'),
                self::cartao('Peso nas vendas', 'servicesShare', 'percentagem', 'pink'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'colunas' => [
                    self::col('Serviço', 'name'), self::col('Qtd', 'qty', 'numero'), self::col('Preço Méd.', 'avg_price', 'dinheiro'), self::col('Faturas', 'invoices_count', 'inteiro'),
                    self::col('Clientes', 'clients_count', 'inteiro'), self::col('Receita', 'revenue', 'dinheiro'), self::col('Custo', 'total_cost', 'dinheiro'),
                    self::col('Lucro', 'profit', 'dinheiro'), self::col('Margem', 'margin', 'percentagem'),
                ],
                'rodape' => ['qty' => 'totals.qty', 'revenue' => 'totals.revenue', 'total_cost' => 'totals.cost', 'profit' => 'totals.profit', 'margin' => 'totals.margin'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        $rows = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->join('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->where('p.type', 'servico')
            ->selectRaw('
                p.id, p.name, p.sku, p.price, p.cost,
                SUM(items.quantity) as qty,
                AVG(items.unit_price) as avg_price,
                SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue,
                SUM(items.quantity * COALESCE(p.cost, 0)) as total_cost,
                COUNT(DISTINCT inv.id) as invoices_count,
                COUNT(DISTINCT inv.client_id) as clients_count
            ')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.price', 'p.cost')
            ->orderByDesc('revenue')
            ->get();

        $rows = $rows->map(function ($r) {
            $r->profit = $r->revenue - $r->total_cost;
            $r->margin = $r->revenue > 0 ? ($r->profit / $r->revenue * 100) : 0;

            return $r;
        });

        $totals = [
            'services_count' => $rows->count(),
            'qty' => $rows->sum('qty'),
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('total_cost'),
            'profit' => $rows->sum('profit'),
            'invoices' => $rows->sum('invoices_count'),
            'clients' => $rows->sum('clients_count'),
        ];
        $totals['margin'] = $totals['revenue'] > 0 ? ($totals['profit'] / $totals['revenue'] * 100) : 0;

        // Total geral de vendas (para comparar peso dos serviços)
        $totalRevenueAll = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');
        $servicesShare = $totalRevenueAll > 0 ? ($totals['revenue'] / $totalRevenueAll * 100) : 0;

        return compact('rows', 'totals', 'servicesShare', 'totalRevenueAll');
    }
}

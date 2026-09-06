<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** A margem por produto: receita, custo, lucro e margem em percentagem. */
class Margens extends Base
{
    public const ORDENS = ['profit_desc' => 'Maior lucro', 'profit_asc' => 'Maior prejuízo', 'margin_desc' => 'Maior margem %', 'margin_asc' => 'Menor margem %', 'revenue_desc' => 'Maior receita'];

    public function esquema(): array
    {
        return [
            'slug' => 'margin',
            'titulo' => 'Análise de Margem',
            'descricao' => 'Lucro e margem por produto no período.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'sortBy', 'rotulo' => 'Ordenar por', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ORDENS), 'omissao' => 'profit_desc'],
                ['nome' => 'limit', 'rotulo' => 'Quantos', 'tipo' => 'number', 'omissao' => 50],
            ],
            'cartoes' => [
                self::cartao('Receita', 'totals.revenue', 'dinheiro', 'blue'),
                self::cartao('Custo', 'totals.cost', 'dinheiro', 'orange'),
                self::cartao('Lucro', 'totals.profit', 'dinheiro', 'green'),
                self::cartao('Margem', 'totals.margin', 'percentagem', 'purple'),
                self::cartao('Com prejuízo', 'lossCount', 'inteiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'colunas' => [
                    self::col('Produto', 'name'), self::col('Tipo', 'type'), self::col('Qtd', 'qty', 'numero'), self::col('Preço Méd.', 'avg_price', 'dinheiro'),
                    self::col('Custo Unit.', 'cost', 'dinheiro'), self::col('Receita', 'revenue', 'dinheiro'), self::col('Custo Total', 'total_cost', 'dinheiro'),
                    self::col('Lucro', 'profit', 'dinheiro'), self::col('Margem %', 'margin', 'percentagem'),
                ],
                'rodape' => ['revenue' => 'totals.revenue', 'total_cost' => 'totals.cost', 'profit' => 'totals.profit', 'margin' => 'totals.margin'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');
        $sortBy = $this->filtro($f, 'sortBy') ?? 'profit_desc';
        $limit = max(1, (int) ($this->filtro($f, 'limit') ?? 50));

        $rows = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('
                p.id, p.name, p.sku, p.type, p.cost,
                SUM(items.quantity) as qty,
                AVG(items.unit_price) as avg_price,
                SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue,
                SUM(items.quantity * COALESCE(p.cost, 0)) as total_cost,
                SUM((items.subtotal - COALESCE(items.discount_amount,0)) - (items.quantity * COALESCE(p.cost, 0))) as profit
            ')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.type', 'p.cost')
            ->get();

        $rows = $rows->map(function ($r) {
            $r->margin = $r->revenue > 0 ? (($r->profit / $r->revenue) * 100) : 0;

            return $r;
        });

        $rows = match ($sortBy) {
            'profit_asc' => $rows->sortBy('profit'),
            'margin_desc' => $rows->sortByDesc('margin'),
            'margin_asc' => $rows->sortBy('margin'),
            'revenue_desc' => $rows->sortByDesc('revenue'),
            default => $rows->sortByDesc('profit'),
        };

        $rows = $rows->take($limit)->values();

        $totals = [
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('total_cost'),
            'profit' => $rows->sum('profit'),
        ];
        $totals['margin'] = $totals['revenue'] > 0 ? ($totals['profit'] / $totals['revenue'] * 100) : 0;

        $lossCount = $rows->filter(fn ($r) => $r->profit < 0)->count();

        return compact('rows', 'totals', 'lossCount');
    }
}

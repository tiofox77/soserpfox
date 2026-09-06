<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** Os produtos mais vendidos, por quantidade. */
class MelhoresProdutos extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'top-products',
            'titulo' => 'Top Produtos Vendidos',
            'descricao' => 'Produtos com maior volume no período.',
            'periodo' => ['omissao' => 'year'],
            'filtros' => [
                ['nome' => 'limit', 'rotulo' => 'Quantos', 'tipo' => 'number', 'omissao' => 20],
            ],
            'cartoes' => [
                self::cartao('Quantidade', 'grandQty', 'numero', 'blue'),
                self::cartao('Receita', 'grandValue', 'dinheiro', 'green'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'numerada' => true,
                'colunas' => [
                    self::col('Produto', 'name'), self::col('Código', 'sku'), self::col('Qtd Total', 'total_qty', 'numero'), self::col('Faturas', 'invoices_count', 'inteiro'),
                    self::col('Clientes', 'clients_count', 'inteiro'), self::col('Receita', 'total_value', 'dinheiro'), self::col('Volume', 'pct', 'percentagem'),
                ],
                'rodape' => ['total_qty' => 'grandQty', 'total_value' => 'grandValue'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'year');
        $limit = max(1, (int) ($this->filtro($f, 'limit') ?? 20));

        $rows = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->selectRaw('p.id, p.name, p.sku, SUM(items.quantity) as total_qty, SUM(items.subtotal) as total_value, COUNT(DISTINCT inv.id) as invoices_count, COUNT(DISTINCT inv.client_id) as clients_count')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        $grandQty = $rows->sum('total_qty');
        $grandValue = $rows->sum('total_value');
        $rows->each(fn ($r) => $r->pct = $grandValue > 0 ? $r->total_value / $grandValue * 100 : 0);

        return compact('rows', 'grandQty', 'grandValue');
    }
}

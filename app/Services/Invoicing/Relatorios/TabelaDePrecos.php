<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** A tabela de preços: custo, venda, lucro unitário, margem e markup. */
class TabelaDePrecos extends Base
{
    public const TIPOS = ['all' => 'Todos', 'produto' => 'Produtos', 'servico' => 'Serviços'];

    public const ESTADOS = ['active' => 'Activos', 'inactive' => 'Inactivos', 'all' => 'Todos'];

    public const ORDENS = ['name_asc' => 'Nome (A-Z)', 'profit_desc' => 'Maior lucro', 'margin_desc' => 'Maior margem', 'price_desc' => 'Preço mais alto', 'cost_desc' => 'Custo mais alto', 'stock_asc' => 'Menor stock'];

    public function esquema(): array
    {
        return [
            'slug' => 'price-list',
            'titulo' => 'Tabela de Preços e Lucro',
            'descricao' => 'Preço de compra, de venda, lucro unitário, margem e markup de cada artigo.',
            'periodo' => null,
            'filtros' => [
                ['nome' => 'search', 'rotulo' => 'Procurar', 'tipo' => 'text'],
                ['nome' => 'typeFilter', 'rotulo' => 'Tipo', 'tipo' => 'select', 'opcoes' => self::opcoes(self::TIPOS), 'omissao' => 'all'],
                ['nome' => 'statusFilter', 'rotulo' => 'Estado', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ESTADOS), 'omissao' => 'active'],
                ['nome' => 'sortBy', 'rotulo' => 'Ordenar por', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ORDENS), 'omissao' => 'name_asc'],
            ],
            'cartoes' => [
                self::cartao('Artigos', 'totals.count', 'inteiro', 'blue'),
                self::cartao('Sem custo', 'totals.without_cost', 'inteiro', 'orange'),
                self::cartao('Margem negativa', 'totals.negative_margin', 'inteiro', 'red'),
                self::cartao('Margem média', 'totals.avg_margin', 'percentagem', 'purple'),
                self::cartao('Stock ao custo', 'totals.total_stock_value_cost', 'dinheiro', 'gray'),
                self::cartao('Stock à venda', 'totals.total_stock_value_sale', 'dinheiro', 'green'),
            ],
            'tabelas' => [[
                'chave' => 'products',
                'numerada' => true,
                'colunas' => [
                    self::col('Produto', 'name'), self::col('Código', 'sku'), self::col('Tipo', 'type'), self::col('Stock', 'stock_quantity', 'numero'),
                    self::col('Preço Compra', 'cost', 'dinheiro'), self::col('Preço Venda', 'price', 'dinheiro'), self::col('Lucro Unit.', 'profit', 'dinheiro'),
                    self::col('Margem %', 'margin', 'percentagem'), self::col('Markup %', 'markup', 'percentagem'), self::col('Estado', 'estado', 'estado', 'centro'),
                ],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        $statusFilter = $this->filtro($f, 'statusFilter') ?? 'active';
        $typeFilter = $this->filtro($f, 'typeFilter') ?? 'all';
        $search = trim((string) ($this->filtro($f, 'search') ?? ''));
        $sortBy = $this->filtro($f, 'sortBy') ?? 'name_asc';

        $query = DB::table('invoicing_products')->where('tenant_id', $tenantId);

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }
        if ($typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }
        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('sku', 'like', $s)
                    ->orWhere('barcode', 'like', $s);
            });
        }

        $products = $query->get()->map(function ($p) {
            $p->cost = (float) ($p->cost ?? 0);
            $p->price = (float) ($p->price ?? 0);
            $p->profit = $p->price - $p->cost;
            $p->margin = $p->price > 0 ? ($p->profit / $p->price * 100) : 0;
            $p->markup = $p->cost > 0 ? ($p->profit / $p->cost * 100) : 0;
            $p->estado = $p->is_active ? 'active' : 'inactive';

            return $p;
        });

        $products = match ($sortBy) {
            'profit_desc' => $products->sortByDesc('profit'),
            'margin_desc' => $products->sortByDesc('margin'),
            'price_desc' => $products->sortByDesc('price'),
            'cost_desc' => $products->sortByDesc('cost'),
            'stock_asc' => $products->sortBy('stock_quantity'),
            default => $products->sortBy('name'),
        };
        $products = $products->values();

        $totals = [
            'count' => $products->count(),
            'without_cost' => $products->where('cost', 0)->count(),
            'without_price' => $products->where('price', 0)->count(),
            'negative_margin' => $products->filter(fn ($p) => $p->profit < 0)->count(),
            'avg_margin' => $products->where('price', '>', 0)->avg('margin') ?? 0,
            'total_stock_value_cost' => $products->sum(fn ($p) => ($p->stock_quantity ?? 0) * $p->cost),
            'total_stock_value_sale' => $products->sum(fn ($p) => ($p->stock_quantity ?? 0) * $p->price),
        ];

        return compact('products', 'totals');
    }
}

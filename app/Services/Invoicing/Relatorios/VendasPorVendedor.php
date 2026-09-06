<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** Quem vendeu quanto: o ranking por utilizador. */
class VendasPorVendedor extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'sales-by-user',
            'titulo' => 'Vendas por Vendedor',
            'descricao' => 'Ranking e desempenho por utilizador, sem anuladas nem creditadas.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('Documentos', 'grandCount', 'inteiro', 'blue'),
                self::cartao('Total vendido', 'grandTotal', 'dinheiro', 'green'),
            ],
            'tabelas' => [[
                'chave' => 'byUser',
                'numerada' => true,
                'colunas' => [
                    self::col('Vendedor', 'user'), self::col('Docs', 'count', 'inteiro'), self::col('Total Vendido', 'total', 'dinheiro'), self::col('Recebido', 'paid', 'dinheiro'),
                    self::col('Pendente', 'pending', 'dinheiro'), self::col('Ticket Médio', 'avg', 'dinheiro'), self::col('% do Total', 'pct', 'percentagem'),
                ],
                'rodape' => ['count' => 'grandCount', 'total' => 'grandTotal'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        // Vendas efectivas (exclui canceladas e creditadas). Colunas qualificadas (join com users).
        $base = DB::table('invoicing_sales_invoices')
            ->where('invoicing_sales_invoices.tenant_id', $tenantId)
            ->whereBetween('invoicing_sales_invoices.invoice_date', [$de, $ate])
            ->whereNotIn('invoicing_sales_invoices.status', ['cancelled', 'credited']);

        $grandTotal = (float) (clone $base)->sum('total');
        $grandCount = (int) (clone $base)->count();

        $byUser = (clone $base)
            ->leftJoin('users', 'invoicing_sales_invoices.created_by', '=', 'users.id')
            ->select(
                'invoicing_sales_invoices.created_by',
                DB::raw('COALESCE(users.name, "—") as user_name'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(invoicing_sales_invoices.total) as total'),
                DB::raw('SUM(invoicing_sales_invoices.paid_amount) as paid')
            )
            ->groupBy('invoicing_sales_invoices.created_by', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user_name,
                'count' => (int) $r->cnt,
                'total' => (float) $r->total,
                'paid' => (float) $r->paid,
                'pending' => max(0, (float) $r->total - (float) $r->paid),
                'avg' => $r->cnt > 0 ? (float) $r->total / $r->cnt : 0,
                'pct' => $grandTotal > 0 ? (float) $r->total / $grandTotal * 100 : 0,
            ]);

        return compact('byUser', 'grandTotal', 'grandCount');
    }
}

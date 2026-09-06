<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\SalesInvoice;

/** O ranking de clientes por facturação. */
class MelhoresClientes extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'top-clients',
            'titulo' => 'Top Clientes',
            'descricao' => 'Ranking de clientes por facturação no período.',
            'periodo' => ['omissao' => 'year'],
            'filtros' => [
                ['nome' => 'limit', 'rotulo' => 'Quantos', 'tipo' => 'number', 'omissao' => 20],
            ],
            'cartoes' => [
                self::cartao('Facturado pelos melhores', 'grandTotal', 'dinheiro', 'green'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'numerada' => true,
                'colunas' => [
                    self::col('Cliente', 'client.name'), self::col('Faturas', 'invoices_count', 'inteiro'), self::col('Faturado', 'total_revenue', 'dinheiro'),
                    self::col('Pago', 'total_paid', 'dinheiro'), self::col('% do Total', 'pct', 'percentagem'),
                ],
                'rodape' => ['total_revenue' => 'grandTotal'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'year');
        $limit = max(1, (int) ($this->filtro($f, 'limit') ?? 20));

        $rows = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->selectRaw('client_id, COUNT(*) as invoices_count, SUM(total) as total_revenue, SUM(paid_amount) as total_paid')
            ->groupBy('client_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        $grandTotal = $rows->sum('total_revenue');
        $rows->each(fn ($r) => $r->pct = $grandTotal > 0 ? $r->total_revenue / $grandTotal * 100 : 0);

        return compact('rows', 'grandTotal');
    }
}

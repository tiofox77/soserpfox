<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\PurchaseInvoice;

/** O ranking de fornecedores por valor comprado. */
class MelhoresFornecedores extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'top-suppliers',
            'titulo' => 'Top Fornecedores',
            'descricao' => 'Ranking de fornecedores por valor comprado no período.',
            'periodo' => ['omissao' => 'year'],
            'filtros' => [
                ['nome' => 'limit', 'rotulo' => 'Quantos', 'tipo' => 'number', 'omissao' => 20],
            ],
            'cartoes' => [
                self::cartao('Comprado aos melhores', 'grandTotal', 'dinheiro', 'orange'),
            ],
            'tabelas' => [[
                'chave' => 'rows',
                'numerada' => true,
                'colunas' => [
                    self::col('Fornecedor', 'supplier.name'), self::col('Faturas', 'invoices_count', 'inteiro'), self::col('Comprado', 'total_value', 'dinheiro'),
                    self::col('Pago', 'total_paid', 'dinheiro'), self::col('% do Total', 'pct', 'percentagem'),
                ],
                'rodape' => ['total_value' => 'grandTotal'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'year');
        $limit = max(1, (int) ($this->filtro($f, 'limit') ?? 20));

        $rows = PurchaseInvoice::with('supplier')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate])
            ->selectRaw('supplier_id, COUNT(*) as invoices_count, SUM(total) as total_value, SUM(paid_amount) as total_paid')
            ->groupBy('supplier_id')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        $grandTotal = $rows->sum('total_value');
        $rows->each(fn ($r) => $r->pct = $grandTotal > 0 ? $r->total_value / $grandTotal * 100 : 0);

        return compact('rows', 'grandTotal');
    }
}

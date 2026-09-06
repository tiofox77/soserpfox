<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;

/** Quantos documentos de cada tipo saíram no período, e quanto valem. */
class Documentos extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'documents',
            'titulo' => 'Mapa de Documentos',
            'descricao' => 'Resumo de faturas, notas de crédito e débito, recibos e adiantamentos.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [],
            'tabelas' => [[
                'chave' => 'rows',
                'colunas' => [
                    self::col('Tipo de Documento', 'name'), self::col('Quantidade', 'count', 'inteiro'), self::col('Valor Total (Kz)', 'total', 'dinheiro'),
                ],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$from, $to] = $this->intervalo($f, 'month');

        $rows = [];
        $rows[] = $this->resumir('Faturas de Venda', 'fa-file-invoice', 'green',
            SalesInvoice::where('tenant_id', $tenantId)->whereBetween('invoice_date', [$from, $to]), 'total');
        $rows[] = $this->resumir('Faturas de Compra', 'fa-file-invoice-dollar', 'orange',
            PurchaseInvoice::where('tenant_id', $tenantId)->whereBetween('invoice_date', [$from, $to]), 'total');
        $rows[] = $this->resumir('Notas de Crédito', 'fa-file-circle-minus', 'red',
            CreditNote::where('tenant_id', $tenantId)->whereBetween('issue_date', [$from, $to]), 'total');
        $rows[] = $this->resumir('Notas de Débito', 'fa-file-circle-plus', 'pink',
            DebitNote::where('tenant_id', $tenantId)->whereBetween('issue_date', [$from, $to]), 'total');
        $rows[] = $this->resumir('Recibos', 'fa-receipt', 'blue',
            Receipt::where('tenant_id', $tenantId)->whereBetween('payment_date', [$from, $to]), 'amount_paid');
        $rows[] = $this->resumir('Adiantamentos', 'fa-coins', 'yellow',
            Advance::where('tenant_id', $tenantId)->whereBetween('payment_date', [$from, $to]), 'amount');

        try {
            $rows[] = $this->resumir('Proformas Venda', 'fa-file-alt', 'purple',
                SalesProforma::where('tenant_id', $tenantId)->whereBetween('proforma_date', [$from, $to]), 'total');
        } catch (\Throwable $e) {
        }

        return compact('rows');
    }

    private function resumir(string $name, string $icon, string $color, $query, string $totalField): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'color' => $color,
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum($totalField),
        ];
    }
}

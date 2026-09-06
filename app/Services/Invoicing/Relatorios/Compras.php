<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Supplier;

/** O mapa de compras: as facturas de fornecedores do período. */
class Compras extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'purchases',
            'titulo' => 'Mapa de Compras',
            'descricao' => 'Compras por período e fornecedor.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'supplierId', 'rotulo' => 'Fornecedor', 'tipo' => 'select', 'opcoes' => 'suppliers'],
            ],
            'cartoes' => [
                self::cartao('Documentos', 'totals.count', 'inteiro', 'blue'),
                self::cartao('Subtotal', 'totals.subtotal', 'dinheiro', 'gray'),
                self::cartao('IVA', 'totals.tax', 'dinheiro', 'purple'),
                self::cartao('Total', 'totals.total', 'dinheiro', 'orange'),
                self::cartao('Pendente', 'totals.pending', 'dinheiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'invoices',
                'colunas' => [
                    self::col('Nº', 'invoice_number'), self::col('Data', 'invoice_date', 'data'), self::col('Fornecedor', 'supplier.name'),
                    self::col('Subtotal', 'subtotal', 'dinheiro'), self::col('IVA', 'tax_amount', 'dinheiro'), self::col('Total', 'total', 'dinheiro'),
                    self::col('Pago', 'paid_amount', 'dinheiro'), self::col('Estado', 'status', 'estado', 'centro'),
                ],
                'rodape' => ['subtotal' => 'totals.subtotal', 'tax_amount' => 'totals.tax', 'total' => 'totals.total', 'paid_amount' => 'totals.paid'],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        $query = PurchaseInvoice::with('supplier')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate]);

        if ($fornecedor = $this->filtro($f, 'supplierId')) {
            $query->where('supplier_id', $fornecedor);
        }

        $invoices = (clone $query)->orderByDesc('invoice_date')->limit(500)->get();

        $totals = [
            'count' => (clone $query)->count(),
            'subtotal' => (clone $query)->sum('subtotal'),
            'tax' => (clone $query)->sum('tax_amount'),
            'total' => (clone $query)->sum('total'),
            'paid' => (clone $query)->sum('paid_amount'),
        ];
        $totals['pending'] = max(0, $totals['total'] - $totals['paid']);

        $suppliers = Supplier::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return compact('invoices', 'totals', 'suppliers');
    }
}

<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;

/** O mapa de vendas: as facturas do período, por cliente e estado. */
class Vendas extends Base
{
    public const ESTADOS = ['pending' => 'Pendente', 'partial' => 'Parcial', 'paid' => 'Paga', 'cancelled' => 'Anulada', 'credited' => 'Creditada'];

    public function esquema(): array
    {
        return [
            'slug' => 'sales',
            'titulo' => 'Mapa de Vendas',
            'descricao' => 'Vendas por período, cliente e estado.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'clientId', 'rotulo' => 'Cliente', 'tipo' => 'select', 'opcoes' => 'clients'],
                ['nome' => 'status', 'rotulo' => 'Estado', 'tipo' => 'select', 'opcoes' => self::opcoes(self::ESTADOS)],
            ],
            'cartoes' => [
                self::cartao('Documentos', 'totals.count', 'inteiro', 'blue'),
                self::cartao('Subtotal', 'totals.subtotal', 'dinheiro', 'gray'),
                self::cartao('IVA', 'totals.tax', 'dinheiro', 'purple'),
                self::cartao('Total', 'totals.total', 'dinheiro', 'green'),
                self::cartao('Pendente', 'totals.pending', 'dinheiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'invoices',
                'colunas' => [
                    self::col('Nº', 'invoice_number'), self::col('Data', 'invoice_date', 'data'), self::col('Cliente', 'client.name'),
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

        $query = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$de, $ate]);

        if ($cliente = $this->filtro($f, 'clientId')) {
            $query->where('client_id', $cliente);
        }
        if ($estado = $this->filtro($f, 'status')) {
            $query->where('status', $estado);
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

        $clients = Client::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return compact('invoices', 'totals', 'clients');
    }
}

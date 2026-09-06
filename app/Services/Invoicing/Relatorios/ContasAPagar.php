<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\PurchaseInvoice;
use Carbon\Carbon;

/** As facturas de fornecedores com saldo em aberto, hoje. */
class ContasAPagar extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'accounts-payable',
            'titulo' => 'Contas a Pagar',
            'descricao' => 'Faturas pendentes a fornecedores, com saldo em aberto.',
            'periodo' => null,
            'filtros' => [
                ['nome' => 'statusFilter', 'rotulo' => 'Mostrar', 'tipo' => 'select', 'opcoes' => self::opcoes(ContasAReceber::FILTROS), 'omissao' => 'open'],
            ],
            'cartoes' => [
                self::cartao('Total facturado', 'totals.total', 'dinheiro', 'blue'),
                self::cartao('Já pago', 'totals.paid', 'dinheiro', 'green'),
                self::cartao('Saldo em aberto', 'totals.balance', 'dinheiro', 'orange'),
                self::cartao('Vencido', 'overdueTotal', 'dinheiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'invoices',
                'colunas' => [
                    self::col('Nº Fatura', 'invoice_number'), self::col('Fornecedor', 'supplier.name'), self::col('Emissão', 'invoice_date', 'data'),
                    self::col('Vencimento', 'due_date', 'data'), self::col('Dias', 'dias', 'dias'), self::col('Total', 'total', 'dinheiro'),
                    self::col('Pago', 'paid_amount', 'dinheiro'), self::col('Saldo', 'saldo', 'dinheiro'),
                ],
                'rodape' => ['total' => 'totals.total', 'paid_amount' => 'totals.paid', 'saldo' => 'totals.balance'],
                'vazio' => 'Nada em aberto.',
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        $today = Carbon::today();

        $query = PurchaseInvoice::with('supplier')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled', 'credited'])
            ->whereRaw('total > COALESCE(paid_amount, 0) + 0.01');

        $filtro = $this->filtro($f, 'statusFilter') ?? 'open';
        if ($filtro === 'overdue') {
            $query->where('due_date', '<', $today);
        } elseif ($filtro === 'current') {
            $query->where('due_date', '>=', $today);
        }

        $invoices = (clone $query)->orderBy('due_date')->limit(500)->get();
        $invoices->each(function ($i) use ($today) {
            $i->dias = $i->due_date ? (int) $i->due_date->diffInDays($today, false) : null;
            $i->saldo = (float) $i->total - (float) ($i->paid_amount ?? 0);
        });

        $totals = [
            'total' => $invoices->sum('total'),
            'paid' => $invoices->sum('paid_amount'),
        ];
        $totals['balance'] = $totals['total'] - $totals['paid'];

        $overdueRows = $invoices->filter(fn ($i) => $i->due_date && $i->due_date->lt($today));
        $overdueTotal = $overdueRows->sum(fn ($i) => $i->total - ($i->paid_amount ?? 0));

        return compact('invoices', 'totals', 'overdueTotal', 'today');
    }
}

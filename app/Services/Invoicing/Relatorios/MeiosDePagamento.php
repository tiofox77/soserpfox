<?php

namespace App\Services\Invoicing\Relatorios;

use Illuminate\Support\Facades\DB;

/** Os recebimentos do período, por meio de pagamento. */
class MeiosDePagamento extends Base
{
    public static function rotulo($m): string
    {
        $map = [
            'cash' => 'Numerário', 'numerario' => 'Numerário', 'dinheiro' => 'Numerário',
            'transfer' => 'Transferência', 'transferencia' => 'Transferência', 'bank_transfer' => 'Transferência',
            'multicaixa' => 'Multicaixa', 'tpa' => 'TPA / Multicaixa', 'card' => 'Cartão',
            'cheque' => 'Cheque', 'check' => 'Cheque', 'mobile' => 'Pagamento Móvel',
            'express' => 'Multicaixa Express', 'deposit' => 'Depósito',
        ];

        return $map[strtolower((string) $m)] ?? ($m ? ucfirst((string) $m) : 'Não especificado');
    }

    public function esquema(): array
    {
        return [
            'slug' => 'payment-methods',
            'titulo' => 'Recebimentos por Meio de Pagamento',
            'descricao' => 'Total recebido por forma de pagamento, e os últimos recebimentos.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'method', 'rotulo' => 'Meio', 'tipo' => 'select', 'opcoes' => 'metodos'],
            ],
            'cartoes' => [
                self::cartao('Recibos', 'grandCount', 'inteiro', 'blue'),
                self::cartao('Total recebido', 'grandTotal', 'dinheiro', 'green'),
            ],
            'tabelas' => [
                ['titulo' => 'Por meio de pagamento', 'chave' => 'byMethod', 'colunas' => [self::col('Meio', 'label'), self::col('Nº', 'count', 'inteiro'), self::col('Total', 'total', 'dinheiro'), self::col('%', 'pct', 'percentagem')], 'rodape' => ['count' => 'grandCount', 'total' => 'grandTotal']],
                ['titulo' => 'Últimos recebimentos', 'chave' => 'receipts', 'colunas' => [self::col('Data', 'payment_date', 'data'), self::col('Recibo', 'receipt_number'), self::col('Cliente', 'client_name'), self::col('Meio', 'label'), self::col('Valor', 'amount_paid', 'dinheiro')]],
            ],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');
        $method = (string) ($this->filtro($f, 'method') ?? '');

        // Colunas qualificadas (há join com invoicing_clients, que também tem tenant_id)
        $base = DB::table('invoicing_receipts')
            ->where('invoicing_receipts.tenant_id', $tenantId)
            ->whereBetween('invoicing_receipts.payment_date', [$de, $ate]);
        if ($method !== '') {
            $base->where('invoicing_receipts.payment_method', $method);
        }

        $grandTotal = (float) (clone $base)->sum('amount_paid');
        $grandCount = (int) (clone $base)->count();

        $byMethod = (clone $base)
            ->select('payment_method', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(amount_paid) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->payment_method,
                'label' => self::rotulo($r->payment_method),
                'count' => (int) $r->cnt,
                'total' => (float) $r->total,
                'pct' => $grandTotal > 0 ? (float) $r->total / $grandTotal * 100 : 0,
            ]);

        // Meios disponíveis para o filtro
        $methods = DB::table('invoicing_receipts')
            ->where('tenant_id', $tenantId)
            ->select('payment_method')->distinct()->pluck('payment_method')
            ->filter()->values();
        $metodos = $methods->map(fn ($m) => ['valor' => $m, 'rotulo' => self::rotulo($m)])->values();

        $receipts = (clone $base)
            ->leftJoin('invoicing_clients', 'invoicing_receipts.client_id', '=', 'invoicing_clients.id')
            ->select('invoicing_receipts.*', 'invoicing_clients.name as client_name')
            ->orderByDesc('payment_date')
            ->limit(300)
            ->get()
            ->each(fn ($r) => $r->label = self::rotulo($r->payment_method));

        return compact('byMethod', 'grandTotal', 'grandCount', 'methods', 'metodos', 'receipts');
    }
}

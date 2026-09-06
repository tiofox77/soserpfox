<?php

namespace App\Services\Invoicing\Relatorios;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** O melhor fornecedor por um score de volume, frequência, fiabilidade e prazo. */
class MelhorFornecedor extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'best-supplier',
            'titulo' => 'Melhor Fornecedor',
            'descricao' => 'Score multi-critério: volume (30), frequência (20), fiabilidade (25) e prazo de pagamento (25).',
            'periodo' => ['omissao' => 'year'],
            'filtros' => [],
            'cartoes' => [],
            'tabelas' => [[
                'chave' => 'stats',
                'numerada' => true,
                'colunas' => [
                    self::col('Fornecedor', 'name'), self::col('NIF', 'nif'), self::col('Faturas', 'invoices_count', 'inteiro'), self::col('Volume', 'total_value', 'dinheiro'),
                    self::col('Ticket Médio', 'avg_ticket', 'dinheiro'), self::col('Fiabilidade', 'reliability_pct', 'percentagem'), self::col('Prazo Médio', 'avg_payment_term', 'dias'),
                    self::col('Score', 'total_score', 'numero'), self::col('Distribuição', 'distribuicao'),
                ],
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'year');
        $today = Carbon::today();

        $stats = DB::table('invoicing_purchase_invoices as inv')
            ->join('invoicing_suppliers as s', 's.id', '=', 'inv.supplier_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$de, $ate])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('
                s.id, s.name, s.nif,
                COUNT(inv.id) as invoices_count,
                SUM(inv.total) as total_value,
                AVG(inv.total) as avg_ticket,
                SUM(CASE WHEN inv.status = "paid" THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN inv.due_date < ? AND inv.status != "paid" THEN 1 ELSE 0 END) as overdue_count,
                AVG(DATEDIFF(COALESCE(inv.due_date, inv.invoice_date), inv.invoice_date)) as avg_payment_term
            ', [$today])
            ->groupBy('s.id', 's.name', 's.nif')
            ->having('invoices_count', '>=', 1)
            ->get();

        // Score (0-100) por vários critérios
        $maxValue = $stats->max('total_value') ?: 1;
        $maxInvoices = $stats->max('invoices_count') ?: 1;

        $stats = $stats->map(function ($s) use ($maxValue, $maxInvoices) {
            $volumeScore = ($s->total_value / $maxValue) * 30;
            $frequencyScore = ($s->invoices_count / $maxInvoices) * 20;
            $reliabilityScore = $s->invoices_count > 0 ? (($s->invoices_count - $s->overdue_count) / $s->invoices_count) * 25 : 0;
            $paymentScore = min(25, max(0, ($s->avg_payment_term ?? 0) / 60 * 25));

            $s->volume_score = round($volumeScore, 1);
            $s->frequency_score = round($frequencyScore, 1);
            $s->reliability_score = round($reliabilityScore, 1);
            $s->payment_score = round($paymentScore, 1);
            $s->total_score = round($volumeScore + $frequencyScore + $reliabilityScore + $paymentScore, 1);
            $s->reliability_pct = $s->invoices_count > 0 ? round((($s->invoices_count - $s->overdue_count) / $s->invoices_count) * 100, 1) : 0;
            $s->distribuicao = "V {$s->volume_score} · F {$s->frequency_score} · Fiab. {$s->reliability_score} · Prazo {$s->payment_score}";

            return $s;
        })->sortByDesc('total_score')->values();

        return compact('stats');
    }
}

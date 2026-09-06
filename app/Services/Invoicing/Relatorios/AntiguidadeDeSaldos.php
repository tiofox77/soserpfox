<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\SalesInvoice;
use Carbon\Carbon;

/** O aging: os saldos em aberto de cada cliente, por faixa de atraso. */
class AntiguidadeDeSaldos extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'aging-clients',
            'titulo' => 'Aging de Clientes',
            'descricao' => 'Antiguidade dos saldos em aberto, por faixa de dias de atraso.',
            'periodo' => null,
            'filtros' => [],
            'cartoes' => [
                self::cartao('A vencer', 'bucketTotals.current', 'dinheiro', 'green'),
                self::cartao('1 a 30 dias', 'bucketTotals.days_30', 'dinheiro', 'yellow'),
                self::cartao('31 a 90 dias', 'bucketTotals.medio', 'dinheiro', 'orange'),
                self::cartao('Mais de 90 dias', 'bucketTotals.tardio', 'dinheiro', 'red'),
            ],
            'tabelas' => [[
                'chave' => 'linhas',
                'colunas' => [
                    self::col('Cliente', 'name'), self::col('A Vencer', 'current', 'dinheiro'), self::col('1-30', 'days_30', 'dinheiro'),
                    self::col('31-60', 'days_60', 'dinheiro'), self::col('61-90', 'days_90', 'dinheiro'), self::col('91-120', 'days_120', 'dinheiro'),
                    self::col('+120', 'over_120', 'dinheiro'), self::col('Total', 'total', 'dinheiro'),
                ],
                'rodape' => [
                    'current' => 'bucketTotals.current', 'days_30' => 'bucketTotals.days_30', 'days_60' => 'bucketTotals.days_60',
                    'days_90' => 'bucketTotals.days_90', 'days_120' => 'bucketTotals.days_120', 'over_120' => 'bucketTotals.over_120', 'total' => 'bucketTotals.grand',
                ],
                'vazio' => 'Nenhum saldo em aberto.',
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        $today = Carbon::today();

        $invoices = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled', 'credited'])
            ->whereRaw('total > COALESCE(paid_amount, 0) + 0.01')
            ->get();

        // Agrupar por cliente e faixa
        $grouped = [];
        foreach ($invoices as $inv) {
            $clientId = $inv->client_id;
            $clientName = $inv->client->name ?? 'Cliente removido';
            $balance = $inv->total - ($inv->paid_amount ?? 0);

            if (!isset($grouped[$clientId])) {
                $grouped[$clientId] = [
                    'name' => $clientName,
                    'current' => 0, 'days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'days_120' => 0, 'over_120' => 0,
                    'total' => 0,
                ];
            }

            $daysOverdue = $inv->due_date ? $today->diffInDays($inv->due_date, false) : 0;
            $daysOverdue = -$daysOverdue; // positive = overdue

            if ($daysOverdue <= 0) {
                $grouped[$clientId]['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $grouped[$clientId]['days_30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $grouped[$clientId]['days_60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $grouped[$clientId]['days_90'] += $balance;
            } elseif ($daysOverdue <= 120) {
                $grouped[$clientId]['days_120'] += $balance;
            } else {
                $grouped[$clientId]['over_120'] += $balance;
            }
            $grouped[$clientId]['total'] += $balance;
        }

        uasort($grouped, fn ($a, $b) => $b['total'] <=> $a['total']);

        $bucketTotals = [
            'current' => array_sum(array_column($grouped, 'current')),
            'days_30' => array_sum(array_column($grouped, 'days_30')),
            'days_60' => array_sum(array_column($grouped, 'days_60')),
            'days_90' => array_sum(array_column($grouped, 'days_90')),
            'days_120' => array_sum(array_column($grouped, 'days_120')),
            'over_120' => array_sum(array_column($grouped, 'over_120')),
        ];
        $bucketTotals['grand'] = array_sum($bucketTotals);
        // Os cartões do genérico juntam faixas; a vista de sempre não os usa.
        $bucketTotals['medio'] = $bucketTotals['days_60'] + $bucketTotals['days_90'];
        $bucketTotals['tardio'] = $bucketTotals['days_120'] + $bucketTotals['over_120'];

        // Um mapa por id de cliente perde a ordem ao virar JSON: a lista vai à parte.
        $linhas = array_values($grouped);

        return compact('grouped', 'bucketTotals', 'linhas');
    }
}

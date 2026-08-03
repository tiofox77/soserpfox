<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;

/**
 * Mapa de Retenções na Fonte (obrigação fiscal — Angola).
 *
 * AGNÓSTICO AO PLANO: ancora cada tipo de retenção num `integration_key`
 * (`withholding_irt`, `withholding_services`) colocado na conta respetiva e soma
 * TODA a subárvore por PREFIXO de código (convenção universal do plano), tal como
 * as demais demonstrações. As contas de retenção são passivos (Estado a pagar), por
 * isso o valor retido = Crédito − Débito no período.
 */
class WithholdingReportService
{
    /** Tipos de retenção suportados (integration_key => rótulo). */
    protected array $types = [
        'withholding_irt' => 'Retenção na Fonte — IRT (Trabalho)',
        'withholding_services' => 'Retenção na Fonte — Prestação de Serviços',
    ];

    public function generate($tenantId, $dateFrom, $dateTo): array
    {
        $accounts = Account::where('tenant_id', $tenantId)
            ->with(['moveLines' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('move', function ($m) use ($dateFrom, $dateTo) {
                    $m->where('state', 'posted')->whereBetween('date', [$dateFrom, $dateTo]);
                })->with('move');
            }])
            ->get();

        $result = ['types' => [], 'total' => 0, 'lines' => []];

        foreach ($this->types as $key => $label) {
            // Prefixos das contas-âncora deste tipo
            $prefixes = $accounts->where('integration_key', $key)->pluck('code')->all();
            $total = 0;
            $lines = [];

            if (!empty($prefixes)) {
                $subtree = $accounts->filter(fn ($a) =>
                    !$a->is_view && $this->matchesPrefix((string) $a->code, $prefixes)
                );
                foreach ($subtree as $account) {
                    foreach ($account->moveLines as $line) {
                        $amount = round(($line->credit ?? 0) - ($line->debit ?? 0), 2);
                        if (abs($amount) < 0.01) {
                            continue;
                        }
                        $total += $amount;
                        $lines[] = [
                            'date' => optional($line->move)->date,
                            'ref' => optional($line->move)->ref,
                            'account_code' => $account->code,
                            'account_name' => $account->name,
                            'narration' => $line->narration ?? optional($line->move)->narration,
                            'amount' => $amount,
                        ];
                    }
                }
            }

            // Ordenar linhas por data
            usort($lines, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

            $result['types'][$key] = [
                'name' => $label,
                'total' => round($total, 2),
                'count' => count($lines),
                'lines' => $lines,
            ];
            $result['total'] += $total;
            $result['lines'] = array_merge($result['lines'], $lines);
        }

        $result['total'] = round($result['total'], 2);
        return $result;
    }

    protected function matchesPrefix(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if ($p !== '' && str_starts_with($code, (string) $p)) {
                return true;
            }
        }
        return false;
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;

/**
 * Balanço (Demonstração da Posição Financeira).
 *
 * AGNÓSTICO AO PLANO DE CONTAS: agrega por `type` (asset/liability/equity/revenue/expense),
 * não por padrões de código. Funciona tanto para o plano SNC (tenants antigos) como para
 * o PGC-AO (tenants novos), desde que cada conta tenha o `type` correto.
 *
 * Inclui o RESULTADO LÍQUIDO do período (proveitos − custos) no Capital Próprio, para o
 * balanço fechar mesmo antes do apuramento de fim de exercício.
 */
class BalanceSheetService
{
    public function generate($tenantId, $dateEnd)
    {
        $activo = $this->balanceByType($tenantId, $dateEnd, 'asset');
        $passivo = $this->balanceByType($tenantId, $dateEnd, 'liability');
        $capital = $this->balanceByType($tenantId, $dateEnd, 'equity');

        // Resultado líquido do período = Proveitos − Custos (ainda não apurado em conta de capital)
        $proveitos = $this->balanceByType($tenantId, $dateEnd, 'revenue');
        $custos = $this->balanceByType($tenantId, $dateEnd, 'expense');
        $resultadoLiquido = $proveitos['total'] - $custos['total'];

        $capital['details'][] = [
            'code' => '—',
            'name' => 'Resultado Líquido do Período',
            'balance' => $resultadoLiquido,
        ];
        $capital['total'] += $resultadoLiquido;

        $balanceSheet = [
            'activo' => ['activo' => ['label' => 'ACTIVO', 'items' => ['activo' => $activo]]],
            'passivo' => ['passivo' => ['label' => 'PASSIVO', 'items' => ['passivo' => $passivo]]],
            'capital_proprio' => ['capital_proprio' => ['label' => 'CAPITAL PRÓPRIO', 'items' => ['capital_proprio' => $capital]]],
        ];

        $balanceSheet['total_activo'] = $activo['total'];
        $balanceSheet['total_passivo'] = $passivo['total'];
        $balanceSheet['total_capital_proprio'] = $capital['total'];
        $balanceSheet['resultado_liquido'] = $resultadoLiquido;
        $balanceSheet['total_passivo_capital'] = $passivo['total'] + $capital['total'];
        $balanceSheet['balanced'] = abs($balanceSheet['total_activo'] - $balanceSheet['total_passivo_capital']) < 0.01;
        $balanceSheet['difference'] = $balanceSheet['total_activo'] - $balanceSheet['total_passivo_capital'];

        return $balanceSheet;
    }

    /**
     * Soma o saldo de todas as contas MOVIMENTÁVEIS (is_view=false) de um dado tipo.
     * Saldo: asset/expense = Débito − Crédito; liability/equity/revenue = Crédito − Débito.
     */
    protected function balanceByType($tenantId, $dateEnd, $type)
    {
        $accounts = Account::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('is_view', false)
            ->with(['moveLines' => function ($q) use ($dateEnd) {
                $q->whereHas('move', function ($m) use ($dateEnd) {
                    $m->where('state', 'posted')->where('date', '<=', $dateEnd);
                });
            }])
            ->get();

        $total = 0;
        $details = [];

        foreach ($accounts as $account) {
            $debit = $account->moveLines->sum('debit');
            $credit = $account->moveLines->sum('credit');
            $balance = in_array($type, ['asset', 'expense'], true)
                ? ($debit - $credit)
                : ($credit - $debit);

            if (abs($balance) > 0.01) {
                $details[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $balance];
                $total += $balance;
            }
        }

        return ['total' => $total, 'details' => $details, 'count' => count($details)];
    }

    public function generateComparative($tenantId, $dateEnd1, $dateEnd2)
    {
        $period1 = $this->generate($tenantId, $dateEnd1);
        $period2 = $this->generate($tenantId, $dateEnd2);

        return [
            'period1' => $period1,
            'period2' => $period2,
            'date1' => $dateEnd1,
            'date2' => $dateEnd2,
            'variance' => $this->calculateVariance($period1, $period2),
        ];
    }

    protected function calculateVariance($period1, $period2)
    {
        $pct = fn ($p2, $p1) => $p1 != 0 ? (($p2 - $p1) / abs($p1)) * 100 : 0;

        return [
            'activo' => $period2['total_activo'] - $period1['total_activo'],
            'passivo' => $period2['total_passivo'] - $period1['total_passivo'],
            'capital_proprio' => $period2['total_capital_proprio'] - $period1['total_capital_proprio'],
            'activo_percent' => $pct($period2['total_activo'], $period1['total_activo']),
            'passivo_percent' => $pct($period2['total_passivo'], $period1['total_passivo']),
            'capital_proprio_percent' => $pct($period2['total_capital_proprio'], $period1['total_capital_proprio']),
        ];
    }
}

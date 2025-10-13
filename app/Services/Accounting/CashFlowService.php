<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\MoveLine;
use Illuminate\Support\Facades\DB;

class CashFlowService
{
    /**
     * Gera a Demonstração de Fluxos de Caixa (DFC) - Método Indireto
     * 
     * @param int $tenantId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function generate($tenantId, $dateFrom, $dateTo)
    {
        // Resultado líquido (base)
        $netIncome = $this->getNetIncome($tenantId, $dateFrom, $dateTo);
        
        // ATIVIDADES OPERACIONAIS
        $operatingActivities = [
            'net_income' => $netIncome,
            'adjustments' => $this->getOperatingAdjustments($tenantId, $dateFrom, $dateTo),
            'working_capital' => $this->getWorkingCapitalChanges($tenantId, $dateFrom, $dateTo),
        ];
        
        $operatingActivities['total'] = $netIncome 
            + array_sum($operatingActivities['adjustments'])
            + array_sum($operatingActivities['working_capital']);
        
        // ATIVIDADES DE INVESTIMENTO
        $investmentActivities = $this->getInvestmentActivities($tenantId, $dateFrom, $dateTo);
        
        // ATIVIDADES DE FINANCIAMENTO
        $financingActivities = $this->getFinancingActivities($tenantId, $dateFrom, $dateTo);
        
        // VARIAÇÃO LÍQUIDA DE CAIXA
        $netCashChange = $operatingActivities['total'] 
                       + $investmentActivities['total']
                       + $financingActivities['total'];
        
        // RECONCILIAÇÃO DE CAIXA
        $cashStart = $this->getCashBalance($tenantId, $dateFrom, 'start');
        $cashEnd = $this->getCashBalance($tenantId, $dateTo, 'end');
        $cashChange = $cashEnd - $cashStart;
        
        return [
            'operating' => $operatingActivities,
            'investment' => $investmentActivities,
            'financing' => $financingActivities,
            'net_cash_change' => $netCashChange,
            'cash_start' => $cashStart,
            'cash_end' => $cashEnd,
            'cash_change' => $cashChange,
            'reconciled' => abs($netCashChange - $cashChange) < 0.01,
            'difference' => $netCashChange - $cashChange,
        ];
    }
    
    /**
     * Obtém resultado líquido do período
     */
    protected function getNetIncome($tenantId, $dateFrom, $dateTo)
    {
        // Rendimentos (classe 7)
        $revenues = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', fn($q) => $q->where('code', 'like', '7%'))
            ->whereHas('move', fn($q) => $q->where('state', 'posted')
                ->whereBetween('date', [$dateFrom, $dateTo]))
            ->selectRaw('SUM(credit) - SUM(debit) as total')
            ->value('total') ?? 0;
        
        // Gastos (classe 6)
        $expenses = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', fn($q) => $q->where('code', 'like', '6%'))
            ->whereHas('move', fn($q) => $q->where('state', 'posted')
                ->whereBetween('date', [$dateFrom, $dateTo]))
            ->selectRaw('SUM(debit) - SUM(credit) as total')
            ->value('total') ?? 0;
        
        return $revenues - $expenses;
    }
    
    /**
     * Ajustamentos operacionais (itens sem efeito caixa)
     */
    protected function getOperatingAdjustments($tenantId, $dateFrom, $dateTo)
    {
        // Depreciações e Amortizações (64)
        $depreciation = $this->getBalance($tenantId, $dateFrom, $dateTo, '64%');
        
        // Imparidades (65)
        $impairments = $this->getBalance($tenantId, $dateFrom, $dateTo, '65%');
        
        // Provisões (67)
        $provisions = $this->getBalance($tenantId, $dateFrom, $dateTo, '67%');
        
        return [
            'depreciation' => $depreciation,
            'impairments' => $impairments,
            'provisions' => $provisions,
        ];
    }
    
    /**
     * Variação do Capital Circulante
     */
    protected function getWorkingCapitalChanges($tenantId, $dateFrom, $dateTo)
    {
        $previousPeriodEnd = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        
        // Clientes (21) - aumento é negativo para caixa
        $clientsStart = $this->getAccountBalance($tenantId, '21%', $previousPeriodEnd);
        $clientsEnd = $this->getAccountBalance($tenantId, '21%', $dateTo);
        $clientsChange = -($clientsEnd - $clientsStart);
        
        // Inventários (3) - aumento é negativo para caixa
        $inventoryStart = $this->getAccountBalance($tenantId, '3%', $previousPeriodEnd);
        $inventoryEnd = $this->getAccountBalance($tenantId, '3%', $dateTo);
        $inventoryChange = -($inventoryEnd - $inventoryStart);
        
        // Fornecedores (22) - aumento é positivo para caixa
        $suppliersStart = $this->getAccountBalance($tenantId, '22%', $previousPeriodEnd);
        $suppliersEnd = $this->getAccountBalance($tenantId, '22%', $dateTo);
        $suppliersChange = $suppliersEnd - $suppliersStart;
        
        // Estado (24) - variação
        $stateStart = $this->getAccountBalance($tenantId, '24%', $previousPeriodEnd);
        $stateEnd = $this->getAccountBalance($tenantId, '24%', $dateTo);
        $stateChange = $stateEnd - $stateStart;
        
        return [
            'clients' => $clientsChange,
            'inventory' => $inventoryChange,
            'suppliers' => $suppliersChange,
            'state' => $stateChange,
        ];
    }
    
    /**
     * Atividades de Investimento
     */
    protected function getInvestmentActivities($tenantId, $dateFrom, $dateTo)
    {
        // Aquisições de Imobilizado (43)
        $fixedAssetsPurchase = -$this->getBalance($tenantId, $dateFrom, $dateTo, '43%');
        
        // Aquisições de Intangíveis (44)
        $intangiblesPurchase = -$this->getBalance($tenantId, $dateFrom, $dateTo, '44%');
        
        // Investimentos Financeiros (41)
        $investmentsPurchase = -$this->getBalance($tenantId, $dateFrom, $dateTo, '41%');
        
        return [
            'items' => [
                'fixed_assets' => $fixedAssetsPurchase,
                'intangibles' => $intangiblesPurchase,
                'investments' => $investmentsPurchase,
            ],
            'total' => $fixedAssetsPurchase + $intangiblesPurchase + $investmentsPurchase,
        ];
    }
    
    /**
     * Atividades de Financiamento
     */
    protected function getFinancingActivities($tenantId, $dateFrom, $dateTo)
    {
        // Empréstimos Obtidos/Pagos (25, 26)
        $loansChange = $this->getBalance($tenantId, $dateFrom, $dateTo, ['25%', '26%']);
        
        // Aumentos de Capital (51)
        $capitalIncrease = $this->getBalance($tenantId, $dateFrom, $dateTo, '51%');
        
        // Dividendos Pagos (assumindo conta 59 se existir)
        $dividends = -$this->getBalance($tenantId, $dateFrom, $dateTo, '59%');
        
        return [
            'items' => [
                'loans' => $loansChange,
                'capital' => $capitalIncrease,
                'dividends' => $dividends,
            ],
            'total' => $loansChange + $capitalIncrease + $dividends,
        ];
    }
    
    /**
     * Saldo de caixa em determinada data
     */
    protected function getCashBalance($tenantId, $date, $type = 'end')
    {
        $query = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('code', 'like', '12%');
            })
            ->whereHas('move', function($q) use ($date, $type) {
                $q->where('state', 'posted');
                if ($type === 'start') {
                    $q->where('date', '<', $date);
                } else {
                    $q->where('date', '<=', $date);
                }
            });
        
        $debit = $query->sum('debit');
        $credit = (clone $query)->sum('credit');
        
        return $debit - $credit;
    }
    
    /**
     * Saldo de conta em determinada data
     */
    protected function getAccountBalance($tenantId, $pattern, $date)
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        
        $query = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) use ($patterns) {
                $q->where(function($query) use ($patterns) {
                    foreach ($patterns as $p) {
                        $query->orWhere('code', 'like', $p);
                    }
                });
            })
            ->whereHas('move', function($q) use ($date) {
                $q->where('state', 'posted')
                  ->where('date', '<=', $date);
            });
        
        $debit = $query->sum('debit');
        $credit = (clone $query)->sum('credit');
        
        return $debit - $credit;
    }
    
    /**
     * Saldo de movimentos no período
     */
    protected function getBalance($tenantId, $dateFrom, $dateTo, $pattern)
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        
        $total = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) use ($patterns) {
                $q->where(function($query) use ($patterns) {
                    foreach ($patterns as $p) {
                        $query->orWhere('code', 'like', $p);
                    }
                });
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('SUM(debit) - SUM(credit) as total')
            ->value('total') ?? 0;
        
        return $total;
    }
}

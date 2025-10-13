<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\AllocationMatrix;
use Illuminate\Support\Facades\DB;

class IncomeStatementFunctionService
{
    /**
     * Gera a Demonstração de Resultados por Funções (DRF)
     * 
     * @param int $tenantId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function generate($tenantId, $dateFrom, $dateTo)
    {
        // Buscar vendas (classe 7)
        $sales = $this->getSales($tenantId, $dateFrom, $dateTo);
        
        // Alocar gastos por função
        $costOfSales = $this->getAllocatedCosts($tenantId, $dateFrom, $dateTo, 'sales_cost');
        $distributionCosts = $this->getAllocatedCosts($tenantId, $dateFrom, $dateTo, 'distribution');
        $administrativeCosts = $this->getAllocatedCosts($tenantId, $dateFrom, $dateTo, 'administrative');
        $rdCosts = $this->getAllocatedCosts($tenantId, $dateFrom, $dateTo, 'rd');
        
        // Outros rendimentos e gastos
        $otherIncome = $this->getBalance($tenantId, $dateFrom, $dateTo, '74%');
        $otherExpenses = $this->getBalance($tenantId, $dateFrom, $dateTo, '68%');
        
        // Resultados financeiros
        $financialIncome = $this->getBalance($tenantId, $dateFrom, $dateTo, '79%');
        $financialExpenses = $this->getBalance($tenantId, $dateFrom, $dateTo, '69%');
        
        // Impostos
        $incomeTax = $this->getBalance($tenantId, $dateFrom, $dateTo, '89%');
        
        // Cálculos
        $grossMargin = $sales - $costOfSales;
        
        $operatingResult = $grossMargin 
                         - $distributionCosts 
                         - $administrativeCosts 
                         - $rdCosts
                         + $otherIncome
                         - $otherExpenses;
        
        $resultBeforeTax = $operatingResult 
                         + $financialIncome 
                         - $financialExpenses;
        
        $netIncome = $resultBeforeTax - $incomeTax;
        
        // Margens
        $grossMarginPercent = $sales > 0 ? ($grossMargin / $sales) * 100 : 0;
        $operatingMarginPercent = $sales > 0 ? ($operatingResult / $sales) * 100 : 0;
        $netMarginPercent = $sales > 0 ? ($netIncome / $sales) * 100 : 0;
        
        return [
            'sales' => $sales,
            'cost_of_sales' => $costOfSales,
            'gross_margin' => $grossMargin,
            'distribution_costs' => $distributionCosts,
            'administrative_costs' => $administrativeCosts,
            'rd_costs' => $rdCosts,
            'other_income' => $otherIncome,
            'other_expenses' => $otherExpenses,
            'operating_result' => $operatingResult,
            'financial_income' => $financialIncome,
            'financial_expenses' => $financialExpenses,
            'result_before_tax' => $resultBeforeTax,
            'income_tax' => $incomeTax,
            'net_income' => $netIncome,
            'gross_margin_percent' => $grossMarginPercent,
            'operating_margin_percent' => $operatingMarginPercent,
            'net_margin_percent' => $netMarginPercent,
        ];
    }
    
    /**
     * Obtém vendas (rendimentos)
     */
    protected function getSales($tenantId, $dateFrom, $dateTo)
    {
        $total = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) {
                $q->where('code', 'like', '7%')
                  ->whereNotIn('code', ['74%', '75%', '79%']); // Excluir outros rendimentos e financeiros
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('SUM(credit) - SUM(debit) as total')
            ->value('total') ?? 0;
        
        return $total;
    }
    
    /**
     * Aloca custos por função usando matriz de alocação
     */
    protected function getAllocatedCosts($tenantId, $dateFrom, $dateTo, $functionType)
    {
        // Buscar todos os gastos (classe 6)
        $expenses = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) {
                $q->where('code', 'like', '6%');
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->with('account')
            ->get();
        
        $totalAllocated = 0;
        
        foreach ($expenses as $line) {
            $accountCode = $line->account->code;
            $lineTotal = $line->debit - $line->credit;
            
            // Buscar alocação para esta conta e função
            $allocation = AllocationMatrix::where('tenant_id', $tenantId)
                ->where('account_code', $accountCode)
                ->where('function_type', $functionType)
                ->first();
            
            if ($allocation) {
                // Aplicar percentual de alocação
                $allocatedAmount = ($lineTotal * $allocation->allocation_percent) / 100;
                $totalAllocated += $allocatedAmount;
            } else {
                // Se não houver alocação configurada, usar alocação padrão por tipo de conta
                $defaultPercent = $this->getDefaultAllocation($accountCode, $functionType);
                if ($defaultPercent > 0) {
                    $allocatedAmount = ($lineTotal * $defaultPercent) / 100;
                    $totalAllocated += $allocatedAmount;
                }
            }
        }
        
        return $totalAllocated;
    }
    
    /**
     * Alocações padrão por tipo de conta
     */
    protected function getDefaultAllocation($accountCode, $functionType)
    {
        // CMVMC (61) - 100% Custo das Vendas
        if (str_starts_with($accountCode, '61')) {
            return $functionType === 'sales_cost' ? 100 : 0;
        }
        
        // FST (62) - distribuído por função
        if (str_starts_with($accountCode, '62')) {
            return match($functionType) {
                'sales_cost' => 30,
                'distribution' => 30,
                'administrative' => 40,
                default => 0
            };
        }
        
        // Pessoal (63) - distribuído por função
        if (str_starts_with($accountCode, '63')) {
            return match($functionType) {
                'sales_cost' => 25,
                'distribution' => 25,
                'administrative' => 40,
                'rd' => 10,
                default => 0
            };
        }
        
        // Depreciações (64) - distribuído por função
        if (str_starts_with($accountCode, '64')) {
            return match($functionType) {
                'sales_cost' => 40,
                'distribution' => 20,
                'administrative' => 40,
                default => 0
            };
        }
        
        // Imparidades/Provisões (65, 67) - 100% Administrativo
        if (str_starts_with($accountCode, '65') || str_starts_with($accountCode, '67')) {
            return $functionType === 'administrative' ? 100 : 0;
        }
        
        return 0;
    }
    
    /**
     * Calcula saldo de contas
     */
    protected function getBalance($tenantId, $dateFrom, $dateTo, $pattern)
    {
        $total = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) use ($pattern) {
                $q->where('code', 'like', $pattern);
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('SUM(debit) - SUM(credit) as total')
            ->value('total') ?? 0;
        
        return abs($total);
    }
    
    /**
     * Obtém matriz de alocação para um tenant
     */
    public function getAllocationMatrix($tenantId)
    {
        return AllocationMatrix::where('tenant_id', $tenantId)
            ->orderBy('account_code')
            ->orderBy('function_type')
            ->get()
            ->groupBy('account_code');
    }
    
    /**
     * Salva matriz de alocação
     */
    public function saveAllocationMatrix($tenantId, $accountCode, array $allocations)
    {
        return DB::transaction(function() use ($tenantId, $accountCode, $allocations) {
            // Deletar alocações antigas
            AllocationMatrix::where('tenant_id', $tenantId)
                ->where('account_code', $accountCode)
                ->delete();
            
            // Validar que soma = 100%
            $total = collect($allocations)->sum('percent');
            if (abs($total - 100) > 0.01) {
                throw new \Exception("Total de alocações deve somar 100%. Atual: {$total}%");
            }
            
            // Criar novas alocações
            foreach ($allocations as $allocation) {
                if ($allocation['percent'] > 0) {
                    AllocationMatrix::create([
                        'tenant_id' => $tenantId,
                        'account_code' => $accountCode,
                        'function_type' => $allocation['function'],
                        'allocation_percent' => $allocation['percent'],
                        'notes' => $allocation['notes'] ?? null,
                    ]);
                }
            }
            
            return true;
        });
    }
    
    /**
     * Valida se todas as contas de gasto têm alocação configurada
     */
    public function validateAllocations($tenantId)
    {
        $expenseAccounts = Account::where('tenant_id', $tenantId)
            ->where('code', 'like', '6%')
            ->where('is_view', false)
            ->pluck('code');
        
        $warnings = [];
        
        foreach ($expenseAccounts as $code) {
            $allocations = AllocationMatrix::where('tenant_id', $tenantId)
                ->where('account_code', $code)
                ->get();
            
            if ($allocations->isEmpty()) {
                $warnings[] = [
                    'account' => $code,
                    'issue' => 'Sem alocação configurada (usando padrão)',
                    'severity' => 'info'
                ];
                continue;
            }
            
            $total = $allocations->sum('allocation_percent');
            if (abs($total - 100) > 0.01) {
                $warnings[] = [
                    'account' => $code,
                    'issue' => "Alocação não soma 100% (soma: {$total}%)",
                    'severity' => 'error'
                ];
            }
        }
        
        return $warnings;
    }
}

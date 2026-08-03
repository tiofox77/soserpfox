<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\Account;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\IncomeStatementNatureService;
use App\Services\Accounting\IncomeStatementFunctionService;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\WithholdingReportService;
use App\Services\Accounting\ReportExportService;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class ReportsManagement extends Component
{
    public $dateFrom;
    public $dateTo;
    public $reportType = 'trial_balance';
    public $accountId = null;
    public $journalFilter = null;
    
    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * IDs das contas movimentáveis ancoradas em dados integration_keys (subárvore por
     * prefixo de código). Agnóstico ao plano — mesma abordagem das demonstrações.
     */
    private function keyedAccountIds(int $tenantId, array $keys): array
    {
        $accounts = Account::where('tenant_id', $tenantId)->get(['id', 'code', 'integration_key', 'is_view']);
        $prefixes = $accounts->whereIn('integration_key', $keys)->pluck('code')->all();
        if (empty($prefixes)) {
            return [];
        }
        return $accounts->filter(function ($a) use ($prefixes) {
            if ($a->is_view) {
                return false;
            }
            foreach ($prefixes as $p) {
                if ($p !== '' && str_starts_with((string) $a->code, (string) $p)) {
                    return true;
                }
            }
            return false;
        })->pluck('id')->all();
    }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        // Trial Balance - Balancete
        $trialBalance = Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->with(['moveLines' => function($query) {
                $query->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                });
            }])
            ->get()
            ->map(function($account) {
                $debit = $account->moveLines->sum('debit');
                $credit = $account->moveLines->sum('credit');
                $balance = $debit - $credit;
                
                return [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                ];
            })
            ->filter(function($item) {
                return $item['debit'] != 0 || $item['credit'] != 0;
            });
        
        // Journal Report - Diário
        $journalData = null;
        if ($this->reportType === 'journal') {
            $journalData = Move::where('tenant_id', $tenantId)
                ->where('state', 'posted')
                ->whereBetween('date', [$this->dateFrom, $this->dateTo])
                ->when($this->journalFilter, function($query) {
                    $query->where('journal_id', $this->journalFilter);
                })
                ->with(['journal', 'lines.account'])
                ->orderBy('date')
                ->orderBy('id')
                ->get();
        }
        
        // Income Statement - DRE Simplificada
        $incomeStatement = null;
        if ($this->reportType === 'income_statement') {
            // Rendimentos — por TYPE (agnóstico ao plano; em PGC-AO a classe 6=proveitos,
            // em SNC a classe 7=rendimentos — usar o type evita depender do código).
            $revenues = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('type', 'revenue')->where('is_view', false);
                })
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with('account')
                ->get()
                ->groupBy(function($line) {
                    return substr($line->account->code, 0, 2); // Agrupa por 2 primeiros dígitos
                });

            // Gastos — por TYPE
            $expenses = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('type', 'expense')->where('is_view', false);
                })
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with('account')
                ->get()
                ->groupBy(function($line) {
                    return substr($line->account->code, 0, 2);
                });
            
            $totalRevenues = 0;
            $revenuesData = [];
            foreach($revenues as $code => $lines) {
                $amount = $lines->sum('credit') - $lines->sum('debit');
                $totalRevenues += $amount;
                $revenuesData[$code] = [
                    'name' => $lines->first()->account->name,
                    'amount' => $amount
                ];
            }
            
            $totalExpenses = 0;
            $expensesData = [];
            foreach($expenses as $code => $lines) {
                $amount = $lines->sum('debit') - $lines->sum('credit');
                $totalExpenses += $amount;
                $expensesData[$code] = [
                    'name' => $lines->first()->account->name,
                    'amount' => $amount
                ];
            }
            
            $incomeStatement = [
                'revenues' => $revenuesData,
                'expenses' => $expensesData,
                'totalRevenues' => $totalRevenues,
                'totalExpenses' => $totalExpenses,
                'netIncome' => $totalRevenues - $totalExpenses,
            ];
        }
        
        // VAT Report - Mapa de IVA (contas resolvidas por integration_key — agnóstico ao plano)
        $vatData = null;
        if ($this->reportType === 'vat') {
            $collectedIds = $this->keyedAccountIds($tenantId, ['vat_collected']);
            $deductibleIds = $this->keyedAccountIds($tenantId, ['vat_paid']);

            // IVA Liquidado (Vendas — Crédito na conta de IVA)
            $vatCollected = MoveLine::where('tenant_id', $tenantId)
                ->whereIn('account_id', $collectedIds ?: [0])
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with(['move', 'account'])
                ->get();

            // IVA Dedutível (Compras — Débito na conta de IVA)
            $vatDeductible = MoveLine::where('tenant_id', $tenantId)
                ->whereIn('account_id', $deductibleIds ?: [0])
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with(['move', 'account'])
                ->get();
            
            $vatData = [
                'collected' => $vatCollected,
                'deductible' => $vatDeductible,
                'totalCollected' => $vatCollected->sum('credit') - $vatCollected->sum('debit'),
                'totalDeductible' => $vatDeductible->sum('debit') - $vatDeductible->sum('credit'),
                'netVat' => ($vatCollected->sum('credit') - $vatCollected->sum('debit')) - 
                           ($vatDeductible->sum('debit') - $vatDeductible->sum('credit')),
            ];
        }
        
        // Ledger - Razão Geral
        $ledgerData = null;
        if ($this->reportType === 'ledger' && $this->accountId) {
            $account = Account::find($this->accountId);
            
            // Saldo inicial (antes do período)
            $initialBalance = MoveLine::where('tenant_id', $tenantId)
                ->where('account_id', $this->accountId)
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->where('date', '<', $this->dateFrom);
                })
                ->selectRaw('SUM(debit) - SUM(credit) as balance')
                ->value('balance') ?? 0;
            
            // Movimentos do período
            $movements = MoveLine::where('tenant_id', $tenantId)
                ->where('account_id', $this->accountId)
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with(['move.journal'])
                ->orderBy('id')
                ->get();
            
            $runningBalance = $initialBalance;
            $movements = $movements->map(function($line) use (&$runningBalance) {
                $runningBalance += ($line->debit - $line->credit);
                $line->running_balance = $runningBalance;
                return $line;
            });
            
            $ledgerData = [
                'account' => $account,
                'initialBalance' => $initialBalance,
                'movements' => $movements,
                'finalBalance' => $runningBalance,
            ];
        }
            
        $accounts = Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->orderBy('code')
            ->get();
        
        $journals = \App\Models\Accounting\Journal::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
        
        // Balance Sheet - Balanço
        $balanceSheet = null;
        if ($this->reportType === 'balance_sheet') {
            $service = new BalanceSheetService();
            $balanceSheet = $service->generate($tenantId, $this->dateTo);
        }
        
        // Income Statement by Nature - DR por Natureza
        $incomeStatementNature = null;
        if ($this->reportType === 'income_statement_nature') {
            $service = new IncomeStatementNatureService();
            $incomeStatementNature = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
        }
        
        // Income Statement by Function - DR por Funções
        $incomeStatementFunction = null;
        if ($this->reportType === 'income_statement_function') {
            $service = new IncomeStatementFunctionService();
            $incomeStatementFunction = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
        }
        
        // Cash Flow Statement - Fluxos de Caixa
        $cashFlow = null;
        if ($this->reportType === 'cash_flow') {
            $service = new CashFlowService();
            $cashFlow = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
        }

        // Withholding Report - Mapa de Retenções na Fonte
        $withholding = null;
        if ($this->reportType === 'withholding') {
            $withholding = (new WithholdingReportService())->generate($tenantId, $this->dateFrom, $this->dateTo);
        }

        return view('livewire.accounting.reports.reports', [
            'trialBalance' => $trialBalance,
            'ledgerData' => $ledgerData,
            'journalData' => $journalData,
            'vatData' => $vatData,
            'incomeStatement' => $incomeStatement,
            'balanceSheet' => $balanceSheet,
            'incomeStatementNature' => $incomeStatementNature,
            'incomeStatementFunction' => $incomeStatementFunction,
            'cashFlow' => $cashFlow,
            'withholding' => $withholding,
            'accounts' => $accounts,
            'journals' => $journals,
        ]);
    }
    
    /**
     * Exporta relatório para PDF
     */
    public function exportPDF()
    {
        $exportService = new ReportExportService();
        $tenantId = activeTenantId();
        
        switch ($this->reportType) {
            case 'balance_sheet':
                $service = new BalanceSheetService();
                $data = $service->generate($tenantId, $this->dateTo);
                return $exportService->exportBalanceSheetPDF($data, $this->dateTo);
                
            case 'income_statement_nature':
                $service = new IncomeStatementNatureService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportIncomeNaturePDF($data, $this->dateFrom, $this->dateTo);
                
            case 'income_statement_function':
                $service = new IncomeStatementFunctionService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportIncomeFunctionPDF($data, $this->dateFrom, $this->dateTo);
                
            case 'cash_flow':
                $service = new CashFlowService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportCashFlowPDF($data, $this->dateFrom, $this->dateTo);

            case 'withholding':
                $data = (new WithholdingReportService())->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportWithholdingPDF($data, $this->dateFrom, $this->dateTo);

            default:
                session()->flash('error', 'Tipo de relatório não suporta exportação PDF');
                return;
        }
    }
    
    /**
     * Exporta relatório para Excel
     */
    public function exportExcel()
    {
        $exportService = new ReportExportService();
        $tenantId = activeTenantId();
        
        switch ($this->reportType) {
            case 'trial_balance':
                $trialBalance = $this->getTrialBalance($tenantId);
                return $exportService->exportTrialBalanceExcel($trialBalance, $this->dateFrom, $this->dateTo);
                
            case 'balance_sheet':
                $service = new BalanceSheetService();
                $data = $service->generate($tenantId, $this->dateTo);
                return $exportService->exportBalanceSheetExcel($data, $this->dateTo);
                
            case 'income_statement_nature':
                $service = new IncomeStatementNatureService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportIncomeNatureExcel($data, $this->dateFrom, $this->dateTo);
                
            case 'income_statement_function':
                $service = new IncomeStatementFunctionService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportIncomeFunctionExcel($data, $this->dateFrom, $this->dateTo);
                
            case 'cash_flow':
                $service = new CashFlowService();
                $data = $service->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportCashFlowExcel($data, $this->dateFrom, $this->dateTo);
                
            case 'vat':
                $vatData = $this->getVatReport($tenantId);
                return $exportService->exportVatReportExcel($vatData, $this->dateFrom, $this->dateTo);

            case 'withholding':
                $data = (new WithholdingReportService())->generate($tenantId, $this->dateFrom, $this->dateTo);
                return $exportService->exportWithholdingExcel($data, $this->dateFrom, $this->dateTo);

            default:
                session()->flash('error', 'Tipo de relatório não suporta exportação Excel');
                return;
        }
    }
}

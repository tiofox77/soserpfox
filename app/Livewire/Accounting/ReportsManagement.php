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
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
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
            // Rendimentos (Classe 7)
            $revenues = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('code', 'like', '7%');
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
            
            // Gastos (Classe 6)
            $expenses = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('code', 'like', '6%');
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
        
        // VAT Report - Mapa de IVA
        $vatData = null;
        if ($this->reportType === 'vat') {
            // IVA Liquidado (Vendas - Crédito em conta IVA)
            $vatCollected = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('code', 'like', '2433%'); // Conta IVA Liquidado
                })
                ->whereHas('move', function($q) {
                    $q->where('state', 'posted')
                      ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
                })
                ->with(['move', 'account'])
                ->get();
            
            // IVA Dedutível (Compras - Débito em conta IVA)
            $vatDeductible = MoveLine::where('tenant_id', $tenantId)
                ->whereHas('account', function($q) {
                    $q->where('code', 'like', '2432%'); // Conta IVA Dedutível
                })
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
        $tenantId = auth()->user()->tenant_id;
        
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
        $tenantId = auth()->user()->tenant_id;
        
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
                
            default:
                session()->flash('error', 'Tipo de relatório não suporta exportação Excel');
                return;
        }
    }
}

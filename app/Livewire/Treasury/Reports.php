<?php

namespace App\Livewire\Treasury;

use App\Models\Treasury\Transaction;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Relatórios Financeiros')]
class Reports extends Component
{
    public $reportType = 'cash_flow'; // cash_flow, dre, receivables, payables
    public $startDate;
    public $endDate;
    public $period = 'month'; // today, week, month, year, custom

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedPeriod()
    {
        $this->setDatesByPeriod();
    }

    private function setDatesByPeriod()
    {
        switch ($this->period) {
            case 'today':
                $this->startDate = now()->startOfDay()->format('Y-m-d');
                $this->endDate = now()->endOfDay()->format('Y-m-d');
                break;
            case 'week':
                $this->startDate = now()->startOfWeek()->format('Y-m-d');
                $this->endDate = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = now()->startOfYear()->format('Y-m-d');
                $this->endDate = now()->endOfYear()->format('Y-m-d');
                break;
        }
    }

    public function render()
    {
        $data = match($this->reportType) {
            'cash_flow' => $this->getCashFlowReport(),
            'dre' => $this->getDREReport(),
            'receivables' => $this->getReceivablesReport(),
            'payables' => $this->getPayablesReport(),
            default => [],
        };

        return view('livewire.treasury.reports', $data);
    }

    private function getCashFlowReport()
    {
        // Saldo Inicial
        $initialBalance = Transaction::where('tenant_id', activeTenantId())
            ->where('status', 'completed')
            ->where('transaction_date', '<', $this->startDate)
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE -amount END) as balance')
            ->value('balance') ?? 0;

        // Entradas por Categoria
        $incomeByCategory = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalIncome = $incomeByCategory->sum('total');

        // Saídas por Categoria
        $expenseByCategory = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalExpense = $expenseByCategory->sum('total');

        // Saldo Final
        $finalBalance = $initialBalance + $totalIncome - $totalExpense;

        return [
            'initialBalance' => $initialBalance,
            'incomeByCategory' => $incomeByCategory,
            'totalIncome' => $totalIncome,
            'expenseByCategory' => $expenseByCategory,
            'totalExpense' => $totalExpense,
            'finalBalance' => $finalBalance,
        ];
    }

    private function getDREReport()
    {
        // Receita Bruta (Vendas)
        $grossRevenue = SalesInvoice::where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total');

        // Deduções (Devoluções, Descontos)
        $deductions = 0; // Implementar com credit notes

        // Receita Líquida
        $netRevenue = $grossRevenue - $deductions;

        // Custos Operacionais (Compras)
        $operationalCosts = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total');

        // Despesas por Categoria
        $expensesByCategory = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->whereNotNull('category')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalExpenses = $expensesByCategory->sum('total');

        // Lucro Bruto
        $grossProfit = $netRevenue - $operationalCosts;

        // Lucro Operacional
        $operationalProfit = $grossProfit - $totalExpenses;

        // Lucro Líquido (simplificado, sem impostos)
        $netProfit = $operationalProfit;

        return [
            'grossRevenue' => $grossRevenue,
            'deductions' => $deductions,
            'netRevenue' => $netRevenue,
            'operationalCosts' => $operationalCosts,
            'grossProfit' => $grossProfit,
            'expensesByCategory' => $expensesByCategory,
            'totalExpenses' => $totalExpenses,
            'operationalProfit' => $operationalProfit,
            'netProfit' => $netProfit,
        ];
    }

    private function getReceivablesReport()
    {
        // Faturas a Receber
        $receivables = SalesInvoice::where('tenant_id', activeTenantId())
            ->with('client')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function($invoice) {
                return [
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $invoice->client->name,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'total' => $invoice->total,
                    'paid' => $invoice->paid_amount ?? 0,
                    'balance' => $invoice->balance,
                    'status' => $invoice->status,
                    'overdue' => $invoice->due_date && $invoice->due_date->isPast(),
                ];
            });

        $totalReceivables = $receivables->sum('balance');
        $totalOverdue = $receivables->where('overdue', true)->sum('balance');

        return [
            'receivables' => $receivables,
            'totalReceivables' => $totalReceivables,
            'totalOverdue' => $totalOverdue,
        ];
    }

    private function getPayablesReport()
    {
        // Faturas a Pagar
        $payables = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->with('supplier')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function($invoice) {
                return [
                    'invoice_number' => $invoice->invoice_number,
                    'supplier' => $invoice->supplier->name,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'total' => $invoice->total,
                    'paid' => $invoice->paid_amount ?? 0,
                    'balance' => $invoice->balance,
                    'status' => $invoice->status,
                    'overdue' => $invoice->due_date && $invoice->due_date->isPast(),
                ];
            });

        $totalPayables = $payables->sum('balance');
        $totalOverdue = $payables->where('overdue', true)->sum('balance');

        return [
            'payables' => $payables,
            'totalPayables' => $totalPayables,
            'totalOverdue' => $totalOverdue,
        ];
    }
}

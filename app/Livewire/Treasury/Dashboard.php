<?php

namespace App\Livewire\Treasury;

use App\Models\Treasury\Transaction;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Dashboard Tesouraria')]
class Dashboard extends Component
{
    public $period = 'today'; // today, week, month, year
    public $selectedDate;

    public function mount()
    {
        $this->selectedDate = now()->format('Y-m-d');
    }

    public function render()
    {
        // Saldo Total de Caixas
        $totalCashRegisters = CashRegister::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->sum('current_balance');

        // Saldo Total de Contas Bancárias
        $totalBankAccounts = Account::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->sum('current_balance');

        // Saldo Total Geral
        $totalBalance = $totalCashRegisters + $totalBankAccounts;

        // Período para filtros
        $dateRange = $this->getDateRange();

        // Entradas do Período
        $totalIncome = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        // Saídas do Período
        $totalExpense = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        // Saldo do Período
        $periodBalance = $totalIncome - $totalExpense;

        // Transações Recentes (últimas 10)
        $recentTransactions = Transaction::where('tenant_id', activeTenantId())
            ->with(['paymentMethod', 'account', 'cashRegister'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // Dados para Gráfico (últimos 7 dias)
        $chartData = $this->getChartData();

        // Top Categorias (Despesas)
        $topExpenseCategories = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Top Categorias (Receitas)
        $topIncomeCategories = Transaction::where('tenant_id', activeTenantId())
            ->where('type', 'income')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Caixas Individuais
        $cashRegisters = CashRegister::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('current_balance', 'desc')
            ->get();

        // Contas Bancárias Individuais
        $bankAccounts = Account::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with('bank')
            ->orderBy('current_balance', 'desc')
            ->get();

        return view('livewire.treasury.dashboard', [
            'totalCashRegisters' => $totalCashRegisters,
            'totalBankAccounts' => $totalBankAccounts,
            'totalBalance' => $totalBalance,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'periodBalance' => $periodBalance,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
            'topExpenseCategories' => $topExpenseCategories,
            'topIncomeCategories' => $topIncomeCategories,
            'cashRegisters' => $cashRegisters,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    private function getDateRange()
    {
        return match($this->period) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
            ],
            default => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
        };
    }

    private function getChartData()
    {
        $days = [];
        $income = [];
        $expense = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days[] = $date->format('d/m');

            $dayIncome = Transaction::where('tenant_id', activeTenantId())
                ->where('type', 'income')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $date)
                ->sum('amount');

            $dayExpense = Transaction::where('tenant_id', activeTenantId())
                ->where('type', 'expense')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $date)
                ->sum('amount');

            $income[] = $dayIncome;
            $expense[] = $dayExpense;
        }

        return [
            'labels' => $days,
            'income' => $income,
            'expense' => $expense,
        ];
    }
}

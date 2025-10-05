<?php

namespace App\Livewire\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\Advance;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Dashboard - Faturação')]
class InvoicingDashboard extends Component
{
    public $selectedPeriod = 'month'; // week, month, year
    public $chartData = [];

    public function updatedSelectedPeriod()
    {
        $this->loadChartData();
    }

    public function mount()
    {
        $this->loadChartData();
    }

    public function loadChartData()
    {
        $tenantId = activeTenantId();
        
        switch ($this->selectedPeriod) {
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            default: // month
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
        }

        $this->chartData = SalesInvoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(invoice_date) as date, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Estatísticas principais
        $stats = [
            // Faturação
            'total_invoiced' => SalesInvoice::where('tenant_id', $tenantId)
                ->whereMonth('invoice_date', $today->month)
                ->whereYear('invoice_date', $today->year)
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
            
            'total_invoiced_last_month' => SalesInvoice::where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$lastMonth, $lastMonthEnd])
                ->where('status', '!=', 'cancelled')
                ->sum('total'),

            // Recebimentos
            'total_received' => Receipt::where('tenant_id', $tenantId)
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->where('status', 'issued')
                ->sum('amount_paid'),

            // Pendentes
            'total_pending' => SalesInvoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'partially_paid'])
                ->sum('total'),

            // Vencidas
            'total_overdue' => SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('due_date', '<', $today)
                ->sum('total'),
        ];

        // Calcular crescimento
        $stats['growth'] = $stats['total_invoiced_last_month'] > 0
            ? (($stats['total_invoiced'] - $stats['total_invoiced_last_month']) / $stats['total_invoiced_last_month']) * 100
            : 0;

        // Documentos por tipo
        $documents = [
            'invoices' => SalesInvoice::where('tenant_id', $tenantId)
                ->whereMonth('invoice_date', $today->month)
                ->whereYear('invoice_date', $today->year)
                ->count(),
            
            'credit_notes' => CreditNote::where('tenant_id', $tenantId)
                ->whereMonth('issue_date', $today->month)
                ->whereYear('issue_date', $today->year)
                ->count(),
            
            'debit_notes' => DebitNote::where('tenant_id', $tenantId)
                ->whereMonth('issue_date', $today->month)
                ->whereYear('issue_date', $today->year)
                ->count(),
            
            'receipts' => Receipt::where('tenant_id', $tenantId)
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->count(),
            
            'advances' => Advance::where('tenant_id', $tenantId)
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->count(),
        ];

        // Faturas pendentes (top 10)
        $pendingInvoices = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Top 5 clientes por valor
        $topClients = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereMonth('invoice_date', $today->month)
            ->whereYear('invoice_date', $today->year)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('client_id, SUM(total) as total_amount, COUNT(*) as invoice_count')
            ->groupBy('client_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Atividades recentes
        $recentActivities = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Status de faturas
        $invoiceStatus = [
            'paid' => SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', 'paid')
                ->whereMonth('invoice_date', $today->month)
                ->count(),
            
            'pending' => SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->whereMonth('invoice_date', $today->month)
                ->count(),
            
            'partially_paid' => SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', 'partially_paid')
                ->whereMonth('invoice_date', $today->month)
                ->count(),
            
            'overdue' => SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('due_date', '<', $today)
                ->whereMonth('invoice_date', $today->month)
                ->count(),
        ];

        return view('livewire.invoicing.invoicing-dashboard', [
            'stats' => $stats,
            'documents' => $documents,
            'pendingInvoices' => $pendingInvoices,
            'topClients' => $topClients,
            'recentActivities' => $recentActivities,
            'invoiceStatus' => $invoiceStatus,
        ]);
    }
}

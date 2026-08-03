<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Vendas por Vendedor')]
class SalesByUserReport extends Component
{
    use HasReportFilters;

    public function mount()
    {
        $this->initFilters('month');
    }

    public function render()
    {
        $tenantId = activeTenantId();

        // Vendas efetivas (exclui canceladas e creditadas). Colunas qualificadas (join com users).
        $base = DB::table('invoicing_sales_invoices')
            ->where('invoicing_sales_invoices.tenant_id', $tenantId)
            ->whereBetween('invoicing_sales_invoices.invoice_date', [$this->dateFrom, $this->dateTo])
            ->whereNotIn('invoicing_sales_invoices.status', ['cancelled', 'credited']);

        $byUser = (clone $base)
            ->leftJoin('users', 'invoicing_sales_invoices.created_by', '=', 'users.id')
            ->select(
                'invoicing_sales_invoices.created_by',
                DB::raw('COALESCE(users.name, "—") as user_name'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(invoicing_sales_invoices.total) as total'),
                DB::raw('SUM(invoicing_sales_invoices.paid_amount) as paid')
            )
            ->groupBy('invoicing_sales_invoices.created_by', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user_name,
                'count' => (int) $r->cnt,
                'total' => (float) $r->total,
                'paid' => (float) $r->paid,
                'pending' => max(0, (float) $r->total - (float) $r->paid),
                'avg' => $r->cnt > 0 ? (float) $r->total / $r->cnt : 0,
            ]);

        $grandTotal = (float) (clone $base)->sum('total');
        $grandCount = (int) (clone $base)->count();

        return view('livewire.invoicing.reports.sales-by-user-report', compact('byUser', 'grandTotal', 'grandCount'));
    }
}

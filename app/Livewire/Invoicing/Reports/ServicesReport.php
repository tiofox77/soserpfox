<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Mapa de Serviços')]
class ServicesReport extends Component
{
    use HasReportFilters;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $rows = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->join('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->where('p.type', 'servico')
            ->selectRaw('
                p.id, p.name, p.sku, p.price, p.cost,
                SUM(items.quantity) as qty,
                AVG(items.unit_price) as avg_price,
                SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue,
                SUM(items.quantity * COALESCE(p.cost, 0)) as total_cost,
                COUNT(DISTINCT inv.id) as invoices_count,
                COUNT(DISTINCT inv.client_id) as clients_count
            ')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.price', 'p.cost')
            ->orderByDesc('revenue')
            ->get();
        
        $rows = $rows->map(function ($r) {
            $r->profit = $r->revenue - $r->total_cost;
            $r->margin = $r->revenue > 0 ? ($r->profit / $r->revenue * 100) : 0;
            return $r;
        });
        
        $totals = [
            'services_count' => $rows->count(),
            'qty' => $rows->sum('qty'),
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('total_cost'),
            'profit' => $rows->sum('profit'),
            'invoices' => $rows->sum('invoices_count'),
            'clients' => $rows->sum('clients_count'),
        ];
        $totals['margin'] = $totals['revenue'] > 0 ? ($totals['profit'] / $totals['revenue'] * 100) : 0;
        
        // Total geral de vendas (para comparar peso dos serviços)
        $totalRevenueAll = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');
        
        $servicesShare = $totalRevenueAll > 0 ? ($totals['revenue'] / $totalRevenueAll * 100) : 0;
        
        return view('livewire.invoicing.reports.services-report', compact('rows', 'totals', 'servicesShare', 'totalRevenueAll'));
    }
}

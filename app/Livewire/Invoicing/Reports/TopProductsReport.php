<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Top Produtos Vendidos')]
class TopProductsReport extends Component
{
    use HasReportFilters;
    
    public $limit = 20;
    
    public function mount() { $this->initFilters('year'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $rows = \DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('p.id, p.name, p.sku, SUM(items.quantity) as total_qty, SUM(items.subtotal) as total_value, COUNT(DISTINCT inv.id) as invoices_count, COUNT(DISTINCT inv.client_id) as clients_count')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('total_qty')
            ->limit($this->limit)
            ->get();
        
        $grandQty = $rows->sum('total_qty');
        $grandValue = $rows->sum('total_value');
        
        return view('livewire.invoicing.reports.top-products-report', compact('rows', 'grandQty', 'grandValue'));
    }
}

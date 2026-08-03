<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\PurchaseInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Top Fornecedores')]
class TopSuppliersReport extends Component
{
    use HasReportFilters;
    
    public $limit = 20;
    
    public function mount() { $this->initFilters('year'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $rows = PurchaseInvoice::with('supplier')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('supplier_id, COUNT(*) as invoices_count, SUM(total) as total_value, SUM(paid_amount) as total_paid')
            ->groupBy('supplier_id')
            ->orderByDesc('total_value')
            ->limit($this->limit)
            ->get();
        
        $grandTotal = $rows->sum('total_value');
        
        return view('livewire.invoicing.reports.top-suppliers-report', compact('rows', 'grandTotal'));
    }
}

<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Top Clientes')]
class TopClientsReport extends Component
{
    use HasReportFilters;
    
    public $limit = 20;
    
    public function mount() { $this->initFilters('year'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $rows = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->selectRaw('client_id, COUNT(*) as invoices_count, SUM(total) as total_revenue, SUM(paid_amount) as total_paid')
            ->groupBy('client_id')
            ->orderByDesc('total_revenue')
            ->limit($this->limit)
            ->get();
        
        $grandTotal = $rows->sum('total_revenue');
        
        return view('livewire.invoicing.reports.top-clients-report', compact('rows', 'grandTotal'));
    }
}

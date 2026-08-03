<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Mapa de Compras')]
class PurchasesReport extends Component
{
    use HasReportFilters;
    
    public $supplierId = '';
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $query = PurchaseInvoice::with('supplier')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo]);
        
        if ($this->supplierId) $query->where('supplier_id', $this->supplierId);
        
        $invoices = (clone $query)->orderByDesc('invoice_date')->limit(500)->get();
        
        $totals = [
            'count' => (clone $query)->count(),
            'subtotal' => (clone $query)->sum('subtotal'),
            'tax' => (clone $query)->sum('tax_amount'),
            'total' => (clone $query)->sum('total'),
            'paid' => (clone $query)->sum('paid_amount'),
        ];
        $totals['pending'] = max(0, $totals['total'] - $totals['paid']);
        
        $suppliers = Supplier::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        
        return view('livewire.invoicing.reports.purchases-report', compact('invoices', 'totals', 'suppliers'));
    }
}

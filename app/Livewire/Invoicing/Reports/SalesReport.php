<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Client;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Mapa de Vendas')]
class SalesReport extends Component
{
    use HasReportFilters;
    
    public $clientId = '';
    public $status = '';
    
    public function mount()
    {
        $this->initFilters('month');
    }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $query = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo]);
        
        if ($this->clientId) $query->where('client_id', $this->clientId);
        if ($this->status) $query->where('status', $this->status);
        
        $invoices = (clone $query)->orderByDesc('invoice_date')->limit(500)->get();
        
        $totals = [
            'count' => (clone $query)->count(),
            'subtotal' => (clone $query)->sum('subtotal'),
            'tax' => (clone $query)->sum('tax_amount'),
            'total' => (clone $query)->sum('total'),
            'paid' => (clone $query)->sum('paid_amount'),
        ];
        $totals['pending'] = max(0, $totals['total'] - $totals['paid']);
        
        $clients = Client::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        
        return view('livewire.invoicing.reports.sales-report', compact('invoices', 'totals', 'clients'));
    }
}

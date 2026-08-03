<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\Advance;
use App\Models\Invoicing\SalesProforma;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Mapa de Documentos')]
class DocumentsReport extends Component
{
    use HasReportFilters;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        $from = $this->dateFrom;
        $to = $this->dateTo;
        
        $rows = [];
        
        $rows[] = $this->summarize('Faturas de Venda', 'fa-file-invoice', 'green',
            SalesInvoice::where('tenant_id', $tenantId)->whereBetween('invoice_date', [$from, $to]), 'total');
        
        $rows[] = $this->summarize('Faturas de Compra', 'fa-file-invoice-dollar', 'orange',
            PurchaseInvoice::where('tenant_id', $tenantId)->whereBetween('invoice_date', [$from, $to]), 'total');
        
        $rows[] = $this->summarize('Notas de Crédito', 'fa-file-circle-minus', 'red',
            CreditNote::where('tenant_id', $tenantId)->whereBetween('issue_date', [$from, $to]), 'total');
        
        $rows[] = $this->summarize('Notas de Débito', 'fa-file-circle-plus', 'pink',
            DebitNote::where('tenant_id', $tenantId)->whereBetween('issue_date', [$from, $to]), 'total');
        
        $rows[] = $this->summarize('Recibos', 'fa-receipt', 'blue',
            Receipt::where('tenant_id', $tenantId)->whereBetween('payment_date', [$from, $to]), 'amount_paid');
        
        $rows[] = $this->summarize('Adiantamentos', 'fa-coins', 'yellow',
            Advance::where('tenant_id', $tenantId)->whereBetween('payment_date', [$from, $to]), 'amount');
        
        try {
            $rows[] = $this->summarize('Proformas Venda', 'fa-file-alt', 'purple',
                SalesProforma::where('tenant_id', $tenantId)->whereBetween('proforma_date', [$from, $to]), 'total');
        } catch (\Throwable $e) {}
        
        return view('livewire.invoicing.reports.documents-report', compact('rows'));
    }
    
    protected function summarize(string $name, string $icon, string $color, $query, string $totalField): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'color' => $color,
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum($totalField),
        ];
    }
}

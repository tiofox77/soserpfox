<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Mapa de IVA')]
class VatReport extends Component
{
    use HasReportFilters;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        // IVA Liquidado (vendas)
        $salesByRate = \DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.tax_rate, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as base, SUM(items.tax_amount) as tax')
            ->groupBy('items.tax_rate')
            ->orderBy('items.tax_rate')
            ->get();
        
        // IVA Dedutível (compras)
        $purchasesByRate = \DB::table('invoicing_purchase_invoice_items as items')
            ->join('invoicing_purchase_invoices as inv', 'inv.id', '=', 'items.purchase_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.tax_rate, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as base, SUM(items.tax_amount) as tax')
            ->groupBy('items.tax_rate')
            ->orderBy('items.tax_rate')
            ->get();
        
        $totals = [
            'sales_base' => $salesByRate->sum('base'),
            'sales_tax' => $salesByRate->sum('tax'),
            'purchases_base' => $purchasesByRate->sum('base'),
            'purchases_tax' => $purchasesByRate->sum('tax'),
        ];
        $totals['tax_payable'] = $totals['sales_tax'] - $totals['purchases_tax'];
        
        return view('livewire.invoicing.reports.vat-report', compact('salesByRate', 'purchasesByRate', 'totals'));
    }
}

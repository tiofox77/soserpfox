<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Lucros e Perdas (DRE)')]
class ProfitLossReport extends Component
{
    use HasReportFilters;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        // Receita Bruta (Faturas de Venda)
        $grossRevenue = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');
        
        $discounts = (float) DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'cancelled')
            ->sum(DB::raw('COALESCE(discount_amount, 0)'));
        
        // Notas de Crédito (devoluções)
        $returns = (float) DB::table('invoicing_credit_notes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');
        
        $netRevenue = $grossRevenue - $returns;
        
        // Custo dos Produtos Vendidos (CMV) — cost × quantity
        $cogs = (float) DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->sum(DB::raw('items.quantity * COALESCE(p.cost, 0)'));
        
        $grossProfit = $netRevenue - $cogs;
        $grossMargin = $netRevenue > 0 ? ($grossProfit / $netRevenue * 100) : 0;
        
        // Despesas operacionais (proxy: compras pagas no período)
        $expenses = (float) DB::table('invoicing_purchase_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        
        $netResult = $grossProfit; // sem despesas indirectas modeladas
        
        // Evolução mensal (últimos 6 meses)
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();
            
            $rev = (float) DB::table('invoicing_sales_invoices')
                ->where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$from, $to])
                ->where('status', '!=', 'cancelled')
                ->sum('subtotal');
            
            $cmv = (float) DB::table('invoicing_sales_invoice_items as items')
                ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
                ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
                ->where('inv.tenant_id', $tenantId)
                ->whereBetween('inv.invoice_date', [$from, $to])
                ->where('inv.status', '!=', 'cancelled')
                ->sum(DB::raw('items.quantity * COALESCE(p.cost, 0)'));
            
            $monthly[] = [
                'label' => $month->translatedFormat('M/Y'),
                'revenue' => $rev,
                'cogs' => $cmv,
                'profit' => $rev - $cmv,
            ];
        }
        
        $data = compact('grossRevenue', 'discounts', 'returns', 'netRevenue', 'cogs', 'grossProfit', 'grossMargin', 'expenses', 'netResult', 'monthly');
        
        return view('livewire.invoicing.reports.profit-loss-report', $data);
    }
}

<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Análise de Margem por Produto')]
class MarginReport extends Component
{
    use HasReportFilters;
    
    public $sortBy = 'profit_desc';
    public $limit = 50;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $rows = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('
                p.id, p.name, p.sku, p.type, p.cost,
                SUM(items.quantity) as qty,
                AVG(items.unit_price) as avg_price,
                SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue,
                SUM(items.quantity * COALESCE(p.cost, 0)) as total_cost,
                SUM((items.subtotal - COALESCE(items.discount_amount,0)) - (items.quantity * COALESCE(p.cost, 0))) as profit
            ')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.type', 'p.cost')
            ->get();
        
        // Calcular margem %
        $rows = $rows->map(function ($r) {
            $r->margin = $r->revenue > 0 ? (($r->profit / $r->revenue) * 100) : 0;
            return $r;
        });
        
        // Ordenar
        $rows = match($this->sortBy) {
            'profit_desc' => $rows->sortByDesc('profit'),
            'profit_asc' => $rows->sortBy('profit'),
            'margin_desc' => $rows->sortByDesc('margin'),
            'margin_asc' => $rows->sortBy('margin'),
            'revenue_desc' => $rows->sortByDesc('revenue'),
            default => $rows->sortByDesc('profit'),
        };
        
        $rows = $rows->take($this->limit);
        
        $totals = [
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('total_cost'),
            'profit' => $rows->sum('profit'),
        ];
        $totals['margin'] = $totals['revenue'] > 0 ? ($totals['profit'] / $totals['revenue'] * 100) : 0;
        
        // Produtos com prejuízo (margem negativa)
        $lossCount = $rows->filter(fn($r) => $r->profit < 0)->count();
        
        return view('livewire.invoicing.reports.margin-report', compact('rows', 'totals', 'lossCount'));
    }
}

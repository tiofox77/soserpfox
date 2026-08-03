<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Desempenho de Produtos')]
class ProductPerformanceReport extends Component
{
    use HasReportFilters;
    
    public $typeFilter = 'produto';
    public $sortBy = 'profit_desc';
    public $limit = 100;
    
    public function mount() { $this->initFilters('month'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        // Vendas por produto
        $sales = DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.product_id, SUM(items.quantity) as qty_sold, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as revenue, COUNT(DISTINCT inv.id) as sales_count')
            ->groupBy('items.product_id')
            ->get()
            ->keyBy('product_id');
        
        // Compras por produto
        $purchases = DB::table('invoicing_purchase_invoice_items as items')
            ->join('invoicing_purchase_invoices as inv', 'inv.id', '=', 'items.purchase_invoice_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('items.product_id, SUM(items.quantity) as qty_bought, SUM(items.subtotal - COALESCE(items.discount_amount,0)) as total_cost')
            ->groupBy('items.product_id')
            ->get()
            ->keyBy('product_id');
        
        // Produtos do tenant
        $productsQuery = DB::table('invoicing_products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);
        
        if ($this->typeFilter !== 'all') {
            $productsQuery->where('type', $this->typeFilter);
        }
        
        $products = $productsQuery->get();
        
        $rows = $products->map(function ($p) use ($sales, $purchases) {
            $s = $sales->get($p->id);
            $b = $purchases->get($p->id);
            
            $qtySold = $s->qty_sold ?? 0;
            $revenue = $s->revenue ?? 0;
            $qtyBought = $b->qty_bought ?? 0;
            $totalCostMov = $b->total_cost ?? 0;
            $estimatedCogs = $qtySold * ($p->cost ?? 0);
            $profit = $revenue - $estimatedCogs;
            
            return (object) [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'type' => $p->type,
                'price' => $p->price,
                'cost' => $p->cost,
                'stock' => $p->stock_quantity ?? 0,
                'qty_sold' => $qtySold,
                'qty_bought' => $qtyBought,
                'revenue' => $revenue,
                'cogs' => $estimatedCogs,
                'profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue * 100) : 0,
                'sales_count' => $s->sales_count ?? 0,
                'rotation' => ($p->stock_quantity ?? 0) > 0 ? ($qtySold / $p->stock_quantity) : ($qtySold > 0 ? 999 : 0),
            ];
        })->filter(fn($r) => $r->qty_sold > 0 || $r->qty_bought > 0); // só produtos com movimento
        
        // Ordenação
        $rows = match($this->sortBy) {
            'profit_desc' => $rows->sortByDesc('profit'),
            'revenue_desc' => $rows->sortByDesc('revenue'),
            'qty_sold_desc' => $rows->sortByDesc('qty_sold'),
            'margin_desc' => $rows->sortByDesc('margin'),
            'rotation_desc' => $rows->sortByDesc('rotation'),
            'stock_asc' => $rows->sortBy('stock'),
            default => $rows->sortByDesc('profit'),
        };
        
        $rows = $rows->take($this->limit)->values();
        
        $totals = [
            'qty_sold' => $rows->sum('qty_sold'),
            'qty_bought' => $rows->sum('qty_bought'),
            'revenue' => $rows->sum('revenue'),
            'cogs' => $rows->sum('cogs'),
            'profit' => $rows->sum('profit'),
            'products_count' => $rows->count(),
        ];
        
        return view('livewire.invoicing.reports.product-performance-report', compact('rows', 'totals'));
    }
}

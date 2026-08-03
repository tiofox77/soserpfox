<?php

namespace App\Livewire\Invoicing\Reports;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Tabela de Preços e Lucro')]
class PriceListReport extends Component
{
    public $search = '';
    public $typeFilter = 'all';
    public $statusFilter = 'active';
    public $sortBy = 'name_asc';
    
    public function render()
    {
        $tenantId = activeTenantId();
        
        $query = DB::table('invoicing_products')
            ->where('tenant_id', $tenantId);
        
        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }
        
        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }
        
        if (trim($this->search) !== '') {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('sku', 'like', $s)
                  ->orWhere('barcode', 'like', $s);
            });
        }
        
        $products = $query->get()->map(function ($p) {
            $p->cost = (float) ($p->cost ?? 0);
            $p->price = (float) ($p->price ?? 0);
            $p->profit = $p->price - $p->cost;
            $p->margin = $p->price > 0 ? ($p->profit / $p->price * 100) : 0;
            $p->markup = $p->cost > 0 ? ($p->profit / $p->cost * 100) : 0;
            return $p;
        });
        
        // Ordenação
        $products = match($this->sortBy) {
            'name_asc' => $products->sortBy('name'),
            'profit_desc' => $products->sortByDesc('profit'),
            'margin_desc' => $products->sortByDesc('margin'),
            'price_desc' => $products->sortByDesc('price'),
            'cost_desc' => $products->sortByDesc('cost'),
            'stock_asc' => $products->sortBy('stock_quantity'),
            default => $products->sortBy('name'),
        };
        
        $products = $products->values();
        
        $totals = [
            'count' => $products->count(),
            'without_cost' => $products->where('cost', 0)->count(),
            'without_price' => $products->where('price', 0)->count(),
            'negative_margin' => $products->filter(fn($p) => $p->profit < 0)->count(),
            'avg_margin' => $products->where('price', '>', 0)->avg('margin') ?? 0,
            'total_stock_value_cost' => $products->sum(fn($p) => ($p->stock_quantity ?? 0) * $p->cost),
            'total_stock_value_sale' => $products->sum(fn($p) => ($p->stock_quantity ?? 0) * $p->price),
        ];
        
        return view('livewire.invoicing.reports.price-list-report', compact('products', 'totals'));
    }
    
    public function updatingSearch() { /* reset pagination */ }
    public function updatingTypeFilter() {}
    public function updatingStatusFilter() {}
}

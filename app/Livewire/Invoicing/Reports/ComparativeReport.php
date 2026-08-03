<?php

namespace App\Livewire\Invoicing\Reports;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Comparativo entre Períodos')]
class ComparativeReport extends Component
{
    public $mode = 'month'; // month, year, custom
    public $periodAFrom;
    public $periodATo;
    public $periodBFrom;
    public $periodBTo;
    
    public function mount()
    {
        $this->applyMode();
    }
    
    public function updatedMode()
    {
        $this->applyMode();
    }
    
    protected function applyMode()
    {
        $now = Carbon::now();
        if ($this->mode === 'month') {
            $this->periodAFrom = $now->copy()->startOfMonth()->format('Y-m-d');
            $this->periodATo = $now->copy()->endOfMonth()->format('Y-m-d');
            $this->periodBFrom = $now->copy()->subMonth()->startOfMonth()->format('Y-m-d');
            $this->periodBTo = $now->copy()->subMonth()->endOfMonth()->format('Y-m-d');
        } elseif ($this->mode === 'year') {
            $this->periodAFrom = $now->copy()->startOfYear()->format('Y-m-d');
            $this->periodATo = $now->copy()->endOfYear()->format('Y-m-d');
            $this->periodBFrom = $now->copy()->subYear()->startOfYear()->format('Y-m-d');
            $this->periodBTo = $now->copy()->subYear()->endOfYear()->format('Y-m-d');
        }
    }
    
    protected function metricsFor($from, $to): array
    {
        $tenantId = activeTenantId();
        
        $sales = DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'cancelled');
        
        $purchases = DB::table('invoicing_purchase_invoices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'cancelled');
        
        $cogs = (float) DB::table('invoicing_sales_invoice_items as items')
            ->join('invoicing_sales_invoices as inv', 'inv.id', '=', 'items.sales_invoice_id')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'items.product_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->where('inv.status', '!=', 'cancelled')
            ->sum(DB::raw('items.quantity * COALESCE(p.cost, 0)'));
        
        $revenue = (float) (clone $sales)->sum('subtotal');
        
        return [
            'revenue' => $revenue,
            'invoices_count' => (clone $sales)->count(),
            'clients_active' => (clone $sales)->distinct('client_id')->count('client_id'),
            'avg_ticket' => (float) (clone $sales)->avg('total'),
            'purchases' => (float) (clone $purchases)->sum('total'),
            'purchases_count' => (clone $purchases)->count(),
            'cogs' => $cogs,
            'profit' => $revenue - $cogs,
        ];
    }
    
    public function render()
    {
        $a = $this->metricsFor($this->periodAFrom, $this->periodATo);
        $b = $this->metricsFor($this->periodBFrom, $this->periodBTo);
        
        $variance = [];
        foreach ($a as $key => $valueA) {
            $valueB = $b[$key];
            $diff = $valueA - $valueB;
            $pct = $valueB != 0 ? ($diff / abs($valueB) * 100) : ($valueA != 0 ? 100 : 0);
            $variance[$key] = ['diff' => $diff, 'pct' => $pct];
        }
        
        return view('livewire.invoicing.reports.comparative-report', compact('a', 'b', 'variance'));
    }
}

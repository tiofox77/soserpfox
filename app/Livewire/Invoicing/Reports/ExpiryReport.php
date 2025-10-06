<?php

namespace App\Livewire\Invoicing\Reports;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use App\Models\Invoicing\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Relatório de Validade de Produtos')]
class ExpiryReport extends Component
{
    use WithPagination;

    public $reportType = 'expiring_soon'; // expiring_soon, expired, all
    public $daysFilter = 30;
    public $warehouseFilter = '';
    public $categoryFilter = '';
    public $searchFilter = '';
    
    // Exportação
    public $exportFormat = 'pdf';

    public function mount()
    {
        $this->daysFilter = 30;
    }

    public function updatedReportType()
    {
        $this->resetPage();
    }

    public function updatedDaysFilter()
    {
        $this->resetPage();
    }

    public function generateReport()
    {
        $this->resetPage();
    }

    public function exportReport()
    {
        // Implementar exportação
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Exportação em desenvolvimento...'
        ]);
    }

    public function render()
    {
        // Query base
        $query = ProductBatch::with(['product.category', 'warehouse'])
            ->where('invoicing_product_batches.tenant_id', activeTenantId())
            ->where('quantity_available', '>', 0);

        // Filtros por tipo de relatório
        switch ($this->reportType) {
            case 'expiring_soon':
                $query->where('status', 'active')
                    ->whereDate('expiry_date', '<=', Carbon::now()->addDays($this->daysFilter))
                    ->whereDate('expiry_date', '>=', Carbon::now());
                break;
                
            case 'expired':
                $query->whereDate('expiry_date', '<', Carbon::now());
                break;
                
            case 'all':
                $query->whereNotNull('expiry_date');
                break;
        }

        // Filtro por armazém
        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        // Filtro por categoria
        if ($this->categoryFilter) {
            $query->whereHas('product', function ($q) {
                $q->where('category_id', $this->categoryFilter);
            });
        }

        // Filtro por busca
        if ($this->searchFilter) {
            $query->where(function ($q) {
                $q->where('batch_number', 'like', '%' . $this->searchFilter . '%')
                  ->orWhereHas('product', function ($p) {
                      $p->where('name', 'like', '%' . $this->searchFilter . '%');
                  });
            });
        }

        $batches = $query->orderBy('expiry_date', 'asc')->paginate(20);

        // Estatísticas
        $stats = $this->getStatistics();

        // Dados para filtros
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = DB::table('invoicing_categories')
            ->where('tenant_id', activeTenantId())
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.reports.expiry-report', compact(
            'batches',
            'stats',
            'warehouses',
            'categories'
        ));
    }

    private function getStatistics()
    {
        $tenantId = activeTenantId();

        // Total de produtos com validade
        $totalWithExpiry = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->count();

        // Expirando em 7 dias
        $expiring7Days = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(7))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->count();

        // Expirando em 30 dias
        $expiring30Days = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->count();

        // Já expirados
        $expired = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<', Carbon::now())
            ->count();

        // Valor total em risco (produtos expirando)
        $valueAtRisk = ProductBatch::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->sum(DB::raw('quantity_available * cost_price'));

        // Valor perdido (produtos expirados)
        $valueLost = ProductBatch::where('tenant_id', $tenantId)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '<', Carbon::now())
            ->sum(DB::raw('quantity_available * cost_price'));

        return [
            'total_with_expiry' => $totalWithExpiry,
            'expiring_7_days' => $expiring7Days,
            'expiring_30_days' => $expiring30Days,
            'expired' => $expired,
            'value_at_risk' => $valueAtRisk,
            'value_lost' => $valueLost,
        ];
    }
}

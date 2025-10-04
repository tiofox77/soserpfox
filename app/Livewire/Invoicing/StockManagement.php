<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Stock')]
class StockManagement extends Component
{
    use WithPagination;

    public $showAdjustModal = false;
    public $showTransferModal = false;
    public $showMovementsModal = false;

    // Filters
    public $search = '';
    public $warehouseFilter = '';
    public $lowStockFilter = false;
    public $perPage = 15;

    // Adjust Form
    public $adjustStockId;
    public $adjustWarehouseId;
    public $adjustProductId;
    public $adjustProductName;
    public $adjustCurrentQty = 0;
    public $adjustNewQty;
    public $adjustNotes;

    // Transfer Form
    public $transferProductId;
    public $transferProductName;
    public $transferFromWarehouse;
    public $transferToWarehouse;
    public $transferQuantity;
    public $transferNotes;
    public $transferMaxQty = 0;

    // Movements
    public $movementsProductId;
    public $movementsProductName;

    protected $queryString = ['search', 'warehouseFilter'];

    public function render()
    {
        $query = Stock::where('tenant_id', activeTenantId())
            ->with(['warehouse', 'product']);

        // Warehouse Filter
        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        // Search
        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        // Low Stock Filter
        if ($this->lowStockFilter) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min');
            });
        }

        $stocks = $query->paginate($this->perPage);

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        // Stats
        $stats = [
            'total_products' => Stock::where('tenant_id', activeTenantId())->distinct('product_id')->count('product_id'),
            'total_quantity' => Stock::where('tenant_id', activeTenantId())->sum('quantity'),
            'total_value' => Stock::where('tenant_id', activeTenantId())->selectRaw('SUM(quantity * unit_cost)')->value('SUM(quantity * unit_cost)') ?? 0,
            'low_stock' => Stock::where('tenant_id', activeTenantId())
                ->whereHas('product', function ($q) {
                    $q->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min');
                })->count(),
        ];

        return view('livewire.invoicing.stock.stock-management', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    public function openAdjustModal($stockId)
    {
        $stock = Stock::where('tenant_id', activeTenantId())
            ->with('product')
            ->findOrFail($stockId);

        $this->adjustStockId = $stock->id;
        $this->adjustWarehouseId = $stock->warehouse_id;
        $this->adjustProductId = $stock->product_id;
        $this->adjustProductName = $stock->product->name;
        $this->adjustCurrentQty = (int) $stock->quantity;
        $this->adjustNewQty = (int) $stock->quantity;
        $this->adjustNotes = '';

        $this->showAdjustModal = true;
    }

    public function saveAdjustment()
    {
        $this->validate([
            'adjustNewQty' => 'required|numeric|min:0',
            'adjustNotes' => 'nullable|string|max:500',
        ]);

        StockMovement::createAdjustment(
            $this->adjustWarehouseId,
            $this->adjustProductId,
            $this->adjustNewQty,
            $this->adjustNotes
        );

        session()->flash('message', 'Stock ajustado com sucesso!');
        $this->showAdjustModal = false;
        $this->resetAdjustForm();
    }

    public function openTransferModal($stockId)
    {
        $stock = Stock::where('tenant_id', activeTenantId())
            ->with('product')
            ->findOrFail($stockId);

        $this->transferProductId = $stock->product_id;
        $this->transferProductName = $stock->product->name;
        $this->transferFromWarehouse = $stock->warehouse_id;
        $this->transferToWarehouse = '';
        $this->transferQuantity = '';
        $this->transferNotes = '';
        $this->transferMaxQty = (int) $stock->available_quantity;

        $this->showTransferModal = true;
    }

    public function saveTransfer()
    {
        $this->validate([
            'transferFromWarehouse' => 'required|exists:invoicing_warehouses,id',
            'transferToWarehouse' => 'required|exists:invoicing_warehouses,id|different:transferFromWarehouse',
            'transferQuantity' => 'required|numeric|min:0.001|max:' . $this->transferMaxQty,
            'transferNotes' => 'nullable|string|max:500',
        ]);

        try {
            StockMovement::createTransfer(
                $this->transferFromWarehouse,
                $this->transferToWarehouse,
                $this->transferProductId,
                $this->transferQuantity,
                $this->transferNotes
            );

            session()->flash('message', 'Transferência realizada com sucesso!');
            $this->showTransferModal = false;
            $this->resetTransferForm();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function showMovements($productId, $productName)
    {
        $this->movementsProductId = $productId;
        $this->movementsProductName = $productName;
        $this->showMovementsModal = true;
    }

    private function resetAdjustForm()
    {
        $this->adjustStockId = null;
        $this->adjustWarehouseId = null;
        $this->adjustProductId = null;
        $this->adjustProductName = '';
        $this->adjustCurrentQty = 0;
        $this->adjustNewQty = null;
        $this->adjustNotes = '';
        $this->resetErrorBag();
    }

    private function resetTransferForm()
    {
        $this->transferProductId = null;
        $this->transferProductName = '';
        $this->transferFromWarehouse = '';
        $this->transferToWarehouse = '';
        $this->transferQuantity = '';
        $this->transferNotes = '';
        $this->transferMaxQty = 0;
        $this->resetErrorBag();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingWarehouseFilter()
    {
        $this->resetPage();
    }
}

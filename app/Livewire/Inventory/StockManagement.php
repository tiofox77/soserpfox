<?php

namespace App\Livewire\Inventory;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Product;
use App\Models\Invoicing\Warehouse;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Gestão de Stock')]
class StockManagement extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterWarehouse = '';
    public $filterLowStock = false;
    public $perPage = 15;
    
    // Ajuste manual
    public $showAdjustModal = false;
    public $adjustingStock = null;
    public $adjustType = 'in'; // in ou out
    public $adjustQuantity = 0;
    public $adjustReason = '';
    public $adjustNotes = '';
    
    protected $queryString = [
        'search' => ['except' => ''],
        'filterWarehouse' => ['except' => ''],
        'filterLowStock' => ['except' => false],
    ];
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingFilterWarehouse()
    {
        $this->resetPage();
    }
    
    public function updatingFilterLowStock()
    {
        $this->resetPage();
    }
    
    public function openAdjustModal($stockId)
    {
        $this->adjustingStock = Stock::with(['product', 'warehouse'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($stockId);
        
        $this->reset(['adjustType', 'adjustQuantity', 'adjustReason', 'adjustNotes']);
        $this->showAdjustModal = true;
    }
    
    public function closeAdjustModal()
    {
        $this->showAdjustModal = false;
        $this->adjustingStock = null;
    }
    
    public function saveAdjustment()
    {
        $this->validate([
            'adjustType' => 'required|in:in,out',
            'adjustQuantity' => 'required|numeric|min:0.01',
            'adjustReason' => 'required|string|max:255',
        ]);
        
        if (!$this->adjustingStock) {
            $this->dispatch('error', message: 'Stock não encontrado!');
            return;
        }
        
        DB::beginTransaction();
        
        try {
            // Atualizar quantidade
            if ($this->adjustType === 'in') {
                $this->adjustingStock->quantity += $this->adjustQuantity;
            } else {
                if ($this->adjustingStock->quantity < $this->adjustQuantity) {
                    throw new \Exception('Quantidade insuficiente em stock!');
                }
                $this->adjustingStock->quantity -= $this->adjustQuantity;
            }
            
            $this->adjustingStock->save();
            
            // Registrar movimento
            StockMovement::create([
                'tenant_id' => activeTenantId(),
                'product_id' => $this->adjustingStock->product_id,
                'warehouse_id' => $this->adjustingStock->warehouse_id,
                'type' => $this->adjustType,
                'quantity' => $this->adjustQuantity,
                'reference_type' => 'manual_adjustment',
                'reference' => 'Ajuste Manual',
                'notes' => $this->adjustReason . ($this->adjustNotes ? ' - ' . $this->adjustNotes : ''),
                'user_id' => auth()->id(),
            ]);
            
            DB::commit();
            
            $this->dispatch('success', message: 'Ajuste de stock realizado com sucesso!');
            $this->closeAdjustModal();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Erro ao ajustar stock: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $query = Stock::with(['product', 'warehouse'])
            ->where('tenant_id', activeTenantId());
        
        // Busca
        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }
        
        // Filtro por armazém
        if ($this->filterWarehouse) {
            $query->where('warehouse_id', $this->filterWarehouse);
        }
        
        // Filtro stock baixo
        if ($this->filterLowStock) {
            $query->whereHas('product', function ($q) {
                $q->whereRaw('(SELECT SUM(quantity) FROM invoicing_stocks WHERE product_id = invoicing_products.id) <= invoicing_products.stock_min');
            });
        }
        
        $stocks = $query->orderBy('id', 'desc')->paginate($this->perPage);
        
        // Calcular estatísticas
        $stats = [
            'total_products' => Product::where('tenant_id', activeTenantId())
                ->where('manage_stock', true)
                ->count(),
            'low_stock' => Stock::where('tenant_id', activeTenantId())
                ->whereHas('product', function ($q) {
                    $q->whereRaw('invoicing_stocks.quantity <= invoicing_products.stock_min');
                })
                ->count(),
            'out_of_stock' => Stock::where('tenant_id', activeTenantId())
                ->where('quantity', '<=', 0)
                ->count(),
            'total_value' => Stock::where('tenant_id', activeTenantId())
                ->join('invoicing_products', 'invoicing_stocks.product_id', '=', 'invoicing_products.id')
                ->selectRaw('SUM(invoicing_stocks.quantity * invoicing_products.cost) as total')
                ->value('total') ?? 0,
        ];
        
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        
        return view('livewire.inventory.stock-management', [
            'stocks' => $stocks,
            'stats' => $stats,
            'warehouses' => $warehouses,
        ]);
    }
}

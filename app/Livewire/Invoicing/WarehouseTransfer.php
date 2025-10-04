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
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Transferências e Ajustes de Stock')]
class WarehouseTransfer extends Component
{
    use WithPagination;

    public $showTransferModal = false;
    public $showAdjustModal = false;
    public $showQuantityModal = false;
    public $showDetailsModal = false;
    public $selectedBatchDetails = [];
    public $selectedBatchId;

    // Transfer Modal
    public $transferFromWarehouse = '';
    public $transferToWarehouse = '';
    public $transferNotes = '';
    
    // Add Product to Transfer
    public $selectedProduct = '';
    public $selectedProductName = '';
    public $selectedProductCode = '';
    public $productQuantity = '';
    public $availableStock = 0;
    public $productSearch = '';
    
    // Transfer Items Cart
    public $transferItems = [];

    // Adjust Modal
    public $adjustWarehouse = '';
    public $adjustType = 'in'; // in ou out
    public $adjustReason = '';
    public $adjustSearch = '';
    
    // Selected product for adjust
    public $adjustSelectedProduct = '';
    public $adjustSelectedProductName = '';
    public $adjustSelectedProductCode = '';
    public $adjustProductQuantity = '';
    public $adjustAvailableStock = 0;
    public $showAdjustQuantityModal = false;
    
    // Adjust Items Cart
    public $adjustItems = [];

    // Filters
    public $search = '';
    public $warehouseFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public function openTransferModal()
    {
        $this->reset(['transferFromWarehouse', 'transferToWarehouse', 'transferNotes', 'selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'transferItems', 'productSearch']);
        $this->showTransferModal = true;
    }

    public function selectProductForTransfer($productId)
    {
        if (!$this->transferFromWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém de origem primeiro.');
            return;
        }

        $product = Product::find($productId);
        $this->selectedProduct = $productId;
        $this->selectedProductName = $product->name;
        $this->selectedProductCode = $product->code;
        
        // Buscar stock disponível
        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->transferFromWarehouse)
            ->where('product_id', $productId)
            ->first();
        
        $this->availableStock = $stock ? $stock->quantity : 0;
        $this->productQuantity = '';
        $this->showQuantityModal = true;
    }

    public function addProductToTransfer()
    {
        if (!$this->transferFromWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém de origem primeiro.');
            return;
        }

        if (!$this->selectedProduct || !$this->productQuantity) {
            $this->dispatch('error', message: 'Selecione um produto e quantidade.');
            return;
        }

        if ($this->productQuantity > $this->availableStock) {
            $this->dispatch('error', message: 'Quantidade maior que o stock disponível.');
            return;
        }

        // Verificar se produto já está na lista
        $exists = false;
        foreach ($this->transferItems as $index => $item) {
            if ($item['product_id'] == $this->selectedProduct) {
                $this->transferItems[$index]['quantity'] += $this->productQuantity;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $product = Product::find($this->selectedProduct);
            $this->transferItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'quantity' => $this->productQuantity,
            ];
        }

        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock']);
        $this->showQuantityModal = false;
        $this->dispatch('success', message: 'Produto adicionado à transferência!');
    }

    public function removeProductFromTransfer($index)
    {
        unset($this->transferItems[$index]);
        $this->transferItems = array_values($this->transferItems);
    }

    public function openAdjustModal()
    {
        $this->reset(['adjustWarehouse', 'adjustType', 'adjustReason', 'adjustSearch', 'adjustSelectedProduct', 'adjustSelectedProductName', 'adjustSelectedProductCode', 'adjustProductQuantity', 'adjustAvailableStock', 'adjustItems']);
        $this->showAdjustModal = true;
    }

    public function selectProductForAdjust($productId)
    {
        if (!$this->adjustWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém primeiro.');
            return;
        }

        $product = Product::find($productId);
        $this->adjustSelectedProduct = $productId;
        $this->adjustSelectedProductName = $product->name;
        $this->adjustSelectedProductCode = $product->code;
        
        // Buscar stock atual
        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->adjustWarehouse)
            ->where('product_id', $productId)
            ->first();
        
        $this->adjustAvailableStock = $stock ? $stock->quantity : 0;
        $this->adjustProductQuantity = '';
        $this->showAdjustQuantityModal = true;
    }

    public function addProductToAdjust()
    {
        if (!$this->adjustSelectedProduct || !$this->adjustProductQuantity) {
            $this->dispatch('error', message: 'Selecione um produto e quantidade.');
            return;
        }

        // Verificar se produto já está na lista
        $exists = false;
        foreach ($this->adjustItems as $index => $item) {
            if ($item['product_id'] == $this->adjustSelectedProduct) {
                $this->adjustItems[$index]['quantity'] = $this->adjustProductQuantity;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $product = Product::find($this->adjustSelectedProduct);
            $this->adjustItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'quantity' => $this->adjustProductQuantity,
            ];
        }

        $this->reset(['adjustSelectedProduct', 'adjustSelectedProductName', 'adjustSelectedProductCode', 'adjustProductQuantity', 'adjustAvailableStock']);
        $this->showAdjustQuantityModal = false;
        $this->dispatch('success', message: 'Produto adicionado ao ajuste!');
    }

    public function removeProductFromAdjust($index)
    {
        unset($this->adjustItems[$index]);
        $this->adjustItems = array_values($this->adjustItems);
    }

    public function openDetailsModal()
    {
        if (!$this->selectedBatchId) {
            return;
        }
        
        $this->selectedBatchDetails = StockMovement::where('tenant_id', activeTenantId())
            ->where('reference_id', (int)$this->selectedBatchId)
            ->with(['product', 'warehouse'])
            ->get()
            ->toArray();
        
        $this->showDetailsModal = true;
    }

    public function updatedSelectedProduct()
    {
        if ($this->selectedProduct && $this->transferFromWarehouse) {
            $stock = Stock::where('tenant_id', activeTenantId())
                ->where('warehouse_id', $this->transferFromWarehouse)
                ->where('product_id', $this->selectedProduct)
                ->first();
            
            $this->availableStock = $stock ? $stock->quantity : 0;
        }
    }

    public function updatedTransferFromWarehouse()
    {
        $this->availableStock = 0;
        if ($this->selectedProduct) {
            $this->updatedSelectedProduct();
        }
    }

    public function saveTransfer()
    {
        if (empty($this->transferItems)) {
            $this->dispatch('error', message: 'Adicione pelo menos um produto à transferência.');
            return;
        }

        if (!$this->transferFromWarehouse || !$this->transferToWarehouse) {
            $this->dispatch('error', message: 'Selecione os armazéns de origem e destino.');
            return;
        }

        try {
            DB::beginTransaction();

            $warehouseFrom = Warehouse::find($this->transferFromWarehouse);
            $warehouseTo = Warehouse::find($this->transferToWarehouse);
            
            // Gerar ID único para este lote de transferência
            $batchId = crc32(uniqid('TRANS-' . auth()->id() . '-', true));

            foreach ($this->transferItems as $item) {
                // Reduzir stock origem
                $stockFrom = Stock::where('tenant_id', activeTenantId())
                    ->where('warehouse_id', $this->transferFromWarehouse)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stockFrom || $stockFrom->quantity < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para {$item['product_name']}");
                }

                $stockFrom->decrement('quantity', $item['quantity']);

                // Aumentar stock destino
                $stockTo = Stock::firstOrCreate([
                    'tenant_id' => activeTenantId(),
                    'warehouse_id' => $this->transferToWarehouse,
                    'product_id' => $item['product_id'],
                ], ['quantity' => 0]);

                $stockTo->increment('quantity', $item['quantity']);

                $product = Product::find($item['product_id']);
                
                // Registrar movimento saída
                StockMovement::create([
                    'tenant_id' => activeTenantId(),
                    'warehouse_id' => $this->transferFromWarehouse,
                    'product_id' => $item['product_id'],
                    'type' => 'transfer',
                    'quantity' => -$item['quantity'],
                    'unit_cost' => $product->cost,
                    'reference_type' => 'transfer_batch',
                    'reference_id' => $batchId,
                    'notes' => "Transfer para {$warehouseTo->name}. {$this->transferNotes}",
                    'user_id' => auth()->id(),
                ]);

                // Registrar movimento entrada
                StockMovement::create([
                    'tenant_id' => activeTenantId(),
                    'warehouse_id' => $this->transferToWarehouse,
                    'product_id' => $item['product_id'],
                    'type' => 'transfer',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $product->cost,
                    'reference_type' => 'transfer_batch',
                    'reference_id' => $batchId,
                    'notes' => "Transfer de {$warehouseFrom->name}. {$this->transferNotes}",
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            $this->dispatch('success', message: count($this->transferItems) . ' produto(s) transferido(s) com sucesso!');
            $this->showTransferModal = false;
            $this->reset(['transferItems']);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function saveAdjust()
    {
        if (empty($this->adjustItems)) {
            $this->dispatch('error', message: 'Adicione pelo menos um produto ao ajuste.');
            return;
        }

        if (!$this->adjustWarehouse || !$this->adjustReason) {
            $this->dispatch('error', message: 'Selecione o armazém e informe o motivo.');
            return;
        }

        try {
            DB::beginTransaction();
            
            // Gerar ID único para este lote de ajuste
            $batchId = crc32(uniqid('ADJ-' . auth()->id() . '-', true));

            foreach ($this->adjustItems as $item) {
                $product = Product::find($item['product_id']);

                // Atualizar stock
                $stock = Stock::firstOrCreate([
                    'tenant_id' => activeTenantId(),
                    'warehouse_id' => $this->adjustWarehouse,
                    'product_id' => $item['product_id'],
                ], ['quantity' => 0]);

                if ($this->adjustType == 'in') {
                    $stock->increment('quantity', $item['quantity']);
                } else {
                    $stock->decrement('quantity', $item['quantity']);
                }

                // Registrar movimento
                StockMovement::create([
                    'tenant_id' => activeTenantId(),
                    'warehouse_id' => $this->adjustWarehouse,
                    'product_id' => $item['product_id'],
                    'type' => 'adjustment',
                    'quantity' => $this->adjustType == 'in' ? $item['quantity'] : -$item['quantity'],
                    'unit_cost' => $product->cost,
                    'reference_type' => 'adjustment_batch',
                    'reference_id' => $batchId,
                    'notes' => "Ajuste manual ({$this->adjustType}): {$this->adjustReason}",
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            $this->dispatch('success', message: count($this->adjustItems) . ' produto(s) ajustado(s) com sucesso!');
            $this->showAdjustModal = false;
            $this->reset(['adjustItems']);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $products = Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Histórico de movimentos - Agrupar por lote (reference_id)
        $query = StockMovement::where('tenant_id', activeTenantId())
            ->whereIn('type', ['transfer', 'adjustment'])
            ->select(
                'reference_id',
                'reference_type',
                DB::raw('MAX(id) as id'),
                DB::raw('MAX(tenant_id) as tenant_id'),
                DB::raw('MAX(warehouse_id) as warehouse_id'),
                DB::raw('MAX(product_id) as product_id'),
                DB::raw('MAX(type) as type'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('MAX(notes) as notes'),
                DB::raw('MAX(created_at) as created_at'),
                DB::raw('COUNT(*) as products_count'),
                DB::raw('SUM(ABS(quantity)) as total_quantity')
            );

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->search) {
            $query->whereHas('product', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Agrupar por reference_id para mostrar apenas um registro por lote
        $movements = $query->groupBy('reference_id', 'reference_type')
              ->orderBy('created_at', 'desc')
              ->paginate(15);
              
        // Carregar relacionamentos após a query
        $movements->load(['warehouse', 'product', 'user']);

        return view('livewire.invoicing.warehouse-transfer.warehouse-transfer', [
            'warehouses' => $warehouses,
            'products' => $products,
            'movements' => $movements,
        ]);
    }
}

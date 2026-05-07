<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Transferências Inter-Empresas')]
class InterCompanyTransfer extends Component
{
    use WithPagination;

    public $showTransferModal = false;

    // Warehouses & Tenant
    public $warehouseFromId = '';
    public $tenantToId = '';
    public $warehouseToId = '';
    public $notes = '';

    // Product search
    public $productSearch = '';

    // Quantity modal
    public $showQuantityModal = false;
    public $selectedProduct = '';
    public $selectedProductName = '';
    public $selectedProductCode = '';
    public $productQuantity = '';
    public $availableStock = 0;
    public $selectedUnitCost = 0;

    // Cart
    public $transferItems = [];

    public function render()
    {
        $myTenants = auth()->user()->tenants()
            ->where('tenants.id', '!=', activeTenantId())
            ->get();

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $products = Product::where('tenant_id', activeTenantId())
            ->whereHas('stocks', function ($q) {
                $q->where('quantity', '>', 0);
            })
            ->with('stocks')
            ->get();

        $transfers = StockMovement::where('tenant_id', activeTenantId())
            ->where('reference_type', 'inter_company')
            ->whereIn('type', ['transfer', 'in'])
            ->with([
                'product',
                'warehouse',
                'toWarehouse' => fn($q) => $q->withoutGlobalScope('tenant'),
                'fromWarehouse' => fn($q) => $q->withoutGlobalScope('tenant'),
                'user',
            ])
            ->latest()
            ->paginate(10);

        return view('livewire.invoicing.stock.inter-company-transfer', [
            'myTenants' => $myTenants,
            'warehouses' => $warehouses,
            'products' => $products,
            'transfers' => $transfers,
        ]);
    }

    public function updatedTenantToId()
    {
        $this->warehouseToId = '';
    }

    public function getWarehousesForTenant()
    {
        if (!$this->tenantToId) {
            return collect();
        }

        return Warehouse::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantToId)
            ->where('is_active', true)
            ->get();
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showTransferModal = true;
    }

    public function selectProduct($productId)
    {
        if (!$this->warehouseFromId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém de origem primeiro.']);
            return;
        }

        $product = Product::find($productId);
        if (!$product) return;

        $this->selectedProduct = $productId;
        $this->selectedProductName = $product->name;
        $this->selectedProductCode = $product->code;

        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->warehouseFromId)
            ->where('product_id', $productId)
            ->first();

        $this->availableStock = $stock ? $stock->available_quantity : 0;
        $this->selectedUnitCost = $stock ? $stock->unit_cost : 0;
        $this->productQuantity = '';
        $this->showQuantityModal = true;
    }

    public function addProductToTransfer()
    {
        if (!$this->selectedProduct || !$this->productQuantity || $this->productQuantity <= 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione um produto e quantidade válida.']);
            return;
        }

        if ($this->productQuantity > $this->availableStock) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Quantidade maior que o stock disponível.']);
            return;
        }

        // Check if product already in cart
        $exists = false;
        foreach ($this->transferItems as $index => $item) {
            if ($item['product_id'] == $this->selectedProduct) {
                $newQty = $this->transferItems[$index]['quantity'] + $this->productQuantity;
                if ($newQty > $this->availableStock) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => 'Quantidade total excede o stock disponível.']);
                    return;
                }
                $this->transferItems[$index]['quantity'] = $newQty;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $this->transferItems[] = [
                'product_id' => $this->selectedProduct,
                'product_name' => $this->selectedProductName,
                'product_code' => $this->selectedProductCode,
                'quantity' => $this->productQuantity,
                'unit_cost' => $this->selectedUnitCost,
            ];
        }

        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'selectedUnitCost']);
        $this->showQuantityModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Produto adicionado!']);
    }

    public function removeProduct($index)
    {
        unset($this->transferItems[$index]);
        $this->transferItems = array_values($this->transferItems);
    }

    public function saveTransfer()
    {
        if (!$this->warehouseFromId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém de origem.']);
            return;
        }
        if (!$this->tenantToId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione a empresa destino.']);
            return;
        }
        if (!$this->warehouseToId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém destino.']);
            return;
        }
        if (empty($this->transferItems)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Adicione pelo menos um produto.']);
            return;
        }
        if (!$this->notes) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Informe o motivo da transferência.']);
            return;
        }

        try {
            DB::beginTransaction();

            $tenantTo = Tenant::find($this->tenantToId);
            if (!$tenantTo) {
                throw new \Exception('Empresa destino não encontrada.');
            }

            $batchId = time() . rand(1000, 9999);
            $originTenantName = activeTenant()->name;

            foreach ($this->transferItems as $item) {
                $sourceProduct = Product::find($item['product_id']);
                if (!$sourceProduct) {
                    throw new \Exception('Produto não encontrado: ' . $item['product_name']);
                }

                $unitCost = $item['unit_cost'] ?? 0;

                // 1. Encontrar ou criar o produto no tenant destino
                $destProduct = $this->findOrCreateProductInTenant($sourceProduct, $this->tenantToId);

                // 2. Remover stock da empresa origem
                Stock::removeStock($this->warehouseFromId, $sourceProduct->id, $item['quantity']);

                // 3. Adicionar stock na empresa destino (usando o product_id do destino)
                $destStock = Stock::withoutGlobalScope('tenant')
                    ->where('tenant_id', $this->tenantToId)
                    ->where('warehouse_id', $this->warehouseToId)
                    ->where('product_id', $destProduct->id)
                    ->first();

                if ($destStock) {
                    if ($unitCost > 0) {
                        $totalCost = ($destStock->quantity * $destStock->unit_cost) + ($item['quantity'] * $unitCost);
                        $totalQty = $destStock->quantity + $item['quantity'];
                        $destStock->unit_cost = $totalQty > 0 ? $totalCost / $totalQty : $unitCost;
                    }
                    $destStock->quantity += $item['quantity'];
                    $destStock->save();
                } else {
                    $newStock = new Stock();
                    $newStock->tenant_id = $this->tenantToId;
                    $newStock->warehouse_id = $this->warehouseToId;
                    $newStock->product_id = $destProduct->id;
                    $newStock->quantity = $item['quantity'];
                    $newStock->reserved_quantity = 0;
                    $newStock->unit_cost = $unitCost;
                    $newStock->saveQuietly();
                }

                // 4. Transferir lotes se o produto rastreia lotes
                if ($sourceProduct->track_batches) {
                    $this->transferBatchesInterCompany(
                        $sourceProduct->id,
                        $destProduct->id,
                        $this->warehouseFromId,
                        $this->warehouseToId,
                        $this->tenantToId,
                        $item['quantity']
                    );
                }

                // 5. Registrar movimentos sem disparar boot
                StockMovement::withoutEvents(function () use ($item, $tenantTo, $unitCost, $batchId, $sourceProduct, $destProduct, $originTenantName) {
                    // Movimento saída (origem) - usa product_id da origem
                    StockMovement::create([
                        'tenant_id' => activeTenantId(),
                        'warehouse_id' => $this->warehouseFromId,
                        'product_id' => $sourceProduct->id,
                        'type' => 'transfer',
                        'quantity' => -$item['quantity'],
                        'unit_cost' => $unitCost,
                        'reference_type' => 'inter_company',
                        'reference_id' => $batchId,
                        'to_warehouse_id' => $this->warehouseToId,
                        'user_id' => auth()->id(),
                        'notes' => $this->notes . ' (Transferido para: ' . $tenantTo->name . ')',
                    ]);

                    // Movimento entrada (destino) - usa product_id do destino
                    StockMovement::create([
                        'tenant_id' => $this->tenantToId,
                        'warehouse_id' => $this->warehouseToId,
                        'product_id' => $destProduct->id,
                        'type' => 'in',
                        'quantity' => $item['quantity'],
                        'unit_cost' => $unitCost,
                        'reference_type' => 'inter_company',
                        'reference_id' => $batchId,
                        'from_warehouse_id' => $this->warehouseFromId,
                        'user_id' => auth()->id(),
                        'notes' => $this->notes . ' (Recebido de: ' . $originTenantName . ')',
                    ]);
                });
            }

            DB::commit();

            $count = count($this->transferItems);
            $this->dispatch('notify', ['type' => 'success', 'message' => $count . ' produto(s) transferido(s) para ' . $tenantTo->name . '!']);
            $this->showTransferModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        }
    }

    private function findOrCreateProductInTenant(Product $sourceProduct, $tenantId): Product
    {
        // Procurar produto existente no tenant destino por nome exacto
        $existing = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($sourceProduct) {
                $q->where('name', $sourceProduct->name);
                if ($sourceProduct->sku) {
                    $q->orWhere('sku', $sourceProduct->sku);
                }
                if ($sourceProduct->barcode) {
                    $q->orWhere('barcode', $sourceProduct->barcode);
                }
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        // Criar cópia do produto no tenant destino
        $newProduct = new Product();
        $newProduct->tenant_id = $tenantId;
        $newProduct->name = $sourceProduct->name;
        $newProduct->description = $sourceProduct->description;
        $newProduct->type = $sourceProduct->type ?? 'produto';
        $newProduct->sku = $sourceProduct->sku;
        $newProduct->barcode = $sourceProduct->barcode;
        $newProduct->price = $sourceProduct->price;
        $newProduct->cost = $sourceProduct->cost;
        $newProduct->tax_type = $sourceProduct->tax_type;
        $newProduct->tax_rate_id = $sourceProduct->tax_rate_id;
        $newProduct->exemption_reason = $sourceProduct->exemption_reason;
        $newProduct->unit = $sourceProduct->unit;
        $newProduct->manage_stock = $sourceProduct->manage_stock;
        $newProduct->track_batches = $sourceProduct->track_batches;
        $newProduct->track_expiry = $sourceProduct->track_expiry;
        $newProduct->is_active = true;
        $newProduct->featured_image = $sourceProduct->featured_image;
        $newProduct->save();

        return $newProduct;
    }

    private function transferBatchesInterCompany($sourceProductId, $destProductId, $fromWarehouseId, $toWarehouseId, $tenantToId, $quantity)
    {
        $remaining = $quantity;

        // FEFO: buscar lotes do armazém origem, ordenados por validade
        $sourceBatches = ProductBatch::where('tenant_id', activeTenantId())
            ->where('product_id', $sourceProductId)
            ->where('warehouse_id', $fromWarehouseId)
            ->where('quantity_available', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($sourceBatches as $sourceBatch) {
            if ($remaining <= 0) break;

            $take = min($remaining, $sourceBatch->quantity_available);

            // Diminuir quantidade no lote origem
            $sourceBatch->quantity_available -= $take;
            $sourceBatch->updateStatus();

            // Criar lote no tenant destino (sem global scope)
            $destBatch = ProductBatch::withoutGlobalScopes()
                ->where('tenant_id', $tenantToId)
                ->where('product_id', $destProductId)
                ->where('warehouse_id', $toWarehouseId)
                ->where('batch_number', $sourceBatch->batch_number)
                ->first();

            if ($destBatch) {
                $destBatch->quantity += $take;
                $destBatch->quantity_available += $take;
                $destBatch->updateStatus();
            } else {
                $newBatch = new ProductBatch();
                $newBatch->tenant_id = $tenantToId;
                $newBatch->product_id = $destProductId;
                $newBatch->warehouse_id = $toWarehouseId;
                $newBatch->batch_number = $sourceBatch->batch_number;
                $newBatch->manufacturing_date = $sourceBatch->manufacturing_date;
                $newBatch->expiry_date = $sourceBatch->expiry_date;
                $newBatch->quantity = $take;
                $newBatch->quantity_available = $take;
                $newBatch->cost_price = $sourceBatch->cost_price;
                $newBatch->alert_days = $sourceBatch->alert_days;
                $newBatch->status = 'active';
                $newBatch->notes = 'Transferência inter-empresas';
                $newBatch->save();
            }

            $remaining -= $take;
        }
    }

    private function resetForm()
    {
        $this->warehouseFromId = '';
        $this->tenantToId = '';
        $this->warehouseToId = '';
        $this->notes = '';
        $this->productSearch = '';
        $this->transferItems = [];
        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'selectedUnitCost']);
        $this->resetErrorBag();
    }
}

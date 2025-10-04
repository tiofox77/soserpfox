<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Transferências Inter-Empresas')]
class InterCompanyTransfer extends Component
{
    use WithPagination;

    public $showTransferModal = false;

    // Transfer Form
    public $productId;
    public $warehouseFromId;
    public $tenantToId;
    public $warehouseToId;
    public $quantity;
    public $notes;
    public $unitCost;

    public $availableQuantity = 0;

    protected function rules()
    {
        return [
            'productId' => 'required|exists:invoicing_products,id',
            'warehouseFromId' => 'required|exists:invoicing_warehouses,id',
            'tenantToId' => 'required|exists:tenants,id',
            'warehouseToId' => 'required|exists:invoicing_warehouses,id',
            'quantity' => 'required|numeric|min:0.001|max:' . $this->availableQuantity,
            'unitCost' => 'nullable|numeric|min:0',
            'notes' => 'required|string|max:500',
        ];
    }

    public function render()
    {
        // Apenas empresas que o usuário tem acesso
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

        // Histórico de transferências
        $transfers = StockMovement::where('tenant_id', activeTenantId())
            ->where('type', 'transfer')
            ->where('reference_type', 'inter_company')
            ->with(['product', 'warehouse', 'toWarehouse', 'user'])
            ->latest()
            ->paginate(10);

        return view('livewire.invoicing.stock.inter-company-transfer', [
            'myTenants' => $myTenants,
            'warehouses' => $warehouses,
            'products' => $products,
            'transfers' => $transfers,
        ]);
    }

    public function updatedProductId()
    {
        $this->updateAvailableQuantity();
    }

    public function updatedWarehouseFromId()
    {
        $this->updateAvailableQuantity();
    }

    private function updateAvailableQuantity()
    {
        if ($this->productId && $this->warehouseFromId) {
            $stock = Stock::where('product_id', $this->productId)
                ->where('warehouse_id', $this->warehouseFromId)
                ->where('tenant_id', activeTenantId())
                ->first();

            $this->availableQuantity = $stock ? $stock->available_quantity : 0;
            $this->unitCost = $stock ? $stock->unit_cost : null;
        } else {
            $this->availableQuantity = 0;
        }
    }

    public function getWarehousesForTenant()
    {
        if (!$this->tenantToId) {
            return [];
        }

        return Warehouse::where('tenant_id', $this->tenantToId)
            ->where('is_active', true)
            ->get();
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showTransferModal = true;
    }

    public function saveTransfer()
    {
        $this->validate();

        try {
            \DB::beginTransaction();

            // 1. Remove stock da empresa origem
            Stock::removeStock($this->warehouseFromId, $this->productId, $this->quantity);

            // 2. Cria movimento na empresa origem (saída)
            StockMovement::create([
                'tenant_id' => activeTenantId(),
                'warehouse_id' => $this->warehouseFromId,
                'product_id' => $this->productId,
                'type' => 'transfer',
                'quantity' => $this->quantity,
                'unit_cost' => $this->unitCost,
                'reference_type' => 'inter_company',
                'reference_id' => $this->tenantToId,
                'to_warehouse_id' => $this->warehouseToId,
                'user_id' => auth()->id(),
                'notes' => $this->notes . ' (Transferência para: ' . Tenant::find($this->tenantToId)->name . ')',
            ]);

            // 3. Adiciona stock na empresa destino
            Stock::create([
                'tenant_id' => $this->tenantToId,
                'warehouse_id' => $this->warehouseToId,
                'product_id' => $this->productId,
                'quantity' => $this->quantity,
                'unit_cost' => $this->unitCost,
            ]);

            // 4. Cria movimento na empresa destino (entrada)
            StockMovement::create([
                'tenant_id' => $this->tenantToId,
                'warehouse_id' => $this->warehouseToId,
                'product_id' => $this->productId,
                'type' => 'in',
                'quantity' => $this->quantity,
                'unit_cost' => $this->unitCost,
                'reference_type' => 'inter_company',
                'reference_id' => activeTenantId(),
                'from_warehouse_id' => $this->warehouseFromId,
                'user_id' => auth()->id(),
                'notes' => $this->notes . ' (Recebido de: ' . activeTenant()->name . ')',
            ]);

            \DB::commit();

            session()->flash('message', 'Transferência entre empresas realizada com sucesso!');
            $this->showTransferModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            \DB::rollBack();
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }

    private function resetForm()
    {
        $this->productId = null;
        $this->warehouseFromId = null;
        $this->tenantToId = null;
        $this->warehouseToId = null;
        $this->quantity = null;
        $this->notes = '';
        $this->unitCost = null;
        $this->availableQuantity = 0;
        $this->resetErrorBag();
    }
}

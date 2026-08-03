<?php

namespace App\Livewire\Restaurant;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Restaurant\Waste;
use App\Services\Restaurant\RestaurantStockService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock e Desperdícios - Restaurante')]
class StockWasteManagement extends Component
{
    public ?int $productId = null, $warehouseId = null;
    public float $quantity = 1;
    public string $reason = '';
    public bool $showWaste = false;

    public function mount(): void { $this->warehouseId = Warehouse::where('tenant_id', activeTenantId())->where('is_default', true)->value('id'); }
    public function waste(RestaurantStockService $service): void
    {
        $this->validate(['productId'=>'required|integer','warehouseId'=>'required|integer','quantity'=>'required|numeric|min:0.001','reason'=>'required|string|max:500']);
        try {
            $service->waste($this->productId, $this->quantity, $this->warehouseId, $this->reason, activeTenantId(), auth()->id());
            $this->showWaste = false; $this->reset(['productId','reason']); $this->quantity = 1;
            $this->dispatch('notify', type:'success', message:'Desperdício registado.');
        } catch (\Throwable $e) { $this->dispatch('notify', type:'error', message:$e->getMessage()); }
    }

    public function render()
    {
        $tenantId = activeTenantId();
        return view('livewire.restaurant.stock-waste-management', [
            'stocks'=>Stock::with(['product','warehouse'])->where('tenant_id',$tenantId)->orderBy('available_quantity')->get(),
            'wastes'=>StockMovement::with('product')->where('tenant_id',$tenantId)->where('reference_type','restaurant_waste')->latest()->limit(50)->get(),
            'productionWastes'=>Waste::with(['order','orderItem','user'])->where('tenant_id',$tenantId)->latest()->limit(50)->get(),
            'products'=>Product::where('tenant_id',$tenantId)->where('manage_stock',true)->where('is_active',true)->orderBy('name')->get(),
            'warehouses'=>Warehouse::where('tenant_id',$tenantId)->where('is_active',true)->get(),
        ]);
    }
}

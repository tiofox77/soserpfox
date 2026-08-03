<?php

namespace App\Livewire\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\DiningTable;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantCheckoutService;
use App\Models\Client;
use App\Models\Treasury\PaymentMethod;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Comandas - Restaurante')]
class OrderManagement extends Component
{
    use WithPagination;

    #[Url]
    public ?int $order = null;
    public string $search = '';
    public string $productSearch = '';
    public float $quantity = 1;
    public ?string $itemNotes = null;
    public ?int $selectedProductId = null;
    public bool $showCheckout = false;
    public string $documentType = 'FR';
    public ?int $clientId = null;
    public ?int $paymentMethodId = null;
    public string $checkoutKey = '';
    public ?string $invoiceResult = null;
    public array $billItemIds = [];
    public ?int $secondPaymentMethodId = null;
    public float $secondPaymentAmount = 0;
    public ?int $targetTableId = null;
    public ?int $targetOrderId = null;
    public string $voidReason = '';

    public function selectOrder(int $id): void
    {
        Order::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->order = $id;
    }

    public function addItem(RestaurantOrderService $service): void
    {
        $this->validate([
            'order' => ['required', 'integer'],
            'selectedProductId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'itemNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
        $service->addItem(
            $order,
            $this->selectedProductId,
            $this->quantity,
            $this->itemNotes,
            activeTenantId(),
            auth()->id()
        );

        $this->reset(['selectedProductId', 'productSearch', 'itemNotes']);
        $this->quantity = 1;
        $this->dispatch('notify', type: 'success', message: 'Artigo adicionado à comanda.');
    }

    public function removeItem(int $itemId, RestaurantOrderService $service): void
    {
        $item = OrderItem::where('tenant_id', activeTenantId())->findOrFail($itemId);
        $service->removeItem($item, activeTenantId(), auth()->id(), 'Removido antes da confirmação');
        $this->dispatch('notify', type: 'success', message: 'Artigo removido.');
    }

    public function confirmOrder(RestaurantOrderService $service): void
    {
        $order = Order::where('tenant_id', activeTenantId())->with('items')->findOrFail($this->order);
        $service->confirm($order, activeTenantId(), auth()->id());
        $this->dispatch('notify', type: 'success', message: 'Comanda enviada para preparação.');
    }

    public function openCheckout(): void
    {
        $selected = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
        if (!in_array($selected->status, ['ready', 'served', 'partially_billed'], true)) {
            $this->dispatch('notify', type: 'error', message: 'A comanda deve estar pronta ou servida antes de faturar.');
            return;
        }
        $this->checkoutKey = (string) Str::uuid();
        $this->paymentMethodId = PaymentMethod::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->clientId = $selected->client_id;
        $this->showCheckout = true;
        $this->billItemIds = $selected->items()->whereColumn('billed_quantity','<','quantity')->pluck('id')->map(fn($id)=>(string)$id)->all();
    }

    public function checkout(RestaurantCheckoutService $service): void
    {
        $this->validate([
            'documentType' => ['required', 'in:FR,FT'],
            'clientId' => ['nullable', 'integer'],
            'paymentMethodId' => [$this->documentType === 'FR' ? 'required' : 'nullable', 'integer'],
            'checkoutKey' => ['required', 'uuid'],
            'billItemIds' => ['required', 'array', 'min:1'],
            'secondPaymentAmount' => ['numeric', 'min:0', 'max:'.$this->checkoutTotal],
        ]);
        try {
            $order = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
            $invoice = $service->checkout($order, [
                'document_type' => $this->documentType, 'client_id' => $this->clientId,
                'payment_method_id' => $this->paymentMethodId, 'idempotency_key' => $this->checkoutKey, 'item_ids'=>$this->billItemIds,
                'payments' => $this->documentType==='FR' ? array_values(array_filter([
                    ['payment_method_id'=>$this->paymentMethodId,'amount'=>$this->checkoutTotal-$this->secondPaymentAmount],
                    $this->secondPaymentMethodId&&$this->secondPaymentAmount>0?['payment_method_id'=>$this->secondPaymentMethodId,'amount'=>$this->secondPaymentAmount]:null,
                ])) : null,
            ], activeTenantId(), auth()->id());
            $this->invoiceResult = $invoice->invoice_number;
            $this->showCheckout = false;
            $this->dispatch('notify', type: 'success', message: "Documento {$invoice->invoice_number} emitido com sucesso.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }
    public function getCheckoutTotalProperty(): float {if(!$this->order||empty($this->billItemIds))return 0;return (float)OrderItem::where('tenant_id',activeTenantId())->where('order_id',$this->order)->whereIn('id',$this->billItemIds)->sum('line_total');}

    public function releaseTable(RestaurantOrderService $service): void
    {
        try {
            $order = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
            $service->releaseTable($order, activeTenantId(), auth()->id());
            $this->dispatch('notify', type: 'success', message: 'Mesa limpa e novamente disponível.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function transferTable(RestaurantOrderService $service): void
    {
        $this->validate(['targetTableId' => ['required', 'integer']]);
        try {
            $selected = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
            $service->transfer($selected, $this->targetTableId, activeTenantId(), auth()->id());
            $this->targetTableId = null;
            $this->dispatch('notify', type: 'success', message: 'Comanda transferida para a nova mesa.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function mergeOrder(RestaurantOrderService $service): void
    {
        $this->validate(['targetOrderId' => ['required', 'integer']]);
        try {
            $source = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
            $target = Order::where('tenant_id', activeTenantId())->findOrFail($this->targetOrderId);
            $merged = $service->merge($source, $target, activeTenantId(), auth()->id());
            $this->order = $merged->id;
            $this->targetOrderId = null;
            $this->dispatch('notify', type: 'success', message: 'Comandas juntadas com sucesso.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function voidProducedItem(int $itemId, RestaurantOrderService $service): void
    {
        $this->validate(['voidReason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            $item = OrderItem::where('tenant_id', activeTenantId())->findOrFail($itemId);
            $service->voidProducedItem($item, $this->voidReason, activeTenantId(), auth()->id());
            $this->voidReason = '';
            $this->dispatch('notify', type: 'success', message: 'Artigo anulado e desperdício registado.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $orders = Order::with(['table', 'waiter'])
            ->when($this->search, function ($query) {
                $term = '%' . $this->search . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('order_number', 'like', $term)
                        ->orWhereHas('table', fn ($table) => $table->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(12);

        $selectedOrder = $this->order
            ? Order::with(['items.product', 'table', 'venue', 'waiter'])->find($this->order)
            : null;

        $products = collect();
        if ($selectedOrder && mb_strlen($this->productSearch) >= 2) {
            $products = Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('name', 'like', '%' . $this->productSearch . '%')
                ->orderBy('name')
                ->limit(12)
                ->get();
        }

        $clients = Client::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')->limit(100)->get();
        $paymentMethods = PaymentMethod::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('sort_order')->get();
        $availableTables = collect();
        $mergeOrders = collect();
        if ($selectedOrder && in_array($selectedOrder->status, Order::OPEN_STATUSES, true)) {
            $availableTables = DiningTable::where('tenant_id', activeTenantId())->where('venue_id', $selectedOrder->venue_id)->where('status', 'available')->where('is_active', true)->orderBy('name')->get();
            $mergeOrders = Order::where('tenant_id', activeTenantId())->where('venue_id', $selectedOrder->venue_id)->where('id', '!=', $selectedOrder->id)->whereIn('status', Order::OPEN_STATUSES)->with('table')->latest()->get();
        }
        return view('livewire.restaurant.order-management', compact('orders', 'selectedOrder', 'products', 'clients', 'paymentMethods', 'availableTables', 'mergeOrders'));
    }
}

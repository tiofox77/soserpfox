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
use App\Models\Invoicing\PosShift;
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
    public bool $showShiftRequired = false;
    public string $documentType = 'FR';
    public ?int $clientId = null;
    public ?int $paymentMethodId = null;
    public string $checkoutKey = '';
    public ?string $invoiceResult = null;
    public ?int $invoiceResultId = null;
    public bool $showPrintModal = false;
    public array $billItemIds = [];
    public ?int $secondPaymentMethodId = null;
    public float $secondPaymentAmount = 0;
    public bool $multiPayment = false;

    // A gorjeta do fecho. Nao entra na factura — passa pela caixa e fica na
    // comanda. Ver GorjetaETaxaDeServicoTest para o porque.
    public float $tipAmount = 0;
    public array $payments = [];
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

    public function quickAddProduct(int $productId, RestaurantOrderService $service): void
    {
        $this->selectedProductId = $productId;
        $this->addItem($service);
    }

    public function removeItem(int $itemId, RestaurantOrderService $service): void
    {
        $item = OrderItem::where('tenant_id', activeTenantId())->findOrFail($itemId);
        $service->removeItem($item, activeTenantId(), auth()->id(), 'Removido antes da confirmação');
        $this->dispatch('notify', type: 'success', message: 'Artigo removido.');
    }

    public function changeItemQuantity(int $itemId, float $delta, RestaurantOrderService $service): void
    {
        try {
            $item = OrderItem::where('tenant_id', activeTenantId())->findOrFail($itemId);
            $next = round((float) $item->quantity + $delta, 3);
            if ($next <= 0) {
                $service->removeItem($item, activeTenantId(), auth()->id(), 'Quantidade reduzida a zero no POS');
            } else {
                $service->updateItemQuantity($item, $next, activeTenantId(), auth()->id());
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function confirmOrder(RestaurantOrderService $service): void
    {
        $order = Order::where('tenant_id', activeTenantId())->with('items')->findOrFail($this->order);
        $service->confirm($order, activeTenantId(), auth()->id());
        $this->dispatch('notify', type: 'success', message: 'Comanda enviada para preparação.');
    }

    public function openCheckout(): void
    {
        if (! PosShift::withoutGlobalScopes()->where('tenant_id', activeTenantId())
            ->where('user_id', auth()->id())->whereNull('deleted_at')->where('status', 'open')->exists()) {
            $this->showCheckout = false;
            $this->showShiftRequired = true;
            return;
        }
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
        $this->multiPayment = false;
        $this->payments = [];
        $this->tipAmount = 0;
        $this->secondPaymentMethodId = null;
        $this->secondPaymentAmount = 0;
    }

    public function toggleMultiPayment(): void
    {
        $this->multiPayment = ! $this->multiPayment;
        $this->payments = $this->multiPayment ? [[
            'payment_method_id' => $this->paymentMethodId,
            'amount' => round($this->checkoutTotal, 2),
        ]] : [];
    }

    public function addPayment(): void
    {
        $used = array_filter(array_column($this->payments, 'payment_method_id'));
        $next = PaymentMethod::where('tenant_id', activeTenantId())->where('is_active', true)
            ->whereNotIn('id', $used)->orderBy('sort_order')->value('id');
        if (! $next) {
            $this->dispatch('notify', type: 'error', message: 'Todos os métodos de pagamento disponíveis já foram adicionados.');
            return;
        }
        $this->payments[] = ['payment_method_id' => $next, 'amount' => max(0, $this->paymentRemaining)];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function getPaymentsTotalProperty(): float
    {
        return round(collect($this->payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0)), 2);
    }

    public function getPaymentRemainingProperty(): float
    {
        return round($this->checkoutTotal - $this->paymentsTotal, 2);
    }

    public function checkout(RestaurantCheckoutService $service): void
    {
        $this->validate([
            'documentType' => ['required', 'in:FR,FT'],
            'clientId' => ['nullable', 'integer'],
            'paymentMethodId' => [$this->documentType === 'FR' ? 'required' : 'nullable', 'integer'],
            'checkoutKey' => ['required', 'uuid'],
            'billItemIds' => ['required', 'array', 'min:1'],
            'payments' => [$this->multiPayment && $this->documentType === 'FR' ? 'required' : 'nullable', 'array'],
            'payments.*.payment_method_id' => ['nullable', 'integer'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'tipAmount' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $tenders = $this->multiPayment ? collect($this->payments)
                ->filter(fn ($p) => (float) ($p['amount'] ?? 0) > 0)
                ->values()->all() : [['payment_method_id' => $this->paymentMethodId, 'amount' => $this->checkoutTotal]];
            if ($this->documentType === 'FR' && $this->multiPayment) {
                if (count($tenders) < 2) throw new \InvalidArgumentException('Adicione pelo menos dois métodos para usar pagamento múltiplo.');
                if (abs($this->paymentRemaining) > .02) throw new \InvalidArgumentException($this->paymentRemaining > 0 ? 'Ainda falta distribuir '.number_format($this->paymentRemaining, 2, ',', '.').' Kz.' : 'Os pagamentos excedem o total em '.number_format(abs($this->paymentRemaining), 2, ',', '.').' Kz.');
                if (count(array_unique(array_column($tenders, 'payment_method_id'))) !== count($tenders)) throw new \InvalidArgumentException('Não repita o mesmo método de pagamento. Some os valores numa única linha.');
            }
            $order = Order::where('tenant_id', activeTenantId())->findOrFail($this->order);
            $invoice = $service->checkout($order, [
                'document_type' => $this->documentType, 'client_id' => $this->clientId,
                'payment_method_id' => $this->paymentMethodId, 'idempotency_key' => $this->checkoutKey, 'item_ids'=>$this->billItemIds,
                'payments' => $this->documentType==='FR' ? $tenders : null,
                'tip_amount' => $this->documentType==='FR' ? max(0, (float) $this->tipAmount) : 0,
            ], activeTenantId(), auth()->id());
            $this->invoiceResult = $invoice->invoice_number;
            $this->invoiceResultId = $invoice->id;
            $this->showCheckout = false;
            $this->showPrintModal = true;
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
        if ($selectedOrder && in_array($selectedOrder->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served'], true)) {
            $products = Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->when(trim($this->productSearch) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->productSearch) . '%'))
                ->orderBy('name')
                ->limit(24)
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
        // A vista do fecho precisa das definicoes (gorjetas ligadas?).
        $restaurantSettings = \App\Models\Restaurant\RestaurantSettings::forTenant(activeTenantId());

        return view('livewire.restaurant.order-management', compact('orders', 'selectedOrder', 'products', 'clients', 'paymentMethods', 'availableTables', 'mergeOrders', 'restaurantSettings'));
    }
}

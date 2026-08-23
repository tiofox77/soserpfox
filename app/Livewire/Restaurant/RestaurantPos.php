<?php

namespace App\Livewire\Restaurant;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\Venue;
use App\Models\Treasury\PaymentMethod;
use App\Models\Invoicing\Tax;
use App\Models\Restaurant\RestaurantSettings;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantReservationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('POS Restaurante')]
class RestaurantPos extends OrderManagement
{
    public ?int $venueId = null;
    public ?int $areaId = null;
    public ?int $categoryId = null;
    public int $guestCount = 1;
    public bool $showTables = true;
    public bool $showOrders = false;
    public bool $showReservation = false;
    public ?int $reservationTableId = null;
    public string $reservationGuestName = '';
    public string $reservationPhone = '';
    public int $reservationGuests = 2;
    public string $reservationAt = '';
    public int $reservationDuration = 120;
    public string $reservationNotes = '';
    public bool $showQuickProduct = false;
    public string $quickProductName = '';
    public float $quickProductPrice = 0;
    public ?int $quickProductCategoryId = null;
    public ?int $quickProductTaxId = null;
    public bool $quickProductManageStock = false;

    public function mount(): void
    {
        $this->venueId = Venue::where('is_active', true)->orderBy('id')->value('id');
        $this->areaId = $this->venueId
            ? Area::where('venue_id', $this->venueId)->where('is_active', true)->orderBy('sort_order')->value('id')
            : null;
        $this->reservationAt = now()->addHour()->format('Y-m-d\TH:i');
        $this->quickProductTaxId = Tax::where('tenant_id', activeTenantId())->where('is_active', true)->orderByDesc('rate')->value('id');
    }

    public function createQuickProduct(): void
    {
        $settings = RestaurantSettings::forTenant(activeTenantId());
        if ($settings->require_recipe_for_products) {
            $this->dispatch('notify', type: 'error', message: 'A configuração exige ficha técnica. Crie o prato em Produtos e complete os ingredientes em Fichas Técnicas.');
            return;
        }
        $data = $this->validate([
            'quickProductName' => ['required', 'string', 'max:255'],
            'quickProductPrice' => ['required', 'numeric', 'min:0'],
            'quickProductCategoryId' => ['nullable', 'integer'],
            'quickProductTaxId' => ['required', 'integer'],
            'quickProductManageStock' => ['boolean'],
        ]);
        $category = $data['quickProductCategoryId']
            ? Category::where('tenant_id', activeTenantId())->findOrFail($data['quickProductCategoryId']) : null;
        $tax = Tax::where('tenant_id', activeTenantId())->where('is_active', true)->findOrFail($data['quickProductTaxId']);
        $product = Product::create([
            'tenant_id' => activeTenantId(), 'category_id' => $category?->id,
            'type' => 'produto', 'name' => trim($data['quickProductName']),
            'price' => $data['quickProductPrice'], 'cost' => 0, 'unit' => 'UN',
            'tax_type' => 'iva', 'tax_rate_id' => $tax->id,
            'manage_stock' => $data['quickProductManageStock'], 'is_active' => true,
        ]);
        $this->categoryId = $category?->id;
        $this->reset(['showQuickProduct', 'quickProductName', 'quickProductPrice', 'quickProductCategoryId', 'quickProductManageStock']);
        $this->dispatch('notify', type: 'success', message: "Prato {$product->name} criado e disponível no POS.");
    }

    public function tableStatus(int $tableId, string $status, RestaurantOrderService $service): void
    {
        try {
            $table = DiningTable::where('tenant_id', activeTenantId())->where('venue_id', $this->venueId)->findOrFail($tableId);
            $service->changeTableStatus($table, $status, activeTenantId());
            $this->dispatch('notify', type: 'success', message: 'Estado da mesa atualizado para '.$table->fresh()->status_label.'.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function prepareReservation(int $tableId): void
    {
        $table = DiningTable::where('tenant_id', activeTenantId())->where('venue_id', $this->venueId)->findOrFail($tableId);
        if ($table->activeOrder()->exists() || in_array($table->status, ['blocked', 'cleaning'], true)) {
            $this->dispatch('notify', type: 'error', message: 'Esta mesa não pode ser reservada neste momento.');
            return;
        }
        $this->reservationTableId = $table->id;
        $this->reservationGuests = min(2, max(1, (int) $table->capacity));
        $this->showReservation = true;
    }

    public function saveReservation(RestaurantReservationService $service): void
    {
        $data = $this->validate([
            'reservationTableId' => ['required', 'integer'],
            'reservationGuestName' => ['required', 'string', 'max:150'],
            'reservationPhone' => ['nullable', 'string', 'max:30'],
            'reservationGuests' => ['required', 'integer', 'min:1', 'max:100'],
            'reservationAt' => ['required', 'date', 'after_or_equal:now'],
            'reservationDuration' => ['required', 'integer', 'min:30', 'max:720'],
            'reservationNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $reservation = $service->save([
                'venue_id' => $this->venueId,
                'table_id' => $data['reservationTableId'],
                'guest_name' => trim($data['reservationGuestName']),
                'phone' => trim($data['reservationPhone']),
                'email' => null,
                'guest_count' => $data['reservationGuests'],
                'reserved_at' => $data['reservationAt'],
                'duration_minutes' => $data['reservationDuration'],
                'notes' => $data['reservationNotes'],
            ], activeTenantId(), auth()->id());
            $service->changeStatus($reservation, 'confirmed', activeTenantId());
            $this->reset(['showReservation', 'reservationTableId', 'reservationGuestName', 'reservationPhone', 'reservationNotes']);
            $this->reservationAt = now()->addHour()->format('Y-m-d\TH:i');
            $this->dispatch('notify', type: 'success', message: 'Mesa reservada com sucesso.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function updatedVenueId(): void
    {
        $this->areaId = Area::where('venue_id', $this->venueId)->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->order = null;
    }

    public function chooseTable(int $tableId, RestaurantOrderService $service): void
    {
        $table = DiningTable::with('activeOrder')->where('tenant_id', activeTenantId())
            ->where('venue_id', $this->venueId)->where('is_active', true)->findOrFail($tableId);

        if ($table->activeOrder) {
            $this->order = $table->activeOrder->id;
            $this->showTables = false;
            return;
        }
        if (in_array($table->status, ['blocked', 'cleaning'], true)) {
            $this->dispatch('notify', type: 'warning', message: 'Esta mesa não está disponível para novo atendimento.');
            return;
        }

        try {
            $opened = $service->open([
                'venue_id' => $this->venueId, 'table_id' => $table->id,
                'guest_count' => $this->guestCount,
            ], activeTenantId(), auth()->id());
            $this->order = $opened->id;
            $this->showTables = false;
            $this->dispatch('notify', type: 'success', message: "Comanda aberta em {$table->name}.");
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function openCounterOrder(RestaurantOrderService $service): void
    {
        if (!$this->venueId) return;
        $opened = $service->open([
            'venue_id' => $this->venueId, 'channel' => 'counter',
            'guest_count' => 1,
        ], activeTenantId(), auth()->id());
        $this->order = $opened->id;
        $this->showTables = false;
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $restaurantSettings = RestaurantSettings::forTenant($tenantId);
        $venues = Venue::where('is_active', true)->orderBy('name')->get();
        $areas = Area::where('venue_id', $this->venueId)->where('is_active', true)->orderBy('sort_order')->get();
        $tables = DiningTable::with(['area', 'activeOrder'])
            ->where('venue_id', $this->venueId)
            ->when($this->areaId, fn ($q) => $q->where('area_id', $this->areaId))
            ->where('is_active', true)->orderBy('code')->get();
        $orders = Order::with(['table', 'waiter'])->whereIn('status', Order::OPEN_STATUSES)
            ->when($this->venueId, fn ($q) => $q->where('venue_id', $this->venueId))
            ->latest()->get();
        $selectedOrder = $this->order
            ? Order::with(['items.product', 'table', 'venue', 'waiter'])->find($this->order)
            : null;
        $categories = Category::where('tenant_id', $tenantId)->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))->orderBy('order')->get();
        $products = Product::where('tenant_id', $tenantId)->where('is_active', true)
            ->with('category:id,name') // sem isto, a grelha do POS fazia 1 query por produto (N+1)
            ->when($restaurantSettings->require_recipe_for_products, fn ($q) => $q->whereExists(fn ($recipe) => $recipe
                ->selectRaw('1')->from('restaurant_recipes')
                ->whereColumn('restaurant_recipes.product_id', 'invoicing_products.id')
                ->where('restaurant_recipes.tenant_id', $tenantId)->where('restaurant_recipes.is_active', true)))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when(trim($this->productSearch) !== '', function ($q) {
                $term = '%' . trim($this->productSearch) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('barcode', 'like', $term));
            })->orderBy('name')->limit(60)->get();
        $clients = Client::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->limit(100)->get();
        $paymentMethods = PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->get();
        // Só é preciso no modal de produto rápido — não em cada render do POS.
        $taxes = $this->showQuickProduct
            ? Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('rate')->get()
            : collect();
        $availableTables = collect();
        $mergeOrders = collect();
        if ($selectedOrder && in_array($selectedOrder->status, Order::OPEN_STATUSES, true)) {
            $availableTables = DiningTable::where('venue_id', $selectedOrder->venue_id)->where('status', 'available')->where('is_active', true)->orderBy('name')->get();
            $mergeOrders = Order::where('venue_id', $selectedOrder->venue_id)->where('id', '!=', $selectedOrder->id)
                ->whereIn('status', Order::OPEN_STATUSES)->with('table')->latest()->get();
        }

        return view('livewire.restaurant.restaurant-pos', compact(
            'venues', 'areas', 'tables', 'orders', 'selectedOrder', 'categories', 'products',
            'clients', 'paymentMethods', 'availableTables', 'mergeOrders', 'restaurantSettings', 'taxes'
        ));
    }
}

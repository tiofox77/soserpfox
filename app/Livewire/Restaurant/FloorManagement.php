<?php

namespace App\Livewire\Restaurant;

use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\Venue;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sala e Mesas - Restaurante')]
class FloorManagement extends Component
{
    public ?int $venueId = null;
    public ?int $areaId = null;
    public bool $showTableForm = false;
    public bool $showOpenOrder = false;
    public ?int $selectedTableId = null;
    public string $tableCode = '';
    public string $tableName = '';
    public int $capacity = 4;
    public int $guestCount = 1;
    public ?string $notes = null;

    public function mount(): void
    {
        $venue = Venue::where('is_active', true)->orderBy('id')->first();
        $this->venueId = $venue?->id;
        $this->areaId = $venue?->areas()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function updatedVenueId(): void
    {
        $this->areaId = Area::where('venue_id', $this->venueId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');
    }

    public function createTable(): void
    {
        $tenantId = activeTenantId();
        $validated = $this->validate([
            'venueId' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'areaId' => ['nullable', Rule::exists('restaurant_areas', 'id')->where('tenant_id', $tenantId)],
            'tableCode' => [
                'required', 'string', 'max:30',
                Rule::unique('restaurant_tables', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('venue_id', $this->venueId)),
            ],
            'tableName' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        DiningTable::create([
            'tenant_id' => $tenantId,
            'venue_id' => $validated['venueId'],
            'area_id' => $validated['areaId'],
            'code' => mb_strtoupper(trim($validated['tableCode'])),
            'name' => trim($validated['tableName']),
            'capacity' => $validated['capacity'],
        ]);

        $this->reset(['showTableForm', 'tableCode', 'tableName']);
        $this->capacity = 4;
        $this->dispatch('notify', type: 'success', message: 'Mesa criada com sucesso.');
    }

    public function prepareOpenOrder(int $tableId): void
    {
        $table = DiningTable::where('is_active', true)->findOrFail($tableId);
        if ($table->activeOrder()->exists()) {
            $this->redirectRoute('restaurant.orders', ['order' => $table->activeOrder()->value('id')]);
            return;
        }
        if ($table->status === 'cleaning') {
            $this->dispatch('notify', type: 'warning', message: 'A mesa aguarda limpeza. Marque-a como limpa antes de abrir novo atendimento.');
            return;
        }
        if ($table->status === 'blocked') {
            $this->dispatch('notify', type: 'error', message: 'A mesa está bloqueada e não pode receber atendimento.');
            return;
        }
        $this->selectedTableId = $table->id;
        $this->guestCount = 1;
        $this->showOpenOrder = true;
    }

    public function openOrder(RestaurantOrderService $service): void
    {
        $this->validate([
            'selectedTableId' => ['required', 'integer'],
            'guestCount' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order = $service->open([
                'venue_id' => $this->venueId,
                'table_id' => $this->selectedTableId,
                'guest_count' => $this->guestCount,
                'notes' => $this->notes,
            ], activeTenantId(), auth()->id());

            $this->redirectRoute('restaurant.orders', ['order' => $order->id]);
        } catch (\InvalidArgumentException $e) {
            $this->showOpenOrder = false;
            $this->selectedTableId = null;
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Aceitar um pedido que o cliente fez na carta online.
     *
     * É AQUI que o pedido vira comanda, e não antes: a comanda exige turno
     * aberto, e o turno é o de quem está a aceitar. O cliente sentado à mesa
     * não tem turno nenhum — deixá-lo abrir comandas obrigava a furar a regra
     * que impede vender com a caixa fechada, e num sítio aberto ao público.
     *
     * OS PREÇOS SÃO OS DE AGORA. O pedido guardou o que o cliente VIU, mas
     * quem manda é o catálogo neste momento: uma carta pública pode estar
     * numa página aberta há uma hora, e um preço congelado por aí seria um
     * desconto que ninguém autorizou.
     */
    public function aceitarPedidoDoMenu(int $pedidoId, RestaurantOrderService $service): void
    {
        try {
            $pedido = \App\Models\Restaurant\MenuOrder::where('tenant_id', activeTenantId())
                ->where('status', 'pending')
                ->findOrFail($pedidoId);

            $comanda = $service->open([
                'venue_id'    => $this->venueId,
                'table_id'    => $pedido->table_id,
                'guest_count' => 1,
                'notes'       => trim(__('Pedido pela carta online') . ' · ' . (string) $pedido->notes, ' ·'),
            ], activeTenantId(), auth()->id());

            foreach ($pedido->items as $linha) {
                $service->addItem(
                    $comanda,
                    (int) $linha['product_id'],
                    (float) $linha['quantity'],
                    null,
                    activeTenantId(),
                    auth()->id()
                );
            }

            $pedido->update([
                'status'     => 'accepted',
                'order_id'   => $comanda->id,
                'handled_by' => auth()->id(),
                'handled_at' => now(),
            ]);

            $this->redirectRoute('restaurant.orders', ['order' => $comanda->id]);
        } catch (\Throwable $e) {
            // A mesa pode ter sido ocupada entretanto, ou o turno pode estar
            // fechado. Dizer porquê — o empregado tem o cliente à frente.
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /** Descartar um pedido: um engano, uma brincadeira, ou já foi resolvido à mão. */
    public function descartarPedidoDoMenu(int $pedidoId): void
    {
        \App\Models\Restaurant\MenuOrder::where('tenant_id', activeTenantId())
            ->where('status', 'pending')
            ->whereKey($pedidoId)
            ->update([
                'status'     => 'dismissed',
                'handled_by' => auth()->id(),
                'handled_at' => now(),
            ]);

        $this->dispatch('notify', type: 'success', message: __('Pedido descartado.'));
    }

    public function markTableClean(int $tableId, RestaurantOrderService $service): void
    {
        try {
            $table = DiningTable::where('is_active', true)->findOrFail($tableId);
            if ($table->status !== 'cleaning') {
                throw new \InvalidArgumentException('A mesa já não está em limpeza. Atualize a sala.');
            }

            $lastBilled = Order::where('table_id', $table->id)->where('status', 'billed')->latest('closed_at')->first();
            if ($lastBilled) {
                $service->releaseTable($lastBilled, activeTenantId(), auth()->id());
            } elseif (!$table->activeOrder()->exists()) {
                // Recupera apenas um estado órfão antigo. Nunca liberta uma mesa
                // que ainda tenha comanda aberta.
                $table->update(['status' => 'available']);
                \Log::warning('Estado de limpeza órfão corrigido no mapa do restaurante.', [
                    'tenant_id' => activeTenantId(), 'table_id' => $table->id, 'user_id' => auth()->id(),
                ]);
            } else {
                throw new \InvalidArgumentException('A mesa ainda possui uma comanda pendente.');
            }

            $this->dispatch('notify', type: 'success', message: 'Mesa limpa e disponível para novo atendimento.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $venues = Venue::where('is_active', true)->orderBy('name')->get();
        $areas = Area::where('venue_id', $this->venueId)->where('is_active', true)->orderBy('sort_order')->get();
        $tables = DiningTable::with(['area', 'activeOrder'])
            ->where('venue_id', $this->venueId)
            ->when($this->areaId, fn ($query) => $query->where('area_id', $this->areaId))
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Os pedidos que os clientes fizeram na carta online e ainda ninguém
        // atendeu. Aparecem aqui porque é aqui que o empregado olha.
        $pedidosDoMenu = \App\Models\Restaurant\MenuOrder::where('tenant_id', activeTenantId())
            ->aEspera()
            ->with('mesa')
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.restaurant.floor-management', compact('venues', 'areas', 'tables', 'pedidosDoMenu'));
    }
}

<?php

namespace App\Livewire\Restaurant;

use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Restaurant\VenueLimitRequest;
use App\Models\Tenant;
use App\Services\Restaurant\RestaurantVenueLimitService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configurações - Restaurante')]
class SettingsManagement extends Component
{
    public ?int $warehouseId = null, $clientId = null, $selectedVenueId = null;
    public bool $requireOpenShift = true, $useKitchen = true, $requireRecipes = false, $reserveStock = true, $consumeStock = true, $allowNegative = false;

    // A taxa de servico e receita da casa (vai a factura); a gorjeta e do
    // pessoal (so passa pela caixa). Ver a migracao gorjeta_e_taxa_de_servico.
    public float $serviceChargePercent = 0;

    public bool $tipsEnabled = true;

    public bool $kitchenAutoPrint = false;
    public string $venueCode = '', $venueName = '', $areaName = '', $stationCode = '', $stationName = '';
    public ?int $venueWarehouseId = null;
    public ?string $editType = null;
    public ?int $editId = null;
    public string $editName = '', $editCode = '';
    public int $editCapacity = 4;
    public bool $showVenueRequest = false;
    public int $requestedVenueLimit = 2;
    public string $venueRequestReason = '';

    // ── Menu online ───────────────────────────────────────────────────────
    // A carta pública. Nasce desligada: um restaurante que não pediu isto não
    // pode acordar com os seus preços numa página aberta ao mundo.
    public bool $menuAtivo = false;
    public bool $menuWhatsapp = true;
    public bool $menuPedidos = false;
    public bool $menuMostrarPrecos = true;
    public string $menuSlug = '';
    public string $menuNumeroWhatsapp = '';
    public string $menuTitulo = '';
    public string $menuDescricao = '';
    public string $menuCor = '#ea580c';

    /** O ecrã de imprimir os QR das mesas. */
    public bool $mostrarQrDasMesas = false;

    public function mount(): void
    {
        $settings = RestaurantSettings::forTenant(activeTenantId());
        $this->warehouseId = $settings->default_warehouse_id;
        $this->clientId = $settings->default_client_id;
        $this->requireOpenShift = true;
        $this->useKitchen = (bool) ($settings->use_kitchen_workflow ?? true);
        $this->requireRecipes = (bool) ($settings->require_recipe_for_products ?? false);
        $this->reserveStock = (bool) ($settings->reserve_stock_on_confirm ?? true);
        $this->consumeStock = (bool) ($settings->consume_stock_on_kitchen ?? true);
        $this->allowNegative = (bool) ($settings->allow_negative_stock ?? false);
        $this->serviceChargePercent = (float) ($settings->service_charge_percent ?? 0);
        $this->tipsEnabled = (bool) ($settings->tips_enabled ?? true);
        $this->kitchenAutoPrint = (bool) ($settings->kitchen_auto_print ?? false);
        $this->selectedVenueId = Venue::where('tenant_id', activeTenantId())->orderBy('name')->value('id');

        $this->menuAtivo = (bool) $settings->online_menu_enabled;
        $this->menuWhatsapp = (bool) ($settings->menu_whatsapp_enabled ?? true);
        $this->menuPedidos = (bool) $settings->menu_orders_enabled;
        $this->menuMostrarPrecos = (bool) ($settings->menu_show_prices ?? true);
        $this->menuSlug = (string) ($settings->menu_slug ?? '');
        $this->menuNumeroWhatsapp = (string) ($settings->menu_whatsapp_number ?? '');
        $this->menuTitulo = (string) ($settings->menu_title ?? '');
        $this->menuDescricao = (string) ($settings->menu_description ?? '');
        $this->menuCor = (string) ($settings->menu_primary_color ?? '#ea580c');

        // Um endereço sugerido a partir do nome da empresa, para quem só quer
        // ligar o interruptor e acabar. Continua editável.
        if ($this->menuSlug === '') {
            $this->menuSlug = \Illuminate\Support\Str::slug(
                (string) Tenant::find(activeTenantId())?->name
            );
        }
    }

    /**
     * Guarda a carta pública.
     *
     * Separado do `save()` geral de propósito: publicar preços ao mundo é uma
     * decisão diferente de escolher um armazém por omissão, e misturá-las fazia
     * um clique numa caixa qualquer publicar a carta sem querer.
     */
    public function guardarMenu(): void
    {
        $dados = $this->validate([
            // O slug é o ENDEREÇO PÚBLICO e é único na tabela inteira, não por
            // empresa: dois restaurantes com o mesmo endereço é um a servir a
            // carta do outro.
            'menuSlug' => [
                $this->menuAtivo ? 'required' : 'nullable',
                'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('restaurant_settings', 'menu_slug')
                    ->ignore(activeTenantId(), 'tenant_id'),
            ],
            'menuNumeroWhatsapp' => ['nullable', 'string', 'max:30'],
            'menuTitulo'         => ['nullable', 'string', 'max:120'],
            'menuDescricao'      => ['nullable', 'string', 'max:2000'],
            'menuCor'            => ['nullable', 'string', 'max:20'],
        ], [
            'menuSlug.required' => __('Dê um endereço à carta antes de a publicar.'),
            'menuSlug.regex'    => __('O endereço só pode ter letras minúsculas, números e hífens.'),
            'menuSlug.unique'   => __('Esse endereço já está a ser usado por outro restaurante.'),
        ]);

        // O WhatsApp sem número não serve para nada, e um botão que não leva a
        // lado nenhum é pior do que botão nenhum.
        if ($this->menuWhatsapp && trim($this->menuNumeroWhatsapp) === '') {
            $this->addError('menuNumeroWhatsapp', __('Indique o número que vai receber os pedidos.'));

            return;
        }

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', activeTenantId())->update([
            'menu_slug'             => $dados['menuSlug'] ?: null,
            'online_menu_enabled'   => $this->menuAtivo,
            'menu_whatsapp_enabled' => $this->menuWhatsapp,
            'menu_orders_enabled'   => $this->menuPedidos,
            'menu_show_prices'      => $this->menuMostrarPrecos,
            'menu_whatsapp_number'  => $dados['menuNumeroWhatsapp'] ?: null,
            'menu_title'            => $dados['menuTitulo'] ?: null,
            'menu_description'      => $dados['menuDescricao'] ?: null,
            'menu_primary_color'    => $dados['menuCor'] ?: null,
        ]);

        $this->dispatch('notify', type: 'success', message: __('Carta guardada.'));
    }

    /** O endereço público da carta, para se poder copiar e testar. */
    public function getUrlDoMenuProperty(): ?string
    {
        return $this->menuSlug ? url('/menu/' . $this->menuSlug) : null;
    }

    public function save(): void
    {
        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', activeTenantId())->update([
            'default_warehouse_id' => $this->warehouseId, 'default_client_id' => $this->clientId,
            'require_open_shift' => true, 'reserve_stock_on_confirm' => $this->reserveStock,
            'use_kitchen_workflow' => $this->useKitchen, 'require_recipe_for_products' => $this->requireRecipes,
            'consume_stock_on_kitchen' => $this->consumeStock, 'allow_negative_stock' => $this->allowNegative,
            'service_charge_percent' => max(0, min(100, $this->serviceChargePercent)),
            'tips_enabled' => $this->tipsEnabled,
            'kitchen_auto_print' => $this->kitchenAutoPrint,
        ]);
        $this->dispatch('notify', type: 'success', message: 'Configurações guardadas.');
    }

    public function createVenue(RestaurantVenueLimitService $service): void
    {
        $data = $this->validate([
            'venueCode' => ['required', 'max:30', Rule::unique('restaurant_venues', 'code')->where('tenant_id', activeTenantId())],
            'venueName' => ['required', 'max:120'], 'venueWarehouseId' => ['nullable', 'integer'],
        ]);
        $venue = $service->createVenue(activeTenantId(), ['code' => strtoupper($data['venueCode']), 'name' => $data['venueName'], 'warehouse_id' => $data['venueWarehouseId'], 'is_active' => true]);
        $this->selectedVenueId = $venue->id;
        $this->reset(['venueCode', 'venueName', 'venueWarehouseId']);
        $this->dispatch('notify', type: 'success', message: 'Estabelecimento criado.');
    }

    public function openVenueRequest(): void
    {
        $limit = max(1, (int) Tenant::findOrFail(activeTenantId())->restaurant_venue_limit);
        $this->requestedVenueLimit = $limit + 1;
        $this->venueRequestReason = '';
        $this->showVenueRequest = true;
    }

    public function requestVenueIncrease(RestaurantVenueLimitService $service): void
    {
        $this->validate([
            'requestedVenueLimit' => ['required', 'integer', 'min:2', 'max:20'],
            'venueRequestReason' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->requestIncrease(activeTenantId(), auth()->id(), $this->requestedVenueLimit, $this->venueRequestReason);
        $this->showVenueRequest = false;
        $this->dispatch('notify', type: 'success', message: 'Pedido enviado ao administrador. Será avisado após a análise.');
    }

    public function createArea(): void
    {
        $this->validate(['selectedVenueId' => ['required', 'integer'], 'areaName' => ['required', 'max:100']]);
        $venue = Venue::where('tenant_id', activeTenantId())->findOrFail($this->selectedVenueId);
        Area::create(['tenant_id' => activeTenantId(), 'venue_id' => $venue->id, 'name' => $this->areaName, 'sort_order' => $venue->areas()->count(), 'is_active' => true]);
        $this->areaName = '';
        $this->dispatch('notify', type: 'success', message: 'Zona criada.');
    }

    public function createStation(): void
    {
        $this->validate(['selectedVenueId' => ['required', 'integer'], 'stationCode' => ['required', 'max:30'], 'stationName' => ['required', 'max:100']]);
        $venue = Venue::where('tenant_id', activeTenantId())->findOrFail($this->selectedVenueId);
        KitchenStation::create(['tenant_id' => activeTenantId(), 'venue_id' => $venue->id, 'code' => strtoupper($this->stationCode), 'name' => $this->stationName, 'sort_order' => KitchenStation::where('venue_id', $venue->id)->count(), 'is_active' => true]);
        $this->reset(['stationCode', 'stationName']);
        $this->dispatch('notify', type: 'success', message: 'Estação criada.');
    }

    public function toggle(string $type, int $id): void
    {
        $model = match ($type) {'venue' => Venue::class, 'area' => Area::class, 'table' => DiningTable::class, 'station' => KitchenStation::class, default => abort(404)};
        $record = $model::where('tenant_id', activeTenantId())->findOrFail($id);
        $record->update(['is_active' => !$record->is_active]);
    }

    public function startEdit(string $type, int $id): void
    {
        $record = $this->findRecord($type, $id);
        $this->editType = $type;
        $this->editId = $record->id;
        $this->editName = $record->name;
        $this->editCode = (string) ($record->code ?? '');
        $this->editCapacity = (int) ($record->capacity ?? 4);
    }

    public function saveEdit(): void
    {
        $this->validate(['editType' => ['required'], 'editId' => ['required', 'integer'], 'editName' => ['required', 'string', 'max:120'], 'editCode' => ['nullable', 'string', 'max:30'], 'editCapacity' => ['integer', 'min:1', 'max:100']]);
        $record = $this->findRecord($this->editType, $this->editId);
        $data = ['name' => $this->editName];
        if (in_array($this->editType, ['venue', 'table', 'station'], true)) $data['code'] = strtoupper($this->editCode);
        if ($this->editType === 'table') $data['capacity'] = $this->editCapacity;
        $record->update($data);
        $this->cancelEdit();
        $this->dispatch('notify', type: 'success', message: 'Registo atualizado.');
    }

    public function deleteRecord(string $type, int $id): void
    {
        try {
            $record = $this->findRecord($type, $id);
            if ($type === 'venue' && ($record->orders()->exists() || $record->tables()->exists())) throw new \RuntimeException('O estabelecimento possui mesas ou comandas e não pode ser eliminado. Desative-o.');
            if ($type === 'area' && $record->tables()->exists()) throw new \RuntimeException('A zona possui mesas e não pode ser eliminada.');
            if ($type === 'table' && $record->orders()->exists()) throw new \RuntimeException('A mesa possui histórico e não pode ser eliminada. Desative-a.');
            if ($type === 'station' && $record->tickets()->exists()) throw new \RuntimeException('A estação possui tickets e não pode ser eliminada. Desative-a.');
            $record->delete();
            $this->dispatch('notify', type: 'success', message: 'Registo eliminado.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cancelEdit(): void
    {
        $this->reset(['editType', 'editId', 'editName', 'editCode']);
        $this->editCapacity = 4;
    }

    private function findRecord(string $type, int $id)
    {
        $model = match ($type) {'venue' => Venue::class, 'area' => Area::class, 'table' => DiningTable::class, 'station' => KitchenStation::class, default => abort(404)};
        return $model::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function render()
    {
        $venues = Venue::where('tenant_id', activeTenantId())->with(['areas.tables'])->orderBy('name')->get();
        $tenant = Tenant::findOrFail(activeTenantId());
        return view('livewire.restaurant.settings-management', [
            'venues' => $venues,
            'venueLimit' => max(1, (int) $tenant->restaurant_venue_limit),
            'pendingVenueRequest' => VenueLimitRequest::withoutGlobalScopes()->where('tenant_id', activeTenantId())->where('status', 'pending')->latest()->first(),
            'stations' => KitchenStation::where('tenant_id', activeTenantId())->with('venue')->orderBy('sort_order')->get(),
            'warehouses' => Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->get(),
            'clients' => Client::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}

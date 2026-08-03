<?php

namespace App\Livewire\Restaurant;

use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configurações - Restaurante')]
class SettingsManagement extends Component
{
    public ?int $warehouseId = null, $clientId = null, $selectedVenueId = null;
    public bool $requireOpenShift = true, $reserveStock = true, $consumeStock = true, $allowNegative = false;
    public string $venueCode = '', $venueName = '', $areaName = '', $stationCode = '', $stationName = '';
    public ?int $venueWarehouseId = null;
    public ?string $editType = null;
    public ?int $editId = null;
    public string $editName = '', $editCode = '';
    public int $editCapacity = 4;

    public function mount(): void
    {
        $settings = RestaurantSettings::forTenant(activeTenantId());
        $this->warehouseId = $settings->default_warehouse_id;
        $this->clientId = $settings->default_client_id;
        $this->requireOpenShift = (bool) ($settings->require_open_shift ?? true);
        $this->reserveStock = (bool) ($settings->reserve_stock_on_confirm ?? true);
        $this->consumeStock = (bool) ($settings->consume_stock_on_kitchen ?? true);
        $this->allowNegative = (bool) ($settings->allow_negative_stock ?? false);
        $this->selectedVenueId = Venue::where('tenant_id', activeTenantId())->orderBy('name')->value('id');
    }

    public function save(): void
    {
        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', activeTenantId())->update([
            'default_warehouse_id' => $this->warehouseId, 'default_client_id' => $this->clientId,
            'require_open_shift' => $this->requireOpenShift, 'reserve_stock_on_confirm' => $this->reserveStock,
            'consume_stock_on_kitchen' => $this->consumeStock, 'allow_negative_stock' => $this->allowNegative,
        ]);
        $this->dispatch('notify', type: 'success', message: 'Configurações guardadas.');
    }

    public function createVenue(): void
    {
        $data = $this->validate([
            'venueCode' => ['required', 'max:30', Rule::unique('restaurant_venues', 'code')->where('tenant_id', activeTenantId())],
            'venueName' => ['required', 'max:120'], 'venueWarehouseId' => ['nullable', 'integer'],
        ]);
        $venue = Venue::create(['tenant_id' => activeTenantId(), 'code' => strtoupper($data['venueCode']), 'name' => $data['venueName'], 'warehouse_id' => $data['venueWarehouseId'], 'is_active' => true]);
        $this->selectedVenueId = $venue->id;
        $this->reset(['venueCode', 'venueName', 'venueWarehouseId']);
        $this->dispatch('notify', type: 'success', message: 'Estabelecimento criado.');
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
        return view('livewire.restaurant.settings-management', [
            'venues' => $venues,
            'stations' => KitchenStation::where('tenant_id', activeTenantId())->with('venue')->orderBy('sort_order')->get(),
            'warehouses' => Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->get(),
            'clients' => Client::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}

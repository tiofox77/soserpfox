<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Hotel\RateSeason;
use App\Models\Hotel\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RateManagement extends Component
{
    use WithPagination;

    public $activeTab = 'seasons';
    
    // Modal Season
    public $showSeasonModal = false;
    public $editingSeasonId = null;
    public $seasonName = '';
    public $seasonColor = '#6366f1';
    public $seasonStartDate = '';
    public $seasonEndDate = '';
    public $seasonModifier = 1.00;
    public $seasonModifierType = 'multiplier';
    public $seasonPriority = 0;
    public $seasonDescription = '';
    public $seasonIsActive = true;

    // Modal Weekday
    public $showWeekdayModal = false;
    public $selectedRoomTypeId = '';
    public $weekdayRates = [];

    // Modal Special Rate
    public $showSpecialModal = false;
    public $specialDate = '';
    public $specialRoomTypeId = '';
    public $specialPrice = '';
    public $specialReason = '';

    // Filtros
    public $search = '';
    public $filterActive = '';

    protected $rules = [
        'seasonName' => 'required|string|max:255',
        'seasonStartDate' => 'required|date',
        'seasonEndDate' => 'required|date|after_or_equal:seasonStartDate',
        'seasonModifier' => 'required|numeric|min:0',
        'seasonModifierType' => 'required|in:multiplier,percentage,fixed',
    ];

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    // ========== SEASONS ==========

    public function openSeasonModal($id = null)
    {
        $this->resetSeasonForm();
        
        if ($id) {
            $season = RateSeason::find($id);
            if ($season) {
                $this->editingSeasonId = $id;
                $this->seasonName = $season->name;
                $this->seasonColor = $season->color;
                $this->seasonStartDate = $season->start_date->format('Y-m-d');
                $this->seasonEndDate = $season->end_date->format('Y-m-d');
                $this->seasonModifier = $season->price_modifier;
                $this->seasonModifierType = $season->modifier_type;
                $this->seasonPriority = $season->priority;
                $this->seasonDescription = $season->description;
                $this->seasonIsActive = $season->is_active;
            }
        }
        
        $this->showSeasonModal = true;
    }

    public function resetSeasonForm()
    {
        $this->editingSeasonId = null;
        $this->seasonName = '';
        $this->seasonColor = '#6366f1';
        $this->seasonStartDate = now()->format('Y-m-d');
        $this->seasonEndDate = now()->addMonth()->format('Y-m-d');
        $this->seasonModifier = 1.00;
        $this->seasonModifierType = 'multiplier';
        $this->seasonPriority = 0;
        $this->seasonDescription = '';
        $this->seasonIsActive = true;
    }

    public function saveSeason()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $this->seasonName,
            'color' => $this->seasonColor,
            'start_date' => $this->seasonStartDate,
            'end_date' => $this->seasonEndDate,
            'price_modifier' => $this->seasonModifier,
            'modifier_type' => $this->seasonModifierType,
            'priority' => $this->seasonPriority,
            'description' => $this->seasonDescription,
            'is_active' => $this->seasonIsActive,
        ];

        if ($this->editingSeasonId) {
            RateSeason::find($this->editingSeasonId)->update($data);
            session()->flash('success', 'Temporada atualizada com sucesso!');
        } else {
            RateSeason::create($data);
            session()->flash('success', 'Temporada criada com sucesso!');
        }

        $this->showSeasonModal = false;
        $this->resetSeasonForm();
    }

    public function deleteSeason($id)
    {
        RateSeason::where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->delete();
        session()->flash('success', 'Temporada eliminada!');
    }

    public function toggleSeasonActive($id)
    {
        $season = RateSeason::find($id);
        if ($season) {
            $season->update(['is_active' => !$season->is_active]);
        }
    }

    // ========== WEEKDAY RATES ==========

    public function openWeekdayModal($roomTypeId = null)
    {
        $this->selectedRoomTypeId = $roomTypeId ?: '';
        $this->loadWeekdayRates();
        $this->showWeekdayModal = true;
    }

    public function loadWeekdayRates()
    {
        if (!$this->selectedRoomTypeId) {
            $this->weekdayRates = [];
            return;
        }

        $tenantId = auth()->user()->tenant_id;
        $existing = DB::table('hotel_weekday_rates')
            ->where('tenant_id', $tenantId)
            ->where('room_type_id', $this->selectedRoomTypeId)
            ->pluck('price_modifier', 'day_of_week')
            ->toArray();

        $this->weekdayRates = [];
        for ($i = 0; $i <= 6; $i++) {
            $this->weekdayRates[$i] = $existing[$i] ?? 1.00;
        }
    }

    public function updatedSelectedRoomTypeId()
    {
        $this->loadWeekdayRates();
    }

    public function saveWeekdayRates()
    {
        if (!$this->selectedRoomTypeId) {
            session()->flash('error', 'Selecione um tipo de quarto');
            return;
        }

        $tenantId = auth()->user()->tenant_id;

        // Apagar existentes e inserir novos
        DB::table('hotel_weekday_rates')
            ->where('tenant_id', $tenantId)
            ->where('room_type_id', $this->selectedRoomTypeId)
            ->delete();

        foreach ($this->weekdayRates as $day => $modifier) {
            if ($modifier != 1.00) {
                DB::table('hotel_weekday_rates')->insert([
                    'tenant_id' => $tenantId,
                    'room_type_id' => $this->selectedRoomTypeId,
                    'day_of_week' => $day,
                    'price_modifier' => $modifier,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        session()->flash('success', 'Tarifas por dia da semana salvas!');
        $this->showWeekdayModal = false;
    }

    // ========== SPECIAL RATES ==========

    public function openSpecialModal()
    {
        $this->specialDate = now()->format('Y-m-d');
        $this->specialRoomTypeId = '';
        $this->specialPrice = '';
        $this->specialReason = '';
        $this->showSpecialModal = true;
    }

    public function saveSpecialRate()
    {
        $this->validate([
            'specialDate' => 'required|date',
            'specialPrice' => 'required|numeric|min:0',
        ]);

        $tenantId = auth()->user()->tenant_id;

        DB::table('hotel_special_rates')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'room_type_id' => $this->specialRoomTypeId ?: null,
                'date' => $this->specialDate,
            ],
            [
                'price' => $this->specialPrice,
                'reason' => $this->specialReason,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        session()->flash('success', 'Tarifa especial salva!');
        $this->showSpecialModal = false;
    }

    public function deleteSpecialRate($id)
    {
        DB::table('hotel_special_rates')
            ->where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->delete();
        session()->flash('success', 'Tarifa especial eliminada!');
    }

    // ========== CALCULATE PRICE ==========

    /**
     * Calcula o preço para uma data específica considerando todas as tarifas
     */
    public static function calculatePrice($tenantId, $roomTypeId, $date, $basePrice)
    {
        $date = Carbon::parse($date);
        $finalPrice = $basePrice;

        // 1. Verificar temporada
        $season = RateSeason::getForDate($tenantId, $date);
        if ($season) {
            $finalPrice = $season->applyModifier($finalPrice);
        }

        // 2. Verificar dia da semana
        $weekdayRate = DB::table('hotel_weekday_rates')
            ->where('tenant_id', $tenantId)
            ->where('room_type_id', $roomTypeId)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if ($weekdayRate) {
            $finalPrice = $finalPrice * $weekdayRate->price_modifier;
        }

        // 3. Verificar tarifa especial (sobrepõe tudo)
        $specialRate = DB::table('hotel_special_rates')
            ->where('tenant_id', $tenantId)
            ->where('date', $date->format('Y-m-d'))
            ->where('is_active', true)
            ->where(function($q) use ($roomTypeId) {
                $q->whereNull('room_type_id')
                  ->orWhere('room_type_id', $roomTypeId);
            })
            ->orderByDesc('room_type_id') // Priorizar específico do quarto
            ->first();

        if ($specialRate) {
            if ($specialRate->price) {
                $finalPrice = $specialRate->price;
            } elseif ($specialRate->price_modifier) {
                $finalPrice = $finalPrice * $specialRate->price_modifier;
            }
        }

        return round($finalPrice, 2);
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $seasons = RateSeason::where('tenant_id', $tenantId)
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterActive !== '', fn($q) => $q->where('is_active', $this->filterActive))
            ->orderBy('start_date')
            ->paginate(10);

        $roomTypes = RoomType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $specialRates = DB::table('hotel_special_rates')
            ->where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subMonth())
            ->orderBy('date')
            ->get();

        // Calendário de preços para visualização
        $calendarData = $this->getCalendarData($tenantId, $roomTypes);

        return view('livewire.hotel.rate-management', [
            'seasons' => $seasons,
            'roomTypes' => $roomTypes,
            'specialRates' => $specialRates,
            'calendarData' => $calendarData,
        ])->layout('layouts.app');
    }

    protected function getCalendarData($tenantId, $roomTypes)
    {
        $data = [];
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        foreach ($roomTypes->take(3) as $roomType) {
            $prices = [];
            $current = $startDate->copy();
            
            while ($current <= $endDate) {
                $prices[$current->format('Y-m-d')] = self::calculatePrice(
                    $tenantId,
                    $roomType->id,
                    $current,
                    $roomType->base_price
                );
                $current->addDay();
            }
            
            $data[$roomType->id] = [
                'name' => $roomType->name,
                'base_price' => $roomType->base_price,
                'prices' => $prices,
            ];
        }

        return $data;
    }
}

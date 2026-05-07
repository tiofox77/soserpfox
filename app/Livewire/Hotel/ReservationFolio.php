<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\ReservationItem;

class ReservationFolio extends Component
{
    public $reservationId;
    public $reservation;

    // New charge form
    public $category = 'minibar';
    public $description = '';
    public $quantity = 1;
    public $unitPrice = 0;
    public $notes = '';

    // UI state
    public $filterCategory = '';
    public $showAddModal = false;

    protected $rules = [
        'category' => 'required|string',
        'description' => 'required|string|min:2|max:200',
        'quantity' => 'required|numeric|min:0.01',
        'unitPrice' => 'required|numeric|min:0',
    ];

    public function mount($id)
    {
        $this->reservationId = $id;
        $this->loadReservation();
    }

    protected function loadReservation()
    {
        $this->reservation = Reservation::where('tenant_id', activeTenantId())
            ->with(['guest', 'client', 'room', 'roomType', 'items.chargedByUser'])
            ->findOrFail($this->reservationId);
    }

    public function openAdd($category = null)
    {
        $this->reset(['description', 'quantity', 'unitPrice', 'notes']);
        $this->quantity = 1;
        if ($category) {
            $this->category = $category;
        }
        $this->showAddModal = true;
    }

    public function closeAdd()
    {
        $this->showAddModal = false;
    }

    public function addCharge()
    {
        $this->validate();

        // Map category to legacy "type" field for compatibility
        $typeMap = [
            'minibar' => 'minibar',
            'room_service' => 'room_service',
            'restaurant' => 'restaurant',
            'laundry' => 'laundry',
            'transfer' => 'transfer',
            'spa' => 'spa',
            'telephone' => 'service',
            'other' => 'other',
        ];

        ReservationItem::create([
            'reservation_id' => $this->reservation->id,
            'type' => $typeMap[$this->category] ?? 'other',
            'category' => $this->category,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'date' => now()->toDateString(),
            'charged_at' => now(),
            'charged_by' => auth()->id(),
            'notes' => $this->notes ?: null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Consumo adicionado ao folio!');
        $this->closeAdd();
        $this->loadReservation();
    }

    public function deleteCharge($itemId)
    {
        $item = ReservationItem::where('reservation_id', $this->reservation->id)->find($itemId);
        if ($item) {
            $item->delete();
            $this->reservation->calculateTotals();
            $this->reservation->saveQuietly();
            $this->dispatch('toast', type: 'success', message: 'Consumo removido.');
            $this->loadReservation();
        }
    }

    public function render()
    {
        $extras = $this->reservation->items()
            ->whereNotNull('category')
            ->when($this->filterCategory, fn($q) => $q->where('category', $this->filterCategory))
            ->orderByDesc('charged_at')
            ->orderByDesc('id')
            ->get();

        $byCategory = $this->reservation->items()
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count, SUM(total) as total')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        return view('livewire.hotel.reservation-folio', [
            'extras' => $extras,
            'byCategory' => $byCategory,
            'categories' => ReservationItem::CATEGORIES,
        ])->layout('layouts.app', ['title' => 'Folio — ' . $this->reservation->reservation_number]);
    }
}

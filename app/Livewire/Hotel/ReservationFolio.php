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
        if ($msg = $this->folioFechado()) {
            $this->dispatch('toast', type: 'error', message: $msg);
            return;
        }

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

        // Actualizar o total da reserva. Sem isto o consumo ficava gravado mas
        // o total da reserva não subia — o saldo em dívida não reflectia o que
        // o hóspede tinha consumido (ao contrário do deleteCharge, que já
        // recalculava).
        $this->reservation->calculateTotals();
        $this->reservation->saveQuietly();

        $this->dispatch('toast', type: 'success', message: 'Consumo adicionado ao folio!');
        $this->closeAdd();
        $this->loadReservation();
    }

    public function deleteCharge($itemId)
    {
        if ($msg = $this->folioFechado()) {
            $this->dispatch('toast', type: 'error', message: $msg);
            return;
        }

        $item = ReservationItem::where('reservation_id', $this->reservation->id)->find($itemId);
        if ($item) {
            $item->delete();
            $this->reservation->calculateTotals();
            $this->reservation->saveQuietly();
            $this->dispatch('toast', type: 'success', message: 'Consumo removido.');
            $this->loadReservation();
        }
    }

    /**
     * O folio já fechou? Devolve a razão, ou null se ainda está aberto.
     *
     * Depois do check-out o folio continuava a aceitar consumos: o total da
     * reserva subia, o estado de pagamento caía de "Pago" para "Parcial" e
     * ficava um saldo em dívida de um hóspede que já tinha ido embora — sem
     * relação nenhuma com a factura já emitida.
     */
    protected function folioFechado(): ?string
    {
        if (!$this->reservation) {
            return 'Reserva não encontrada.';
        }

        if ($this->reservation->status === \App\Models\Hotel\Reservation::STATUS_CHECKED_OUT) {
            return 'Esta estadia já fez check-out — o folio está fechado. '
                . 'Para cobrar um consumo em falta, emita um documento próprio na Faturação.';
        }

        if ($this->reservation->status === \App\Models\Hotel\Reservation::STATUS_CANCELLED) {
            return 'Reserva cancelada — o folio está fechado.';
        }

        return null;
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

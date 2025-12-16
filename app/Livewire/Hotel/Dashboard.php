<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use App\Models\Hotel\Reservation;
use Carbon\Carbon;

class Dashboard extends Component
{
    public $selectedDate;
    public $viewMode = 'day'; // day, week, month

    public function mount()
    {
        $this->selectedDate = now()->toDateString();
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
    }

    public function goToToday()
    {
        $this->selectedDate = now()->toDateString();
    }

    public function quickCheckIn($reservationId)
    {
        $reservation = Reservation::forTenant()->findOrFail($reservationId);
        $reservation->checkIn();
        
        $this->dispatch('success', message: 'Check-in realizado com sucesso!');
    }

    public function quickCheckOut($reservationId)
    {
        $reservation = Reservation::forTenant()->findOrFail($reservationId);
        $reservation->checkOut();
        
        $this->dispatch('success', message: 'Check-out realizado com sucesso!');
    }

    public function render()
    {
        $tenantId = activeTenantId();

        // Estatísticas de Quartos
        $totalRooms = Room::forTenant()->active()->count();
        $availableRooms = Room::forTenant()->active()->available()->count();
        $occupiedRooms = Room::forTenant()->active()->where('status', 'occupied')->count();
        $maintenanceRooms = Room::forTenant()->active()->where('status', 'maintenance')->count();

        // Taxa de ocupação
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        // Reservas do dia
        $todayCheckIns = Reservation::forTenant()
            ->checkingInToday()
            ->with(['guest', 'room', 'roomType'])
            ->get();

        $todayCheckOuts = Reservation::forTenant()
            ->checkingOutToday()
            ->with(['guest', 'room', 'roomType'])
            ->get();

        // Hóspedes atualmente hospedados
        $currentGuests = Reservation::forTenant()
            ->currentlyStaying()
            ->with(['guest', 'room', 'roomType'])
            ->get();

        // Reservas pendentes
        $pendingReservations = Reservation::forTenant()
            ->where('status', 'pending')
            ->with(['guest', 'roomType'])
            ->orderBy('check_in_date')
            ->limit(10)
            ->get();

        // Quartos por status
        $roomsByStatus = Room::forTenant()
            ->active()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Receita do mês
        $monthlyRevenue = Reservation::forTenant()
            ->whereMonth('check_out_date', now()->month)
            ->whereYear('check_out_date', now()->year)
            ->where('status', 'checked_out')
            ->sum('total');

        // Próximas chegadas (7 dias)
        $upcomingArrivals = Reservation::forTenant()
            ->whereBetween('check_in_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->with(['guest', 'roomType'])
            ->orderBy('check_in_date')
            ->get();

        // Mapa de quartos com status atual
        $roomsMap = Room::forTenant()
            ->active()
            ->with(['roomType', 'currentReservation.guest'])
            ->orderBy('floor')
            ->orderBy('number')
            ->get()
            ->groupBy('floor');

        return view('livewire.hotel.dashboard', compact(
            'totalRooms',
            'availableRooms',
            'occupiedRooms',
            'maintenanceRooms',
            'occupancyRate',
            'todayCheckIns',
            'todayCheckOuts',
            'currentGuests',
            'pendingReservations',
            'roomsByStatus',
            'monthlyRevenue',
            'upcomingArrivals',
            'roomsMap'
        ))->layout('layouts.app');
    }
}

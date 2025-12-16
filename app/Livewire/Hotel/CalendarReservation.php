<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

#[Layout('layouts.app')]
#[Title('Calendário de Reservas - Hotel')]
class CalendarReservation extends Component
{
    public $currentDate;
    public $viewType = 'month'; // 'week', 'month'
    public $roomTypeFilter = '';
    public $statusFilter = '';
    
    // Modal de reserva rápida
    public $showQuickModal = false;
    public $quickRoom = null;
    public $quickDate = null;
    public $quickCheckOut = null;
    public $quickGuest = '';
    public $quickAdults = 1;
    public $quickChildren = 0;
    public $quickNotes = '';
    
    // Modal de visualização
    public $showViewModal = false;
    public $viewingReservation = null;
    
    // Drag & Drop
    public $draggingReservation = null;

    public function mount()
    {
        $this->currentDate = now()->startOfMonth()->toDateString();
    }

    public function previousPeriod()
    {
        $date = Carbon::parse($this->currentDate);
        
        if ($this->viewType === 'week') {
            $this->currentDate = $date->subWeek()->toDateString();
        } else {
            $this->currentDate = $date->subMonth()->toDateString();
        }
    }

    public function nextPeriod()
    {
        $date = Carbon::parse($this->currentDate);
        
        if ($this->viewType === 'week') {
            $this->currentDate = $date->addWeek()->toDateString();
        } else {
            $this->currentDate = $date->addMonth()->toDateString();
        }
    }

    public function goToToday()
    {
        $this->currentDate = now()->startOfMonth()->toDateString();
    }

    public function setViewType($type)
    {
        $this->viewType = $type;
    }

    /**
     * Obter período de datas para exibição
     */
    public function getDatesProperty()
    {
        $start = Carbon::parse($this->currentDate);
        
        if ($this->viewType === 'week') {
            $start = $start->startOfWeek();
            $end = $start->copy()->endOfWeek();
        } else {
            $start = $start->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }
        
        return collect(CarbonPeriod::create($start, $end))->map(function ($date) {
            return [
                'date' => $date->toDateString(),
                'day' => $date->day,
                'dayName' => $date->locale('pt')->shortDayName,
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
                'isPast' => $date->isPast() && !$date->isToday(),
            ];
        });
    }

    /**
     * Obter quartos com reservas
     */
    public function getRoomsWithReservationsProperty()
    {
        $start = Carbon::parse($this->currentDate);
        $end = $this->viewType === 'week' 
            ? $start->copy()->endOfWeek() 
            : $start->copy()->endOfMonth();

        // Query de quartos
        $roomsQuery = Room::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with(['roomType']);
        
        if ($this->roomTypeFilter) {
            $roomsQuery->where('room_type_id', $this->roomTypeFilter);
        }
        
        $rooms = $roomsQuery->orderBy('floor')->orderBy('number')->get();

        // Query de reservas
        $reservationsQuery = Reservation::where('tenant_id', activeTenantId())
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('check_in_date', [$start, $end])
                  ->orWhereBetween('check_out_date', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('check_in_date', '<=', $start)
                         ->where('check_out_date', '>=', $end);
                  });
            })
            ->whereNotIn('status', ['cancelled'])
            ->with(['guest', 'roomType']);
        
        if ($this->statusFilter) {
            $reservationsQuery->where('status', $this->statusFilter);
        }
        
        $reservations = $reservationsQuery->get();

        // Mapear reservas por quarto
        return $rooms->map(function ($room) use ($reservations, $start, $end) {
            $roomReservations = $reservations->filter(function ($r) use ($room) {
                return $r->room_id === $room->id || 
                       ($r->room_id === null && $r->room_type_id === $room->room_type_id);
            })->map(function ($r) use ($start, $end) {
                $checkIn = Carbon::parse($r->check_in_date);
                $checkOut = Carbon::parse($r->check_out_date);
                
                // Calcular posição e largura no calendário
                $startOffset = max(0, $start->diffInDays($checkIn, false));
                $endOffset = min($start->diffInDays($end), $start->diffInDays($checkOut, false));
                $width = max(1, $endOffset - $startOffset);
                
                return [
                    'id' => $r->id,
                    'reservation_number' => $r->reservation_number,
                    'guest_name' => $r->guest?->name ?? 'Sem hóspede',
                    'check_in' => $checkIn->format('d/m'),
                    'check_out' => $checkOut->format('d/m'),
                    'nights' => $r->nights,
                    'status' => $r->status,
                    'status_label' => Reservation::STATUSES[$r->status] ?? $r->status,
                    'total' => $r->total,
                    'adults' => $r->adults,
                    'children' => $r->children,
                    'source' => $r->source,
                    'payment_status' => $r->payment_status,
                    'start_offset' => $startOffset,
                    'width' => $width,
                    'starts_before' => $checkIn->lt($start),
                    'ends_after' => $checkOut->gt($end),
                ];
            })->values();

            return [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'type_name' => $room->roomType->name ?? '-',
                'type_color' => $room->roomType->color ?? '#6366f1',
                'status' => $room->status,
                'reservations' => $roomReservations,
            ];
        });
    }

    /**
     * Estatísticas do período
     */
    public function getStatsProperty()
    {
        $start = Carbon::parse($this->currentDate);
        $end = $this->viewType === 'week' 
            ? $start->copy()->endOfWeek() 
            : $start->copy()->endOfMonth();

        $totalRooms = Room::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->count();

        $reservations = Reservation::where('tenant_id', activeTenantId())
            ->whereBetween('check_in_date', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $checkInsToday = Reservation::where('tenant_id', activeTenantId())
            ->whereDate('check_in_date', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $checkOutsToday = Reservation::where('tenant_id', activeTenantId())
            ->whereDate('check_out_date', today())
            ->where('status', 'checked_in')
            ->count();

        $occupiedToday = Reservation::where('tenant_id', activeTenantId())
            ->where('check_in_date', '<=', today())
            ->where('check_out_date', '>', today())
            ->where('status', 'checked_in')
            ->count();

        return [
            'total_rooms' => $totalRooms,
            'reservations_period' => $reservations->count(),
            'revenue_period' => $reservations->sum('total'),
            'check_ins_today' => $checkInsToday,
            'check_outs_today' => $checkOutsToday,
            'occupied_today' => $occupiedToday,
            'occupancy_rate' => $totalRooms > 0 ? round(($occupiedToday / $totalRooms) * 100) : 0,
        ];
    }

    /**
     * Abrir modal de reserva rápida
     */
    public function openQuickReservation($roomId, $date)
    {
        $this->quickRoom = Room::with('roomType')->find($roomId);
        $this->quickDate = $date;
        $this->quickCheckOut = Carbon::parse($date)->addDay()->toDateString();
        $this->quickGuest = '';
        $this->quickAdults = 1;
        $this->quickChildren = 0;
        $this->quickNotes = '';
        $this->showQuickModal = true;
    }

    /**
     * Criar reserva rápida
     */
    public function createQuickReservation()
    {
        $this->validate([
            'quickGuest' => 'required|string|min:2',
            'quickCheckOut' => 'required|date|after:quickDate',
            'quickAdults' => 'required|integer|min:1',
        ]);

        // Criar ou buscar hóspede
        $guest = Guest::firstOrCreate(
            ['tenant_id' => activeTenantId(), 'name' => $this->quickGuest],
            ['document_type' => 'bi', 'document_number' => 'TEMP-' . time()]
        );

        $nights = Carbon::parse($this->quickDate)->diffInDays($this->quickCheckOut);
        $rate = $this->quickRoom->roomType->base_price ?? 0;

        Reservation::create([
            'tenant_id' => activeTenantId(),
            'guest_id' => $guest->id,
            'room_id' => $this->quickRoom->id,
            'room_type_id' => $this->quickRoom->room_type_id,
            'check_in_date' => $this->quickDate,
            'check_out_date' => $this->quickCheckOut,
            'adults' => $this->quickAdults,
            'children' => $this->quickChildren,
            'nights' => $nights,
            'room_rate' => $rate,
            'subtotal' => $rate * $nights,
            'total' => $rate * $nights,
            'status' => 'confirmed',
            'source' => 'direct',
            'special_requests' => $this->quickNotes,
            'created_by' => auth()->id(),
        ]);

        $this->showQuickModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva criada com sucesso!']);
    }

    /**
     * Ver detalhes da reserva
     */
    public function viewReservation($id)
    {
        $this->viewingReservation = Reservation::with(['guest', 'room', 'roomType'])->find($id);
        $this->showViewModal = true;
    }

    /**
     * Fechar modais
     */
    public function closeModals()
    {
        $this->showQuickModal = false;
        $this->showViewModal = false;
        $this->viewingReservation = null;
    }

    /**
     * Alterar status da reserva
     */
    public function updateStatus($id, $status)
    {
        $reservation = Reservation::find($id);
        
        if ($reservation) {
            $data = ['status' => $status];
            
            if ($status === 'checked_in') {
                $data['actual_check_in'] = now();
            } elseif ($status === 'checked_out') {
                $data['actual_check_out'] = now();
            } elseif ($status === 'cancelled') {
                $data['cancelled_at'] = now();
                $data['cancelled_by'] = auth()->id();
            }
            
            $reservation->update($data);
            
            $this->dispatch('notify', [
                'type' => 'success', 
                'message' => 'Status atualizado para: ' . (Reservation::STATUSES[$status] ?? $status)
            ]);
        }
        
        $this->closeModals();
    }

    /**
     * Mover reserva (drag & drop) - atualiza datas
     */
    public function moveReservation($reservationId, $newRoomId, $newStartDate)
    {
        $reservation = Reservation::find($reservationId);
        
        if ($reservation) {
            $nights = $reservation->nights;
            $newCheckOut = Carbon::parse($newStartDate)->addDays($nights)->toDateString();
            
            $reservation->update([
                'room_id' => $newRoomId,
                'check_in_date' => $newStartDate,
                'check_out_date' => $newCheckOut,
            ]);
            
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva movida com sucesso!']);
        }
    }

    public function render()
    {
        $roomTypes = RoomType::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.hotel.calendar.calendar', [
            'roomTypes' => $roomTypes,
            'dates' => $this->dates,
            'roomsWithReservations' => $this->roomsWithReservations,
            'stats' => $this->stats,
        ]);
    }
}

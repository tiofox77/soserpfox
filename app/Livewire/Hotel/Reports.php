<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class Reports extends Component
{
    public $activeTab = 'occupancy';
    
    // Filtros
    public $dateFrom;
    public $dateTo;
    public $roomTypeId = '';
    public $period = 'month'; // day, week, month, year
    
    // Dados
    public $occupancyData = [];
    public $revenueData = [];
    public $guestData = [];
    public $kpis = [];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->loadData();
    }

    public function updatedDateFrom()
    {
        $this->loadData();
    }

    public function updatedDateTo()
    {
        $this->loadData();
    }

    public function updatedRoomTypeId()
    {
        $this->loadData();
    }

    public function updatedPeriod()
    {
        $this->setPeriodDates();
        $this->loadData();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadData();
    }

    protected function setPeriodDates()
    {
        switch ($this->period) {
            case 'day':
                $this->dateFrom = now()->toDateString();
                $this->dateTo = now()->toDateString();
                break;
            case 'week':
                $this->dateFrom = now()->startOfWeek()->toDateString();
                $this->dateTo = now()->endOfWeek()->toDateString();
                break;
            case 'month':
                $this->dateFrom = now()->startOfMonth()->toDateString();
                $this->dateTo = now()->endOfMonth()->toDateString();
                break;
            case 'year':
                $this->dateFrom = now()->startOfYear()->toDateString();
                $this->dateTo = now()->endOfYear()->toDateString();
                break;
        }
    }

    public function loadData()
    {
        $this->loadKPIs();
        
        switch ($this->activeTab) {
            case 'occupancy':
                $this->loadOccupancyData();
                break;
            case 'revenue':
                $this->loadRevenueData();
                break;
            case 'guests':
                $this->loadGuestData();
                break;
        }
    }

    protected function loadKPIs()
    {
        $tenantId = auth()->user()->tenant_id;
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $days = $from->diffInDays($to) + 1;

        // Total de quartos
        $totalRooms = Room::where('tenant_id', $tenantId)
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        // Noites vendidas (room nights)
        $roomNights = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->sum('nights');

        // Noites disponíveis
        $availableNights = $totalRooms * $days;

        // Taxa de ocupação
        $occupancyRate = $availableNights > 0 ? round(($roomNights / $availableNights) * 100, 1) : 0;

        // Receita total
        $totalRevenue = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->sum('total');

        // ADR (Average Daily Rate)
        $adr = $roomNights > 0 ? round($totalRevenue / $roomNights, 2) : 0;

        // RevPAR (Revenue Per Available Room)
        $revpar = $availableNights > 0 ? round($totalRevenue / $availableNights, 2) : 0;

        // Total de reservas
        $totalReservations = Reservation::where('tenant_id', $tenantId)
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        // Check-ins no período
        $checkIns = Reservation::where('tenant_id', $tenantId)
            ->whereBetween('check_in_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        // Check-outs no período
        $checkOuts = Reservation::where('tenant_id', $tenantId)
            ->whereBetween('check_out_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        // Cancelamentos
        $cancellations = Reservation::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        // Novos hóspedes
        $newGuests = Guest::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $this->kpis = [
            'occupancy_rate' => $occupancyRate,
            'total_revenue' => $totalRevenue,
            'adr' => $adr,
            'revpar' => $revpar,
            'room_nights' => $roomNights,
            'available_nights' => $availableNights,
            'total_rooms' => $totalRooms,
            'total_reservations' => $totalReservations,
            'check_ins' => $checkIns,
            'check_outs' => $checkOuts,
            'cancellations' => $cancellations,
            'new_guests' => $newGuests,
        ];
    }

    protected function loadOccupancyData()
    {
        $tenantId = auth()->user()->tenant_id;
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        $totalRooms = Room::where('tenant_id', $tenantId)
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->count();

        $data = [];
        $period = CarbonPeriod::create($from, $to);

        foreach ($period as $date) {
            $occupied = Reservation::where('tenant_id', $tenantId)
                ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
                ->where('check_in_date', '<=', $date)
                ->where('check_out_date', '>', $date)
                ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
                ->count();

            $data[] = [
                'date' => $date->format('d/m'),
                'date_full' => $date->format('Y-m-d'),
                'occupied' => $occupied,
                'available' => $totalRooms - $occupied,
                'rate' => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0,
            ];
        }

        $this->occupancyData = $data;
    }

    protected function loadRevenueData()
    {
        $tenantId = auth()->user()->tenant_id;
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        // Receita por tipo de quarto
        $byRoomType = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->select('room_type_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('room_type_id')
            ->with('roomType')
            ->get()
            ->map(fn($r) => [
                'name' => $r->roomType->name ?? 'N/A',
                'total' => $r->total,
                'count' => $r->count,
            ]);

        // Receita por fonte
        $bySource = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->select('source', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->get()
            ->map(fn($r) => [
                'name' => $this->getSourceLabel($r->source),
                'source' => $r->source,
                'total' => $r->total,
                'count' => $r->count,
            ]);

        // Receita por dia
        $byDay = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->select(DB::raw('DATE(check_in_date) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => [
                'date' => Carbon::parse($r->date)->format('d/m'),
                'total' => $r->total,
            ]);

        // Receita por status de pagamento
        $byPaymentStatus = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$from, $to])
            ->when($this->roomTypeId, fn($q) => $q->where('room_type_id', $this->roomTypeId))
            ->select('payment_status', DB::raw('SUM(total) as total'), DB::raw('SUM(paid_amount) as paid'))
            ->groupBy('payment_status')
            ->get();

        $this->revenueData = [
            'by_room_type' => $byRoomType,
            'by_source' => $bySource,
            'by_day' => $byDay,
            'by_payment_status' => $byPaymentStatus,
            'total_pending' => $byPaymentStatus->where('payment_status', 'pending')->sum('total'),
            'total_paid' => $byPaymentStatus->sum('paid'),
        ];
    }

    protected function loadGuestData()
    {
        $tenantId = auth()->user()->tenant_id;
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        // Top hóspedes por número de estadias
        $topGuests = Guest::where('tenant_id', $tenantId)
            ->withCount(['reservations' => fn($q) => $q->whereBetween('check_in_date', [$from, $to])])
            ->withSum(['reservations' => fn($q) => $q->whereBetween('check_in_date', [$from, $to])], 'total')
            ->having('reservations_count', '>', 0)
            ->orderByDesc('reservations_count')
            ->take(10)
            ->get();

        // Hóspedes por país/nacionalidade
        $byNationality = Guest::where('tenant_id', $tenantId)
            ->whereHas('reservations', fn($q) => $q->whereBetween('check_in_date', [$from, $to]))
            ->select('nationality', DB::raw('COUNT(*) as count'))
            ->groupBy('nationality')
            ->orderByDesc('count')
            ->get()
            ->map(fn($g) => [
                'nationality' => $g->nationality ?: 'Não informado',
                'count' => $g->count,
            ]);

        // Estatísticas de estadia
        $avgStay = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('check_in_date', [$from, $to])
            ->avg('nights');

        // Hóspedes recorrentes vs novos
        $repeatGuests = Guest::where('tenant_id', $tenantId)
            ->whereHas('reservations', fn($q) => $q->whereBetween('check_in_date', [$from, $to]))
            ->withCount('reservations')
            ->get()
            ->filter(fn($g) => $g->reservations_count > 1)
            ->count();

        $newGuests = Guest::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $this->guestData = [
            'top_guests' => $topGuests,
            'by_nationality' => $byNationality,
            'avg_stay' => round($avgStay ?? 0, 1),
            'repeat_guests' => $repeatGuests,
            'new_guests' => $newGuests,
        ];
    }

    protected function getSourceLabel($source)
    {
        return match($source) {
            'direct' => 'Direto',
            'online' => 'Website',
            'phone' => 'Telefone',
            'walkin' => 'Walk-in',
            'booking' => 'Booking.com',
            'expedia' => 'Expedia',
            'airbnb' => 'Airbnb',
            default => ucfirst($source ?? 'Outro'),
        };
    }

    public function exportPdf()
    {
        // TODO: Implementar exportação PDF
        session()->flash('info', 'Exportação PDF em desenvolvimento.');
    }

    public function exportExcel()
    {
        // TODO: Implementar exportação Excel
        session()->flash('info', 'Exportação Excel em desenvolvimento.');
    }

    public function render()
    {
        $roomTypes = RoomType::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.hotel.reports', [
            'roomTypes' => $roomTypes,
        ])->layout('layouts.app');
    }
}

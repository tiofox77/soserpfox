<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use App\Models\Hotel\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        try {
            $reservation->checkIn();
        } catch (\DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());
            return;
        }

        $this->dispatch('success', message: 'Check-in realizado com sucesso!');
    }

    /**
     * Check-out rápido do painel.
     *
     * Encaminha para o ecrã de check-out: fechar a estadia aqui deixava-a
     * fechada SEM factura e sem cobrar os consumos do folio.
     */
    public function quickCheckOut($reservationId)
    {
        $reservation = Reservation::forTenant()->findOrFail($reservationId);

        return redirect()->route('hotel.checkout', ['reservationId' => $reservation->id]);
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
        ) + [
            'receitaMensal'  => $this->receitaPorMes(),
            'ocupacaoDias'   => $this->ocupacaoPorDia(),
            'porTipoDeQuarto' => $this->receitaPorTipoDeQuarto(),
            'estadoQuartos'  => $this->quartosPorEstado($roomsByStatus),
        ])->layout('layouts.app');
    }

    /**
     * A receita dos últimos 12 meses.
     *
     * O painel mostrava só o mês corrente. Num hotel a sazonalidade é o
     * negócio — sem os doze meses não se distingue um mau mês de uma época
     * baixa, e são decisões opostas.
     */
    private function receitaPorMes(): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = Reservation::forTenant()
            ->where('status', 'checked_out')
            ->where('check_out_date', '>=', $desde)
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(check_out_date, '%Y-%m') as mes, SUM(total) as total")
            ->pluck('total', 'mes');

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = (float) ($porMes[$data->format('Y-m')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /**
     * Quantos quartos estiveram ocupados em cada um dos últimos 30 dias.
     *
     * Conta-se por NOITE e não por reserva: uma reserva de cinco noites ocupa
     * cinco dias, e contá-la uma vez no dia da chegada dava uma ocupação que
     * parecia um serrote — picos nos dias de check-in e vazio no meio.
     */
    private function ocupacaoPorDia(): array
    {
        $dias = 30;
        $desde = today()->subDays($dias - 1);

        $reservas = Reservation::forTenant()
            ->whereIn('status', ['checked_in', 'checked_out', 'confirmed'])
            ->where('check_out_date', '>=', $desde)
            ->where('check_in_date', '<=', today())
            ->get(['check_in_date', 'check_out_date']);

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $data = $desde->copy()->addDays($d);

            $etiquetas[] = $data->format('d/m');
            $valores[] = $reservas->filter(function ($r) use ($data) {
                // A noite de saída não conta: quem sai de manhã liberta o
                // quarto nesse dia.
                return $r->check_in_date <= $data && $r->check_out_date > $data;
            })->count();
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Quanto rende cada tipo de quarto — decide onde investir. */
    private function receitaPorTipoDeQuarto(): array
    {
        $linhas = DB::table('hotel_reservations as r')
            ->join('hotel_rooms as q', 'q.id', '=', 'r.room_id')
            ->leftJoin('hotel_room_types as t', 't.id', '=', 'q.room_type_id')
            ->where('r.tenant_id', activeTenantId())
            ->where('r.status', 'checked_out')
            ->where('r.check_out_date', '>=', now()->subMonths(6))
            ->groupBy('t.id', 't.name')
            ->selectRaw('COALESCE(t.name, "—") as nome, SUM(r.total) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->map(fn ($l) => (float) $l->total)->all(),
        ];
    }

    /** Os quartos por estado — já vinha calculado, faltava desenhá-lo. */
    private function quartosPorEstado(array $porEstado): array
    {
        $rotulos = [
            'available'   => __('Livre'),
            'occupied'    => __('Ocupado'),
            'reserved'    => __('Reservado'),
            'maintenance' => __('Manutenção'),
            'cleaning'    => __('Limpeza'),
            'blocked'     => __('Bloqueado'),
        ];

        return [
            'etiquetas' => array_map(fn ($k) => $rotulos[$k] ?? $k, array_keys($porEstado)),
            'chaves'    => array_keys($porEstado),
            'valores'   => array_map('intval', array_values($porEstado)),
        ];
    }
}

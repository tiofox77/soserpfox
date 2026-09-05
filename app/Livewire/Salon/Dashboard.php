<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Appointment;
use App\Models\Salon\Client;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Dashboard - Salão de Beleza')]
class Dashboard extends Component
{
    public $selectedDate;
    public $viewMode = 'day'; // day, week

    public function mount()
    {
        $this->selectedDate = today()->toDateString();
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
        $this->selectedDate = today()->toDateString();
    }

    public function quickConfirm($appointmentId)
    {
        $appointment = Appointment::forTenant()->find($appointmentId);
        if ($appointment) {
            $appointment->confirm();
            $this->dispatch('success', message: 'Agendamento confirmado!');
        }
    }

    public function quickStart($appointmentId)
    {
        $appointment = Appointment::forTenant()->find($appointmentId);
        if ($appointment) {
            $appointment->start();
            $this->dispatch('success', message: 'Atendimento iniciado!');
        }
    }

    public function quickComplete($appointmentId)
    {
        $appointment = Appointment::forTenant()->find($appointmentId);
        if ($appointment) {
            $appointment->complete($appointment->total, 'cash');
            $this->dispatch('success', message: 'Atendimento concluído!');
        }
    }

    public function render()
    {
        $date = Carbon::parse($this->selectedDate);
        
        // Stats
        $completedToday = Appointment::forTenant()
            ->forDate($date)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();
            
        $avgDuration = $completedToday->avg(fn($a) => $a->actual_duration) ?? 0;
        $avgWait = $completedToday->filter(fn($a) => $a->wait_time !== null)->avg('wait_time') ?? 0;
        
        $stats = [
            'today_appointments' => Appointment::forTenant()->forDate($date)->count(),
            'confirmed' => Appointment::forTenant()->forDate($date)->where('status', 'confirmed')->count(),
            'in_progress' => Appointment::forTenant()->forDate($date)->where('status', 'in_progress')->count(),
            'completed' => Appointment::forTenant()->forDate($date)->where('status', 'completed')->count(),
            'revenue_today' => Appointment::forTenant()->forDate($date)->where('status', 'completed')->sum('total'),
            'revenue_month' => Appointment::forTenant()->whereMonth('date', $date->month)->whereYear('date', $date->year)->where('status', 'completed')->sum('total'),
            'avg_duration' => round($avgDuration),
            'avg_wait' => round($avgWait),
        ];

        // Today's appointments
        $appointments = Appointment::forTenant()
            ->forDate($date)
            ->with(['client', 'professional', 'services.service'])
            ->orderBy('start_time')
            ->get();

        // Professionals
        $professionals = Professional::forTenant()->active()->get();

        // Group appointments by professional
        $schedule = [];
        foreach ($professionals as $professional) {
            $schedule[$professional->id] = [
                'professional' => $professional,
                'appointments' => $appointments->where('professional_id', $professional->id),
            ];
        }

        // Next clients
        $nextClients = Appointment::forTenant()
            ->forDate($date)
            ->whereIn('status', ['scheduled', 'confirmed', 'arrived'])
            ->with(['client', 'professional'])
            ->orderBy('start_time')
            ->take(5)
            ->get();

        return view('livewire.salon.dashboard', compact('stats', 'appointments', 'professionals', 'schedule', 'nextClients') + [
            'receitaDias'   => $this->receitaPorDia(),
            'porEstado'     => $this->marcacoesPorEstado(),
            'porProfissional' => $this->receitaPorProfissional(),
            'servicosTop'   => $this->servicosMaisPedidos(),
        ]);
    }

    /**
     * A receita dos últimos 30 dias.
     *
     * O painel mostrava o dia e o mês em números soltos. Nenhum deles diz se
     * a semana passada foi melhor do que esta — e é isso que faz um salão
     * mudar horários ou promoções.
     *
     * Os dias sem marcação vão a ZERO e não desaparecem: uma linha que salta
     * a segunda-feira fechada dá-lhe a receita do domingo.
     */
    private function receitaPorDia(): array
    {
        $dias = 30;
        $desde = today()->subDays($dias - 1);

        $porDia = Appointment::forTenant()
            ->where('status', 'completed')
            ->where('date', '>=', $desde->format('Y-m-d'))
            ->groupBy('dia')
            ->selectRaw('DATE(date) as dia, SUM(total) as total')
            ->pluck('total', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $data = $desde->copy()->addDays($d);

            $etiquetas[] = $data->format('d/m');
            $valores[] = (float) ($porDia[$data->format('Y-m-d')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /**
     * As marcações do mês por estado.
     *
     * As FALTAS são o número que interessa aqui: um salão com muitos
     * "no_show" tem um problema de confirmação, não de procura — e isso não
     * se via em lado nenhum.
     */
    private function marcacoesPorEstado(): array
    {
        $linhas = Appointment::forTenant()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->get();

        $rotulos = [
            'scheduled'   => __('Marcada'),
            'confirmed'   => __('Confirmada'),
            'in_progress' => __('Em curso'),
            'completed'   => __('Concluída'),
            'cancelled'   => __('Cancelada'),
            'no_show'     => __('Faltou'),
        ];

        return [
            'etiquetas' => $linhas->map(fn ($l) => $rotulos[$l->status] ?? $l->status)->all(),
            'chaves'    => $linhas->pluck('status')->all(),
            'valores'   => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    /** Quanto rende cada profissional no mês. */
    private function receitaPorProfissional(): array
    {
        $linhas = DB::table('salon_appointments as a')
            ->leftJoin('salon_professionals as p', 'p.id', '=', 'a.professional_id')
            ->where('a.tenant_id', activeTenantId())
            ->where('a.status', 'completed')
            ->whereMonth('a.date', now()->month)
            ->whereYear('a.date', now()->year)
            ->groupBy('p.id', 'p.name')
            ->selectRaw('COALESCE(p.name, "—") as nome, SUM(a.total) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->map(fn ($l) => (float) $l->total)->all(),
        ];
    }

    /** Os serviços mais pedidos no mês. */
    private function servicosMaisPedidos(): array
    {
        $linhas = DB::table('salon_appointment_services as s')
            ->join('salon_appointments as a', 'a.id', '=', 's.appointment_id')
            ->join('invoicing_products as v', 'v.id', '=', 's.service_id')
            ->where('a.tenant_id', activeTenantId())
            ->whereNotIn('a.status', ['cancelled'])
            ->whereMonth('a.date', now()->month)
            ->whereYear('a.date', now()->year)
            ->groupBy('v.id', 'v.name')
            ->selectRaw('v.name as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }
}

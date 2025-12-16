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
        $appointment = Appointment::find($appointmentId);
        if ($appointment) {
            $appointment->confirm();
            $this->dispatch('success', message: 'Agendamento confirmado!');
        }
    }

    public function quickStart($appointmentId)
    {
        $appointment = Appointment::find($appointmentId);
        if ($appointment) {
            $appointment->start();
            $this->dispatch('success', message: 'Atendimento iniciado!');
        }
    }

    public function quickComplete($appointmentId)
    {
        $appointment = Appointment::find($appointmentId);
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

        return view('livewire.salon.dashboard', compact('stats', 'appointments', 'professionals', 'schedule', 'nextClients'));
    }
}

<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Appointment;
use App\Models\Salon\AppointmentService;
use App\Models\Salon\Client;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Agendamentos - Salão de Beleza')]
class AppointmentManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $statusFilter = '';
    public $dateFilter = '';
    public $professionalFilter = '';
    public $sourceFilter = ''; // 'online', 'system', ou fonte específica
    
    // View mode
    public $viewMode = 'list'; // 'list' or 'calendar'
    public $calendarDate;
    public $calendarView = 'month'; // 'day', 'week', 'month'
    public $showModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $editingId = null;
    public $viewingAppointment = null;
    public $deletingId = null;
    public $deletingAppointment = null;

    // Stats
    public $totalToday = 0;
    public $totalPending = 0;
    public $totalCompletedMonth = 0;
    public $totalRevenue = 0;

    // Form fields
    public $client_id, $professional_id, $date, $start_time;
    public $selected_services = [];
    public $notes, $source = 'system'; // Default: agendamento pelo sistema
    public $clientSearch = '';
    public $showClientDropdown = false;

    // Quick Client Modal
    public $showClientModal = false;
    public $quickClientName = '';
    public $quickClientPhone = '';
    public $quickClientEmail = '';
    public $quickClientNif = '999999999';
    public $quickClientAddress = '';
    public $quickClientCity = '';
    public $quickClientCountry = 'AO';

    protected function rules()
    {
        return [
            'professional_id' => 'required|exists:salon_professionals,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'selected_services' => 'required|array|min:1',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $appointment = Appointment::with(['services', 'client'])->find($id);
            $this->editingId = $id;
            $this->client_id = $appointment->client_id;
            $this->professional_id = $appointment->professional_id;
            $this->date = $appointment->date->format('Y-m-d');
            $this->start_time = Carbon::parse($appointment->start_time)->format('H:i');
            $this->selected_services = $appointment->services->pluck('service_id')->toArray();
            $this->notes = $appointment->notes;
            $this->source = $appointment->source;
            // Preencher pesquisa de cliente
            if ($appointment->client) {
                $this->clientSearch = $appointment->client->name . ($appointment->client->phone ? ' - ' . $appointment->client->phone : '');
            }
        } else {
            $this->date = today()->toDateString();
        }
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingAppointment = Appointment::with(['client', 'professional', 'services.service'])->find($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingAppointment = null;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function openDeleteModal($id)
    {
        $this->deletingAppointment = Appointment::with(['client', 'professional'])->find($id);
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingAppointment = null;
    }

    public function confirmCancel()
    {
        Appointment::find($this->deletingId)->cancel('Cancelado pelo sistema');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Agendamento cancelado!']);
        $this->cancelDelete();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'dateFilter', 'professionalFilter', 'sourceFilter']);
    }

    public function mount()
    {
        $this->calendarDate = today()->toDateString();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function setCalendarView($view)
    {
        $this->calendarView = $view;
    }

    public function previousPeriod()
    {
        $date = Carbon::parse($this->calendarDate);
        
        switch ($this->calendarView) {
            case 'day':
                $this->calendarDate = $date->subDay()->toDateString();
                break;
            case 'week':
                $this->calendarDate = $date->subWeek()->toDateString();
                break;
            case 'month':
                $this->calendarDate = $date->subMonth()->toDateString();
                break;
        }
    }

    public function nextPeriod()
    {
        $date = Carbon::parse($this->calendarDate);
        
        switch ($this->calendarView) {
            case 'day':
                $this->calendarDate = $date->addDay()->toDateString();
                break;
            case 'week':
                $this->calendarDate = $date->addWeek()->toDateString();
                break;
            case 'month':
                $this->calendarDate = $date->addMonth()->toDateString();
                break;
        }
    }

    public function goToToday()
    {
        $this->calendarDate = today()->toDateString();
    }

    public function selectDate($date)
    {
        $this->calendarDate = $date;
        $this->calendarView = 'day';
    }

    public function getCalendarDataProperty()
    {
        $date = Carbon::parse($this->calendarDate);
        
        switch ($this->calendarView) {
            case 'day':
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();
                break;
            case 'week':
                $start = $date->copy()->startOfWeek();
                $end = $date->copy()->endOfWeek();
                break;
            case 'month':
            default:
                $start = $date->copy()->startOfMonth()->startOfWeek();
                $end = $date->copy()->endOfMonth()->endOfWeek();
                break;
        }

        $appointments = Appointment::forTenant()
            ->with(['client', 'professional', 'services.service'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($this->professionalFilter, fn($q) => $q->where('professional_id', $this->professionalFilter))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn($a) => $a->date->format('Y-m-d'));

        return [
            'start' => $start,
            'end' => $end,
            'appointments' => $appointments,
            'currentDate' => $date,
        ];
    }

    public function updatedClientSearch()
    {
        $this->showClientDropdown = strlen($this->clientSearch) > 0;
    }

    public function selectClient($id)
    {
        $client = Client::find($id);
        if ($client) {
            $this->client_id = $client->id;
            $this->clientSearch = $client->name . ($client->phone ? ' - ' . $client->phone : '');
            $this->showClientDropdown = false;
        }
    }

    public function clearClient()
    {
        $this->client_id = null;
        $this->clientSearch = '';
        $this->showClientDropdown = false;
    }

    public function getFilteredClientsProperty()
    {
        if (empty($this->clientSearch)) {
            return Client::forTenant()->orderBy('name')->limit(10)->get();
        }

        return Client::forTenant()
            ->where(function($q) {
                $q->where('name', 'like', '%' . $this->clientSearch . '%')
                  ->orWhere('phone', 'like', '%' . $this->clientSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->clientSearch . '%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function openClientModal()
    {
        $this->resetClientForm();
        $this->showClientModal = true;
    }

    public function closeClientModal()
    {
        $this->showClientModal = false;
        $this->resetClientForm();
    }

    public function createQuickClient()
    {
        $this->validate([
            'quickClientName' => 'required|string|min:2|max:255',
            'quickClientPhone' => 'nullable|string|max:20',
            'quickClientEmail' => 'nullable|email|max:255',
            'quickClientNif' => 'nullable|string|max:20',
            'quickClientAddress' => 'nullable|string|max:255',
            'quickClientCity' => 'nullable|string|max:100',
            'quickClientCountry' => 'nullable|string|max:100',
        ]);

        $client = Client::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->quickClientName,
            'phone' => $this->quickClientPhone,
            'mobile' => $this->quickClientPhone,
            'email' => $this->quickClientEmail,
            'nif' => $this->quickClientNif ?: '999999999',
            'address' => $this->quickClientAddress,
            'city' => $this->quickClientCity,
            'country' => $this->quickClientCountry,
            'type' => 'particular',
            'is_active' => true,
        ]);

        $this->client_id = $client->id;
        $this->showClientModal = false;
        $this->resetClientForm();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Cliente criado: ' . $client->name
        ]);
    }

    private function resetClientForm()
    {
        $this->quickClientName = '';
        $this->quickClientPhone = '';
        $this->quickClientEmail = '';
        $this->quickClientNif = '999999999';
        $this->quickClientAddress = '';
        $this->quickClientCity = '';
        $this->quickClientCountry = 'AO';
    }

    public function save()
    {
        $this->validate();

        // Calculate duration and end time
        $services = Service::whereIn('id', $this->selected_services)->get();
        $totalDuration = $services->sum('duration');
        $startTime = Carbon::parse($this->start_time);
        $endTime = $startTime->copy()->addMinutes($totalDuration);
        $subtotal = $services->sum('price');

        $data = [
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'date' => $this->date,
            'start_time' => $startTime->format('H:i'),
            'end_time' => $endTime->format('H:i'),
            'total_duration' => $totalDuration,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'notes' => $this->notes,
            'source' => $this->source,
        ];

        if ($this->editingId) {
            $appointment = Appointment::find($this->editingId);
            $appointment->update($data);
            $appointment->services()->delete();
        } else {
            $appointment = Appointment::create($data);
        }

        // Add services
        foreach ($services as $service) {
            AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'professional_id' => $this->professional_id,
                'duration' => $service->duration,
                'price' => $service->price,
                'total' => $service->price,
            ]);
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => $this->editingId ? 'Agendamento atualizado!' : 'Agendamento criado!']);
        $this->showModal = false;
        $this->resetForm();
    }

    public function confirm($id)
    {
        Appointment::find($id)->confirm();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Agendamento confirmado!']);
    }

    public function markArrived($id)
    {
        Appointment::find($id)->markArrived();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente chegou!']);
    }

    public function start($id)
    {
        Appointment::find($id)->start();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Atendimento iniciado!']);
    }

    public function complete($id)
    {
        $appointment = Appointment::find($id);
        $appointment->complete($appointment->total, 'cash');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Atendimento concluído!']);
    }

    public function cancel($id)
    {
        Appointment::find($id)->cancel('Cancelado pelo sistema');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Agendamento cancelado!']);
    }

    public function markNoShow($id)
    {
        Appointment::find($id)->markNoShow();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Marcado como não compareceu!']);
    }

    private function resetForm()
    {
        $this->reset(['editingId', 'client_id', 'professional_id', 'date', 'start_time', 'selected_services', 'notes', 'clientSearch', 'showClientDropdown']);
        $this->source = 'system';
    }

    public function render()
    {
        // Stats
        $this->totalToday = Appointment::forTenant()->forDate(today())->count();
        $this->totalPending = Appointment::forTenant()->upcoming()->count();
        $this->totalCompletedMonth = Appointment::forTenant()->whereMonth('date', now()->month)->completed()->count();
        $this->totalRevenue = Appointment::forTenant()->whereMonth('date', now()->month)->completed()->sum('total');

        // Stats por fonte
        $totalOnline = Appointment::forTenant()->onlineBooking()->upcoming()->count();
        $totalSystem = Appointment::forTenant()->systemBooking()->upcoming()->count();

        $appointments = Appointment::forTenant()
            ->with(['client', 'professional', 'services.service'])
            ->when($this->search, fn($q) => $q->whereHas('client', fn($c) => $c->where('name', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->professionalFilter, fn($q) => $q->where('professional_id', $this->professionalFilter))
            ->when($this->sourceFilter, fn($q) => $q->bySource($this->sourceFilter))
            ->when($this->dateFilter === 'today', fn($q) => $q->forDate(today()))
            ->when($this->dateFilter === 'tomorrow', fn($q) => $q->forDate(today()->addDay()))
            ->when($this->dateFilter === 'week', fn($q) => $q->whereBetween('date', [today(), today()->addWeek()]))
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->paginate($this->perPage);

        $clients = Client::forTenant()->orderBy('name')->get();
        $professionals = Professional::forTenant()->active()->orderBy('name')->get();
        $services = Service::forTenant()->active()->with('category')->orderBy('name')->get();
        
        // Fontes disponíveis para filtro
        $sources = Appointment::SOURCES;
        $sourceColors = Appointment::SOURCE_COLORS;
        $sourceIcons = Appointment::SOURCE_ICONS;

        return view('livewire.salon.appointments.appointments', compact(
            'appointments', 'clients', 'professionals', 'services',
            'totalOnline', 'totalSystem', 'sources', 'sourceColors', 'sourceIcons'
        ));
    }
}

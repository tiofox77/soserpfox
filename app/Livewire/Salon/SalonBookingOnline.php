<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\Service;
use App\Models\Salon\Professional;
use App\Models\Salon\Client;
use App\Models\Salon\Appointment;
use App\Models\Salon\AppointmentService;
use App\Models\Salon\ServiceCategory;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SalonBookingOnline extends Component
{
    public $slug;
    public $settings;
    public $tenantId;
    
    // Step control: 1 = Serviços, 2 = Profissional/Data, 3 = Dados/Auth, 4 = Confirmação
    public $step = 1;
    
    // Serviços selecionados
    public $selectedServices = [];
    public $selectedCategory = null;
    
    // Profissional e horário
    public $selectedProfessional = null;
    public $selectedDate = null;
    public $selectedTime = null;
    
    // Modo de autenticação: 'select', 'login', 'register', 'guest'
    public $authMode = 'select';
    
    // Cliente autenticado
    public $authenticatedClient = null;
    public $clientId = null;
    
    // Dados de login
    public $loginPhone = '';
    public $loginPassword = '';
    public $loginError = '';
    
    // Dados de registo
    public $registerName = '';
    public $registerPhone = '';
    public $registerEmail = '';
    public $registerPassword = '';
    public $registerPasswordConfirm = '';
    public $registerError = '';
    
    // Dados do cliente (reserva rápida)
    public $clientName = '';
    public $clientPhone = '';
    public $clientEmail = '';
    public $clientNotes = '';
    
    // Resultado
    public $appointmentNumber = null;
    public $confirmationData = null;

    protected $rules = [
        'clientName' => 'required|string|min:2|max:255',
        'clientPhone' => 'required|string|min:9|max:50',
        'clientEmail' => 'nullable|email|max:255',
    ];

    public function mount($slug)
    {
        $this->slug = $slug;
        $this->settings = SalonSettings::getBySlug($slug);
        
        if (!$this->settings) {
            abort(404, 'Salão não encontrado');
        }
        
        if (!$this->settings->online_booking_enabled) {
            abort(403, 'Agendamento online não está disponível');
        }
        
        $this->tenantId = $this->settings->tenant_id;
        $this->selectedDate = now()->addDay()->toDateString();
    }

    public function selectCategory($categoryId)
    {
        $this->selectedCategory = $categoryId ?: null;
    }

    public function toggleService($serviceId)
    {
        if (in_array($serviceId, $this->selectedServices)) {
            $this->selectedServices = array_values(array_diff($this->selectedServices, [$serviceId]));
        } else {
            $this->selectedServices[] = $serviceId;
        }
    }

    public function nextStep()
    {
        if ($this->step === 1 && count($this->selectedServices) === 0) {
            $this->dispatch('error', message: 'Selecione pelo menos um serviço');
            return;
        }
        
        if ($this->step === 2) {
            if (!$this->selectedProfessional) {
                $this->dispatch('error', message: 'Selecione um profissional');
                return;
            }
            if (!$this->selectedDate || !$this->selectedTime) {
                $this->dispatch('error', message: 'Selecione data e horário');
                return;
            }
        }
        
        $this->step++;
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep($step)
    {
        if ($step <= $this->step) {
            $this->step = $step;
        }
    }

    public function selectProfessional($professionalId)
    {
        $this->selectedProfessional = $professionalId;
        $this->selectedTime = null; // Reset time when professional changes
    }

    public function selectDate($date)
    {
        $this->selectedDate = $date;
        $this->selectedTime = null; // Reset time when date changes
    }

    public function selectTime($time)
    {
        $this->selectedTime = $time;
    }

    public function getSelectedServicesDataProperty()
    {
        if (empty($this->selectedServices)) {
            return collect();
        }
        
        return Service::where('tenant_id', $this->tenantId)
            ->whereIn('id', $this->selectedServices)
            ->get();
    }

    public function getTotalDurationProperty()
    {
        return $this->selectedServicesData->sum('duration');
    }

    public function getTotalPriceProperty()
    {
        return $this->selectedServicesData->sum('price');
    }

    public function getAvailableSlotsProperty()
    {
        if (!$this->selectedProfessional || !$this->selectedDate) {
            return [];
        }
        
        $professional = Professional::find($this->selectedProfessional);
        if (!$professional) {
            return [];
        }
        
        $date = Carbon::parse($this->selectedDate);
        $dayOfWeek = $date->dayOfWeekIso;
        
        // Verificar se o profissional trabalha neste dia
        $workingDays = $professional->working_days ?? $this->settings->working_days ?? [];
        if (!in_array($dayOfWeek, $workingDays)) {
            return [];
        }
        
        // Horários de trabalho
        $workStart = Carbon::parse($professional->work_start ?? $this->settings->opening_time ?? '09:00');
        $workEnd = Carbon::parse($professional->work_end ?? $this->settings->closing_time ?? '19:00');
        $lunchStart = $professional->lunch_start ? Carbon::parse($professional->lunch_start) : null;
        $lunchEnd = $professional->lunch_end ? Carbon::parse($professional->lunch_end) : null;
        
        // Intervalo de slots
        $interval = $this->settings->slot_interval ?? 30;
        $totalDuration = $this->totalDuration;
        
        // Buscar agendamentos existentes do profissional neste dia
        $existingAppointments = Appointment::where('tenant_id', $this->tenantId)
            ->where('professional_id', $this->selectedProfessional)
            ->where('date', $this->selectedDate)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();
        
        // Gerar slots disponíveis
        $slots = [];
        $current = $workStart->copy()->setDate($date->year, $date->month, $date->day);
        $endOfDay = $workEnd->copy()->setDate($date->year, $date->month, $date->day);
        
        // Antecedência mínima
        $minTime = now()->addHours($this->settings->min_advance_booking_hours ?? 2);
        
        while ($current->copy()->addMinutes($totalDuration)->lte($endOfDay)) {
            $slotStart = $current->copy();
            $slotEnd = $current->copy()->addMinutes($totalDuration);
            
            // Verificar se é hoje e já passou a antecedência mínima
            if ($slotStart->lt($minTime)) {
                $current->addMinutes($interval);
                continue;
            }
            
            // Verificar se está no horário de almoço
            if ($lunchStart && $lunchEnd) {
                $lunchStartFull = $lunchStart->copy()->setDate($date->year, $date->month, $date->day);
                $lunchEndFull = $lunchEnd->copy()->setDate($date->year, $date->month, $date->day);
                
                if ($slotStart->lt($lunchEndFull) && $slotEnd->gt($lunchStartFull)) {
                    $current->addMinutes($interval);
                    continue;
                }
            }
            
            // Verificar conflitos com agendamentos existentes
            $hasConflict = false;
            foreach ($existingAppointments as $appointment) {
                $appointmentStart = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->start_time);
                $appointmentEnd = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->end_time);
                
                if ($slotStart->lt($appointmentEnd) && $slotEnd->gt($appointmentStart)) {
                    $hasConflict = true;
                    break;
                }
            }
            
            if (!$hasConflict) {
                $slots[] = $slotStart->format('H:i');
            }
            
            $current->addMinutes($interval);
        }
        
        return $slots;
    }

    public function getAvailableDatesProperty()
    {
        $dates = [];
        $start = now()->addDay();
        $maxDays = $this->settings->max_advance_booking_days ?? 30;
        
        for ($i = 0; $i < $maxDays; $i++) {
            $date = $start->copy()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso;
            
            $workingDays = $this->settings->working_days ?? [1, 2, 3, 4, 5, 6];
            
            if (in_array($dayOfWeek, $workingDays)) {
                $dates[] = [
                    'date' => $date->toDateString(),
                    'formatted' => $date->isoFormat('ddd, D MMM'),
                    'isToday' => $date->isToday(),
                    'isTomorrow' => $date->isTomorrow(),
                ];
            }
        }
        
        return $dates;
    }

    /**
     * Definir modo de autenticação
     */
    public function setAuthMode($mode)
    {
        $this->authMode = $mode;
        $this->loginError = '';
        $this->registerError = '';
    }

    /**
     * Login do cliente
     */
    public function loginClient()
    {
        $this->loginError = '';
        
        if (empty($this->loginPhone)) {
            $this->loginError = 'Informe o número de telefone';
            return;
        }
        
        // Normalizar telefone
        $phone = preg_replace('/[^0-9]/', '', $this->loginPhone);
        
        // Procurar cliente pelo telefone
        $client = Client::where('tenant_id', $this->tenantId)
            ->where(function($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%")
                  ->orWhere('mobile', 'like', "%{$phone}%");
            })
            ->first();
        
        if (!$client) {
            $this->loginError = 'Cliente não encontrado. Crie uma conta ou faça reserva rápida.';
            return;
        }
        
        // Se cliente tem password, verificar
        $salonData = $client->salon_data;
        if (!empty($salonData['password'])) {
            if (empty($this->loginPassword)) {
                $this->loginError = 'Informe a password';
                return;
            }
            if (!Hash::check($this->loginPassword, $salonData['password'])) {
                $this->loginError = 'Password incorreta';
                return;
            }
        }
        
        // Login bem sucedido
        $this->authenticatedClient = $client;
        $this->clientId = $client->id;
        $this->clientName = $client->name;
        $this->clientPhone = $client->phone ?? $client->mobile;
        $this->clientEmail = $client->email;
        $this->authMode = 'authenticated';
        
        $this->dispatch('success', message: 'Bem-vindo, ' . $client->first_name . '!');
    }

    /**
     * Registar novo cliente
     */
    public function registerClient()
    {
        $this->registerError = '';
        
        // Validações
        if (empty($this->registerName)) {
            $this->registerError = 'Informe o seu nome';
            return;
        }
        if (empty($this->registerPhone)) {
            $this->registerError = 'Informe o telefone';
            return;
        }
        if (!empty($this->registerPassword) && strlen($this->registerPassword) < 4) {
            $this->registerError = 'Password deve ter pelo menos 4 caracteres';
            return;
        }
        if ($this->registerPassword !== $this->registerPasswordConfirm) {
            $this->registerError = 'As passwords não coincidem';
            return;
        }
        
        // Normalizar telefone
        $phone = preg_replace('/[^0-9]/', '', $this->registerPhone);
        
        // Verificar se já existe
        $existingClient = Client::where('tenant_id', $this->tenantId)
            ->where(function($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%")
                  ->orWhere('mobile', 'like', "%{$phone}%");
            })
            ->first();
        
        if ($existingClient) {
            $this->registerError = 'Já existe uma conta com este telefone. Faça login.';
            return;
        }
        
        try {
            // Criar cliente
            $client = Client::create([
                'tenant_id' => $this->tenantId,
                'name' => $this->registerName,
                'phone' => $this->registerPhone,
                'mobile' => $this->registerPhone,
                'email' => $this->registerEmail,
            ]);
            
            // Se definiu password, guardar nos dados do salão
            if (!empty($this->registerPassword)) {
                $client->updateSalonData([
                    'password' => Hash::make($this->registerPassword),
                    'registered_at' => now()->toISOString(),
                ]);
            }
            
            // Login automático
            $this->authenticatedClient = $client;
            $this->clientId = $client->id;
            $this->clientName = $client->name;
            $this->clientPhone = $client->phone;
            $this->clientEmail = $client->email;
            $this->authMode = 'authenticated';
            
            $this->dispatch('success', message: 'Conta criada com sucesso!');
            
        } catch (\Exception $e) {
            \Log::error('Erro ao registar cliente', ['error' => $e->getMessage()]);
            $this->registerError = 'Erro ao criar conta. Tente novamente.';
        }
    }

    /**
     * Continuar como convidado (reserva rápida)
     */
    public function continueAsGuest()
    {
        $this->authMode = 'guest';
    }

    /**
     * Logout do cliente
     */
    public function logoutClient()
    {
        $this->authenticatedClient = null;
        $this->clientId = null;
        $this->authMode = 'select';
        $this->clientName = '';
        $this->clientPhone = '';
        $this->clientEmail = '';
    }

    /**
     * Obter agendamentos anteriores do cliente autenticado
     */
    public function getClientAppointmentsProperty()
    {
        if (!$this->authenticatedClient) {
            return collect();
        }
        
        return Appointment::where('tenant_id', $this->tenantId)
            ->where('client_id', $this->authenticatedClient->id)
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->take(5)
            ->get();
    }

    public function submitBooking()
    {
        // Validar apenas se for guest
        if ($this->authMode === 'guest') {
            $this->validate();
        }
        
        try {
            $client = null;
            
            // Se está autenticado, usar o cliente existente
            if ($this->authenticatedClient) {
                $client = $this->authenticatedClient;
                // Atualizar email se foi preenchido
                if ($this->clientEmail && $this->clientEmail !== $client->email) {
                    $client->update(['email' => $this->clientEmail]);
                }
            } else {
                // Criar ou encontrar cliente (reserva rápida)
                $client = Client::firstOrCreate(
                    [
                        'tenant_id' => $this->tenantId,
                        'phone' => $this->clientPhone,
                    ],
                    [
                        'name' => $this->clientName,
                        'email' => $this->clientEmail,
                    ]
                );
                
                // Atualizar nome e email se cliente já existe
                $client->update([
                    'name' => $this->clientName,
                    'email' => $this->clientEmail ?: $client->email,
                ]);
            }
            
            // Calcular horário de fim
            $startTime = Carbon::parse($this->selectedTime);
            $endTime = $startTime->copy()->addMinutes($this->totalDuration);
            
            // Criar agendamento
            $appointment = Appointment::create([
                'tenant_id' => $this->tenantId,
                'client_id' => $client->id,
                'professional_id' => $this->selectedProfessional,
                'date' => $this->selectedDate,
                'start_time' => $startTime->format('H:i'),
                'end_time' => $endTime->format('H:i'),
                'total_duration' => $this->totalDuration,
                'subtotal' => $this->totalPrice,
                'total' => $this->totalPrice,
                'notes' => $this->clientNotes,
                'source' => 'website',
                'status' => $this->settings->require_confirmation ? 'scheduled' : 'confirmed',
            ]);
            
            // Adicionar serviços ao agendamento
            foreach ($this->selectedServicesData as $service) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'professional_id' => $this->selectedProfessional,
                    'duration' => $service->duration,
                    'price' => $service->price,
                    'total' => $service->price,
                ]);
            }
            
            $this->appointmentNumber = $appointment->appointment_number;
            $this->confirmationData = [
                'appointment' => $appointment->load(['client', 'professional', 'services.service']),
                'services' => $this->selectedServicesData,
                'professional' => Professional::find($this->selectedProfessional),
            ];
            
            $this->step = 4;
            
        } catch (\Exception $e) {
            \Log::error('Erro ao criar agendamento online', ['error' => $e->getMessage()]);
            $this->dispatch('error', message: 'Erro ao processar agendamento. Tente novamente.');
        }
    }

    public function render()
    {
        $categories = ServiceCategory::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
        
        $servicesQuery = Service::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->onlineBooking();
        
        if ($this->selectedCategory) {
            $servicesQuery->forCategory($this->selectedCategory);
        }
        
        $services = $servicesQuery->orderBy('name')->get();
        
        $professionals = Professional::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->where('accepts_online_booking', true)
            ->orderBy('name')
            ->get();
        
        return view('livewire.salon.booking-online', compact('categories', 'services', 'professionals'))
            ->layout('layouts.guest');
    }
}

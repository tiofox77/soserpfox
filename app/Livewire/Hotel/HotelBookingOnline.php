<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Room;
use App\Models\Hotel\Reservation;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class HotelBookingOnline extends Component
{
    public $slug;
    public $settings;
    public $tenantId;
    
    // Step control: 1 = Quarto, 2 = Datas, 3 = Auth/Dados, 4 = Confirmacao
    public $step = 1;
    
    // Quarto selecionado
    public $selectedRoomType = null;
    public $selectedRoom = null;
    
    // Galeria
    public $viewingRoomId = null;
    public $viewingRoom = null;
    
    // Datas
    public $checkInDate;
    public $checkOutDate;
    public $nights = 1;
    public $adults = 2;
    public $children = 0;
    
    // Modo de autenticacao: 'select', 'login', 'register', 'guest', 'authenticated'
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
    
    // Dados do cliente (reserva rapida)
    public $clientName = '';
    public $clientPhone = '';
    public $clientEmail = '';
    public $clientNotes = '';
    
    // Resultado
    public $reservationNumber = null;
    public $confirmationData = null;

    protected $rules = [
        'clientName' => 'required|string|min:2|max:255',
        'clientPhone' => 'required|string|min:9|max:50',
        'clientEmail' => 'nullable|email|max:255',
        'checkInDate' => 'required|date|after_or_equal:today',
        'checkOutDate' => 'required|date|after:checkInDate',
    ];

    public function mount($slug)
    {
        $this->slug = $slug;
        $this->settings = HotelSettings::findBySlug($slug);
        
        if (!$this->settings) {
            abort(404, 'Hotel não encontrado');
        }
        
        if (!$this->settings->online_booking_enabled) {
            abort(403, 'Reservas online não estão disponíveis');
        }
        
        $this->tenantId = $this->settings->tenant_id;
        $this->checkInDate = now()->addDay()->toDateString();
        $this->checkOutDate = now()->addDays(2)->toDateString();
        $this->calculateNights();
    }

    public function updatedCheckInDate()
    {
        $this->calculateNights();
    }

    public function updatedCheckOutDate()
    {
        $this->calculateNights();
    }

    public function calculateNights()
    {
        if ($this->checkInDate && $this->checkOutDate) {
            $checkIn = Carbon::parse($this->checkInDate);
            $checkOut = Carbon::parse($this->checkOutDate);
            $this->nights = max(1, $checkIn->diffInDays($checkOut));
        }
    }

    public function selectRoomType($roomTypeId)
    {
        $roomType = RoomType::where('tenant_id', $this->tenantId)
            ->where('id', $roomTypeId)
            ->where('is_active', true)
            ->first();
        
        if ($roomType) {
            $this->selectedRoomType = $roomType;
        }
    }

    public function viewRoomGallery($roomId)
    {
        $this->viewingRoomId = $roomId;
        $this->viewingRoom = RoomType::find($roomId);
    }

    public function closeGallery()
    {
        $this->viewingRoomId = null;
        $this->viewingRoom = null;
    }

    public function goToStep($step)
    {
        if ($step < $this->step || $this->canProceed()) {
            $this->step = $step;
        }
    }

    public function nextStep()
    {
        if ($this->step === 1 && !$this->selectedRoomType) {
            session()->flash('error', 'Selecione um quarto');
            return;
        }
        
        if ($this->step === 2) {
            if (!$this->checkInDate || !$this->checkOutDate) {
                session()->flash('error', 'Selecione as datas');
                return;
            }
        }
        
        if ($this->step < 4) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    // ========== METODOS DE AUTENTICACAO ==========
    
    public function setAuthMode($mode)
    {
        $this->authMode = $mode;
        $this->loginError = '';
        $this->registerError = '';
    }

    public function loginClient()
    {
        $this->loginError = '';
        
        if (empty($this->loginPhone)) {
            $this->loginError = 'Informe o numero de telefone';
            return;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $this->loginPhone);
        
        $client = Client::where('tenant_id', $this->tenantId)
            ->where(function($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%")
                  ->orWhere('mobile', 'like', "%{$phone}%");
            })
            ->first();
        
        if (!$client) {
            $this->loginError = 'Cliente nao encontrado. Crie uma conta ou faca reserva rapida.';
            return;
        }
        
        // Se cliente tem password, verificar
        $hotelData = $client->hotel_data ?? [];
        if (!empty($hotelData['password'])) {
            if (empty($this->loginPassword)) {
                $this->loginError = 'Informe a password';
                return;
            }
            if (!Hash::check($this->loginPassword, $hotelData['password'])) {
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
    }

    public function registerClient()
    {
        $this->registerError = '';
        
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
            $this->registerError = 'As passwords nao coincidem';
            return;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $this->registerPhone);
        
        $existingClient = Client::where('tenant_id', $this->tenantId)
            ->where(function($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%")
                  ->orWhere('mobile', 'like', "%{$phone}%");
            })
            ->first();
        
        if ($existingClient) {
            $this->registerError = 'Ja existe uma conta com este telefone. Faca login.';
            return;
        }
        
        try {
            $client = Client::create([
                'tenant_id' => $this->tenantId,
                'name' => $this->registerName,
                'phone' => $this->registerPhone,
                'mobile' => $this->registerPhone,
                'email' => $this->registerEmail,
            ]);
            
            // Guardar password nos dados do hotel
            if (!empty($this->registerPassword)) {
                $hotelData = $client->hotel_data ?? [];
                $hotelData['password'] = Hash::make($this->registerPassword);
                $hotelData['registered_at'] = now()->toISOString();
                $client->update(['hotel_data' => $hotelData]);
            }
            
            // Login automatico
            $this->authenticatedClient = $client;
            $this->clientId = $client->id;
            $this->clientName = $client->name;
            $this->clientPhone = $client->phone;
            $this->clientEmail = $client->email;
            $this->authMode = 'authenticated';
            
        } catch (\Exception $e) {
            \Log::error('Erro ao registar cliente hotel', ['error' => $e->getMessage()]);
            $this->registerError = 'Erro ao criar conta. Tente novamente.';
        }
    }

    public function continueAsGuest()
    {
        $this->authMode = 'guest';
    }

    public function logoutClient()
    {
        $this->authenticatedClient = null;
        $this->clientId = null;
        $this->authMode = 'select';
        $this->clientName = '';
        $this->clientPhone = '';
        $this->clientEmail = '';
    }

    // ========== FIM METODOS DE AUTENTICACAO ==========

    public function canProceed()
    {
        return match($this->step) {
            1 => $this->selectedRoomType !== null,
            2 => $this->checkInDate && $this->checkOutDate && $this->nights > 0,
            3 => !empty($this->clientName) && !empty($this->clientPhone),
            default => true,
        };
    }

    public function getTotalPrice()
    {
        if (!$this->selectedRoomType) return 0;
        return $this->selectedRoomType->base_price * $this->nights;
    }

    public function submit()
    {
        try {
            if (!$this->selectedRoomType) {
                session()->flash('error', 'Por favor, selecione um tipo de quarto.');
                return;
            }
            
            // Validar apenas se for guest
            if ($this->authMode === 'guest') {
                $this->validate();
            }

            // Encontrar quarto disponivel
            $availableRoom = Room::where('tenant_id', $this->tenantId)
                ->where('room_type_id', $this->selectedRoomType->id)
                ->where('status', 'available')
                ->first();

            if (!$availableRoom) {
                // Se nao houver quarto disponivel, criar um automaticamente
                $availableRoom = Room::create([
                    'tenant_id' => $this->tenantId,
                    'room_type_id' => $this->selectedRoomType->id,
                    'room_number' => 'AUTO-' . now()->timestamp,
                    'floor' => '1',
                    'status' => 'available',
                ]);
            }

            $client = null;
            
            // Se esta autenticado, usar o cliente existente
            if ($this->authenticatedClient) {
                $client = $this->authenticatedClient;
                // Atualizar email se foi preenchido
                if ($this->clientEmail && $this->clientEmail !== $client->email) {
                    $client->update(['email' => $this->clientEmail]);
                }
            } else {
                // Criar ou encontrar cliente (reserva rapida)
                $client = Client::firstOrCreate(
                    ['phone' => $this->clientPhone, 'tenant_id' => $this->tenantId],
                    [
                        'name' => $this->clientName,
                        'email' => $this->clientEmail,
                    ]
                );
                
                // Atualizar nome e email se cliente ja existe
                $client->update([
                    'name' => $this->clientName,
                    'email' => $this->clientEmail ?: $client->email,
                ]);
            }

            // Calcular valores
            $totalPrice = $this->getTotalPrice();
            $depositAmount = $this->settings->require_deposit 
                ? ($totalPrice * $this->settings->deposit_percent / 100) 
                : 0;

            // Criar reserva
            $reservation = Reservation::create([
                'tenant_id' => $this->tenantId,
                'client_id' => $client->id,
                'room_id' => $availableRoom->id,
                'room_type_id' => $this->selectedRoomType->id,
                'check_in_date' => $this->checkInDate,
                'check_out_date' => $this->checkOutDate,
                'adults' => $this->adults,
                'children' => $this->children,
                'nights' => $this->nights,
                'total_amount' => $totalPrice,
                'paid_amount' => 0,
                'status' => 'pending',
                'payment_status' => 'pending',
                'source' => 'online',
                'special_requests' => $this->clientNotes,
            ]);

            $this->reservationNumber = $reservation->reservation_number;
            $this->confirmationData = [
                'reservation' => $reservation,
                'client' => $client,
                'roomType' => $this->selectedRoomType,
                'checkIn' => $this->checkInDate,
                'checkOut' => $this->checkOutDate,
                'nights' => $this->nights,
                'total' => $totalPrice,
            ];
            
            $this->step = 4;
            
        } catch (\Exception $e) {
            \Log::error('Erro ao criar reserva hotel online', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Erro ao processar reserva: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $roomTypes = RoomType::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('base_price')
            ->get();

        $featuredRoomTypes = $roomTypes;
        if (!empty($this->settings->featured_rooms)) {
            $featuredRoomTypes = $roomTypes->whereIn('id', $this->settings->featured_rooms);
            if ($featuredRoomTypes->isEmpty()) {
                $featuredRoomTypes = $roomTypes;
            }
        }

        return view('livewire.hotel.booking-online', [
            'roomTypes' => $roomTypes,
            'featuredRoomTypes' => $featuredRoomTypes,
        ])->layout('layouts.guest');
    }
    
    // Computed property para o preço total
    public function getTotalPriceProperty()
    {
        if (!$this->selectedRoomType) return 0;
        return $this->selectedRoomType->base_price * $this->nights;
    }
}

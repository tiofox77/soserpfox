<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use App\Models\Hotel\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;

class BookingOnline extends Component
{
    public $tenantSlug;
    public $tenant;
    
    // Step control
    public $step = 1; // 1: Datas, 2: Quarto, 3: Dados, 4: Confirmação
    
    // Busca
    public $check_in_date;
    public $check_out_date;
    public $adults = 2;
    public $children = 0;
    
    // Quarto selecionado
    public $selectedRoomType = null;
    public $selectedRoomTypeData = null;
    
    // Dados do hóspede
    public $guest_name = '';
    public $guest_email = '';
    public $guest_phone = '';
    public $guest_document_type = 'bi';
    public $guest_document_number = '';
    public $special_requests = '';
    
    // Resultado
    public $reservationNumber = null;
    public $confirmationCode = null;

    protected $rules = [
        'check_in_date' => 'required|date|after_or_equal:today',
        'check_out_date' => 'required|date|after:check_in_date',
        'adults' => 'required|integer|min:1|max:10',
        'children' => 'required|integer|min:0|max:10',
        'guest_name' => 'required|string|max:255',
        'guest_email' => 'required|email|max:255',
        'guest_phone' => 'required|string|max:50',
    ];

    public function mount($tenant = null)
    {
        $this->tenantSlug = $tenant;
        $this->check_in_date = now()->addDay()->toDateString();
        $this->check_out_date = now()->addDays(2)->toDateString();
        
        // Encontrar o tenant pelo slug ou usar o primeiro ativo
        if ($tenant) {
            $this->tenant = Tenant::where('slug', $tenant)->first();
        }
        
        if (!$this->tenant) {
            // Para demo, usar o primeiro tenant com quartos
            $this->tenant = Tenant::whereHas('id', function($q) {
                // Verificar se tem tipos de quarto
            })->first() ?? Tenant::first();
        }
    }

    public function searchRooms()
    {
        $this->validate([
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
        ]);

        $this->step = 2;
    }

    public function selectRoom($roomTypeId)
    {
        $this->selectedRoomType = $roomTypeId;
        $this->selectedRoomTypeData = RoomType::find($roomTypeId);
        $this->step = 3;
    }

    public function backToSearch()
    {
        $this->step = 1;
        $this->selectedRoomType = null;
        $this->selectedRoomTypeData = null;
    }

    public function backToRooms()
    {
        $this->step = 2;
    }

    public function submitBooking()
    {
        $this->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:50',
        ]);

        if (!$this->tenant || !$this->selectedRoomType) {
            $this->dispatch('error', message: 'Erro ao processar reserva.');
            return;
        }

        try {
            // Criar ou encontrar hóspede
            $guest = Guest::firstOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'email' => $this->guest_email,
                ],
                [
                    'name' => $this->guest_name,
                    'phone' => $this->guest_phone,
                    'document_type' => $this->guest_document_type,
                    'document_number' => $this->guest_document_number,
                ]
            );

            $nights = Carbon::parse($this->check_in_date)->diffInDays(Carbon::parse($this->check_out_date));
            $roomType = RoomType::find($this->selectedRoomType);

            // Criar reserva
            $reservation = Reservation::create([
                'tenant_id' => $this->tenant->id,
                'guest_id' => $guest->id,
                'room_type_id' => $this->selectedRoomType,
                'check_in_date' => $this->check_in_date,
                'check_out_date' => $this->check_out_date,
                'adults' => $this->adults,
                'children' => $this->children,
                'source' => 'website',
                'status' => 'pending',
                'room_rate' => $roomType->base_price,
                'nights' => $nights,
                'special_requests' => $this->special_requests,
            ]);

            $this->reservationNumber = $reservation->reservation_number;
            $this->confirmationCode = $reservation->confirmation_code;
            $this->step = 4;

        } catch (\Exception $e) {
            \Log::error('Erro ao criar reserva online', ['error' => $e->getMessage()]);
            $this->dispatch('error', message: 'Erro ao processar reserva. Tente novamente.');
        }
    }

    public function getNightsProperty()
    {
        if ($this->check_in_date && $this->check_out_date) {
            return Carbon::parse($this->check_in_date)->diffInDays(Carbon::parse($this->check_out_date));
        }
        return 0;
    }

    public function getTotalProperty()
    {
        if ($this->selectedRoomTypeData && $this->nights > 0) {
            $subtotal = $this->selectedRoomTypeData->base_price * $this->nights;
            $tax = $subtotal * 0.14;
            return $subtotal + $tax;
        }
        return 0;
    }

    public function render()
    {
        $availableRoomTypes = collect();

        if ($this->tenant && $this->step >= 2) {
            $availableRoomTypes = RoomType::where('tenant_id', $this->tenant->id)
                ->where('is_active', true)
                ->where('capacity', '>=', $this->adults)
                ->withCount(['rooms' => function ($q) {
                    $q->where('status', 'available')->where('is_active', true);
                }])
                ->having('rooms_count', '>', 0)
                ->get();
        }

        return view('livewire.hotel.booking-online', compact('availableRoomTypes'))
            ->layout('layouts.guest');
    }
}

<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use App\Models\Client;
use Carbon\Carbon;

class WalkIn extends Component
{
    // Step: 1 = Quarto, 2 = Hóspede, 3 = Confirmação
    public $step = 1;
    
    // Quarto
    public $selectedRoomTypeId = '';
    public $selectedRoomId = '';
    public $availableRooms = [];
    
    // Datas
    public $checkInDate;
    public $checkOutDate;
    public $nights = 1;
    public $adults = 1;
    public $children = 0;
    
    // Hóspede (busca ou novo)
    public $guestSearch = '';
    public $foundGuests = [];
    public $selectedGuestId = '';
    public $isNewGuest = false;
    
    // Novo hóspede
    public $guestName = '';
    public $guestPhone = '';
    public $guestEmail = '';
    public $guestIdNumber = '';
    public $guestNationality = 'Angola';
    
    // Preço
    public $roomRate = 0;
    public $discount = 0;
    public $totalAmount = 0;
    public $paidAmount = 0;
    public $specialRequests = '';
    
    // Resultado
    public $reservation = null;

    public function mount()
    {
        $this->checkInDate = now()->toDateString();
        $this->checkOutDate = now()->addDay()->toDateString();
        $this->calculateNights();
    }

    public function updatedCheckInDate()
    {
        $this->calculateNights();
        $this->loadAvailableRooms();
    }

    public function updatedCheckOutDate()
    {
        $this->calculateNights();
        $this->loadAvailableRooms();
    }

    public function calculateNights()
    {
        if ($this->checkInDate && $this->checkOutDate) {
            $checkIn = Carbon::parse($this->checkInDate);
            $checkOut = Carbon::parse($this->checkOutDate);
            $this->nights = max(1, $checkIn->diffInDays($checkOut));
            $this->calculateTotal();
        }
    }

    public function updatedSelectedRoomTypeId()
    {
        $this->selectedRoomId = '';
        $this->loadAvailableRooms();
        
        $roomType = RoomType::find($this->selectedRoomTypeId);
        if ($roomType) {
            $this->roomRate = $roomType->base_price;
            $this->calculateTotal();
        }
    }

    public function selectRoomType($id)
    {
        $this->selectedRoomTypeId = $id;
        $this->selectedRoomId = '';
        $this->loadAvailableRooms();
        
        $roomType = RoomType::find($id);
        if ($roomType) {
            $this->roomRate = $roomType->base_price;
            $this->calculateTotal();
        }
    }

    public function selectRoom($id)
    {
        $this->selectedRoomId = $id;
    }

    public function loadAvailableRooms()
    {
        if (!$this->selectedRoomTypeId) {
            $this->availableRooms = [];
            return;
        }

        $tenantId = auth()->user()->tenant_id;
        
        $this->availableRooms = Room::where('tenant_id', $tenantId)
            ->where('room_type_id', $this->selectedRoomTypeId)
            ->where('status', 'available')
            ->orderBy('number')
            ->get();
    }

    public function updatedGuestSearch()
    {
        if (strlen($this->guestSearch) >= 2) {
            $tenantId = auth()->user()->tenant_id;
            
            $this->foundGuests = Guest::where('tenant_id', $tenantId)
                ->where(function($q) {
                    $q->where('name', 'like', "%{$this->guestSearch}%")
                      ->orWhere('phone', 'like', "%{$this->guestSearch}%")
                      ->orWhere('email', 'like', "%{$this->guestSearch}%")
                      ->orWhere('id_number', 'like', "%{$this->guestSearch}%");
                })
                ->take(5)
                ->get();
        } else {
            $this->foundGuests = [];
        }
    }

    public function selectGuest($guestId)
    {
        $guest = Guest::find($guestId);
        if ($guest) {
            $this->selectedGuestId = $guestId;
            $this->guestName = $guest->name;
            $this->guestPhone = $guest->phone;
            $this->guestEmail = $guest->email;
            $this->guestIdNumber = $guest->id_number;
            $this->guestNationality = $guest->nationality ?? 'Angola';
            $this->isNewGuest = false;
            $this->foundGuests = [];
            $this->guestSearch = '';
        }
    }

    public function createNewGuest()
    {
        $this->isNewGuest = true;
        $this->selectedGuestId = '';
        $this->guestName = $this->guestSearch;
        $this->guestPhone = '';
        $this->guestEmail = '';
        $this->guestIdNumber = '';
        $this->guestNationality = 'Angola';
        $this->foundGuests = [];
    }

    public function calculateTotal()
    {
        $this->totalAmount = ($this->roomRate * $this->nights) - $this->discount;
    }

    public function updatedRoomRate()
    {
        $this->calculateTotal();
    }

    public function updatedDiscount()
    {
        $this->calculateTotal();
    }

    public function nextStep()
    {
        if ($this->step === 1) {
            if (!$this->selectedRoomTypeId) {
                session()->flash('error', 'Selecione um tipo de quarto');
                return;
            }
            if (!$this->selectedRoomId) {
                session()->flash('error', 'Selecione um quarto disponível');
                return;
            }
        }

        if ($this->step === 2) {
            if (!$this->selectedGuestId && !$this->isNewGuest) {
                if (empty($this->guestName)) {
                    session()->flash('error', 'Selecione ou crie um hóspede');
                    return;
                }
                $this->isNewGuest = true;
            }
            if (empty($this->guestName)) {
                session()->flash('error', 'Nome do hóspede é obrigatório');
                return;
            }
            if (empty($this->guestPhone)) {
                session()->flash('error', 'Telefone do hóspede é obrigatório');
                return;
            }
        }

        if ($this->step < 3) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function submit()
    {
        $tenantId = auth()->user()->tenant_id;

        try {
            // Criar ou obter hóspede
            if ($this->isNewGuest || !$this->selectedGuestId) {
                $guest = Guest::create([
                    'tenant_id' => $tenantId,
                    'name' => $this->guestName,
                    'phone' => $this->guestPhone,
                    'email' => $this->guestEmail,
                    'id_number' => $this->guestIdNumber,
                    'nationality' => $this->guestNationality,
                ]);
                $this->selectedGuestId = $guest->id;
            }

            // Criar reserva
            $this->reservation = Reservation::create([
                'tenant_id' => $tenantId,
                'guest_id' => $this->selectedGuestId,
                'room_id' => $this->selectedRoomId,
                'room_type_id' => $this->selectedRoomTypeId,
                'check_in_date' => $this->checkInDate,
                'check_out_date' => $this->checkOutDate,
                'adults' => $this->adults,
                'children' => $this->children,
                'nights' => $this->nights,
                'room_rate' => $this->roomRate,
                'discount' => $this->discount,
                'total' => $this->totalAmount,
                'paid_amount' => $this->paidAmount,
                'status' => 'checked_in', // Já faz check-in automático
                'payment_status' => $this->paidAmount >= $this->totalAmount ? 'paid' : ($this->paidAmount > 0 ? 'partial' : 'pending'),
                'source' => 'walkin',
                'special_requests' => $this->specialRequests,
                'actual_check_in' => now(),
            ]);

            // Atualizar status do quarto
            Room::find($this->selectedRoomId)->update(['status' => 'occupied']);

            $this->step = 4; // Confirmação
            
        } catch (\Exception $e) {
            \Log::error('Erro no Walk-in', ['error' => $e->getMessage()]);
            session()->flash('error', 'Erro ao processar: ' . $e->getMessage());
        }
    }

    public function newWalkIn()
    {
        $this->reset();
        $this->mount();
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $roomTypes = RoomType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount(['rooms' => fn($q) => $q->where('status', 'available')])
            ->orderBy('name')
            ->get();

        return view('livewire.hotel.walk-in', [
            'roomTypes' => $roomTypes,
        ])->layout('layouts.app');
    }
}

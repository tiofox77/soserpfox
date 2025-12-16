<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Guest;
use App\Models\Client;
use App\Models\Treasury\PaymentMethod;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use Carbon\Carbon;

class ReservationManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $dateFilter = '';
    public $sourceFilter = '';
    public $perPage = 15;
    
    public $showModal = false;
    public $viewModal = false;
    public $checkInModal = false;
    public $paymentModal = false;
    public $editingId = null;
    public $viewingReservation = null;
    public $checkingInReservation = null;
    public $payingReservation = null;
    
    // Payment modal fields
    public $payment_amount = 0;
    public $payment_method_id = null;
    public $generate_invoice = true;

    // Form fields
    public $client_id = '';
    public $room_type_id = '';
    public $room_id = '';
    public $check_in_date = '';
    public $check_out_date = '';
    public $adults = 1;
    public $children = 0;
    public $extra_beds = 0;
    public $source = 'direct';
    public $room_rate = 0;
    public $discount = 0;
    public $special_requests = '';
    public $internal_notes = '';
    public $payment_method = '';
    public $paid_amount = 0;

    // Pesquisa de cliente
    public $clientSearch = '';
    public $showClientDropdown = false;

    // Modal novo cliente
    public $showClientModal = false;
    public $client_name = '';
    public $client_email = '';
    public $client_phone = '';
    public $client_nif = '';
    public $client_address = '';
    public $client_city = '';
    public $client_province = 'Luanda';

    protected $rules = [
        'room_type_id' => 'required|exists:hotel_room_types,id',
        'room_id' => 'nullable|exists:hotel_rooms,id',
        'check_in_date' => 'required|date',
        'check_out_date' => 'required|date|after:check_in_date',
        'adults' => 'required|integer|min:1|max:10',
        'children' => 'required|integer|min:0|max:10',
        'extra_beds' => 'required|integer|min:0|max:5',
        'source' => 'required|string',
        'room_rate' => 'required|numeric|min:0',
        'discount' => 'required|numeric|min:0',
        'special_requests' => 'nullable|string',
        'internal_notes' => 'nullable|string',
        'payment_method' => 'nullable|string',
        'paid_amount' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        $this->check_in_date = now()->toDateString();
        $this->check_out_date = now()->addDay()->toDateString();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'dateFilter', 'sourceFilter']);
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedRoomTypeId($value)
    {
        if ($value) {
            $roomType = RoomType::find($value);
            if ($roomType) {
                $this->room_rate = $roomType->base_price;
            }
        }
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
        if ($this->check_in_date && $this->check_out_date) {
            return Carbon::parse($this->check_in_date)->diffInDays(Carbon::parse($this->check_out_date));
        }
        return 0;
    }

    // ===== Métodos de Pesquisa de Cliente =====
    
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
        $query = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);

        if (!empty($this->clientSearch)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->clientSearch . '%')
                  ->orWhere('phone', 'like', '%' . $this->clientSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->clientSearch . '%')
                  ->orWhere('nif', 'like', '%' . $this->clientSearch . '%');
            });
        }

        return $query->orderBy('name')->limit(10)->get();
    }

    // ===== Modal Novo Cliente =====

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

    public function resetClientForm()
    {
        $this->reset([
            'client_name', 'client_email', 'client_phone', 
            'client_nif', 'client_address', 'client_city', 'client_province'
        ]);
    }

    public function saveClient()
    {
        $this->validate([
            'client_name' => 'required|string|min:2|max:255',
            'client_phone' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_nif' => 'nullable|string|max:20',
        ]);

        $client = Client::create([
            'tenant_id' => activeTenantId(),
            'type' => 'individual',
            'name' => $this->client_name,
            'email' => $this->client_email,
            'phone' => $this->client_phone,
            'nif' => $this->client_nif,
            'address' => $this->client_address,
            'city' => $this->client_city,
            'province' => $this->client_province,
            'country' => 'Angola',
            'is_active' => true,
        ]);

        // Selecionar o novo cliente
        $this->selectClient($client->id);
        $this->closeClientModal();
        
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente criado com sucesso!']);
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset([
            'editingId', 'client_id', 'room_type_id', 'room_id', 'adults', 'children',
            'extra_beds', 'source', 'room_rate', 'discount', 'special_requests',
            'internal_notes', 'payment_method', 'paid_amount',
            'clientSearch', 'showClientDropdown'
        ]);
        $this->check_in_date = now()->toDateString();
        $this->check_out_date = now()->addDay()->toDateString();
        $this->adults = 1;
        $this->source = 'direct';
    }

    public function edit($id)
    {
        $reservation = Reservation::forTenant()->with(['client', 'room', 'roomType'])->findOrFail($id);
        
        $this->editingId = $id;
        $this->client_id = $reservation->client_id;
        
        // Preencher pesquisa de cliente
        if ($reservation->client) {
            $this->clientSearch = $reservation->client->name . ($reservation->client->phone ? ' - ' . $reservation->client->phone : '');
        }
        
        $this->room_type_id = $reservation->room_type_id;
        $this->room_id = $reservation->room_id;
        $this->check_in_date = $reservation->check_in_date->format('Y-m-d');
        $this->check_out_date = $reservation->check_out_date->format('Y-m-d');
        $this->adults = $reservation->adults;
        $this->children = $reservation->children;
        $this->extra_beds = $reservation->extra_beds;
        $this->source = $reservation->source;
        $this->room_rate = $reservation->room_rate;
        $this->discount = $reservation->discount;
        $this->special_requests = $reservation->special_requests;
        $this->internal_notes = $reservation->internal_notes;
        $this->payment_method = $reservation->payment_method;
        $this->paid_amount = $reservation->paid_amount;
        
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingReservation = Reservation::forTenant()
            ->with(['client', 'room', 'roomType', 'items', 'createdBy', 'invoice'])
            ->findOrFail($id);
        $this->viewModal = true;
    }

    public function closeViewModal()
    {
        $this->viewModal = false;
        $this->viewingReservation = null;
    }

    public function save()
    {
        // Validar cliente
        $this->validate(['client_id' => 'required|exists:invoicing_clients,id']);
        $this->validate();

        $nights = $this->calculateNights();

        $data = [
            'client_id' => $this->client_id,
            'room_type_id' => $this->room_type_id,
            'room_id' => $this->room_id ?: null,
            'check_in_date' => $this->check_in_date,
            'check_out_date' => $this->check_out_date,
            'adults' => $this->adults,
            'children' => $this->children,
            'extra_beds' => $this->extra_beds,
            'source' => $this->source,
            'room_rate' => $this->room_rate,
            'nights' => $nights,
            'discount' => $this->discount,
            'special_requests' => $this->special_requests,
            'internal_notes' => $this->internal_notes,
            'payment_method' => $this->payment_method,
            'paid_amount' => $this->paid_amount,
        ];

        if ($this->editingId) {
            $reservation = Reservation::forTenant()->findOrFail($this->editingId);
            $reservation->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva atualizada com sucesso!']);
        } else {
            $data['status'] = 'pending';
            Reservation::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva criada com sucesso!']);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirm($id)
    {
        $reservation = Reservation::forTenant()->findOrFail($id);
        $reservation->confirm();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva confirmada!']);
    }

    public function openCheckInModal($id)
    {
        $this->checkingInReservation = Reservation::forTenant()
            ->with(['guest', 'roomType'])
            ->findOrFail($id);
        
        // Buscar quartos disponíveis do tipo
        $this->checkInModal = true;
    }

    public function processCheckIn($roomId = null)
    {
        if (!$this->checkingInReservation) return;
        
        $roomToAssign = $roomId ?: $this->checkingInReservation->room_id;
        
        if (!$roomToAssign) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione um quarto para o check-in.']);
            return;
        }

        $this->checkingInReservation->checkIn($roomToAssign);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Check-in realizado com sucesso!']);
        
        $this->checkInModal = false;
        $this->checkingInReservation = null;
    }

    public function checkOut($id)
    {
        $reservation = Reservation::forTenant()->findOrFail($id);
        $reservation->checkOut();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Check-out realizado com sucesso!']);
    }

    public function cancel($id)
    {
        $reservation = Reservation::forTenant()->findOrFail($id);
        $reservation->cancel('Cancelado pelo usuário');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Reserva cancelada!']);
    }

    // Payment Methods
    public function openPaymentModal($id)
    {
        $this->payingReservation = Reservation::forTenant()->with(['client', 'room', 'roomType'])->findOrFail($id);
        $this->payment_amount = $this->payingReservation->total - $this->payingReservation->paid_amount;
        $this->payment_method_id = PaymentMethod::where('tenant_id', activeTenantId())->where('is_active', true)->first()?->id;
        $this->generate_invoice = true;
        $this->paymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->paymentModal = false;
        $this->payingReservation = null;
        $this->payment_amount = 0;
        $this->payment_method_id = null;
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function registerPayment()
    {
        if (!$this->payingReservation) return;

        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:treasury_payment_methods,id',
        ]);

        $paymentMethod = PaymentMethod::find($this->payment_method_id);
        $newPaidAmount = $this->payingReservation->paid_amount + $this->payment_amount;
        $paymentStatus = $newPaidAmount >= $this->payingReservation->total ? 'paid' : 'partial';

        // Criar Fatura se solicitado
        $invoice = null;
        if ($this->generate_invoice && $this->payingReservation->client_id) {
            $invoice = $this->createInvoiceForReservation($this->payingReservation, $this->payment_amount);
        }

        // Atualizar reserva
        $this->payingReservation->update([
            'paid_amount' => $newPaidAmount,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod?->name,
            'invoice_id' => $invoice?->id,
        ]);

        // Incrementar fidelidade do cliente
        if ($this->payingReservation->client) {
            $this->payingReservation->client->incrementStays($this->payment_amount);
        }

        $message = 'Pagamento de ' . number_format($this->payment_amount, 0, ',', '.') . ' Kz registado!';
        if ($invoice) {
            $message .= ' Fatura ' . $invoice->invoice_number . ' criada.';
        }
        
        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        
        // Guardar ID antes de fechar modal
        $payingId = $this->payingReservation->id;
        
        $this->closePaymentModal();
        
        // Atualizar viewingReservation se estiver aberto
        if ($this->viewingReservation && $this->viewingReservation->id === $payingId) {
            $this->viewingReservation = Reservation::forTenant()->with(['client', 'room', 'roomType', 'invoice'])->find($payingId);
        }
    }

    protected function createInvoiceForReservation(Reservation $reservation, $amount)
    {
        $taxRate = 14; // IVA Angola
        $subtotal = round($amount / (1 + ($taxRate / 100)), 2);
        $taxAmount = $amount - $subtotal;

        // Criar fatura
        $invoice = SalesInvoice::create([
            'tenant_id' => activeTenantId(),
            'client_id' => $reservation->client_id,
            'invoice_date' => now(),
            'due_date' => now(),
            'status' => 'paid',
            'invoice_type' => 'FT',
            'is_service' => true,
            'subtotal' => $subtotal,
            'net_total' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_payable' => $taxAmount,
            'total' => $amount,
            'gross_total' => $amount,
            'paid_amount' => $amount,
            'currency' => 'AOA',
            'exchange_rate' => 1,
            'notes' => 'Reserva ' . $reservation->reservation_number . ' - ' . 
                       $reservation->roomType->name . ' (' . $reservation->nights . ' noite(s))',
            'created_by' => auth()->id(),
        ]);

        // Criar item da fatura (serviço de hospedagem, sem produto físico)
        $roomName = $reservation->room 
            ? $reservation->room->number . ' - ' . $reservation->roomType->name
            : $reservation->roomType->name;
            
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => null, // Serviço de hospedagem (não é produto físico)
            'product_name' => 'Hospedagem: Quarto ' . $roomName,
            'description' => 'Reserva ' . $reservation->reservation_number . 
                           ' | Check-in: ' . $reservation->check_in_date->format('d/m/Y') .
                           ' | Check-out: ' . $reservation->check_out_date->format('d/m/Y') .
                           ' | ' . $reservation->nights . ' noite(s)',
            'quantity' => $reservation->nights,
            'unit' => 'noite',
            'unit_price' => $reservation->room_rate,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $amount,
            'order' => 1,
        ]);

        // Finalizar fatura (gerar hash SAFT)
        $invoice->finalizeInvoice();

        return $invoice;
    }

    public function getAvailableRoomsProperty()
    {
        if (!$this->checkingInReservation) return collect();

        return Room::forTenant()
            ->where('room_type_id', $this->checkingInReservation->room_type_id)
            ->where('status', 'available')
            ->where('is_active', true)
            ->orderBy('number')
            ->get();
    }

    public function render()
    {
        $reservations = Reservation::forTenant()
            ->with(['client', 'room', 'roomType'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reservation_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function ($cq) {
                          $cq->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%')
                             ->orWhere('phone', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter, fn($q) => $q->where('source', $this->sourceFilter))
            ->when($this->dateFilter === 'today', fn($q) => $q->today())
            ->when($this->dateFilter === 'checkin_today', fn($q) => $q->checkingInToday())
            ->when($this->dateFilter === 'checkout_today', fn($q) => $q->checkingOutToday())
            ->when($this->dateFilter === 'current', fn($q) => $q->currentlyStaying())
            ->latest()
            ->paginate($this->perPage);

        $roomTypes = RoomType::forTenant()->active()->orderBy('name')->get();
        $rooms = Room::forTenant()->active()->orderBy('number')->get();

        // Estatísticas rápidas
        $stats = [
            'today_checkins' => Reservation::forTenant()->checkingInToday()->count(),
            'today_checkouts' => Reservation::forTenant()->checkingOutToday()->count(),
            'current_guests' => Reservation::forTenant()->currentlyStaying()->count(),
            'pending' => Reservation::forTenant()->where('status', 'pending')->count(),
        ];

        return view('livewire.hotel.reservations.reservations', compact(
            'reservations', 'roomTypes', 'rooms', 'stats'
        ))->layout('layouts.app');
    }
}

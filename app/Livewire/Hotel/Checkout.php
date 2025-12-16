<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\Guest;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Treasury\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Checkout extends Component
{
    public $search = '';
    public $reservationId = null;
    public $reservation = null;
    
    // Modal de checkout
    public $showCheckoutModal = false;
    
    // Consumos extras
    public $extras = [];
    public $newExtraDescription = '';
    public $newExtraAmount = 0;
    public $newExtraQuantity = 1;
    
    // Totais
    public $roomTotal = 0;
    public $extrasTotal = 0;
    public $discountAmount = 0;
    public $taxAmount = 0;
    public $grandTotal = 0;
    public $paidAmount = 0;
    public $balanceDue = 0;
    
    // Pagamento
    public $paymentAmount = 0;
    public $paymentMethodId = null;
    public $paymentMethods = [];
    
    // Fatura
    public $generateInvoice = true;
    public $sendEmail = false;
    public $invoiceNotes = '';
    
    // Resultado
    public $checkoutComplete = false;
    public $generatedInvoice = null;

    public function mount($reservationId = null)
    {
        if ($reservationId) {
            $this->loadReservation($reservationId);
        }
        $this->loadPaymentMethods();
    }

    protected function loadPaymentMethods()
    {
        $tenantId = auth()->user()->tenant_id;
        $this->paymentMethods = PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function loadReservation($id)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $this->reservation = Reservation::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with(['guest', 'room', 'roomType'])
            ->first();
            
        if ($this->reservation) {
            $this->reservationId = $id;
            $this->calculateTotals();
            $this->showCheckoutModal = true;
        }
    }

    public function searchReservations()
    {
        // Buscar reservas para check-out
    }

    public function openCheckout($id)
    {
        $this->loadReservation($id);
    }

    public function addExtra()
    {
        if (empty($this->newExtraDescription) || $this->newExtraAmount <= 0) {
            return;
        }
        
        $this->extras[] = [
            'description' => $this->newExtraDescription,
            'quantity' => $this->newExtraQuantity,
            'unit_price' => $this->newExtraAmount,
            'total' => $this->newExtraAmount * $this->newExtraQuantity,
        ];
        
        $this->reset(['newExtraDescription', 'newExtraAmount', 'newExtraQuantity']);
        $this->newExtraQuantity = 1;
        $this->calculateTotals();
    }

    public function removeExtra($index)
    {
        unset($this->extras[$index]);
        $this->extras = array_values($this->extras);
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        if (!$this->reservation) return;
        
        // Total do quarto
        $this->roomTotal = $this->reservation->total ?? 0;
        
        // Total de extras
        $this->extrasTotal = collect($this->extras)->sum('total');
        
        // Subtotal
        $subtotal = $this->roomTotal + $this->extrasTotal;
        
        // Desconto
        $this->discountAmount = $this->reservation->discount ?? 0;
        
        // Taxa (se aplicável)
        $this->taxAmount = 0;
        
        // Total geral
        $this->grandTotal = $subtotal - $this->discountAmount + $this->taxAmount;
        
        // Já pago
        $this->paidAmount = $this->reservation->paid_amount ?? 0;
        
        // Saldo a pagar
        $this->balanceDue = max(0, $this->grandTotal - $this->paidAmount);
        
        // Valor padrão do pagamento
        $this->paymentAmount = $this->balanceDue;
    }

    public function processCheckout()
    {
        if (!$this->reservation) {
            session()->flash('error', 'Reserva não encontrada');
            return;
        }

        $tenantId = auth()->user()->tenant_id;

        try {
            DB::beginTransaction();

            // Atualizar reserva
            $this->reservation->update([
                'status' => 'checked_out',
                'actual_check_out' => now(),
                'extras_total' => $this->extrasTotal,
                'total' => $this->grandTotal,
                'paid_amount' => $this->paidAmount + $this->paymentAmount,
                'payment_status' => ($this->paidAmount + $this->paymentAmount) >= $this->grandTotal ? 'paid' : 'partial',
            ]);

            // Marcar quarto para limpeza
            if ($this->reservation->room_id) {
                Room::find($this->reservation->room_id)->update([
                    'status' => 'cleaning',
                    'housekeeping_status' => 'dirty'
                ]);
            }

            // Gerar fatura se solicitado
            if ($this->generateInvoice) {
                $this->generatedInvoice = $this->createInvoice();
            }

            DB::commit();

            $this->checkoutComplete = true;
            session()->flash('success', 'Check-out realizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro no checkout', ['error' => $e->getMessage()]);
            session()->flash('error', 'Erro ao processar check-out: ' . $e->getMessage());
        }
    }

    protected function createInvoice()
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Criar fatura
        $invoice = SalesInvoice::create([
            'tenant_id' => $tenantId,
            'client_id' => $this->reservation->guest?->client_id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => now(),
            'subtotal' => $this->roomTotal + $this->extrasTotal,
            'discount' => $this->discountAmount,
            'tax' => $this->taxAmount,
            'total' => $this->grandTotal,
            'paid_amount' => $this->paidAmount + $this->paymentAmount,
            'status' => ($this->paidAmount + $this->paymentAmount) >= $this->grandTotal ? 'paid' : 'partial',
            'notes' => $this->invoiceNotes ?: "Reserva: {$this->reservation->reservation_number}",
            'created_by' => auth()->id(),
        ]);

        // Item do quarto
        SalesInvoiceItem::create([
            'tenant_id' => $tenantId,
            'sales_invoice_id' => $invoice->id,
            'description' => "Hospedagem - {$this->reservation->roomType?->name} ({$this->reservation->nights} noites)",
            'quantity' => $this->reservation->nights ?? 1,
            'unit_price' => $this->reservation->room_rate ?? 0,
            'discount' => 0,
            'tax' => 0,
            'total' => $this->roomTotal,
        ]);

        // Itens extras
        foreach ($this->extras as $extra) {
            SalesInvoiceItem::create([
                'tenant_id' => $tenantId,
                'sales_invoice_id' => $invoice->id,
                'description' => $extra['description'],
                'quantity' => $extra['quantity'],
                'unit_price' => $extra['unit_price'],
                'discount' => 0,
                'tax' => 0,
                'total' => $extra['total'],
            ]);
        }

        // Atualizar reserva com ID da fatura
        $this->reservation->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    protected function generateInvoiceNumber()
    {
        $tenantId = auth()->user()->tenant_id;
        $year = now()->format('Y');
        $count = SalesInvoice::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;
        return "FT{$year}/" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function closeModal()
    {
        $this->reset(['showCheckoutModal', 'reservation', 'reservationId', 'extras', 'checkoutComplete', 'generatedInvoice']);
    }

    public function newCheckout()
    {
        $this->reset(['showCheckoutModal', 'reservation', 'reservationId', 'extras', 'checkoutComplete', 'generatedInvoice']);
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        // Reservas prontas para check-out (checked_in)
        $reservations = Reservation::where('tenant_id', $tenantId)
            ->where('status', 'checked_in')
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('reservation_number', 'like', "%{$this->search}%")
                          ->orWhereHas('guest', fn($g) => $g->where('name', 'like', "%{$this->search}%"))
                          ->orWhereHas('room', fn($r) => $r->where('room_number', 'like', "%{$this->search}%"));
                });
            })
            ->with(['guest', 'room', 'roomType'])
            ->orderBy('check_out_date')
            ->get();

        // Agrupar por data de check-out
        $todayCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isToday());
        $overdueCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isPast() && !Carbon::parse($r->check_out_date)->isToday());
        $futureCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isFuture());

        return view('livewire.hotel.checkout', [
            'todayCheckouts' => $todayCheckouts,
            'overdueCheckouts' => $overdueCheckouts,
            'futureCheckouts' => $futureCheckouts,
            'totalReservations' => $reservations->count(),
        ])->layout('layouts.app');
    }
}

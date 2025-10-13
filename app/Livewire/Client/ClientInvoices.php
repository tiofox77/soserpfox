<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoicing\SalesInvoice;

#[Layout('layouts.client')]
#[Title('Minhas Faturas')]
class ClientInvoices extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    public function render()
    {
        $client = Auth::guard('client')->user();
        
        $invoices = SalesInvoice::where('client_id', $client->id)
            ->when($this->search, function($query) {
                $query->where('invoice_number', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('invoice_date', 'desc')
            ->paginate(15);
        
        return view('livewire.client.client-invoices', compact('invoices'));
    }
}

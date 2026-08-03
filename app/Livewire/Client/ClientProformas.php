<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoicing\SalesProforma;

#[Layout('layouts.client')]
#[Title('Minhas Proformas')]
class ClientProformas extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $client = Auth::guard('client')->user();

        $proformas = SalesProforma::where('client_id', $client->id)
            ->when($this->search, function ($query) {
                $query->where('proforma_number', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('proforma_date', 'desc')
            ->paginate(15);

        $stats = [
            'total' => SalesProforma::where('client_id', $client->id)->count(),
            'converted' => SalesProforma::where('client_id', $client->id)->where('status', 'converted')->count(),
            'pending' => SalesProforma::where('client_id', $client->id)
                ->whereNotIn('status', ['converted', 'cancelled', 'rejected', 'expired'])->count(),
        ];

        return view('livewire.client.client-proformas', compact('proformas', 'stats'));
    }
}

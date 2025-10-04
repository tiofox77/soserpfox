<?php

namespace App\Livewire\Invoicing\Receipts;

use App\Models\Invoicing\Receipt;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Recibos')]
class Receipts extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = '';
    public $filterStatus = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $perPage = 15;
    
    public $showDeleteModal = false;
    public $showViewModal = false;
    public $selectedReceipt = null;
    public $receiptToDelete = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function confirmDelete($receiptId)
    {
        $this->receiptToDelete = $receiptId;
        $this->showDeleteModal = true;
    }

    public function deleteReceipt()
    {
        $receipt = Receipt::where('tenant_id', activeTenantId())->findOrFail($this->receiptToDelete);
        $receipt->delete();
        
        $this->showDeleteModal = false;
        $this->receiptToDelete = null;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Recibo eliminado com sucesso!'
        ]);
    }

    public function viewReceipt($receiptId)
    {
        $this->selectedReceipt = Receipt::with(['client', 'supplier', 'invoice', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($receiptId);
        
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedReceipt = null;
    }

    public function cancelReceipt($receiptId)
    {
        $receipt = Receipt::where('tenant_id', activeTenantId())->findOrFail($receiptId);
        $receipt->cancel();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Recibo cancelado com sucesso!'
        ]);
    }

    public function render()
    {
        $query = Receipt::with(['client', 'supplier', 'invoice', 'creator'])
            ->where('tenant_id', activeTenantId());

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('receipt_number', 'like', '%' . $this->search . '%')
                  ->orWhere('reference', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('supplier', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('payment_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('payment_date', '<=', $this->filterDateTo);
        }

        $receipts = $query->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        // Stats
        $stats = [
            'total' => Receipt::where('tenant_id', activeTenantId())->count(),
            'sales' => Receipt::where('tenant_id', activeTenantId())->where('type', 'sale')->count(),
            'purchases' => Receipt::where('tenant_id', activeTenantId())->where('type', 'purchase')->count(),
            'total_amount' => Receipt::where('tenant_id', activeTenantId())
                ->where('status', 'issued')
                ->sum('amount_paid'),
        ];

        return view('livewire.invoicing.receipts.receipts', [
            'receipts' => $receipts,
            'stats' => $stats,
        ]);
    }
}

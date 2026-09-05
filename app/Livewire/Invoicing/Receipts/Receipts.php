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
    // Cada um vê os documentos que emitiu; com
    // `invoicing.documents.all` vê os de todos e ganha o filtro por autor.
    use \App\Traits\DocumentosPorAutor;

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
        // Verificar bloqueio de eliminação via Software Settings
        if (isDeleteBlocked('receipt')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('A eliminação de Recibos está bloqueada pelo administrador. Apenas anulações são permitidas.')
            ]);
            $this->showDeleteModal = false;
            return;
        }

        $receipt = $this->baseDoAutor()->findOrFail($this->receiptToDelete);
        $receipt->delete();
        
        $this->showDeleteModal = false;
        $this->receiptToDelete = null;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Recibo eliminado com sucesso!')
        ]);
    }

    public function viewReceipt($receiptId)
    {
        $this->selectedReceipt = $this->baseDoAutor()
            ->with(['client', 'supplier', 'invoice', 'creator'])
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
        $receipt = $this->baseDoAutor()->findOrFail($receiptId);
        $receipt->cancel();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Recibo cancelado com sucesso!')
        ]);
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\Receipt::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['client', 'supplier', 'invoice', 'creator']);

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('receipt_number', 'like', '%' . $this->search . '%')
                  // A SÉRIE INTERNA PRIMEIRO: é a que a empresa reconhece
                  // (SOSNC), e não o código críptico que a AGT devolve e que
                  // vai gravado no número. Procura-se também pela da AGT, para
                  // quem venha do portal com o código na mão.
                  ->orWhereHas('series', function ($q3) {
                      $q3->where('series_code', 'like', '%' . $this->search . '%')
                         ->orWhere('agt_series_id', 'like', '%' . $this->search . '%');
                  })
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
            'total' => $this->baseDoAutor()->count(),
            'sales' => $this->baseDoAutor()->where('type', 'sale')->count(),
            'purchases' => $this->baseDoAutor()->where('type', 'purchase')->count(),
            'total_amount' => $this->baseDoAutor()
                ->where('status', 'issued')
                ->sum('amount_paid'),
        ];

        return view('livewire.invoicing.receipts.receipts', [
            'receipts' => $receipts,
            'stats' => $stats,
        ]);
    }
}

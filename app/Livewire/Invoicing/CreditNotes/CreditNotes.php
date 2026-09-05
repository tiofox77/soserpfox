<?php

namespace App\Livewire\Invoicing\CreditNotes;

use App\Models\Invoicing\CreditNote;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Notas de Crédito')]
class CreditNotes extends Component
{
    use WithPagination;
    // Cada um vê os documentos que emitiu; com
    // `invoicing.documents.all` vê os de todos e ganha o filtro por autor.
    use \App\Traits\DocumentosPorAutor;

    public $search = '';
    public $filterStatus = '';
    public $filterReason = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $perPage = 15;
    
    public $showDeleteModal = false;
    public $creditNoteToDelete = null;

    public $showViewModal = false;
    public $selectedCreditNote = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterReason' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function viewCreditNote($creditNoteId)
    {
        // Scoped ao tenant: sem isto um id de outra empresa abria o documento.
        $this->selectedCreditNote = $this->baseDoAutor()
            ->with(['client', 'invoice', 'items.product', 'creator'])
            ->findOrFail($creditNoteId);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedCreditNote = null;
    }

    public function confirmDelete($creditNoteId)
    {
        $this->creditNoteToDelete = $creditNoteId;
        $this->showDeleteModal = true;
    }

    public function deleteCreditNote()
    {
        // Verificar bloqueio de eliminação via Software Settings
        if (isDeleteBlocked('credit_note')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('A eliminação de Notas de Crédito está bloqueada pelo administrador. Apenas anulações são permitidas.')
            ]);
            $this->showDeleteModal = false;
            return;
        }

        $creditNote = $this->baseDoAutor()->findOrFail($this->creditNoteToDelete);
        $creditNote->delete();
        
        $this->showDeleteModal = false;
        $this->creditNoteToDelete = null;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Nota de Crédito eliminada com sucesso!')
        ]);
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\CreditNote::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['client', 'invoice', 'items', 'creator']);

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('credit_note_number', 'like', '%' . $this->search . '%')
                  // A SÉRIE INTERNA PRIMEIRO: é a que a empresa reconhece
                  // (SOSNC), e não o código críptico que a AGT devolve e que
                  // vai gravado no número. Procura-se também pela da AGT, para
                  // quem venha do portal com o código na mão.
                  ->orWhereHas('series', function ($q3) {
                      $q3->where('series_code', 'like', '%' . $this->search . '%')
                         ->orWhere('agt_series_id', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('client', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterReason) {
            $query->where('reason', $this->filterReason);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('issue_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('issue_date', '<=', $this->filterDateTo);
        }

        $creditNotes = $query->orderBy('issue_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        // Stats
        $stats = [
            'total' => $this->baseDoAutor()->count(),
            'draft' => $this->baseDoAutor()->where('status', 'draft')->count(),
            'issued' => $this->baseDoAutor()->where('status', 'issued')->count(),
            'total_amount' => $this->baseDoAutor()
                ->where('status', 'issued')
                ->sum('total'),
        ];

        return view('livewire.invoicing.credit-notes.credit-notes', [
            'creditNotes' => $creditNotes,
            'stats' => $stats,
        ]);
    }
}

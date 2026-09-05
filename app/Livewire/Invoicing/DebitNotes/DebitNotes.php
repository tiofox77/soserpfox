<?php

namespace App\Livewire\Invoicing\DebitNotes;

use App\Models\Invoicing\DebitNote;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Notas de Débito')]
class DebitNotes extends Component
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
    public $debitNoteToDelete = null;

    public $showViewModal = false;
    public $selectedDebitNote = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterReason' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function viewDebitNote($debitNoteId)
    {
        // Scoped ao tenant: sem isto um id de outra empresa abria o documento.
        $this->selectedDebitNote = $this->baseDoAutor()
            ->with(['client', 'invoice', 'items.product', 'creator'])
            ->findOrFail($debitNoteId);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedDebitNote = null;
    }

    public function confirmDelete($debitNoteId)
    {
        $this->debitNoteToDelete = $debitNoteId;
        $this->showDeleteModal = true;
    }

    public function deleteDebitNote()
    {
        // Verificar bloqueio de eliminação via Software Settings.
        // Lia a chave da NOTA DE CRÉDITO (copy-paste): são documentos distintos
        // e cada um tem o seu interruptor em SuperAdmin > Software Settings.
        if (isDeleteBlocked('debit_note')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('A eliminação de Notas de Débito está bloqueada pelo administrador. Apenas anulações são permitidas.')
            ]);
            $this->showDeleteModal = false;
            return;
        }

        $debitNote = $this->baseDoAutor()->findOrFail($this->debitNoteToDelete);
        $debitNote->delete();
        
        $this->showDeleteModal = false;
        $this->debitNoteToDelete = null;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Nota de Débito eliminada com sucesso!')
        ]);
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\DebitNote::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['client', 'invoice', 'items', 'creator']);

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('debit_note_number', 'like', '%' . $this->search . '%')
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

        $debitNotes = $query->orderBy('issue_date', 'desc')
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

        return view('livewire.invoicing.debit-notes.debit-notes', [
            'debitNotes' => $debitNotes,
            'stats' => $stats,
        ]);
    }
}

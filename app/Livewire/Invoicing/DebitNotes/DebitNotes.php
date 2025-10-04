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

    public $search = '';
    public $filterStatus = '';
    public $filterReason = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $perPage = 15;
    
    public $showDeleteModal = false;
    public $debitNoteToDelete = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterReason' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function confirmDelete($debitNoteId)
    {
        $this->debitNoteToDelete = $debitNoteId;
        $this->showDeleteModal = true;
    }

    public function deleteDebitNote()
    {
        $debitNote = DebitNote::where('tenant_id', activeTenantId())->findOrFail($this->debitNoteToDelete);
        $debitNote->delete();
        
        $this->showDeleteModal = false;
        $this->debitNoteToDelete = null;
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Nota de Débito eliminada com sucesso!'
        ]);
    }

    public function render()
    {
        $query = DebitNote::with(['client', 'invoice', 'items', 'creator'])
            ->where('tenant_id', activeTenantId());

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('debit_note_number', 'like', '%' . $this->search . '%')
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
            'total' => DebitNote::where('tenant_id', activeTenantId())->count(),
            'draft' => DebitNote::where('tenant_id', activeTenantId())->where('status', 'draft')->count(),
            'issued' => DebitNote::where('tenant_id', activeTenantId())->where('status', 'issued')->count(),
            'total_amount' => DebitNote::where('tenant_id', activeTenantId())
                ->where('status', 'issued')
                ->sum('total'),
        ];

        return view('livewire.invoicing.debit-notes.debit-notes', [
            'debitNotes' => $debitNotes,
            'stats' => $stats,
        ]);
    }
}

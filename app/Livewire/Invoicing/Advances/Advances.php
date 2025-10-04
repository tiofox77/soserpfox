<?php

namespace App\Livewire\Invoicing\Advances;

use App\Models\Invoicing\Advance;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Adiantamentos')]
class Advances extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $perPage = 15;
    
    public $showDeleteModal = false;
    public $advanceToDelete = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function confirmDelete($advanceId)
    {
        $this->advanceToDelete = $advanceId;
        $this->showDeleteModal = true;
    }

    public function deleteAdvance()
    {
        $advance = Advance::where('tenant_id', activeTenantId())->findOrFail($this->advanceToDelete);
        
        try {
            $advance->cancel();
            $advance->delete();
            
            $this->showDeleteModal = false;
            $this->advanceToDelete = null;
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Adiantamento eliminado com sucesso!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        $query = Advance::with(['client', 'usages', 'creator'])
            ->where('tenant_id', activeTenantId());

        // Filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('advance_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
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

        $advances = $query->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        // Stats
        $stats = [
            'total' => Advance::where('tenant_id', activeTenantId())->count(),
            'active' => Advance::where('tenant_id', activeTenantId())->where('status', 'available')->count(),
            'total_amount' => Advance::where('tenant_id', activeTenantId())
                ->where('status', 'available')
                ->sum('amount'),
            'available_amount' => Advance::where('tenant_id', activeTenantId())
                ->where('status', 'available')
                ->sum('remaining_amount'),
        ];

        return view('livewire.invoicing.advances.advances', [
            'advances' => $advances,
            'stats' => $stats,
        ]);
    }
}

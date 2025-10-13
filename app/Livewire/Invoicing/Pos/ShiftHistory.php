<?php

namespace App\Livewire\Invoicing\Pos;

use App\Models\Invoicing\PosShift;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Histórico de Turnos')]
class ShiftHistory extends Component
{
    use WithPagination;

    public $selectedShift = null;
    public $showDetailModal = false;
    public $dateFrom = '';
    public $dateTo = '';
    public $userId = '';
    public $status = '';

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function viewDetails($shiftId)
    {
        $this->selectedShift = PosShift::with(['user', 'closedBy', 'transactions'])
            ->find($shiftId);
        $this->showDetailModal = true;
    }

    public function closeModal()
    {
        $this->showDetailModal = false;
        $this->selectedShift = null;
    }

    public function exportShift($shiftId)
    {
        // TODO: Implementar exportação para PDF
        $this->dispatch('info', message: 'Exportação em desenvolvimento');
    }

    public function render()
    {
        $shifts = PosShift::with(['user', 'closedBy'])
            ->where('tenant_id', activeTenantId())
            ->when($this->dateFrom, function($query) {
                $query->whereDate('opened_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function($query) {
                $query->whereDate('opened_at', '<=', $this->dateTo);
            })
            ->when($this->userId, function($query) {
                $query->where('user_id', $this->userId);
            })
            ->when($this->status, function($query) {
                $query->where('status', $this->status);
            })
            ->orderBy('opened_at', 'desc')
            ->paginate(20);

        $users = \App\Models\User::where('tenant_id', activeTenantId())
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.pos.shift-history', [
            'shifts' => $shifts,
            'users' => $users,
        ]);
    }
}

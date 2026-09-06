<?php

namespace App\Livewire\Invoicing\Pos;

use App\Services\POS\TurnosDoPos;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * O histórico de turnos, o ecrã de sempre. A regra de quem vê o quê vive na
 * `TurnosDoPos`, partilhada com o ecrã em React: quem não pode ver todos
 * fica preso aos seus, e não escapa pelo filtro.
 */
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

    /** Sem o direito de ver todos, fica preso aos seus. */
    public function getOwnOnlyProperty(): bool
    {
        return !auth()->user()?->can('invoicing.pos.reports.all');
    }

    private function turnos(): TurnosDoPos
    {
        return new TurnosDoPos((int) activeTenantId(), (int) auth()->id());
    }

    public function viewDetails($shiftId)
    {
        $this->selectedShift = $this->turnos()->turno((int) $shiftId, !$this->ownOnly);
        abort_unless($this->selectedShift, 404, __('Turno não encontrado ou sem acesso.'));
        $this->showDetailModal = true;
    }

    public function closeModal()
    {
        $this->showDetailModal = false;
        $this->selectedShift = null;
    }

    public function exportShift($shiftId)
    {
        return redirect()->to(route('invoicing.pos.export.shift-pdf', $shiftId));
    }

    public function exportShiftTicket($shiftId)
    {
        return redirect()->to(route('invoicing.pos.export.shift-ticket', $shiftId));
    }

    public function updatedUserId()
    {
        $this->resetPage();
    }

    public function render()
    {
        $shifts = $this->turnos()
            ->historico(['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo, 'userId' => $this->userId, 'status' => $this->status], !$this->ownOnly)
            ->paginate(20);

        // A lista de nomes é ela própria informação: quem só vê os seus
        // turnos não precisa da lista de colegas nem do filtro.
        $users = $this->ownOnly
            ? collect()
            : \App\Models\User::where('tenant_id', activeTenantId())->orderBy('name')->get();

        return view('livewire.invoicing.pos.shift-history', [
            'shifts' => $shifts,
            'users' => $users,
        ]);
    }
}

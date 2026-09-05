<?php

namespace App\Livewire\Invoicing\Pos;

use App\Models\Invoicing\PosShift;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Histórico de turnos.
 *
 * QUEM VÊ O QUÊ. Um turno diz quanto é que aquele operador vendeu, quanto
 * abriu e fechou a caixa, e que diferença deu. É a mesma informação que o
 * relatório do POS protege atrás de `invoicing.pos.reports.all` — e este
 * ecrã mostrava-a TODA a quem entrasse: a lista trazia os turnos de todos,
 * o filtro por utilizador era só uma conveniência, e o detalhe abria
 * qualquer turno da empresa pelo id.
 *
 * Passa a valer a mesma regra do relatório, uma só no sistema inteiro: sem
 * `invoicing.pos.reports.all`, cada um vê os SEUS turnos.
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

    /**
     * A restrição por operador, aplicada a QUALQUER consulta de turnos.
     *
     * Igual ao applyScope do relatório do POS: quem não pode ver todos não
     * escapa pelo filtro — escolher outro nome na lista era dar a volta à
     * permissão.
     */
    protected function applyScope($query)
    {
        if ($this->ownOnly) {
            return $query->where('user_id', auth()->id());
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        return $query;
    }

    public function viewDetails($shiftId)
    {
        // Pelo id abria-se qualquer turno da empresa, incluindo os movimentos
        // de caixa de um colega. A mesma regra da lista vale aqui.
        $query = PosShift::with(['user', 'closedBy', 'transactions'])
            ->where('tenant_id', activeTenantId());
        $this->applyScope($query);

        $this->selectedShift = $query->find($shiftId);

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
        $query = PosShift::with(['user', 'closedBy'])
            ->where('tenant_id', activeTenantId())
            ->when($this->dateFrom, function($query) {
                $query->whereDate('opened_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function($query) {
                $query->whereDate('opened_at', '<=', $this->dateTo);
            })
            ->when($this->status, function($query) {
                $query->where('status', $this->status);
            });

        $shifts = $this->applyScope($query)
            ->orderBy('opened_at', 'desc')
            ->paginate(20);

        // A lista de nomes é ela própria informação: quem só vê os seus
        // turnos não precisa da lista de colegas nem do filtro.
        $users = $this->ownOnly
            ? collect()
            : \App\Models\User::where('tenant_id', activeTenantId())
                ->orderBy('name')
                ->get();

        return view('livewire.invoicing.pos.shift-history', [
            'shifts' => $shifts,
            'users' => $users,
        ]);
    }
}

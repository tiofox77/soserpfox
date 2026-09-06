<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O ecrã de sempre deste mapa. Os números vêm do `Relatorios\Catalogo`, o
 * mesmo serviço do ecrã em React; aqui ficam só os filtros.
 */
#[Layout('layouts.app')]
#[Title('Contas a Receber')]
class AccountsReceivableReport extends Component
{
    use UsaRelatorio;

    public const RELATORIO = 'accounts-receivable';

    public $statusFilter = 'open';

    public function render()
    {
        return view('livewire.invoicing.reports.accounts-receivable-report', $this->dadosDoRelatorio());
    }
}

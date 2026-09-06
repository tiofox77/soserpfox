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
#[Title('Aging de Clientes')]
class AgingClientsReport extends Component
{
    use UsaRelatorio;

    public const RELATORIO = 'aging-clients';

    public function render()
    {
        return view('livewire.invoicing.reports.aging-clients-report', $this->dadosDoRelatorio());
    }
}

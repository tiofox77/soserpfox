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
#[Title('Tabela de Preços e Lucro')]
class PriceListReport extends Component
{
    use UsaRelatorio;

    public const RELATORIO = 'price-list';

    public $search = '';
    public $typeFilter = 'all';
    public $statusFilter = 'active';
    public $sortBy = 'name_asc';

    public function render()
    {
        return view('livewire.invoicing.reports.price-list-report', $this->dadosDoRelatorio());
    }
}

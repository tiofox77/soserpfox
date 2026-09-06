<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O ecrã de sempre deste mapa. Os números vêm do `Relatorios\Catalogo`, o
 * mesmo serviço do ecrã em React; aqui ficam só os filtros.
 */
#[Layout('layouts.app')]
#[Title('Análise de Margem por Produto')]
class MarginReport extends Component
{
    use HasReportFilters;
    use UsaRelatorio;

    public const RELATORIO = 'margin';

    public $sortBy = 'profit_desc';
    public $limit = 50;

    public function mount()
    {
        $this->initFilters('month');
    }

    public function render()
    {
        return view('livewire.invoicing.reports.margin-report', $this->dadosDoRelatorio());
    }
}

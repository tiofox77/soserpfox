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
#[Title('Top Produtos Vendidos')]
class TopProductsReport extends Component
{
    use HasReportFilters;
    use UsaRelatorio;

    public const RELATORIO = 'top-products';

    public $limit = 20;

    public function mount()
    {
        $this->initFilters('year');
    }

    public function render()
    {
        return view('livewire.invoicing.reports.top-products-report', $this->dadosDoRelatorio());
    }
}

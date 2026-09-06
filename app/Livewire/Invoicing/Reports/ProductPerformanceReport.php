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
#[Title('Desempenho de Produtos')]
class ProductPerformanceReport extends Component
{
    use HasReportFilters;
    use UsaRelatorio;

    public const RELATORIO = 'product-performance';

    public $typeFilter = 'produto';
    public $sortBy = 'profit_desc';
    public $limit = 100;

    public function mount()
    {
        $this->initFilters('month');
    }

    public function render()
    {
        return view('livewire.invoicing.reports.product-performance-report', $this->dadosDoRelatorio());
    }
}

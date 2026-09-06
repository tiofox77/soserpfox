<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use App\Services\Invoicing\Relatorios\Comparativo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O comparativo entre períodos, o ecrã de sempre. As datas dos modos
 * «mês» e «ano» e as métricas vêm do `Comparativo`, partilhado com o React.
 */
#[Layout('layouts.app')]
#[Title('Comparativo entre Períodos')]
class ComparativeReport extends Component
{
    use UsaRelatorio;

    public const RELATORIO = 'comparative';

    public $mode = 'month'; // month, year, custom
    public $periodAFrom;
    public $periodATo;
    public $periodBFrom;
    public $periodBTo;

    public function mount()
    {
        $this->applyMode();
    }

    public function updatedMode()
    {
        $this->applyMode();
    }

    protected function applyMode()
    {
        // Personalizado deixa as datas como a pessoa as escreveu.
        if ($this->mode === 'custom') {
            return;
        }

        [$this->periodAFrom, $this->periodATo, $this->periodBFrom, $this->periodBTo] = Comparativo::periodos($this->mode, []);
    }

    public function render()
    {
        return view('livewire.invoicing.reports.comparative-report', $this->dadosDoRelatorio());
    }
}

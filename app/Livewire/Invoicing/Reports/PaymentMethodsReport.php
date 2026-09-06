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
#[Title('Recebimentos por Meio de Pagamento')]
class PaymentMethodsReport extends Component
{
    use HasReportFilters;
    use UsaRelatorio;

    public const RELATORIO = 'payment-methods';

    public $method = '';

    public function mount()
    {
        $this->initFilters('month');
    }

    /** A vista de sempre chama-o pelo nome antigo; o rótulo vive no serviço. */
    public static function methodLabel($m): string
    {
        return \App\Services\Invoicing\Relatorios\MeiosDePagamento::rotulo($m);
    }

    public function render()
    {
        return view('livewire.invoicing.reports.payment-methods-report', $this->dadosDoRelatorio());
    }
}

<?php

namespace App\Livewire\Invoicing\Reports;

use App\Services\Invoicing\Relatorios\Catalogo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** A porta dos relatórios. As secções vêm do `Catalogo`, como no React. */
#[Layout('layouts.app')]
#[Title('Relatórios - Faturação')]
class ReportsHub extends Component
{
    public function render()
    {
        return view('livewire.invoicing.reports.reports-hub', ['sections' => Catalogo::paraAVistaDeSempre()]);
    }
}

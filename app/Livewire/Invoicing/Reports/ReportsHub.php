<?php

namespace App\Livewire\Invoicing\Reports;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Relatórios - Faturação')]
class ReportsHub extends Component
{
    public function render()
    {
        return view('livewire.invoicing.reports.reports-hub');
    }
}

<?php

namespace App\Livewire\Treasury;

use App\Services\Treasury\RelatoriosDeTesouraria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Os relatórios financeiros da Tesouraria.
 *
 * As contas vivem em App\Services\Treasury\RelatoriosDeTesouraria e não aqui:
 * o PDF e o Excel são gerados por um controlador, e um relatório que dê
 * números diferentes no ecrã e no ficheiro é pior do que não existir — alguém
 * imprime, leva a uma reunião, e descobre em público que não bate certo.
 */
#[Layout('layouts.app')]
#[Title('Relatórios Financeiros')]
class Reports extends Component
{
    public $reportType = 'cash_flow';   // cash_flow, dre, receivables, payables
    public $startDate;
    public $endDate;
    public $period = 'month';           // today, week, month, year, custom

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedPeriod()
    {
        $this->setDatesByPeriod();
    }

    /**
     * Mexer nas datas à mão passa o período a personalizado.
     *
     * Sem isto, o ecrã continuava a dizer "Este mês" com datas que já não eram
     * as do mês — e a etiqueta do relatório impresso mentia.
     */
    public function updatedStartDate()
    {
        $this->period = 'custom';
    }

    public function updatedEndDate()
    {
        $this->period = 'custom';
    }

    private function setDatesByPeriod()
    {
        switch ($this->period) {
            case 'today':
                $this->startDate = now()->startOfDay()->format('Y-m-d');
                $this->endDate = now()->endOfDay()->format('Y-m-d');
                break;
            case 'week':
                $this->startDate = now()->startOfWeek()->format('Y-m-d');
                $this->endDate = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = now()->startOfYear()->format('Y-m-d');
                $this->endDate = now()->endOfYear()->format('Y-m-d');
                break;
        }
    }

    /** O que os botões de descarga precisam de levar no URL. */
    public function getParametrosDeExportacaoProperty(): array
    {
        return [
            'tipo' => $this->reportType,
            'de'   => $this->startDate,
            'ate'  => $this->endDate,
        ];
    }

    public function render()
    {
        $relatorios = new RelatoriosDeTesouraria(
            (int) activeTenantId(),
            $this->startDate,
            $this->endDate
        );

        return view('livewire.treasury.reports', $relatorios->dados($this->reportType));
    }
}

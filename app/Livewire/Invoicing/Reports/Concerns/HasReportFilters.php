<?php

namespace App\Livewire\Invoicing\Reports\Concerns;

use Carbon\Carbon;

trait HasReportFilters
{
    public $dateFrom;
    public $dateTo;
    public $period = 'month';
    
    public function initFilters($period = 'month')
    {
        $this->period = $period;
        $this->applyPeriod();
    }
    
    public function applyPeriod()
    {
        $now = Carbon::now();
        switch ($this->period) {
            case 'today':
                $this->dateFrom = $now->copy()->startOfDay()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfDay()->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'quarter':
                $this->dateFrom = $now->copy()->startOfQuarter()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfQuarter()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
        }
    }
    
    public function updatedPeriod()
    {
        if ($this->period !== 'custom') {
            $this->applyPeriod();
        }
    }
}

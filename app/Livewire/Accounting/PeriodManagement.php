<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\Period;
use App\Services\Accounting\PeriodClosingService;

#[Layout('layouts.app')]
class PeriodManagement extends Component
{
    public $year;
    public $showCloseModal = false;
    public $showReopenModal = false;
    public $selectedPeriodId = null;
    public $selectedPeriodName = null;
    
    public function mount()
    {
        $this->year = now()->year;
    }
    
    public function confirmClose($periodId, $periodName)
    {
        $this->selectedPeriodId = $periodId;
        $this->selectedPeriodName = $periodName;
        $this->showCloseModal = true;
    }
    
    public function closePeriod()
    {
        try {
            $period = Period::where('tenant_id', auth()->user()->tenant_id)
                ->findOrFail($this->selectedPeriodId);
            
            $service = new PeriodClosingService();
            $result = $service->closePeriod($period);
            
            session()->flash('success', $result['message']);
            $this->showCloseModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            $this->showCloseModal = false;
        }
    }
    
    public function confirmReopen($periodId, $periodName)
    {
        $this->selectedPeriodId = $periodId;
        $this->selectedPeriodName = $periodName;
        $this->showReopenModal = true;
    }
    
    public function reopenPeriod()
    {
        try {
            $period = Period::where('tenant_id', auth()->user()->tenant_id)
                ->findOrFail($this->selectedPeriodId);
            
            $service = new PeriodClosingService();
            $result = $service->reopenPeriod($period);
            
            session()->flash('success', $result['message']);
            $this->showReopenModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            $this->showReopenModal = false;
        }
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $periods = Period::where('tenant_id', $tenantId)
            ->whereYear('date_start', $this->year)
            ->orderBy('date_start')
            ->get();
        
        // Estatísticas
        $stats = [
            'total' => $periods->count(),
            'open' => $periods->where('state', 'open')->count(),
            'closed' => $periods->where('state', 'closed')->count(),
        ];
        
        return view('livewire.accounting.periods.periods', [
            'periods' => $periods,
            'stats' => $stats,
        ]);
    }
}

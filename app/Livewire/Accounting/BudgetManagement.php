<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\Budget;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;

#[Layout('layouts.app')]
class BudgetManagement extends Component
{
    public $showModal = false;
    public $selectedYear = 2025;
    
    // Budget fields
    public $budgetId = null;
    public $name = '';
    public $year = 2025;
    public $accountId = null;
    public $costCenterId = null;
    
    // Monthly values
    public $january = 0;
    public $february = 0;
    public $march = 0;
    public $april = 0;
    public $may = 0;
    public $june = 0;
    public $july = 0;
    public $august = 0;
    public $september = 0;
    public $october = 0;
    public $november = 0;
    public $december = 0;
    public $total = 0;
    
    public function mount()
    {
        $this->year = now()->year;
        $this->selectedYear = now()->year;
    }
    
    public function updatedJanuary() { $this->calculateTotal(); }
    public function updatedFebruary() { $this->calculateTotal(); }
    public function updatedMarch() { $this->calculateTotal(); }
    public function updatedApril() { $this->calculateTotal(); }
    public function updatedMay() { $this->calculateTotal(); }
    public function updatedJune() { $this->calculateTotal(); }
    public function updatedJuly() { $this->calculateTotal(); }
    public function updatedAugust() { $this->calculateTotal(); }
    public function updatedSeptember() { $this->calculateTotal(); }
    public function updatedOctober() { $this->calculateTotal(); }
    public function updatedNovember() { $this->calculateTotal(); }
    public function updatedDecember() { $this->calculateTotal(); }
    
    public function calculateTotal()
    {
        $this->total = 
            $this->january + $this->february + $this->march + $this->april +
            $this->may + $this->june + $this->july + $this->august +
            $this->september + $this->october + $this->november + $this->december;
    }
    
    public function edit($id)
    {
        $budget = Budget::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $this->budgetId = $budget->id;
        $this->name = $budget->name;
        $this->year = $budget->year;
        $this->accountId = $budget->account_id;
        $this->costCenterId = $budget->cost_center_id;
        $this->january = $budget->january;
        $this->february = $budget->february;
        $this->march = $budget->march;
        $this->april = $budget->april;
        $this->may = $budget->may;
        $this->june = $budget->june;
        $this->july = $budget->july;
        $this->august = $budget->august;
        $this->september = $budget->september;
        $this->october = $budget->october;
        $this->november = $budget->november;
        $this->december = $budget->december;
        $this->calculateTotal();
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate([
            'name' => 'required',
            'year' => 'required|integer',
            'accountId' => 'required',
        ]);
        
        try {
            $data = [
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $this->name,
                'year' => $this->year,
                'account_id' => $this->accountId,
                'cost_center_id' => $this->costCenterId,
                'january' => $this->january ?? 0,
                'february' => $this->february ?? 0,
                'march' => $this->march ?? 0,
                'april' => $this->april ?? 0,
                'may' => $this->may ?? 0,
                'june' => $this->june ?? 0,
                'july' => $this->july ?? 0,
                'august' => $this->august ?? 0,
                'september' => $this->september ?? 0,
                'october' => $this->october ?? 0,
                'november' => $this->november ?? 0,
                'december' => $this->december ?? 0,
                'total' => $this->total,
                'status' => 'draft',
            ];
            
            if ($this->budgetId) {
                $budget = Budget::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->budgetId);
                $budget->update($data);
                session()->flash('success', 'Orçamento atualizado com sucesso!');
            } else {
                Budget::create($data);
                session()->flash('success', 'Orçamento criado com sucesso!');
            }
            
            $this->reset(['budgetId', 'name', 'accountId', 'costCenterId']);
            $this->resetMonthlyValues();
            $this->showModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    protected function resetMonthlyValues()
    {
        $this->january = $this->february = $this->march = $this->april = 0;
        $this->may = $this->june = $this->july = $this->august = 0;
        $this->september = $this->october = $this->november = $this->december = 0;
        $this->total = 0;
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $budgets = Budget::with(['account', 'costCenter'])
            ->where('tenant_id', $tenantId)
            ->where('year', $this->selectedYear)
            ->orderBy('name')
            ->get();
        
        $accounts = Account::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get();
        
        $costCenters = CostCenter::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        
        return view('livewire.accounting.budgets.budgets', [
            'budgets' => $budgets,
            'accounts' => $accounts,
            'costCenters' => $costCenters,
        ]);
    }
}

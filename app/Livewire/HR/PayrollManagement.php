<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Employee;
use App\Services\HR\PayrollService;
use Carbon\Carbon;

class PayrollManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $yearFilter = '';
    public $monthFilter = '';
    public $statusFilter = '';
    
    // Criar Folha
    public $showCreateModal = false;
    public $createYear;
    public $createMonth;
    
    // Ver Detalhes
    public $showDetailsModal = false;
    public $selectedPayroll;

    public function mount()
    {
        $this->createYear = date('Y');
        $this->createMonth = date('m');
        $this->yearFilter = date('Y');
    }

    public function createPayroll()
    {
        $this->validate([
            'createYear' => 'required|integer|min:2020|max:2100',
            'createMonth' => 'required|integer|min:1|max:12',
        ]);

        try {
            $payrollService = new PayrollService();
            $payroll = $payrollService->createPayroll(
                auth()->user()->tenant_id,
                $this->createYear,
                $this->createMonth
            );

            session()->flash('success', 'Folha de pagamento criada com sucesso!');
            $this->showCreateModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao criar folha: ' . $e->getMessage());
        }
    }

    public function processPayroll($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payrollService = new PayrollService();
            $payrollService->processPayroll($payroll);

            session()->flash('success', 'Folha processada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao processar folha: ' . $e->getMessage());
        }
    }

    public function approvePayroll($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payrollService = new PayrollService();
            $payrollService->approvePayroll($payroll, auth()->id());

            session()->flash('success', 'Folha aprovada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao aprovar folha: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payrollService = new PayrollService();
            $payrollService->markAsPaid($payroll, Carbon::now());

            session()->flash('success', 'Folha marcada como paga!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedPayroll = Payroll::with('items.employee')->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showDetailsModal = false;
        $this->selectedPayroll = null;
    }

    public function render()
    {
        $query = Payroll::where('tenant_id', auth()->user()->tenant_id)
            ->with(['approvedBy', 'processedBy']);

        if ($this->yearFilter) {
            $query->where('year', $this->yearFilter);
        }

        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where('payroll_number', 'like', '%' . $this->search . '%');
        }

        $payrolls = $query->latest()->paginate(12);
        
        // Anos disponíveis
        $years = range(date('Y'), date('Y') - 5);
        
        // Meses
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];

        return view('livewire.hr.payroll.payroll', [
            'payrolls' => $payrolls,
            'years' => $years,
            'months' => $months,
        ])->layout('layouts.app', ['title' => 'Folha de Pagamento']);
    }
}

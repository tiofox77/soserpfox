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
    public $statusFilter = 'all';
    public $perPage = 15;
    
    // Modals
    public $showCreateModal = false;
    public $showDetailsModal = false;
    public $showEditItemModal = false;
    public $showDeleteModal = false;
    
    // Criar Folha
    public $createYear;
    public $createMonth;
    
    // Ver Detalhes
    public $selectedPayroll;
    
    // Editar Item
    public $editingItem;
    public $itemBaseSalary;
    public $itemFoodAllowance;
    public $itemTransportAllowance;
    public $itemOvertimePay;
    public $itemBonuses;
    public $itemAbsenceDeduction;
    public $itemAdvancePayment;
    public $itemLoanDeduction;
    public $itemOtherDeductions;
    
    // Excluir
    public $deletingPayroll;

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
                auth()->user()->activeTenantId(),
                $this->createYear,
                $this->createMonth
            );

            $this->dispatch('notify', type: 'success', message: 'Folha de pagamento criada com sucesso!');
            $this->showCreateModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao criar folha: ' . $e->getMessage());
        }
    }

    public function processPayroll($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payrollService = new PayrollService();
            $payrollService->processPayroll($payroll);

            $this->dispatch('notify', type: 'success', message: 'Folha processada com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao processar folha: ' . $e->getMessage());
        }
    }

    public function approvePayroll($id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payrollService = new PayrollService();
            $payrollService->approvePayroll($payroll, auth()->id());

            $this->dispatch('notify', type: 'success', message: 'Folha aprovada com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao aprovar folha: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $payroll = Payroll::with('items')->findOrFail($id);
            $payrollService = new PayrollService();
            
            // Marcar como paga
            $payrollService->markAsPaid($payroll, Carbon::now());
            
            // Processar deduções de adiantamentos
            $payrollService->processAdvanceDeductions($payroll);

            $this->dispatch('notify', type: 'success', message: 'Folha marcada como paga e adiantamentos atualizados!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedPayroll = Payroll::with([
            'items.employee',
            'approvedBy',
            'processedBy'
        ])->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function editItem($itemId)
    {
        $this->editingItem = PayrollItem::with('employee')->findOrFail($itemId);
        $this->itemBaseSalary = $this->editingItem->base_salary;
        $this->itemFoodAllowance = $this->editingItem->food_allowance;
        $this->itemTransportAllowance = $this->editingItem->transport_allowance;
        $this->itemOvertimePay = $this->editingItem->overtime_pay;
        $this->itemBonuses = $this->editingItem->bonus;
        $this->itemAbsenceDeduction = $this->editingItem->absence_deduction;
        $this->itemAdvancePayment = $this->editingItem->advance_payment;
        $this->itemLoanDeduction = $this->editingItem->loan_deduction;
        $this->itemOtherDeductions = $this->editingItem->other_deductions;
        $this->showEditItemModal = true;
    }
    
    public function getActiveAdvancesProperty()
    {
        if (!$this->editingItem) {
            return collect();
        }
        
        return \App\Models\HR\SalaryAdvance::where('employee_id', $this->editingItem->employee_id)
            ->where('status', 'in_deduction')
            ->where('balance', '>', 0)
            ->get();
    }

    public function saveItem()
    {
        // Validar apenas os campos editáveis manualmente (Empréstimo e Outros Descontos)
        // Adiantamento é automático e não deve ser alterado manualmente
        $this->validate([
            'itemLoanDeduction' => 'nullable|numeric|min:0',
            'itemOtherDeductions' => 'nullable|numeric|min:0',
        ]);

        try {
            // Atualizar APENAS os descontos manuais
            // Adiantamento não é atualizado pois é calculado automaticamente
            $this->editingItem->update([
                'loan_deduction' => $this->itemLoanDeduction ?? 0,
                'other_deductions' => $this->itemOtherDeductions ?? 0,
            ]);

            // Recalcular IRT, INSS e líquido com os novos descontos
            $payrollService = new PayrollService();
            $payrollService->recalculateItem($this->editingItem);

            $this->dispatch('notify', type: 'success', message: 'Descontos atualizados com sucesso!');
            $this->closeEditItemModal();
            $this->viewDetails($this->editingItem->payroll_id);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao atualizar: ' . $e->getMessage());
        }
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->createYear = date('Y');
        $this->createMonth = date('m');
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedPayroll = null;
    }

    public function closeEditItemModal()
    {
        $this->showEditItemModal = false;
        $this->editingItem = null;
        $this->reset(['itemBaseSalary', 'itemFoodAllowance', 'itemTransportAllowance', 'itemBonuses', 'itemAdvancePayment', 'itemLoanDeduction', 'itemOtherDeductions']);
    }

    public function deletePayroll($id)
    {
        $this->deletingPayroll = Payroll::findOrFail($id);
        
        // Validar: não pode excluir folha já paga
        if ($this->deletingPayroll->status === 'paid') {
            $this->dispatch('notify', type: 'error', message: 'Não é possível excluir uma folha já paga!');
            return;
        }
        
        $this->showDeleteModal = true;
    }
    
    public function confirmDelete()
    {
        try {
            $payrollService = new PayrollService();
            $payrollService->deletePayroll($this->deletingPayroll);
            
            $this->dispatch('notify', type: 'success', message: 'Folha de pagamento excluída com sucesso!');
            
            $this->closeDeleteModal();
            
            // Fechar modal de detalhes se estiver aberto
            if ($this->showDetailsModal) {
                $this->closeDetailsModal();
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao excluir: ' . $e->getMessage());
        }
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingPayroll = null;
    }

    public function render()
    {
        $query = Payroll::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['approvedBy', 'processedBy']);

        if ($this->yearFilter) {
            $query->where('year', $this->yearFilter);
        }

        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where('payroll_number', 'like', '%' . $this->search . '%');
        }

        $payrolls = $query->latest()->paginate($this->perPage);
        
        // Stats
        $tenantId = auth()->user()->activeTenantId();
        $stats = [
            'total' => Payroll::where('tenant_id', $tenantId)->count(),
            'draft' => Payroll::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'processing' => Payroll::where('tenant_id', $tenantId)->where('status', 'processing')->count(),
            'approved' => Payroll::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
            'paid' => Payroll::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
        ];
        
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
            'stats' => $stats,
            'years' => $years,
            'months' => $months,
        ])->layout('layouts.app', ['title' => 'Folha de Pagamento']);
    }
}

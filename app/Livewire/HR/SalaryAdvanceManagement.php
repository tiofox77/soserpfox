<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\Employee;
use App\Services\HR\SalaryAdvanceService;
use Illuminate\Support\Facades\Auth;

class SalaryAdvanceManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $yearFilter = '';
    public $statusFilter = '';
    public $employeeFilter = '';
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $showApprovalModal = false;
    public $advanceId;
    
    // Form Fields
    public $employee_id = '';
    public $requested_amount = '';
    public $installments = 1;
    public $reason = '';
    public $notes = '';
    
    // Approval
    public $approvalAdvanceId;
    public $approved_amount = '';
    public $rejection_reason = '';
    public $approval_action = 'approve';
    
    // Details
    public $selectedAdvance;
    
    // Calculated
    public $maxAllowed = 0;
    public $availableAmount = 0;
    public $baseSalary = 0;
    public $installmentAmount = 0;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'requested_amount' => 'required|numeric|min:1',
        'installments' => 'required|integer|min:1|max:12',
        'reason' => 'required|string|min:10|max:500',
        'notes' => 'nullable|string|max:500',
    ];

    public function mount()
    {
        $this->yearFilter = date('Y');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'employee_id') {
            $this->calculateLimits();
        }

        if ($propertyName === 'requested_amount' || $propertyName === 'installments') {
            $this->calculateInstallment();
        }
    }

    private function calculateLimits()
    {
        if ($this->employee_id) {
            $employee = Employee::find($this->employee_id);
            if ($employee) {
                $advanceService = new SalaryAdvanceService();
                $limits = $advanceService->calculateMaxAllowed($employee);
                
                $this->baseSalary = $limits['base_salary'];
                $this->maxAllowed = $limits['max_allowed'];
                $this->availableAmount = $limits['available_amount'];
            }
        }
    }

    private function calculateInstallment()
    {
        if ($this->requested_amount && $this->installments > 0) {
            $this->installmentAmount = round($this->requested_amount / $this->installments, 2);
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $advanceService = new SalaryAdvanceService();
            
            $data = [
                'tenant_id' => tenant('id'),
                'employee_id' => $this->employee_id,
                'requested_amount' => $this->requested_amount,
                'installments' => $this->installments,
                'reason' => $this->reason,
                'notes' => $this->notes,
            ];

            $advanceService->createAdvanceRequest($data);
            session()->flash('success', 'Solicitação de adiantamento criada com sucesso!');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedAdvance = SalaryAdvance::with(['employee', 'approvedBy', 'rejectedBy', 'paidBy'])
            ->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function openApprovalModal($id, $action)
    {
        $this->approvalAdvanceId = $id;
        $this->approval_action = $action;
        $this->rejection_reason = '';
        
        $advance = SalaryAdvance::findOrFail($id);
        $this->approved_amount = $advance->requested_amount;
        
        $this->showApprovalModal = true;
    }

    public function processApproval()
    {
        if ($this->approval_action === 'approve') {
            $this->approve();
        } else {
            $this->reject();
        }
    }

    public function approve()
    {
        $this->validate([
            'approved_amount' => 'required|numeric|min:1',
        ]);

        try {
            $advance = SalaryAdvance::findOrFail($this->approvalAdvanceId);
            $advance->approve(Auth::id(), $this->approved_amount);
            
            session()->flash('success', 'Adiantamento aprovado com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao aprovar adiantamento: ' . $e->getMessage());
        }
    }

    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $advance = SalaryAdvance::findOrFail($this->approvalAdvanceId);
            $advance->reject(Auth::id(), $this->rejection_reason);
            
            session()->flash('success', 'Adiantamento rejeitado com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao rejeitar adiantamento: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'approved') {
                session()->flash('error', 'Apenas adiantamentos aprovados podem ser marcados como pagos!');
                return;
            }

            $advance->markAsPaid(Auth::id());
            session()->flash('success', 'Adiantamento marcado como pago com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao marcar como pago: ' . $e->getMessage());
        }
    }

    public function startDeduction($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'paid') {
                session()->flash('error', 'Apenas adiantamentos pagos podem iniciar dedução!');
                return;
            }

            $advance->startDeduction();
            session()->flash('success', 'Dedução iniciada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao iniciar dedução: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'pending' && $advance->status !== 'cancelled') {
                session()->flash('error', 'Apenas adiantamentos pendentes podem ser excluídos!');
                return;
            }

            $advance->delete();
            session()->flash('success', 'Adiantamento removido com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao remover adiantamento: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDetailsModal = false;
        $this->showApprovalModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->advanceId = null;
        $this->employee_id = '';
        $this->requested_amount = '';
        $this->installments = 1;
        $this->reason = '';
        $this->notes = '';
        $this->approved_amount = '';
        $this->rejection_reason = '';
        $this->maxAllowed = 0;
        $this->availableAmount = 0;
        $this->baseSalary = 0;
        $this->installmentAmount = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = SalaryAdvance::where('tenant_id', tenant('id'))
            ->with(['employee', 'approvedBy']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('advance_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('employee', function ($q2) {
                      $q2->where('first_name', 'like', '%' . $this->search . '%')
                         ->orWhere('last_name', 'like', '%' . $this->search . '%')
                         ->orWhere('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->yearFilter) {
            $query->whereYear('request_date', $this->yearFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }

        $advances = $query->latest()->paginate(15);
        $employees = Employee::where('tenant_id', tenant('id'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.advances.advances', [
            'advances' => $advances,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Adiantamentos Salariais']);
    }
}

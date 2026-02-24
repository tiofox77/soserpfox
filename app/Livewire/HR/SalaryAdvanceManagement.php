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
    public $viewType = 'list'; // 'list' ou 'grid'
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $showApprovalModal = false;
    public $showRejectionModal = false;
    public $showInstallmentModal = false;
    public $showPaymentModal = false;
    public $editMode = false;
    public $advanceId;
    public $installmentAdvanceId;
    public $paymentAdvanceId;
    public $paymentType = 'total';
    public $paymentAmount = 0;
    public $paymentInstallments = '';
    public $paymentDate;
    public $paymentNotes = '';
    
    // Form Fields
    public $employee_id = '';
    public $requested_amount = '';
    public $installments = 1;
    public $reason = '';
    public $notes = '';
    
    // Approval
    public $approvalAdvanceId;
    public $approved_amount = '';
    public $custom_installment_amount = '';
    public $approval_notes = '';
    public $rejection_reason = '';
    public $approval_action = 'approve';
    
    // Details
    public $selectedAdvance;
    
    // Calculated from employee
    public $maxPercentage = 50;
    public $alreadyAdvanced = 0;
    
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
        $this->paymentDate = date('Y-m-d');
        // Carregar percentual das configurações
        $this->maxPercentage = \App\Models\HR\HRSetting::get('max_salary_advance_percentage', 50);
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
                $this->maxPercentage = $limits['percentage'];
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
        // Carregar percentual das configurações
        $this->maxPercentage = \App\Models\HR\HRSetting::get('max_salary_advance_percentage', 50);
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $advanceService = new SalaryAdvanceService();
            
            $data = [
                'tenant_id' => auth()->user()->tenant_id,
                'employee_id' => $this->employee_id,
                'requested_amount' => $this->requested_amount,
                'installments' => $this->installments,
                'reason' => $this->reason,
                'notes' => $this->notes,
            ];

            $advanceService->createAdvanceRequest($data);
            
            $this->dispatch('notify', type: 'success', message: 'Solicitação de adiantamento criada com sucesso!');
            $this->closeModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
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
        $this->approval_notes = '';
        
        $advance = SalaryAdvance::findOrFail($id);
        $this->selectedAdvance = $advance;
        $this->approved_amount = $advance->requested_amount;
        $this->custom_installment_amount = round($advance->requested_amount / $advance->installments, 2);
        
        if ($action === 'approve') {
            $this->showApprovalModal = true;
        } else {
            $this->showRejectionModal = true;
        }
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
            'custom_installment_amount' => 'required|numeric|min:1',
        ]);

        try {
            $advance = SalaryAdvance::findOrFail($this->approvalAdvanceId);
            
            // Aprovar com valor customizado
            $advance->update([
                'status' => 'approved',
                'approved_amount' => $this->approved_amount,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'balance' => $this->approved_amount,
                'installment_amount' => $this->custom_installment_amount, // Valor customizado
            ]);
            
            $this->dispatch('notify', type: 'success', message: 'Adiantamento aprovado com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao aprovar adiantamento: ' . $e->getMessage());
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
            
            $this->dispatch('notify', type: 'success', message: 'Adiantamento rejeitado com sucesso!');
            $this->showRejectionModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao rejeitar adiantamento: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'approved') {
                $this->dispatch('notify', type: 'error', message: 'Apenas adiantamentos aprovados podem ser marcados como pagos!');
                return;
            }

            $advance->markAsPaid(Auth::id());
            $this->dispatch('notify', type: 'success', message: 'Adiantamento marcado como pago com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao marcar como pago: ' . $e->getMessage());
        }
    }

    public function startDeduction($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'paid') {
                $this->dispatch('notify', type: 'error', message: 'Apenas adiantamentos pagos podem iniciar dedução!');
                return;
            }

            $advance->startDeduction();
            $this->dispatch('notify', type: 'success', message: 'Dedução iniciada com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao iniciar dedução: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $advance = SalaryAdvance::findOrFail($id);
            
            if ($advance->status !== 'pending' && $advance->status !== 'cancelled') {
                $this->dispatch('notify', type: 'error', message: 'Apenas adiantamentos pendentes podem ser excluídos!');
                return;
            }

            $advance->delete();
            $this->dispatch('notify', type: 'success', message: 'Adiantamento removido com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao remover adiantamento: ' . $e->getMessage());
        }
    }

    public function generatePDF($id)
    {
        $advance = SalaryAdvance::with(['employee', 'approvedBy'])->findOrFail($id);
        
        return response()->streamDownload(function () use ($advance) {
            echo view('livewire.hr.advances.pdf', compact('advance'))->render();
        }, 'adiantamento-' . $advance->advance_number . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function openInstallmentModal($id)
    {
        $this->installmentAdvanceId = $id;
        $advance = SalaryAdvance::findOrFail($id);
        $this->installmentAmount = $advance->installment_amount;
        $this->showInstallmentModal = true;
    }

    public function processInstallment()
    {
        try {
            $advance = SalaryAdvance::findOrFail($this->installmentAdvanceId);
            
            if ($advance->status !== 'in_deduction') {
                $this->dispatch('notify', type: 'error', message: 'Apenas adiantamentos em dedução podem ter prestações processadas!');
                return;
            }

            if ($advance->balance <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Este adiantamento já foi completamente pago!');
                return;
            }

            // Processar prestação
            $advance->recordInstallmentPayment($this->installmentAmount);
            
            $this->dispatch('notify', type: 'success', message: 'Prestação processada com sucesso!');
            $this->showInstallmentModal = false;
            $this->installmentAdvanceId = null;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao processar prestação: ' . $e->getMessage());
        }
    }

    public function openPaymentModal($id)
    {
        $this->paymentAdvanceId = $id;
        $advance = SalaryAdvance::findOrFail($id);
        
        // Resetar valores
        $this->paymentType = 'total';
        $this->paymentAmount = 0;
        $this->paymentInstallments = '';
        $this->paymentDate = date('Y-m-d');
        $this->paymentNotes = '';
        
        $this->showPaymentModal = true;
    }

    public function processPayment()
    {
        try {
            $advance = SalaryAdvance::findOrFail($this->paymentAdvanceId);
            
            $balance = $advance->balance ?? $advance->approved_amount;
            $paymentValue = 0;

            // Calcular valor do pagamento baseado no tipo
            if ($this->paymentType === 'total') {
                $paymentValue = $balance;
            } elseif ($this->paymentType === 'installment') {
                if (empty($this->paymentInstallments)) {
                    $this->dispatch('notify', type: 'error', message: 'Selecione o número de parcelas!');
                    return;
                }
                $paymentValue = $balance / $this->paymentInstallments;
            } elseif ($this->paymentType === 'custom') {
                if ($this->paymentAmount <= 0) {
                    $this->dispatch('notify', type: 'error', message: 'Informe um valor válido!');
                    return;
                }
                if ($this->paymentAmount > $balance) {
                    $this->dispatch('notify', type: 'error', message: 'Valor não pode ser maior que o saldo!');
                    return;
                }
                $paymentValue = $this->paymentAmount;
            }

            // Registrar pagamento
            $advance->recordInstallmentPayment($paymentValue);
            
            // Adicionar notas se houver
            if ($this->paymentNotes) {
                $currentNotes = $advance->notes ? $advance->notes . "\n" : '';
                $advance->update([
                    'notes' => $currentNotes . "[" . date('d/m/Y') . "] " . $this->paymentNotes
                ]);
            }

            $this->dispatch('notify', type: 'success', message: 'Pagamento registrado com sucesso!');
            $this->showPaymentModal = false;
            $this->paymentAdvanceId = null;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao processar pagamento: ' . $e->getMessage());
        }
    }

    public function setViewType($type)
    {
        $this->viewType = $type;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDetailsModal = false;
        $this->showApprovalModal = false;
        $this->showRejectionModal = false;
        $this->showInstallmentModal = false;
        $this->showPaymentModal = false;
        $this->installmentAdvanceId = null;
        $this->paymentAdvanceId = null;
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
        $query = SalaryAdvance::where('tenant_id', auth()->user()->tenant_id)
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
        $employees = Employee::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.advances.advances', [
            'advances' => $advances,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Adiantamentos Salariais']);
    }
}

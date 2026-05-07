<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\HR\SalaryDiscount;
use App\Models\HR\Employee;
use Illuminate\Support\Facades\Auth;

class SalaryDiscountManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $yearFilter = '';
    public $statusFilter = '';
    public $employeeFilter = '';
    public $viewType = 'list';

    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $showApprovalModal = false;
    public $showRejectionModal = false;
    public $showPaymentModal = false;
    public $editMode = false;
    public $discountId;
    public $paymentDiscountId;

    // Form Fields
    public $employee_id = '';
    public $discount_type = '';
    public $request_date = '';
    public $amount = '';
    public $installments = 1;
    public $reason = '';
    public $notes = '';
    public $signed_document;

    // Approval
    public $approvalDiscountId;
    public $approval_action = 'approve';
    public $rejection_reason = '';

    // Details
    public $selectedDiscount;

    // Calculated
    public $installmentAmount = 0;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'discount_type' => 'required|string',
        'request_date' => 'required|date',
        'amount' => 'required|numeric|min:1',
        'installments' => 'required|integer|min:1|max:24',
        'reason' => 'required|string|min:10|max:500',
        'notes' => 'nullable|string|max:500',
        'signed_document' => 'nullable|file|max:5120',
    ];

    public function mount()
    {
        $this->yearFilter = date('Y');
        $this->request_date = date('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'amount' || $propertyName === 'installments') {
            $this->calculateInstallment();
        }
    }

    private function calculateInstallment()
    {
        if ($this->amount && $this->installments > 0) {
            $this->installmentAmount = round($this->amount / $this->installments, 2);
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->request_date = date('Y-m-d');
        $this->showModal = true;
    }

    public function edit($id)
    {
        $discount = SalaryDiscount::findOrFail($id);

        if ($discount->status !== 'pending') {
            $this->dispatch('notify', type: 'error', message: 'Apenas descontos pendentes podem ser editados!');
            return;
        }

        $this->discountId = $id;
        $this->editMode = true;
        $this->employee_id = $discount->employee_id;
        $this->discount_type = $discount->discount_type;
        $this->request_date = $discount->request_date->format('Y-m-d');
        $this->amount = $discount->amount;
        $this->installments = $discount->installments;
        $this->reason = $discount->reason;
        $this->notes = $discount->notes;
        $this->calculateInstallment();
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $data = [
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $this->employee_id,
                'discount_type' => $this->discount_type,
                'request_date' => $this->request_date,
                'amount' => $this->amount,
                'installments' => $this->installments,
                'installment_amount' => round($this->amount / $this->installments, 2),
                'remaining_installments' => $this->installments,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'created_by' => Auth::id(),
            ];

            if ($this->signed_document) {
                $data['signed_document'] = $this->signed_document->store('hr/salary-discounts', 'public');
            }

            if ($this->editMode && $this->discountId) {
                $discount = SalaryDiscount::findOrFail($this->discountId);
                $discount->update($data);
                $this->dispatch('notify', type: 'success', message: 'Desconto salarial atualizado com sucesso!');
            } else {
                SalaryDiscount::create($data);
                $this->dispatch('notify', type: 'success', message: 'Desconto salarial criado com sucesso!');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedDiscount = SalaryDiscount::with(['employee', 'approvedBy', 'rejectedBy', 'creator'])
            ->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function openApprovalModal($id, $action)
    {
        $this->approvalDiscountId = $id;
        $this->approval_action = $action;
        $this->rejection_reason = '';
        $this->selectedDiscount = SalaryDiscount::with('employee')->findOrFail($id);

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
        try {
            $discount = SalaryDiscount::findOrFail($this->approvalDiscountId);
            $discount->approve(Auth::id());
            $this->dispatch('notify', type: 'success', message: 'Desconto aprovado com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $discount = SalaryDiscount::findOrFail($this->approvalDiscountId);
            $discount->reject(Auth::id(), $this->rejection_reason);
            $this->dispatch('notify', type: 'success', message: 'Desconto rejeitado com sucesso!');
            $this->showRejectionModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao rejeitar: ' . $e->getMessage());
        }
    }

    public function registerPayment($id)
    {
        try {
            $discount = SalaryDiscount::findOrFail($id);

            if ($discount->status !== 'approved') {
                $this->dispatch('notify', type: 'error', message: 'Apenas descontos aprovados podem ter pagamentos registados!');
                return;
            }

            if ($discount->remaining_installments <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Todas as prestações já foram pagas!');
                return;
            }

            $discount->registerPayment();
            $this->dispatch('notify', type: 'success', message: 'Pagamento registado com sucesso! Prestações restantes: ' . $discount->remaining_installments);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao registar pagamento: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $discount = SalaryDiscount::findOrFail($id);

            if (!in_array($discount->status, ['pending', 'rejected'])) {
                $this->dispatch('notify', type: 'error', message: 'Apenas descontos pendentes ou rejeitados podem ser eliminados!');
                return;
            }

            $discount->delete();
            $this->dispatch('notify', type: 'success', message: 'Desconto eliminado com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao eliminar: ' . $e->getMessage());
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
        $this->showPaymentModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->discountId = null;
        $this->editMode = false;
        $this->employee_id = '';
        $this->discount_type = '';
        $this->amount = '';
        $this->installments = 1;
        $this->reason = '';
        $this->notes = '';
        $this->signed_document = null;
        $this->installmentAmount = 0;
        $this->rejection_reason = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['employee', 'approvedBy']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('employee', function ($q2) {
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

        $discounts = $query->latest()->paginate(15);
        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $stats = [
            'total' => SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())->count(),
            'pending' => SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())->where('status', 'pending')->count(),
            'approved' => SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())->where('status', 'approved')->count(),
            'completed' => SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())->where('status', 'completed')->count(),
            'total_amount' => SalaryDiscount::where('tenant_id', auth()->user()->activeTenantId())->whereIn('status', ['approved', 'completed'])->sum('amount'),
        ];

        return view('livewire.hr.discounts.discounts', [
            'discounts' => $discounts,
            'employees' => $employees,
            'stats' => $stats,
        ])->layout('layouts.app', ['title' => 'Descontos Salariais']);
    }
}

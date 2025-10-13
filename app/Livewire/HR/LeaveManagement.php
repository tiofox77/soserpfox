<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\HR\Leave;
use App\Models\HR\Employee;
use App\Services\HR\LeaveService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeaveManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $yearFilter = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $employeeFilter = '';
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $editMode = false;
    public $leaveId;
    
    // Form Fields
    public $employee_id = '';
    public $leave_type = 'justified';
    public $start_date = '';
    public $end_date = '';
    public $reason = '';
    public $notes = '';
    public $has_medical_certificate = false;
    public $document = null;
    
    // Details
    public $selectedLeave;
    
    // Approval
    public $showApprovalModal = false;
    public $approvalLeaveId;
    public $rejection_reason = '';

    // Calculated
    public $totalDays = 0;
    public $workingDays = 0;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'leave_type' => 'required|in:sick,maternity,paternity,bereavement,marriage,study,unpaid,justified,other',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'reason' => 'required|string|min:10|max:500',
        'notes' => 'nullable|string|max:500',
        'has_medical_certificate' => 'boolean',
        'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
        if ($propertyName === 'start_date' || $propertyName === 'end_date') {
            $this->calculateDays();
        }
    }

    private function calculateDays()
    {
        if ($this->start_date && $this->end_date) {
            try {
                $leaveService = new LeaveService();
                $start = \Carbon\Carbon::parse($this->start_date);
                $end = \Carbon\Carbon::parse($this->end_date);
                
                $this->totalDays = $end->diffInDays($start) + 1;
                $this->workingDays = $leaveService->calculateWorkingDays($start, $end);
            } catch (\Exception $e) {
                $this->totalDays = 0;
                $this->workingDays = 0;
            }
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $leaveService = new LeaveService();
            
            $documentPath = null;
            if ($this->document) {
                $documentPath = $this->document->store('hr/leaves', 'public');
            }

            $data = [
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $this->employee_id,
                'leave_type' => $this->leave_type,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'has_medical_certificate' => $this->has_medical_certificate,
                'document_path' => $documentPath,
                'paid' => $this->leave_type !== 'unpaid',
            ];

            $leaveService->createLeaveRequest($data);
            session()->flash('success', 'Solicitação de licença criada com sucesso!');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedLeave = Leave::with(['employee', 'approvedBy', 'rejectedBy'])
            ->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function openApprovalModal($id, $action)
    {
        $this->approvalLeaveId = $id;
        $this->rejection_reason = '';
        
        if ($action === 'approve') {
            $this->approve();
        } else {
            $this->showApprovalModal = true;
        }
    }

    public function approve()
    {
        try {
            $leave = Leave::findOrFail($this->approvalLeaveId);
            $leave->approve(Auth::id());
            
            session()->flash('success', 'Licença aprovada com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao aprovar licença: ' . $e->getMessage());
        }
    }

    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $leave = Leave::findOrFail($this->approvalLeaveId);
            $leave->reject(Auth::id(), $this->rejection_reason);
            
            session()->flash('success', 'Licença rejeitada com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao rejeitar licença: ' . $e->getMessage());
        }
    }

    public function cancel($id)
    {
        try {
            $leave = Leave::findOrFail($id);
            
            if ($leave->status !== 'pending') {
                session()->flash('error', 'Apenas licenças pendentes podem ser canceladas!');
                return;
            }

            $leave->cancel();
            session()->flash('success', 'Licença cancelada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cancelar licença: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $leave = Leave::findOrFail($id);
            
            if ($leave->status !== 'pending' && $leave->status !== 'cancelled') {
                session()->flash('error', 'Apenas licenças pendentes ou canceladas podem ser excluídas!');
                return;
            }

            // Deletar documento se existir
            if ($leave->document_path) {
                Storage::disk('public')->delete($leave->document_path);
            }

            $leave->delete();
            session()->flash('success', 'Licença removida com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao remover licença: ' . $e->getMessage());
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
        $this->leaveId = null;
        $this->employee_id = '';
        $this->leave_type = 'justified';
        $this->start_date = '';
        $this->end_date = '';
        $this->reason = '';
        $this->notes = '';
        $this->has_medical_certificate = false;
        $this->document = null;
        $this->rejection_reason = '';
        $this->totalDays = 0;
        $this->workingDays = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Leave::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['employee', 'approvedBy']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('leave_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('employee', function ($q2) {
                      $q2->where('first_name', 'like', '%' . $this->search . '%')
                         ->orWhere('last_name', 'like', '%' . $this->search . '%')
                         ->orWhere('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->yearFilter) {
            $query->whereYear('start_date', $this->yearFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('leave_type', $this->typeFilter);
        }

        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }

        $leaves = $query->latest()->paginate(15);
        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.leaves.leaves', [
            'leaves' => $leaves,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Licenças e Faltas']);
    }
}

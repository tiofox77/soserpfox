<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\HR\Overtime;
use App\Models\HR\Employee;
use App\Services\HR\OvertimeService;
use Illuminate\Support\Facades\Auth;

class OvertimeManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $monthFilter = '';
    public $yearFilter = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $employeeFilter = '';
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $showRejectionModal = false;
    public $editMode = false;
    public $overtimeId;
    
    // Form Fields
    public $employee_id = '';
    public $date = '';
    public $input_type = 'time_range'; // time_range | daily_hours | monthly_hours
    public $start_time = '';
    public $end_time = '';
    public $direct_hours = '';
    public $overtime_type = 'weekday';
    public $description = '';
    public $notes = '';
    
    // Details
    public $selectedOvertime;
    
    // Approval
    public $showApprovalModal = false;
    public $approvalOvertimeId;
    public $rejection_reason = '';

    // Calculated
    public $totalHours = 0;
    public $totalAmount = 0;
    public $overtimeRate = 0;

    protected function rules()
    {
        $rules = [
            'employee_id' => 'required|exists:hr_employees,id',
            'date' => 'required|date',
            'input_type' => 'required|in:time_range,daily_hours,monthly_hours',
            'overtime_type' => 'required|in:regular,weekday,weekend,holiday,night',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ];

        if ($this->input_type === 'time_range') {
            $rules['start_time'] = 'required';
            $rules['end_time'] = 'required';
        } else {
            $rules['direct_hours'] = 'required|numeric|min:0.5|max:744';
        }

        return $rules;
    }

    public function mount()
    {
        $this->yearFilter = date('Y');
        $this->monthFilter = date('m');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        // Normalizar hora para formato 24h sempre que atualizar
        if ($propertyName === 'start_time' && $this->start_time) {
            $this->start_time = date('H:i', strtotime($this->start_time));
        }
        
        if ($propertyName === 'end_time' && $this->end_time) {
            $this->end_time = date('H:i', strtotime($this->end_time));
        }
        
        if (in_array($propertyName, ['start_time', 'end_time', 'employee_id', 'overtime_type', 'direct_hours', 'input_type'])) {
            $this->calculateOvertime();
        }
    }

    private function calculateOvertime()
    {
        if (!$this->employee_id) return;

        $canCalculate = false;
        $hours = 0;

        if ($this->input_type === 'time_range' && $this->start_time && $this->end_time) {
            $canCalculate = true;
        } elseif (in_array($this->input_type, ['daily_hours', 'monthly_hours']) && $this->direct_hours > 0) {
            $canCalculate = true;
            $hours = (float) $this->direct_hours;
        }

        if ($canCalculate) {
            try {
                $overtimeService = new OvertimeService();
                $employee = Employee::find($this->employee_id);
                
                if ($employee) {
                    if ($this->input_type === 'time_range') {
                        $hours = $overtimeService->calculateHours($this->start_time, $this->end_time);
                    }
                    $this->totalHours = $hours;
                    
                    $calculations = $overtimeService->calculateOvertimePay($employee, $hours, $this->overtime_type);
                    $this->totalAmount = $calculations['total_amount'];
                    $this->overtimeRate = $calculations['overtime_rate'];
                }
            } catch (\Exception $e) {
                $this->totalHours = 0;
                $this->totalAmount = 0;
                $this->overtimeRate = 0;
            }
        } else {
            $this->totalHours = 0;
            $this->totalAmount = 0;
            $this->overtimeRate = 0;
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
            $overtimeService = new OvertimeService();
            
            $data = [
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $this->employee_id,
                'date' => $this->date,
                'input_type' => $this->input_type,
                'start_time' => $this->input_type === 'time_range' ? $this->start_time : null,
                'end_time' => $this->input_type === 'time_range' ? $this->end_time : null,
                'direct_hours' => in_array($this->input_type, ['daily_hours', 'monthly_hours']) ? (float) $this->direct_hours : null,
                'period_type' => $this->input_type === 'monthly_hours' ? 'monthly' : 'daily',
                'overtime_type' => $this->overtime_type,
                'description' => $this->description,
                'notes' => $this->notes,
            ];

            $overtimeService->createOvertimeRecord($data);
            session()->flash('success', 'Hora extra registrada com sucesso!');

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedOvertime = Overtime::with(['employee', 'approvedBy', 'rejectedBy', 'paidBy', 'attendance'])
            ->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function openApprovalModal($id, $action)
    {
        $this->approvalOvertimeId = $id;
        $this->rejection_reason = '';
        
        if ($action === 'approve') {
            $this->approve();
        } else {
            $this->showRejectionModal = true;
        }
    }

    public function approve()
    {
        try {
            $overtime = Overtime::findOrFail($this->approvalOvertimeId);
            $overtime->approve(Auth::id());
            
            session()->flash('success', 'Hora extra aprovada com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao aprovar hora extra: ' . $e->getMessage());
        }
    }

    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $overtime = Overtime::findOrFail($this->approvalOvertimeId);
            $overtime->reject(Auth::id(), $this->rejection_reason);
            
            session()->flash('success', 'Hora extra rejeitada com sucesso!');
            $this->showRejectionModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao rejeitar hora extra: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $overtime = Overtime::findOrFail($id);
            
            if ($overtime->status !== 'approved') {
                session()->flash('error', 'Apenas horas extras aprovadas podem ser marcadas como pagas!');
                return;
            }

            $overtime->markAsPaid(Auth::id());
            session()->flash('success', 'Hora extra marcada como paga com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao marcar como pago: ' . $e->getMessage());
        }
    }

    public function cancel($id)
    {
        try {
            $overtime = Overtime::findOrFail($id);
            
            if ($overtime->status === 'paid') {
                session()->flash('error', 'Horas extras pagas não podem ser canceladas!');
                return;
            }

            $overtime->cancel();
            session()->flash('success', 'Hora extra cancelada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cancelar hora extra: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $overtime = Overtime::findOrFail($id);
            
            if ($overtime->status !== 'pending' && $overtime->status !== 'cancelled') {
                session()->flash('error', 'Apenas horas extras pendentes ou canceladas podem ser excluídas!');
                return;
            }

            $overtime->delete();
            session()->flash('success', 'Hora extra removida com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao remover hora extra: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDetailsModal = false;
        $this->showApprovalModal = false;
        $this->showRejectionModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->overtimeId = null;
        $this->employee_id = '';
        $this->date = '';
        $this->input_type = 'time_range';
        $this->start_time = '';
        $this->end_time = '';
        $this->direct_hours = '';
        $this->overtime_type = 'weekday';
        $this->description = '';
        $this->notes = '';
        $this->rejection_reason = '';
        $this->totalHours = 0;
        $this->totalAmount = 0;
        $this->overtimeRate = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Overtime::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['employee', 'approvedBy']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('overtime_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('employee', function ($q2) {
                      $q2->where('first_name', 'like', '%' . $this->search . '%')
                         ->orWhere('last_name', 'like', '%' . $this->search . '%')
                         ->orWhere('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->yearFilter) {
            $query->whereYear('date', $this->yearFilter);
        }

        if ($this->monthFilter) {
            $query->whereMonth('date', $this->monthFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('overtime_type', $this->typeFilter);
        }

        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }

        $overtimes = $query->latest('date')->paginate(15);
        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.overtime.overtime', [
            'overtimes' => $overtimes,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Horas Extras']);
    }
}

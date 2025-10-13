<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\HR\Vacation;
use App\Models\HR\Employee;
use App\Services\HR\VacationService;
use Illuminate\Support\Facades\Auth;

class VacationManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $view = 'list'; // list or calendar
    public $search = '';
    public $yearFilter = '';
    public $statusFilter = '';
    public $employeeFilter = '';
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $editMode = false;
    public $vacationId;
    
    // Form Fields
    public $employee_id = '';
    public $reference_year = '';
    public $vacation_type = 'normal';
    public $can_split = true;
    public $split_number = null;
    public $total_splits = 1;
    public $parent_vacation_id = null;
    public $start_date = '';
    public $end_date = '';
    public $notes = '';
    public $replacement_employee_id = '';
    public $attachment = null;
    public $is_collective = false;
    public $collective_group = null;
    
    // Details
    public $selectedVacation;
    
    // Approval
    public $showApprovalModal = false;
    public $approvalVacationId;
    public $rejection_reason = '';

    // Calculated fields (read-only)
    public $availableDays = 0;
    public $requestedDays = 0;
    public $workingDays = 0;
    public $totalAmount = 0;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'reference_year' => 'required|integer|min:2020|max:2050',
        'vacation_type' => 'required|in:normal,accumulated,advance,collective',
        'can_split' => 'boolean',
        'start_date' => 'required|date|after:today',
        'end_date' => 'required|date|after:start_date',
        'notes' => 'nullable|string|max:500',
        'replacement_employee_id' => 'nullable|exists:hr_employees,id',
        'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'is_collective' => 'boolean',
        'collective_group' => 'nullable|string|max:255',
    ];

    public function mount()
    {
        $this->yearFilter = date('Y');
        $this->reference_year = date('Y');
    }

    public function changeView($view)
    {
        $this->view = $view;
    }

    public function getCalendarEvents($start, $end)
    {
        $vacations = Vacation::where('tenant_id', auth()->user()->activeTenantId())
            ->with('employee')
            ->whereBetween('start_date', [$start, $end])
            ->orWhereBetween('end_date', [$start, $end])
            ->get();

        return $vacations->map(function ($vacation) {
            $statusLabels = [
                'pending' => 'Pendente',
                'approved' => 'Aprovada',
                'rejected' => 'Rejeitada',
                'in_progress' => 'Em Andamento',
                'completed' => 'Concluída',
                'cancelled' => 'Cancelada',
            ];

            return [
                'id' => $vacation->id,
                'vacation_number' => $vacation->vacation_number,
                'employee_name' => $vacation->employee->full_name,
                'start_date' => $vacation->start_date->format('Y-m-d'),
                'end_date' => $vacation->end_date->format('Y-m-d'),
                'end_date_plus_one' => $vacation->end_date->addDay()->format('Y-m-d'), // FullCalendar exclusive end
                'working_days' => $vacation->working_days,
                'status' => $vacation->status,
                'status_label' => $statusLabels[$vacation->status] ?? $vacation->status,
            ];
        });
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        // Calcular dias disponíveis quando funcionário ou ano muda
        if ($propertyName === 'employee_id' || $propertyName === 'reference_year') {
            $this->calculateAvailableDays();
        }

        // Calcular dias de trabalho quando datas mudam
        if ($propertyName === 'start_date' || $propertyName === 'end_date') {
            $this->calculateWorkingDays();
        }
    }

    private function calculateAvailableDays()
    {
        if ($this->employee_id && $this->reference_year) {
            $employee = Employee::find($this->employee_id);
            if ($employee) {
                $vacationService = new VacationService();
                $availability = $vacationService->getAvailableVacationDays($employee, $this->reference_year);
                $this->availableDays = $availability['available'];
            }
        }
    }

    private function calculateWorkingDays()
    {
        if ($this->start_date && $this->end_date && $this->employee_id) {
            try {
                $vacationService = new VacationService();
                $start = \Carbon\Carbon::parse($this->start_date);
                $end = \Carbon\Carbon::parse($this->end_date);
                
                $this->workingDays = $vacationService->calculateWorkingDays($start, $end);
                $this->requestedDays = $end->diffInDays($start) + 1;
                
                // Calcular valores financeiros
                $employee = Employee::find($this->employee_id);
                if ($employee) {
                    $financials = $vacationService->calculateVacationPay($employee, $this->workingDays);
                    $this->totalAmount = $financials['total_amount'];
                }
            } catch (\Exception $e) {
                $this->workingDays = 0;
                $this->requestedDays = 0;
                $this->totalAmount = 0;
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
            $vacationService = new VacationService();
            
            // Verificar sobreposição
            $hasOverlap = $vacationService->checkOverlap(
                $this->employee_id,
                $this->start_date,
                $this->end_date,
                $this->editMode ? $this->vacationId : null
            );

            if ($hasOverlap) {
                session()->flash('error', 'O funcionário já possui férias programadas neste período!');
                return;
            }

            // Upload de anexo se houver
            $attachmentPath = null;
            if ($this->attachment) {
                $attachmentPath = $this->attachment->store('vacation-documents', 'public');
            }

            $data = [
                'tenant_id' => tenant('id'),
                'employee_id' => $this->employee_id,
                'reference_year' => $this->reference_year,
                'vacation_type' => $this->vacation_type,
                'can_split' => $this->can_split,
                'split_number' => $this->split_number,
                'total_splits' => $this->total_splits,
                'parent_vacation_id' => $this->parent_vacation_id,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'notes' => $this->notes,
                'replacement_employee_id' => $this->replacement_employee_id,
                'attachment_path' => $attachmentPath,
                'is_collective' => $this->is_collective,
                'collective_group' => $this->collective_group,
            ];

            if ($this->editMode) {
                $vacation = Vacation::findOrFail($this->vacationId);
                if ($vacation->status !== 'pending') {
                    session()->flash('error', 'Apenas férias pendentes podem ser editadas!');
                    return;
                }
                
                // Para edição, precisamos recalcular
                $vacation->delete();
                $vacationService->createVacationRequest($data);
                session()->flash('success', 'Solicitação de férias atualizada com sucesso!');
            } else {
                $vacationService->createVacationRequest($data);
                session()->flash('success', 'Solicitação de férias criada com sucesso!');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedVacation = Vacation::with([
            'employee',
            'employee.department',
            'employee.position',
            'approvedBy',
            'rejectedBy',
            'replacementEmployee',
            'replacementEmployee.position',
            'paidBy',
            'advancePaidBy',
            'cancelledBy',
            'payroll',
            'parentVacation',
            'splits'
        ])->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function openApprovalModal($id, $action)
    {
        $this->approvalVacationId = $id;
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
            $vacation = Vacation::findOrFail($this->approvalVacationId);
            $vacation->approve(Auth::id());
            
            session()->flash('success', 'Férias aprovadas com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao aprovar férias: ' . $e->getMessage());
        }
    }

    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $vacation = Vacation::findOrFail($this->approvalVacationId);
            $vacation->reject(Auth::id(), $this->rejection_reason);
            
            session()->flash('success', 'Férias rejeitadas com sucesso!');
            $this->showApprovalModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao rejeitar férias: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        try {
            $vacation = Vacation::findOrFail($id);
            
            if ($vacation->status !== 'approved' && $vacation->status !== 'in_progress') {
                session()->flash('error', 'Apenas férias aprovadas ou em andamento podem ser marcadas como pagas!');
                return;
            }

            $vacation->markAsPaid(Auth::id());
            session()->flash('success', 'Férias marcadas como pagas com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao marcar como pago: ' . $e->getMessage());
        }
    }

    public function cancel($id)
    {
        try {
            $vacation = Vacation::findOrFail($id);
            
            if ($vacation->status === 'completed' || $vacation->status === 'in_progress') {
                session()->flash('error', 'Férias em andamento ou concluídas não podem ser canceladas!');
                return;
            }

            $vacation->update(['status' => 'cancelled']);
            session()->flash('success', 'Férias canceladas com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cancelar férias: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $vacation = Vacation::findOrFail($id);
            
            if ($vacation->status !== 'pending' && $vacation->status !== 'cancelled') {
                session()->flash('error', 'Apenas férias pendentes ou canceladas podem ser excluídas!');
                return;
            }

            $vacation->delete();
            session()->flash('success', 'Solicitação de férias removida com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao remover férias: ' . $e->getMessage());
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
        $this->vacationId = null;
        $this->employee_id = '';
        $this->reference_year = date('Y');
        $this->vacation_type = 'normal';
        $this->can_split = true;
        $this->split_number = null;
        $this->total_splits = 1;
        $this->parent_vacation_id = null;
        $this->start_date = '';
        $this->end_date = '';
        $this->notes = '';
        $this->replacement_employee_id = '';
        $this->attachment = null;
        $this->is_collective = false;
        $this->collective_group = null;
        $this->rejection_reason = '';
        $this->availableDays = 0;
        $this->requestedDays = 0;
        $this->workingDays = 0;
        $this->totalAmount = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Vacation::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['employee', 'approvedBy', 'replacementEmployee']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('vacation_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('employee', function ($q2) {
                      $q2->where('first_name', 'like', '%' . $this->search . '%')
                         ->orWhere('last_name', 'like', '%' . $this->search . '%')
                         ->orWhere('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->yearFilter) {
            $query->byYear($this->yearFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }

        $vacations = $query->latest()->paginate(15);
        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.vacations.vacations', [
            'vacations' => $vacations,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Gestão de Férias']);
    }
}

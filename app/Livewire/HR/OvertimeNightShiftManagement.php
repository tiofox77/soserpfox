<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\HR\Overtime;
use App\Models\HR\Employee;
use App\Models\HR\HRSetting;
use App\Helpers\PayrollCalculatorHelper;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OvertimeNightShiftManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $monthFilter = '';
    public $yearFilter = '';
    public $statusFilter = '';
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
    public $night_days = 1;
    public $description = '';
    public $notes = '';

    // Details
    public $selectedOvertime;

    // Approval
    public $approvalOvertimeId;
    public $rejection_reason = '';

    // Calculated
    public $dailyRate = 0;
    public $nightPercentage = 25;
    public $calculatedAmount = 0;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'date' => 'required|date',
        'night_days' => 'required|integer|min:1|max:31',
        'description' => 'nullable|string|max:500',
        'notes' => 'nullable|string|max:500',
    ];

    public function mount()
    {
        $this->yearFilter = date('Y');
        $this->monthFilter = date('m');
        $this->nightPercentage = (int) HRSetting::get('night_shift_percentage', 25);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['employee_id', 'night_days', 'date'])) {
            $this->calculateNightShift();
        }
    }

    private function calculateNightShift()
    {
        if ($this->employee_id && $this->night_days > 0 && $this->date) {
            try {
                $employee = Employee::find($this->employee_id);
                if ($employee) {
                    $baseSalary = (float) ($employee->base_salary ?? $employee->salary ?? 0);
                    $date = Carbon::parse($this->date);
                    $workOnSaturday = (bool) HRSetting::get('work_on_saturday', false);
                    $workingDays = PayrollCalculatorHelper::countWeekdays(
                        $date->copy()->startOfMonth(),
                        $date->copy()->endOfMonth(),
                        $workOnSaturday
                    );
                    if ($workingDays <= 0) $workingDays = (int) HRSetting::get('monthly_working_days', 22);

                    $this->dailyRate = round($baseSalary / $workingDays, 2);
                    $pct = $this->nightPercentage / 100;
                    $this->calculatedAmount = round($this->dailyRate * $this->night_days * $pct, 2);
                }
            } catch (\Exception $e) {
                $this->dailyRate = 0;
                $this->calculatedAmount = 0;
            }
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->date = date('Y-m-d');
        $this->showModal = true;
    }

    public function edit($id)
    {
        $record = Overtime::findOrFail($id);
        if ($record->status !== 'pending') {
            $this->dispatch('notify', type: 'error', message: 'Apenas registos pendentes podem ser editados!');
            return;
        }

        $this->overtimeId = $id;
        $this->editMode = true;
        $this->employee_id = $record->employee_id;
        $this->date = $record->date->format('Y-m-d');
        $this->night_days = (int) ($record->direct_hours ?? 1);
        $this->description = $record->description;
        $this->notes = $record->notes;
        $this->calculateNightShift();
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $this->calculateNightShift();

            $employee = Employee::findOrFail($this->employee_id);
            $baseSalary = (float) ($employee->base_salary ?? $employee->salary ?? 0);
            $date = Carbon::parse($this->date);
            $workingDays = PayrollCalculatorHelper::countWeekdays(
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth()
            );
            if ($workingDays <= 0) $workingDays = (int) HRSetting::get('monthly_working_days', 22);

            $hourlyRate = round($baseSalary / ($workingDays * ((float) HRSetting::get('working_hours_per_day', 8))), 2);

            $data = [
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $this->employee_id,
                'date' => $this->date,
                'input_type' => 'daily',
                'period_type' => 'month',
                'is_night_shift' => true,
                'overtime_type' => 'night',
                'direct_hours' => $this->night_days,
                'total_hours' => $this->night_days,
                'hourly_rate' => $hourlyRate,
                'rate' => $this->dailyRate * ($this->nightPercentage / 100),
                'amount' => $this->calculatedAmount,
                'total_amount' => $this->calculatedAmount,
                'multiplier' => $this->nightPercentage / 100,
                'description' => $this->description,
                'notes' => $this->notes,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ];

            if ($this->editMode && $this->overtimeId) {
                $record = Overtime::findOrFail($this->overtimeId);
                $record->update($data);
                $this->dispatch('notify', type: 'success', message: 'Registo de turno noturno atualizado!');
            } else {
                // Número de turno noturno gerado por-tenant (robusto a eliminações/concorrência)
                $data['overtime_number'] = Overtime::generateTenantNumber('overtime_number', 'NS-', 6);

                Overtime::create($data);
                $this->dispatch('notify', type: 'success', message: 'Turno noturno registado com sucesso!');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function viewDetails($id)
    {
        $this->selectedOvertime = Overtime::with(['employee', 'approvedBy', 'rejectedBy', 'createdBy'])
            ->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function approve($id)
    {
        try {
            $record = Overtime::findOrFail($id);
            $record->approve(Auth::id());
            $this->dispatch('notify', type: 'success', message: 'Turno noturno aprovado!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    public function openRejectionModal($id)
    {
        $this->approvalOvertimeId = $id;
        $this->rejection_reason = '';
        $this->showRejectionModal = true;
    }

    public function reject()
    {
        $this->validate(['rejection_reason' => 'required|string|min:10|max:500']);

        try {
            $record = Overtime::findOrFail($this->approvalOvertimeId);
            $record->reject(Auth::id(), $this->rejection_reason);
            $this->dispatch('notify', type: 'success', message: 'Turno noturno rejeitado!');
            $this->showRejectionModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $record = Overtime::findOrFail($id);
            if (!in_array($record->status, ['pending', 'cancelled'])) {
                $this->dispatch('notify', type: 'error', message: 'Apenas registos pendentes podem ser eliminados!');
                return;
            }
            $record->delete();
            $this->dispatch('notify', type: 'success', message: 'Registo eliminado!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDetailsModal = false;
        $this->showRejectionModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->overtimeId = null;
        $this->editMode = false;
        $this->employee_id = '';
        $this->date = '';
        $this->night_days = 1;
        $this->description = '';
        $this->notes = '';
        $this->rejection_reason = '';
        $this->dailyRate = 0;
        $this->calculatedAmount = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();

        $query = Overtime::where('tenant_id', $tenantId)
            ->where('is_night_shift', true)
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

        if ($this->yearFilter) $query->whereYear('date', $this->yearFilter);
        if ($this->monthFilter) $query->whereMonth('date', $this->monthFilter);
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->employeeFilter) $query->where('employee_id', $this->employeeFilter);

        $records = $query->latest('date')->paginate(15);
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $stats = [
            'total' => Overtime::where('tenant_id', $tenantId)->where('is_night_shift', true)->count(),
            'pending' => Overtime::where('tenant_id', $tenantId)->where('is_night_shift', true)->where('status', 'pending')->count(),
            'approved' => Overtime::where('tenant_id', $tenantId)->where('is_night_shift', true)->where('status', 'approved')->count(),
            'total_amount' => Overtime::where('tenant_id', $tenantId)->where('is_night_shift', true)->whereIn('status', ['approved', 'paid'])->sum('amount'),
            'total_days' => Overtime::where('tenant_id', $tenantId)->where('is_night_shift', true)->whereIn('status', ['approved', 'paid'])->sum('direct_hours'),
        ];

        return view('livewire.hr.overtime-night-shift.overtime-night-shift', [
            'records' => $records,
            'employees' => $employees,
            'stats' => $stats,
        ])->layout('layouts.app', ['title' => 'Horas Extra — Turno Noturno']);
    }
}

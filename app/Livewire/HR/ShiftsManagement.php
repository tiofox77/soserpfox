<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\HR\Shift;
use App\Models\HR\Employee;

class ShiftsManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $perPage = 15;

    // Modal
    public $showModal = false;
    public $showViewModal = false;
    public $showAssignModal = false;
    public $editMode = false;
    public $shiftId;
    
    // Batch Assignment
    public $assigningShiftId;
    public $selectedEmployees = [];
    public $selectAll = false;

    // Form Fields
    public $name = '';
    public $code = '';
    public $description = '';
    public $start_time = '';
    public $end_time = '';
    public $hours_per_day = 8;
    public $work_days = [];
    public $color = '#3b82f6';
    public $is_night_shift = false;
    public $is_active = true;
    public $display_order = 0;

    // View
    public $viewingShift;

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:10',
        'description' => 'nullable|string|max:500',
        'start_time' => 'required',
        'end_time' => 'required',
        'hours_per_day' => 'required|numeric|min:1|max:24',
        'work_days' => 'nullable|array',
        'color' => 'required|string|max:7',
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $messages = [
        'name.required' => 'O nome do turno é obrigatório.',
        'start_time.required' => 'O horário de início é obrigatório.',
        'end_time.required' => 'O horário de término é obrigatório.',
        'hours_per_day.required' => 'As horas por dia são obrigatórias.',
        'hours_per_day.min' => 'Mínimo de 1 hora por dia.',
        'hours_per_day.max' => 'Máximo de 24 horas por dia.',
    ];

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
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $shift = Shift::findOrFail($id);
        
        $this->shiftId = $shift->id;
        $this->name = $shift->name;
        $this->code = $shift->code;
        $this->description = $shift->description;
        $this->start_time = $shift->start_time ? $shift->start_time->format('H:i') : '';
        $this->end_time = $shift->end_time ? $shift->end_time->format('H:i') : '';
        $this->hours_per_day = $shift->hours_per_day;
        $this->work_days = $shift->work_days ?? [];
        $this->color = $shift->color;
        $this->is_night_shift = $shift->is_night_shift;
        $this->is_active = $shift->is_active;
        $this->display_order = $shift->display_order;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingShift = Shift::with('employees')->findOrFail($id);
        $this->showViewModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'hours_per_day' => $this->hours_per_day,
            'work_days' => $this->work_days,
            'color' => $this->color,
            'is_night_shift' => $this->is_night_shift,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
        ];

        if ($this->editMode) {
            $shift = Shift::findOrFail($this->shiftId);
            $shift->update($data);
            $this->dispatch('notify', type: 'success', message: 'Turno atualizado com sucesso!');
        } else {
            Shift::create($data);
            $this->dispatch('notify', type: 'success', message: 'Turno criado com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        try {
            $shift = Shift::findOrFail($id);
            
            // Verificar se há funcionários vinculados
            if ($shift->employees()->count() > 0) {
                $this->dispatch('notify', type: 'error', message: 'Não é possível excluir. Há funcionários vinculados a este turno.');
                return;
            }

            $shift->delete();
            $this->dispatch('notify', type: 'success', message: 'Turno excluído com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao excluir turno.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingShift = null;
    }

    public function openAssignModal($shiftId)
    {
        $this->assigningShiftId = $shiftId;
        $this->selectedEmployees = [];
        $this->selectAll = false;
        $this->showAssignModal = true;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedEmployees = Employee::where('tenant_id', auth()->user()->activeTenantId())
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedEmployees = [];
        }
    }

    public function assignEmployeesToShift()
    {
        if (empty($this->selectedEmployees)) {
            $this->dispatch('notify', type: 'warning', message: 'Selecione pelo menos um funcionário.');
            return;
        }

        try {
            $updated = Employee::whereIn('id', $this->selectedEmployees)
                ->update(['shift_id' => $this->assigningShiftId]);

            $this->dispatch('notify', type: 'success', message: "{$updated} funcionário(s) atribuído(s) ao turno com sucesso!");
            $this->closeAssignModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao atribuir funcionários ao turno.');
        }
    }

    public function closeAssignModal()
    {
        $this->showAssignModal = false;
        $this->assigningShiftId = null;
        $this->selectedEmployees = [];
        $this->selectAll = false;
    }

    private function resetForm()
    {
        $this->shiftId = null;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->start_time = '';
        $this->end_time = '';
        $this->hours_per_day = 8;
        $this->work_days = [];
        $this->color = '#3b82f6';
        $this->is_night_shift = false;
        $this->is_active = true;
        $this->display_order = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Shift::where('tenant_id', auth()->user()->activeTenantId())->withCount('employees');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $shifts = $query->orderBy('display_order')->orderBy('name')->paginate($this->perPage);

        // Stats
        $stats = [
            'total' => Shift::where('tenant_id', auth()->user()->activeTenantId())->count(),
            'active' => Shift::where('tenant_id', auth()->user()->activeTenantId())->where('is_active', true)->count(),
            'night_shifts' => Shift::where('tenant_id', auth()->user()->activeTenantId())->where('is_night_shift', true)->count(),
        ];

        // Get employees for assignment modal
        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->with(['department', 'position'])
            ->orderBy('first_name')
            ->get();

        return view('livewire.hr.shifts.shifts', [
            'shifts' => $shifts,
            'stats' => $stats,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Gestão de Turnos']);
    }
}

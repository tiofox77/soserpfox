<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\Department;
use App\Models\HR\Position;
use App\Models\HR\Employee;

class DepartmentManagement extends Component
{
    public $activeTab = 'departments';
    
    // Departments
    public $showDeptModal = false;
    public $editDeptMode = false;
    public $deptId;
    public $dept_name = '';
    public $dept_code = '';
    public $dept_description = '';
    public $dept_manager_id = '';
    public $dept_is_active = true;
    
    // Positions
    public $showPosModal = false;
    public $editPosMode = false;
    public $posId;
    public $pos_title = '';
    public $pos_code = '';
    public $pos_department_id = '';
    public $pos_description = '';
    public $pos_is_active = true;

    protected $rules = [
        'dept_name' => 'required|string|max:255',
        'dept_code' => 'nullable|string|max:50',
        'pos_title' => 'required|string|max:255',
        'pos_code' => 'nullable|string|max:50',
        'pos_department_id' => 'nullable|exists:hr_departments,id',
    ];

    // DEPARTMENTS
    public function createDept()
    {
        $this->resetDeptForm();
        $this->editDeptMode = false;
        $this->showDeptModal = true;
    }

    public function editDept($id)
    {
        $dept = Department::findOrFail($id);
        
        $this->deptId = $dept->id;
        $this->dept_name = $dept->name;
        $this->dept_code = $dept->code;
        $this->dept_description = $dept->description;
        $this->dept_manager_id = $dept->manager_id;
        $this->dept_is_active = $dept->is_active;
        
        $this->editDeptMode = true;
        $this->showDeptModal = true;
    }

    public function saveDept()
    {
        $this->validate([
            'dept_name' => 'required|string|max:255',
            'dept_code' => 'nullable|string|max:50',
        ]);

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'name' => $this->dept_name,
            'code' => $this->dept_code ?: null,
            'description' => $this->dept_description ?: null,
            'manager_id' => $this->dept_manager_id ?: null,
            'is_active' => $this->dept_is_active,
        ];

        if ($this->editDeptMode) {
            Department::findOrFail($this->deptId)->update($data);
            session()->flash('success', 'Departamento atualizado!');
        } else {
            Department::create($data);
            session()->flash('success', 'Departamento criado!');
        }

        $this->showDeptModal = false;
        $this->resetDeptForm();
    }

    public function deleteDept($id)
    {
        Department::findOrFail($id)->delete();
        session()->flash('success', 'Departamento removido!');
    }

    private function resetDeptForm()
    {
        $this->deptId = null;
        $this->dept_name = '';
        $this->dept_code = '';
        $this->dept_description = '';
        $this->dept_manager_id = '';
        $this->dept_is_active = true;
    }

    // POSITIONS
    public function createPos()
    {
        $this->resetPosForm();
        $this->editPosMode = false;
        $this->showPosModal = true;
    }

    public function editPos($id)
    {
        $pos = Position::findOrFail($id);
        
        $this->posId = $pos->id;
        $this->pos_title = $pos->title;
        $this->pos_code = $pos->code;
        $this->pos_department_id = $pos->department_id;
        $this->pos_description = $pos->description;
        $this->pos_is_active = $pos->is_active;
        
        $this->editPosMode = true;
        $this->showPosModal = true;
    }

    public function savePos()
    {
        $this->validate([
            'pos_title' => 'required|string|max:255',
            'pos_code' => 'nullable|string|max:50',
            'pos_department_id' => 'nullable|exists:hr_departments,id',
        ]);

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'title' => $this->pos_title,
            'code' => $this->pos_code ?: null,
            'department_id' => $this->pos_department_id ?: null,
            'description' => $this->pos_description ?: null,
            'is_active' => $this->pos_is_active,
        ];

        if ($this->editPosMode) {
            Position::findOrFail($this->posId)->update($data);
            session()->flash('success', 'Cargo atualizado!');
        } else {
            Position::create($data);
            session()->flash('success', 'Cargo criado!');
        }

        $this->showPosModal = false;
        $this->resetPosForm();
    }

    public function deletePos($id)
    {
        Position::findOrFail($id)->delete();
        session()->flash('success', 'Cargo removido!');
    }

    private function resetPosForm()
    {
        $this->posId = null;
        $this->pos_title = '';
        $this->pos_code = '';
        $this->pos_department_id = '';
        $this->pos_description = '';
        $this->pos_is_active = true;
    }

    public function render()
    {
        $departments = Department::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['manager'])
            ->withCount('employees')
            ->latest()
            ->paginate(15);

        $employees = Employee::where('tenant_id', auth()->user()->activeTenantId())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $positions = Position::where('tenant_id', auth()->user()->activeTenantId())
            ->with(['department'])
            ->withCount('employees')
            ->latest()
            ->paginate(15);

        return view('livewire.hr.departments.departments', [
            'departments' => $departments,
            'positions' => $positions,
            'employees' => $employees,
        ])->layout('layouts.app', ['title' => 'Departamentos']);
    }
}

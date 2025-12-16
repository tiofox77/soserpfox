<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Hotel\Staff;
use App\Models\HR\Employee;
use Illuminate\Support\Facades\Storage;

class StaffManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $departmentFilter = '';
    public $positionFilter = '';
    public $showModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $showImportModal = false;
    public $editingId = null;
    public $viewingStaff = null;
    public $deletingId = null;
    public $viewMode = 'grid';
    public $selectedEmployees = [];
    public $selectAll = false;

    // Form fields
    public $name = '';
    public $email = '';
    public $phone = '';
    public $document = '';
    public $photo;
    public $existing_photo = null;
    public $position = 'receptionist';
    public $department = 'front_desk';
    public $address = '';
    public $birth_date = '';
    public $hire_date = '';
    public $working_days = [1, 2, 3, 4, 5, 6];
    public $work_start = '08:00';
    public $work_end = '17:00';
    public $hourly_rate = 0;
    public $monthly_salary = 0;
    public $notes = '';
    public $skills = '';
    public $is_active = true;

    protected $rules = [
        'name' => 'required|min:2',
        'position' => 'required',
        'department' => 'required',
        'email' => 'nullable|email',
        'phone' => 'nullable|string',
        'photo' => 'nullable|image|max:2048',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $staff = Staff::forTenant()->findOrFail($id);
            $this->editingId = $id;
            $this->name = $staff->name;
            $this->email = $staff->email;
            $this->phone = $staff->phone;
            $this->document = $staff->document;
            $this->existing_photo = $staff->photo;
            $this->position = $staff->position;
            $this->department = $staff->department;
            $this->address = $staff->address;
            $this->birth_date = $staff->birth_date?->format('Y-m-d');
            $this->hire_date = $staff->hire_date?->format('Y-m-d');
            $this->working_days = $staff->working_days ?? [1, 2, 3, 4, 5, 6];
            $this->work_start = $staff->work_start ? $staff->work_start->format('H:i') : '08:00';
            $this->work_end = $staff->work_end ? $staff->work_end->format('H:i') : '17:00';
            $this->hourly_rate = $staff->hourly_rate;
            $this->monthly_salary = $staff->monthly_salary;
            $this->notes = $staff->notes;
            $this->skills = $staff->skills;
            $this->is_active = $staff->is_active;
        }
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset([
            'editingId', 'name', 'email', 'phone', 'document', 'photo', 'existing_photo',
            'position', 'department', 'address', 'birth_date', 'hire_date',
            'working_days', 'work_start', 'work_end', 'hourly_rate', 'monthly_salary',
            'notes', 'skills', 'is_active'
        ]);
        $this->position = 'receptionist';
        $this->department = 'front_desk';
        $this->working_days = [1, 2, 3, 4, 5, 6];
        $this->work_start = '08:00';
        $this->work_end = '17:00';
        $this->is_active = true;
    }

    public function save()
    {
        $this->validate();

        $tenantId = activeTenantId();
        $basePath = "tenants/{$tenantId}/hotel/staff";

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'position' => $this->position,
            'department' => $this->department,
            'address' => $this->address,
            'birth_date' => $this->birth_date ?: null,
            'hire_date' => $this->hire_date ?: null,
            'working_days' => $this->working_days,
            'work_start' => $this->work_start,
            'work_end' => $this->work_end,
            'hourly_rate' => $this->hourly_rate,
            'monthly_salary' => $this->monthly_salary,
            'notes' => $this->notes,
            'skills' => $this->skills,
            'is_active' => $this->is_active,
        ];

        // Processar foto
        if ($this->photo) {
            $data['photo'] = $this->photo->store($basePath, 'public');
        } elseif ($this->existing_photo) {
            $data['photo'] = $this->existing_photo;
        }

        if ($this->editingId) {
            $staff = Staff::forTenant()->findOrFail($this->editingId);
            if ($this->photo && $staff->photo) {
                Storage::disk('public')->delete($staff->photo);
            }
            $staff->update($data);
            $this->dispatch('notify', message: 'Funcionario atualizado com sucesso!', type: 'success');
        } else {
            Staff::create($data);
            $this->dispatch('notify', message: 'Funcionario criado com sucesso!', type: 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function view($id)
    {
        $this->viewingStaff = Staff::forTenant()->findOrFail($id);
        $this->showViewModal = true;
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        $staff = Staff::forTenant()->findOrFail($this->deletingId);
        
        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo);
        }
        
        $staff->delete();
        $this->dispatch('notify', message: 'Funcionario removido com sucesso!', type: 'success');
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function toggleStatus($id)
    {
        $staff = Staff::forTenant()->findOrFail($id);
        $staff->update(['is_active' => !$staff->is_active]);
        $this->dispatch('notify', message: 'Status atualizado!', type: 'success');
    }

    // Import from HR
    public function openImportModal()
    {
        $this->selectedEmployees = [];
        $this->selectAll = false;
        $this->showImportModal = true;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedEmployees = $this->hrEmployees->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedEmployees = [];
        }
    }

    public function updatedSelectedEmployees()
    {
        $this->selectAll = count($this->selectedEmployees) === $this->hrEmployees->count();
    }

    public function getHrEmployeesProperty()
    {
        // Buscar funcionários do RH que ainda não foram importados
        $importedIds = Staff::forTenant()->whereNotNull('hr_employee_id')->pluck('hr_employee_id');
        
        return Employee::where('tenant_id', activeTenantId())
            ->where('status', 'active')
            ->whereNotIn('id', $importedIds)
            ->orderBy('first_name')
            ->get();
    }

    public function importFromHR()
    {
        if (empty($this->selectedEmployees)) {
            $this->dispatch('notify', message: 'Selecione pelo menos um funcionario!', type: 'error');
            return;
        }

        $imported = 0;
        foreach ($this->selectedEmployees as $employeeId) {
            $employee = Employee::find($employeeId);
            if ($employee) {
                Staff::create([
                    'hr_employee_id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'phone' => $employee->mobile ?? $employee->phone,
                    'document' => $employee->bi_number ?? $employee->nif,
                    'photo' => $employee->photo,
                    'position' => $this->mapPosition($employee->position_id),
                    'department' => $this->mapDepartment($employee->department_id),
                    'address' => $employee->address,
                    'birth_date' => $employee->birth_date,
                    'hire_date' => $employee->hire_date,
                    'monthly_salary' => $employee->salary ?? 0,
                    'is_active' => true,
                ]);
                $imported++;
            }
        }

        $this->dispatch('notify', message: "{$imported} funcionario(s) importado(s) com sucesso!", type: 'success');
        $this->showImportModal = false;
        $this->selectedEmployees = [];
    }

    private function mapPosition($positionId)
    {
        // Mapeamento básico - pode ser customizado
        return 'other';
    }

    private function mapDepartment($departmentId)
    {
        // Mapeamento básico - pode ser customizado
        return 'front_desk';
    }

    public function render()
    {
        $staff = Staff::forTenant()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->departmentFilter, fn($q) => $q->where('department', $this->departmentFilter))
            ->when($this->positionFilter, fn($q) => $q->where('position', $this->positionFilter))
            ->orderBy('name')
            ->paginate(12);

        $stats = [
            'total' => Staff::forTenant()->count(),
            'active' => Staff::forTenant()->active()->count(),
            'housekeeping' => Staff::forTenant()->byDepartment('housekeeping')->count(),
            'front_desk' => Staff::forTenant()->byDepartment('front_desk')->count(),
        ];

        return view('livewire.hotel.staff.staff', [
            'staffList' => $staff,
            'stats' => $stats,
            'positions' => Staff::POSITIONS,
            'departments' => Staff::DEPARTMENTS,
        ])->layout('layouts.app');
    }
}

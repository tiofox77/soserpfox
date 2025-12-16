<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\HR\Employee;
use App\Models\HR\Department;
use App\Models\HR\Position;
use App\Models\Events\Technician;
use App\Models\Hotel\Staff as HotelStaff;
use App\Models\Treasury\Bank;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EmployeeManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $departmentFilter = '';
    public $statusFilter = '';
    
    // Modal
    public $showModal = false;
    public $showViewModal = false;
    public $showImportModal = false;
    public $showImportHotelModal = false;
    public $editMode = false;
    public $employeeId;
    public $viewingEmployee = null;
    public $selectedTechnicians = [];
    public $selectedHotelStaff = [];
    public $importSource = 'technicians';
    
    // Form Fields
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $mobile = '';
    public $birth_date = '';
    public $gender = '';
    public $nif = '';
    public $bi_number = '';
    public $bi_expiry_date = '';
    public $passport_number = '';
    public $passport_expiry_date = '';
    public $work_permit_number = '';
    public $work_permit_expiry_date = '';
    public $residence_permit_number = '';
    public $residence_permit_expiry_date = '';
    public $driver_license_number = '';
    public $driver_license_expiry_date = '';
    public $driver_license_category = '';
    public $health_insurance_number = '';
    public $health_insurance_expiry_date = '';
    public $health_insurance_provider = '';
    public $social_security_number = '';
    public $contract_expiry_date = '';
    public $probation_end_date = '';
    public $address = '';
    public $city = '';
    public $province = '';
    public $department_id = '';
    public $position_id = '';
    public $hire_date = '';
    public $employment_type = 'Contrato';
    public $status = 'active';
    public $salary = '';
    public $bonus = '';
    public $transport_allowance = '';
    public $meal_allowance = '';
    public $bank_name = '';
    public $bank_account = '';
    public $iban = '';
    public $notes = '';
    
    // Document Uploads
    public $bi_document;
    public $passport_document;
    public $work_permit_document;
    public $residence_permit_document;
    public $driver_license_document;
    public $health_insurance_document;
    public $contract_document;
    public $probation_document;
    public $criminal_record_document;
    
    // Criminal Record
    public $criminal_record_number = '';
    public $criminal_record_issue_date = '';

    protected $rules = [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:20',
        'mobile' => 'nullable|string|max:20',
        'birth_date' => 'nullable|date',
        'gender' => 'nullable|in:M,F,Outro',
        'nif' => 'nullable|string|max:50',
        'bi_number' => 'nullable|string|max:50',
        'bi_expiry_date' => 'nullable|date',
        'social_security_number' => 'nullable|string|max:50',
        'department_id' => 'nullable|exists:hr_departments,id',
        'position_id' => 'nullable|exists:hr_positions,id',
        'hire_date' => 'nullable|date',
        'employment_type' => 'required|in:Contrato,Freelancer,Estágio,Temporário',
        'status' => 'required|in:active,suspended,terminated,on_leave',
        'salary' => 'nullable|numeric|min:0',
        'bonus' => 'nullable|numeric|min:0',
        'transport_allowance' => 'nullable|numeric|min:0',
        'meal_allowance' => 'nullable|numeric|min:0',
        'bi_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'passport_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'work_permit_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'residence_permit_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'driver_license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'health_insurance_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'contract_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'probation_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'criminal_record_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
    ];

    protected $messages = [
        'first_name.required' => 'O primeiro nome é obrigatório. Por favor, acesse a aba "Pessoais" e preencha este campo.',
        'last_name.required' => 'O último nome é obrigatório. Por favor, acesse a aba "Pessoais" e preencha este campo.',
        'email.email' => 'O email informado não é válido. Verifique na aba "Pessoais".',
        'employment_type.required' => 'O tipo de contrato é obrigatório. Por favor, acesse a aba "Profissional" e selecione uma opção.',
        'status.required' => 'O status do funcionário é obrigatório. Por favor, acesse a aba "Profissional" e selecione uma opção.',
        'department_id.exists' => 'Departamento inválido. Por favor, selecione um departamento válido na aba "Profissional".',
        'position_id.exists' => 'Cargo inválido. Por favor, selecione um cargo válido na aba "Profissional".',
        'salary.numeric' => 'O salário deve ser um valor numérico. Verifique na aba "Remuneração".',
        'salary.min' => 'O salário não pode ser negativo. Verifique na aba "Remuneração".',
        'bonus.numeric' => 'O bônus deve ser um valor numérico. Verifique na aba "Remuneração".',
        'transport_allowance.numeric' => 'O subsídio de transporte deve ser um valor numérico. Verifique na aba "Remuneração".',
        'meal_allowance.numeric' => 'O subsídio de alimentação deve ser um valor numérico. Verifique na aba "Remuneração".',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        logger('🆕 Método create() chamado - Abrindo modal de funcionário');
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
        logger('✅ showModal definido como true', ['showModal' => $this->showModal]);
        $this->dispatch('modal-opened');
    }

    public function view($id)
    {
        $this->viewingEmployee = Employee::with(['department', 'position'])->findOrFail($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingEmployee = null;
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        
        $this->employeeId = $employee->id;
        $this->first_name = $employee->first_name;
        $this->last_name = $employee->last_name;
        $this->email = $employee->email;
        $this->phone = $employee->phone;
        $this->mobile = $employee->mobile;
        $this->birth_date = $employee->birth_date ? $employee->birth_date->format('Y-m-d') : '';
        $this->gender = $employee->gender;
        $this->nif = $employee->nif;
        $this->bi_number = $employee->bi_number;
        $this->bi_expiry_date = $employee->bi_expiry_date ? $employee->bi_expiry_date->format('Y-m-d') : '';
        $this->passport_number = $employee->passport_number;
        $this->passport_expiry_date = $employee->passport_expiry_date ? $employee->passport_expiry_date->format('Y-m-d') : '';
        $this->work_permit_number = $employee->work_permit_number;
        $this->work_permit_expiry_date = $employee->work_permit_expiry_date ? $employee->work_permit_expiry_date->format('Y-m-d') : '';
        $this->residence_permit_number = $employee->residence_permit_number;
        $this->residence_permit_expiry_date = $employee->residence_permit_expiry_date ? $employee->residence_permit_expiry_date->format('Y-m-d') : '';
        $this->driver_license_number = $employee->driver_license_number;
        $this->driver_license_expiry_date = $employee->driver_license_expiry_date ? $employee->driver_license_expiry_date->format('Y-m-d') : '';
        $this->driver_license_category = $employee->driver_license_category;
        $this->health_insurance_number = $employee->health_insurance_number;
        $this->health_insurance_expiry_date = $employee->health_insurance_expiry_date ? $employee->health_insurance_expiry_date->format('Y-m-d') : '';
        $this->health_insurance_provider = $employee->health_insurance_provider;
        $this->social_security_number = $employee->social_security_number;
        $this->contract_expiry_date = $employee->contract_expiry_date ? $employee->contract_expiry_date->format('Y-m-d') : '';
        $this->probation_end_date = $employee->probation_end_date ? $employee->probation_end_date->format('Y-m-d') : '';
        $this->address = $employee->address;
        $this->city = $employee->city;
        $this->province = $employee->province;
        $this->department_id = $employee->department_id;
        $this->position_id = $employee->position_id;
        $this->hire_date = $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '';
        $this->employment_type = $employee->employment_type;
        $this->status = $employee->status;
        $this->salary = $employee->salary;
        $this->bonus = $employee->bonus;
        $this->transport_allowance = $employee->transport_allowance;
        $this->meal_allowance = $employee->meal_allowance;
        $this->bank_name = $employee->bank_name;
        $this->bank_account = $employee->bank_account;
        $this->iban = $employee->iban;
        $this->notes = $employee->notes;
        $this->criminal_record_number = $employee->criminal_record_number;
        $this->criminal_record_issue_date = $employee->criminal_record_issue_date ? $employee->criminal_record_issue_date->format('Y-m-d') : '';
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Pegar primeiro erro
            $errors = $e->validator->errors();
            $firstError = $errors->first();
            $firstField = array_key_first($errors->messages());
            
            // Mapear campo para tab
            $tabMap = [
                'first_name' => 'personal',
                'last_name' => 'personal',
                'email' => 'personal',
                'phone' => 'personal',
                'mobile' => 'personal',
                'birth_date' => 'personal',
                'gender' => 'personal',
                'nif' => 'personal',
                'bi_number' => 'documents',
                'bi_expiry_date' => 'documents',
                'passport_number' => 'documents',
                'passport_expiry_date' => 'documents',
                'work_permit_number' => 'documents',
                'work_permit_expiry_date' => 'documents',
                'residence_permit_number' => 'documents',
                'residence_permit_expiry_date' => 'documents',
                'driver_license_number' => 'documents',
                'driver_license_expiry_date' => 'documents',
                'driver_license_category' => 'documents',
                'health_insurance_number' => 'documents',
                'health_insurance_expiry_date' => 'documents',
                'health_insurance_provider' => 'documents',
                'social_security_number' => 'documents',
                'contract_expiry_date' => 'documents',
                'probation_end_date' => 'documents',
                'address' => 'contact',
                'city' => 'contact',
                'province' => 'contact',
                'department_id' => 'professional',
                'position_id' => 'professional',
                'hire_date' => 'professional',
                'employment_type' => 'professional',
                'status' => 'professional',
                'salary' => 'salary',
                'bonus' => 'salary',
                'transport_allowance' => 'salary',
                'meal_allowance' => 'salary',
                'bank_name' => 'banking',
                'bank_account' => 'banking',
                'iban' => 'banking',
            ];
            
            $targetTab = $tabMap[$firstField] ?? 'personal';
            
            // Notificar usuário e mudar para tab com erro
            $this->dispatch('notify', type: 'error', message: $firstError);
            $this->dispatch('switchTab', tab: $targetTab);
            
            throw $e;
        }

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'mobile' => $this->mobile ?: null,
            'birth_date' => $this->birth_date ?: null,
            'gender' => $this->gender ?: null,
            'nif' => $this->nif ?: null,
            'bi_number' => $this->bi_number ?: null,
            'bi_expiry_date' => $this->bi_expiry_date ?: null,
            'passport_number' => $this->passport_number ?: null,
            'passport_expiry_date' => $this->passport_expiry_date ?: null,
            'work_permit_number' => $this->work_permit_number ?: null,
            'work_permit_expiry_date' => $this->work_permit_expiry_date ?: null,
            'residence_permit_number' => $this->residence_permit_number ?: null,
            'residence_permit_expiry_date' => $this->residence_permit_expiry_date ?: null,
            'driver_license_number' => $this->driver_license_number ?: null,
            'driver_license_expiry_date' => $this->driver_license_expiry_date ?: null,
            'driver_license_category' => $this->driver_license_category ?: null,
            'health_insurance_number' => $this->health_insurance_number ?: null,
            'health_insurance_expiry_date' => $this->health_insurance_expiry_date ?: null,
            'health_insurance_provider' => $this->health_insurance_provider ?: null,
            'social_security_number' => $this->social_security_number ?: null,
            'contract_expiry_date' => $this->contract_expiry_date ?: null,
            'probation_end_date' => $this->probation_end_date ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'province' => $this->province ?: null,
            'department_id' => $this->department_id ?: null,
            'position_id' => $this->position_id ?: null,
            'hire_date' => $this->hire_date ?: null,
            'employment_type' => $this->employment_type ?: null,
            'status' => $this->status ?: 'active',
            'salary' => $this->salary ?: null,
            'bonus' => $this->bonus ?: null,
            'transport_allowance' => $this->transport_allowance ?: null,
            'meal_allowance' => $this->meal_allowance ?: null,
            'bank_name' => $this->bank_name ?: null,
            'bank_account' => $this->bank_account ?: null,
            'iban' => $this->iban ?: null,
            'notes' => $this->notes ?: null,
            'criminal_record_number' => $this->criminal_record_number ?: null,
            'criminal_record_issue_date' => $this->criminal_record_issue_date ?: null,
        ];

        if ($this->editMode) {
            $employee = Employee::findOrFail($this->employeeId);
            $employee->update($data);
            session()->flash('success', 'Funcionário atualizado com sucesso!');
        } else {
            // Gerar número de funcionário
            $data['employee_number'] = 'EMP-' . str_pad(Employee::count() + 1, 5, '0', STR_PAD_LEFT);
            $employee = Employee::create($data);
            session()->flash('success', 'Funcionário criado com sucesso!');
        }
        
        // Salvar documentos
        $this->saveEmployeeDocuments($employee);

        $this->closeModal();
    }
    
    private function saveEmployeeDocuments($employee)
    {
        $tenantId = auth()->user()->activeTenantId();
        $employeeFolder = "tenants/{$tenantId}/employees/{$employee->id}/documentos";
        
        $documents = [
            'bi_document' => 'bi',
            'passport_document' => 'passport',
            'work_permit_document' => 'work_permit',
            'residence_permit_document' => 'residence_permit',
            'driver_license_document' => 'driver_license',
            'health_insurance_document' => 'health_insurance',
            'contract_document' => 'contract',
            'probation_document' => 'probation',
            'criminal_record_document' => 'criminal_record',
        ];
        
        foreach ($documents as $property => $filename) {
            if ($this->$property) {
                $pathColumn = $property . '_path';
                
                // Deletar arquivo antigo se existir
                if ($employee->$pathColumn) {
                    Storage::disk('public')->delete($employee->$pathColumn);
                }
                
                // Obter extensão do arquivo
                $extension = $this->$property->extension();
                
                // Salvar novo arquivo com nome fixo
                $path = $this->$property->storeAs(
                    $employeeFolder,
                    "{$filename}.{$extension}",
                    'public'
                );
                
                // Atualizar no banco
                $employee->update([
                    $pathColumn => $path
                ]);
                
                Log::info("Documento salvo", [
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'document_type' => $filename,
                    'path' => $path,
                ]);
            }
        }
    }

    public function delete($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();
        
        session()->flash('success', 'Funcionário removido com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->employeeId = null;
        $this->first_name = '';
        $this->last_name = '';
        $this->email = '';
        $this->phone = '';
        $this->mobile = '';
        $this->birth_date = '';
        $this->gender = '';
        $this->nif = '';
        $this->bi_number = '';
        $this->bi_expiry_date = '';
        $this->passport_number = '';
        $this->passport_expiry_date = '';
        $this->work_permit_number = '';
        $this->work_permit_expiry_date = '';
        $this->residence_permit_number = '';
        $this->residence_permit_expiry_date = '';
        $this->driver_license_number = '';
        $this->driver_license_expiry_date = '';
        $this->driver_license_category = '';
        $this->health_insurance_number = '';
        $this->health_insurance_expiry_date = '';
        $this->health_insurance_provider = '';
        $this->social_security_number = '';
        $this->contract_expiry_date = '';
        $this->probation_end_date = '';
        $this->address = '';
        $this->city = '';
        $this->province = '';
        $this->department_id = '';
        $this->position_id = '';
        $this->hire_date = '';
        $this->employment_type = 'Contrato';
        $this->status = 'active';
        $this->salary = '';
        $this->bonus = '';
        $this->transport_allowance = '';
        $this->meal_allowance = '';
        $this->bank_name = '';
        $this->bank_account = '';
        $this->iban = '';
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function openImportModal()
    {
        $this->selectedTechnicians = [];
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->selectedTechnicians = [];
    }

    public function importSelected()
    {
        if (empty($this->selectedTechnicians)) {
            session()->flash('error', 'Selecione pelo menos um técnico');
            return;
        }

        $imported = 0;
        $skipped = 0;
        $tenantId = auth()->user()->activeTenantId();

        foreach ($this->selectedTechnicians as $technicianId) {
            $technician = Technician::find($technicianId);
            
            if (!$technician) continue;

            // Verifica se já existe funcionário com mesmo email ou telefone
            $exists = Employee::where('tenant_id', $tenantId)
                ->where(function($q) use ($technician) {
                    if ($technician->email) {
                        $q->where('email', $technician->email);
                    }
                    if ($technician->phone) {
                        $q->orWhere('phone', $technician->phone);
                    }
                })
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Separar nome em first_name e last_name
            $nameParts = explode(' ', $technician->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Importar técnico como funcionário
            Employee::create([
                'tenant_id' => $tenantId,
                'user_id' => $technician->user_id,
                'employee_number' => 'EMP-' . strtoupper(uniqid()),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $technician->email,
                'phone' => $technician->phone,
                'document_number' => $technician->document,
                'position' => $technician->specialties[0] ?? 'Técnico',
                'hire_date' => now(),
                'employment_type' => 'Contrato',
                'status' => $technician->is_active ? 'active' : 'inactive',
            ]);

            $imported++;
        }

        $message = "{$imported} técnico(s) importado(s)";
        if ($skipped > 0) {
            $message .= " ({$skipped} já existente(s))";
        }

        session()->flash('success', $message);
        $this->closeImportModal();
    }

    // Import from Hotel Staff
    public function openImportHotelModal()
    {
        $this->selectedHotelStaff = [];
        $this->showImportHotelModal = true;
    }

    public function closeImportHotelModal()
    {
        $this->showImportHotelModal = false;
        $this->selectedHotelStaff = [];
    }

    public function getHotelStaffProperty()
    {
        // Buscar funcionários do Hotel que ainda não foram importados
        $importedIds = Employee::where('tenant_id', activeTenantId())
            ->whereNotNull('hotel_staff_id')
            ->pluck('hotel_staff_id');
        
        return HotelStaff::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->whereNotIn('id', $importedIds)
            ->orderBy('name')
            ->get();
    }

    public function importFromHotel()
    {
        if (empty($this->selectedHotelStaff)) {
            session()->flash('error', 'Selecione pelo menos um funcionário');
            return;
        }

        $imported = 0;
        $skipped = 0;
        $tenantId = auth()->user()->activeTenantId();

        foreach ($this->selectedHotelStaff as $staffId) {
            $staff = HotelStaff::find($staffId);
            
            if (!$staff) continue;

            // Verifica se já existe funcionário com mesmo email
            $exists = Employee::where('tenant_id', $tenantId)
                ->where(function($q) use ($staff) {
                    if ($staff->email) {
                        $q->where('email', $staff->email);
                    }
                    if ($staff->phone) {
                        $q->orWhere('phone', $staff->phone);
                    }
                })
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Separar nome em first_name e last_name
            $nameParts = explode(' ', $staff->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Importar staff do hotel como funcionário
            Employee::create([
                'tenant_id' => $tenantId,
                'user_id' => $staff->user_id,
                'hotel_staff_id' => $staff->id,
                'employee_number' => 'EMP-' . strtoupper(uniqid()),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'bi_number' => $staff->document,
                'address' => $staff->address,
                'birth_date' => $staff->birth_date,
                'hire_date' => $staff->hire_date ?? now(),
                'salary' => $staff->monthly_salary,
                'employment_type' => 'Contrato',
                'status' => 'active',
                'photo' => $staff->photo,
            ]);

            $imported++;
        }

        $message = "{$imported} funcionário(s) do hotel importado(s)";
        if ($skipped > 0) {
            $message .= " ({$skipped} já existente(s))";
        }

        session()->flash('success', $message);
        $this->closeImportHotelModal();
    }

    public function getAvailableTechnicians()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        // Buscar técnicos que ainda não são funcionários
        $existingEmails = Employee::where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $existingPhones = Employee::where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->pluck('phone')
            ->toArray();

        return Technician::where('tenant_id', $tenantId)
            ->where(function($q) use ($existingEmails, $existingPhones) {
                $q->whereNotIn('email', $existingEmails)
                  ->whereNotIn('phone', $existingPhones);
            })
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $query = Employee::where('tenant_id', $tenantId)
            ->with(['department', 'position']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('employee_number', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $employees = $query->latest()->paginate(15);
        $departments = Department::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $positions = Position::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('livewire.hr.employees.employees', [
            'employees' => $employees,
            'departments' => $departments,
            'positions' => $positions,
            'banks' => $banks,
        ])->layout('layouts.app', ['title' => 'Gestão de Funcionários']);
    }
}

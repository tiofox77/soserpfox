<?php

namespace App\Livewire\Workshop;

use App\Models\Workshop\Mechanic;
use App\Models\HR\Employee;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Mecânicos')]
class MechanicManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showViewModal = false;
    public $showImportModal = false;
    public $editingId = null;
    public $viewingMechanic = null;
    public $selectedEmployees = [];
    
    // Form
    public $name = '';
    public $email = '';
    public $phone = '';
    public $mobile = '';
    public $document = '';
    public $specialties = [];
    public $level = 'pleno';
    public $hourly_rate = 0;
    public $daily_rate = 0;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email',
        'specialties' => 'required|array|min:1',
        'level' => 'required|in:junior,pleno,senior,master',
        'hourly_rate' => 'nullable|numeric|min:0',
        'daily_rate' => 'nullable|numeric|min:0',
    ];

    protected $messages = [
        'name.required' => 'O nome é obrigatório',
        'phone.required' => 'O telefone é obrigatório',
        'specialties.required' => 'Selecione pelo menos uma especialidade',
    ];

    public function render()
    {
        $mechanics = Mechanic::where('tenant_id', activeTenantId())
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.workshop.mechanics', compact('mechanics'));
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $mechanic = Mechanic::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $mechanic->name;
        $this->email = $mechanic->email;
        $this->phone = $mechanic->phone;
        $this->mobile = $mechanic->mobile;
        $this->document = $mechanic->document;
        $this->specialties = $mechanic->specialties ?? [];
        $this->level = $mechanic->level;
        $this->hourly_rate = $mechanic->hourly_rate;
        $this->daily_rate = $mechanic->daily_rate;
        $this->is_active = $mechanic->is_active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'document' => $this->document,
            'specialties' => $this->specialties,
            'level' => $this->level,
            'hourly_rate' => $this->hourly_rate,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Mechanic::find($this->editingId)->update($data);
            session()->flash('success', 'Mecânico atualizado com sucesso!');
        } else {
            Mechanic::create($data);
            session()->flash('success', 'Mecânico criado com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        Mechanic::where('tenant_id', activeTenantId())->findOrFail($id)->delete();
        session()->flash('success', 'Mecânico excluído com sucesso!');
    }

    public function openImportModal()
    {
        $this->selectedEmployees = [];
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->selectedEmployees = [];
    }

    public function importSelected()
    {
        if (empty($this->selectedEmployees)) {
            session()->flash('error', 'Selecione pelo menos um funcionário');
            return;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($this->selectedEmployees as $employeeId) {
            $employee = Employee::find($employeeId);
            
            if (!$employee) continue;

            // Verifica se já existe mecânico com mesmo email ou telefone
            $exists = Mechanic::where('tenant_id', activeTenantId())
                ->where(function($q) use ($employee) {
                    if ($employee->email) {
                        $q->where('email', $employee->email);
                    }
                    if ($employee->phone) {
                        $q->orWhere('phone', $employee->phone);
                    }
                })
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Importar funcionário como mecânico
            Mechanic::create([
                'tenant_id' => activeTenantId(),
                'user_id' => $employee->user_id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'mobile' => $employee->mobile,
                'document' => $employee->document_number,
                'specialties' => [$employee->position ?? 'Mecânica Geral'],
                'level' => 'pleno',
                'hourly_rate' => 0,
                'daily_rate' => 0,
                'is_active' => true,
            ]);

            $imported++;
        }

        $message = "{$imported} funcionário(s) importado(s)";
        if ($skipped > 0) {
            $message .= " ({$skipped} já existente(s))";
        }

        session()->flash('success', $message);
        $this->closeImportModal();
    }

    public function getAvailableEmployees()
    {
        // Buscar funcionários de RH que ainda não são mecânicos
        $existingEmails = Mechanic::where('tenant_id', activeTenantId())
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $existingPhones = Mechanic::where('tenant_id', activeTenantId())
            ->whereNotNull('phone')
            ->pluck('phone')
            ->toArray();

        return Employee::where('tenant_id', activeTenantId())
            ->where('status', 'active')
            ->where(function($q) use ($existingEmails, $existingPhones) {
                if (!empty($existingEmails)) {
                    $q->whereNotIn('email', $existingEmails);
                }
                if (!empty($existingPhones)) {
                    $q->whereNotIn('phone', $existingPhones);
                }
            })
            ->orderBy('first_name')
            ->get();
    }

    public function view($id)
    {
        $this->viewingMechanic = Mechanic::with(['workOrders.vehicle', 'workOrders.items'])
            ->findOrFail($id);
        $this->showViewModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingMechanic = null;
    }

    private function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'mobile', 'document', 'specialties', 'editingId']);
        $this->level = 'pleno';
        $this->hourly_rate = 0;
        $this->daily_rate = 0;
        $this->is_active = true;
    }
}

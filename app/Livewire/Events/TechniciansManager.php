<?php

namespace App\Livewire\Events;

use App\Models\Events\Technician;
use App\Models\HR\Employee;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Técnicos')]
class TechniciansManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showImportModal = false;
    public $editingId = null;
    public $selectedEmployees = [];
    
    // Form
    public $name = '';
    public $email = '';
    public $phone = '';
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
        $technicians = Technician::where('tenant_id', activeTenantId())
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.events.technicians-manager', compact('technicians'));
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $tech = Technician::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $tech->name;
        $this->email = $tech->email;
        $this->phone = $tech->phone;
        $this->document = $tech->document;
        $this->specialties = $tech->specialties ?? [];
        $this->level = $tech->level;
        $this->hourly_rate = $tech->hourly_rate;
        $this->daily_rate = $tech->daily_rate;
        $this->is_active = $tech->is_active;
        
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
            'document' => $this->document,
            'specialties' => $this->specialties,
            'level' => $this->level,
            'hourly_rate' => $this->hourly_rate,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Technician::find($this->editingId)->update($data);
            $message = '✅ Técnico atualizado!';
        } else {
            Technician::create($data);
            $message = '✅ Técnico criado!';
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        $this->closeModal();
    }

    public function delete($id)
    {
        Technician::where('tenant_id', activeTenantId())->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Técnico excluído!']);
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
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione pelo menos um funcionário']);
            return;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($this->selectedEmployees as $employeeId) {
            $employee = Employee::find($employeeId);
            
            if (!$employee) continue;

            // Verifica se já existe técnico com mesmo email ou telefone
            $exists = Technician::where('tenant_id', activeTenantId())
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

            // Importar funcionário como técnico
            Technician::create([
                'tenant_id' => activeTenantId(),
                'user_id' => $employee->user_id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'document' => $employee->document_number,
                'specialties' => [$employee->position ?? 'Geral'],
                'level' => 'pleno',
                'hourly_rate' => 0,
                'daily_rate' => 0,
                'is_active' => true,
            ]);

            $imported++;
        }

        $message = "✅ {$imported} funcionário(s) importado(s)";
        if ($skipped > 0) {
            $message .= " ({$skipped} já existente(s))";
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        $this->closeImportModal();
    }

    public function getAvailableEmployees()
    {
        // Buscar funcionários de RH que ainda não são técnicos
        $existingEmails = Technician::where('tenant_id', activeTenantId())
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $existingPhones = Technician::where('tenant_id', activeTenantId())
            ->whereNotNull('phone')
            ->pluck('phone')
            ->toArray();

        return Employee::where('tenant_id', activeTenantId())
            ->where(function($q) use ($existingEmails, $existingPhones) {
                $q->whereNotIn('email', $existingEmails)
                  ->whereNotIn('phone', $existingPhones);
            })
            ->orderBy('first_name')
            ->get();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'document', 'specialties', 'editingId']);
        $this->level = 'pleno';
        $this->hourly_rate = 0;
        $this->daily_rate = 0;
        $this->is_active = true;
    }
}

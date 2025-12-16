<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use App\Models\HR\Employee;

#[Layout('layouts.app')]
#[Title('Profissionais - Salão de Beleza')]
class ProfessionalManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $showModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $showImportModal = false;
    public $editingId = null;
    public $selectedEmployees = [];
    public $viewingProfessional = null;
    public $deletingId = null;
    public $deletingName = '';

    // Stats
    public $totalProfessionals = 0;
    public $totalActive = 0;

    // Form fields
    public $name, $nickname, $email, $phone, $document, $address, $specialization, $level, $bio;
    public $birth_date, $hire_date;
    public $working_days = [1, 2, 3, 4, 5, 6];
    public $work_start = '09:00', $work_end = '18:00';
    public $lunch_start, $lunch_end;
    public $commission_percent = 0, $hourly_rate = 0, $daily_rate = 0;
    public $accepts_online_booking = true, $is_active = true, $is_available = true;
    public $selected_services = [];

    protected function rules()
    {
        return [
            'name' => 'required|min:2',
            'work_start' => 'required',
            'work_end' => 'required',
        ];
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $professional = Professional::with('services')->find($id);
            $this->editingId = $id;
            $this->name = $professional->name;
            $this->nickname = $professional->nickname;
            $this->email = $professional->email;
            $this->phone = $professional->phone;
            $this->document = $professional->document;
            $this->address = $professional->address;
            $this->specialization = $professional->specialization;
            $this->level = $professional->level;
            $this->bio = $professional->bio;
            $this->birth_date = $professional->birth_date?->format('Y-m-d');
            $this->hire_date = $professional->hire_date?->format('Y-m-d');
            $this->working_days = $professional->working_days ?? [1, 2, 3, 4, 5, 6];
            $this->work_start = $professional->work_start ? $professional->work_start->format('H:i') : '09:00';
            $this->work_end = $professional->work_end ? $professional->work_end->format('H:i') : '18:00';
            $this->lunch_start = $professional->lunch_start ? $professional->lunch_start->format('H:i') : null;
            $this->lunch_end = $professional->lunch_end ? $professional->lunch_end->format('H:i') : null;
            $this->commission_percent = $professional->commission_percent;
            $this->hourly_rate = $professional->hourly_rate;
            $this->daily_rate = $professional->daily_rate;
            $this->accepts_online_booking = $professional->accepts_online_booking;
            $this->is_active = $professional->is_active;
            $this->is_available = $professional->is_available;
            $this->selected_services = $professional->services->pluck('id')->toArray();
        }
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'address' => $this->address,
            'specialization' => $this->specialization,
            'level' => $this->level,
            'bio' => $this->bio,
            'birth_date' => $this->birth_date,
            'hire_date' => $this->hire_date,
            'working_days' => $this->working_days,
            'work_start' => $this->work_start,
            'work_end' => $this->work_end,
            'lunch_start' => $this->lunch_start,
            'lunch_end' => $this->lunch_end,
            'commission_percent' => $this->commission_percent,
            'hourly_rate' => $this->hourly_rate,
            'daily_rate' => $this->daily_rate,
            'accepts_online_booking' => $this->accepts_online_booking,
            'is_active' => $this->is_active,
            'is_available' => $this->is_available,
        ];

        if ($this->editingId) {
            $professional = Professional::find($this->editingId);
            $professional->update($data);
            $professional->services()->sync($this->selected_services);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Profissional atualizado!']);
        } else {
            $professional = Professional::create($data);
            $professional->services()->sync($this->selected_services);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Profissional criado!']);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $professional = Professional::find($id);
        $professional->update(['is_active' => !$professional->is_active]);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function view($id)
    {
        $this->viewingProfessional = Professional::with('services')->find($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingProfessional = null;
    }

    public function openDeleteModal($id)
    {
        $professional = Professional::find($id);
        $this->deletingId = $id;
        $this->deletingName = $professional->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete()
    {
        $professional = Professional::find($this->deletingId);
        if ($professional->appointments()->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])->count() > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Profissional tem agendamentos pendentes!']);
            $this->cancelDelete();
            return;
        }
        $professional->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Profissional removido!']);
        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    private function resetForm()
    {
        $this->reset([
            'editingId', 'name', 'nickname', 'email', 'phone', 'document', 'address',
            'specialization', 'level', 'bio', 'birth_date', 'hire_date',
            'lunch_start', 'lunch_end'
        ]);
        $this->working_days = [1, 2, 3, 4, 5, 6];
        $this->work_start = '09:00';
        $this->work_end = '18:00';
        $this->commission_percent = 0;
        $this->hourly_rate = 0;
        $this->daily_rate = 0;
        $this->accepts_online_booking = true;
        $this->is_active = true;
        $this->is_available = true;
        $this->selected_services = [];
    }

    // ==================== IMPORTAÇÃO DE RH ====================

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

    public function toggleAllEmployees()
    {
        $availableEmployees = $this->getAvailableEmployees();
        
        if (count($this->selectedEmployees) === $availableEmployees->count()) {
            $this->selectedEmployees = [];
        } else {
            $this->selectedEmployees = $availableEmployees->pluck('id')->toArray();
        }
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

            // Verifica se já existe profissional com mesmo email ou telefone
            $exists = Professional::forTenant()
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

            // Importar funcionário como profissional
            Professional::create([
                'tenant_id' => activeTenantId(),
                'user_id' => $employee->user_id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone ?? $employee->mobile,
                'document' => $employee->bi_number ?? $employee->nif,
                'address' => $employee->address,
                'specialization' => $employee->position?->name ?? 'Geral',
                'level' => 'pleno',
                'birth_date' => $employee->birth_date,
                'hire_date' => $employee->hire_date ?? now(),
                'working_days' => [1, 2, 3, 4, 5, 6],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'commission_percent' => 0,
                'hourly_rate' => 0,
                'daily_rate' => 0,
                'is_active' => true,
                'is_available' => true,
                'accepts_online_booking' => true,
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
        // Buscar funcionários de RH que ainda não são profissionais
        $existingEmails = Professional::forTenant()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->toArray();

        $existingPhones = Professional::forTenant()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone')
            ->toArray();

        return Employee::where('tenant_id', activeTenantId())
            ->where(function($q) {
                $q->where('status', 'active')
                  ->orWhereNull('status');
            })
            ->when(!empty($existingEmails), function($q) use ($existingEmails) {
                $q->where(function($sub) use ($existingEmails) {
                    $sub->whereNull('email')
                        ->orWhere('email', '')
                        ->orWhereNotIn('email', $existingEmails);
                });
            })
            ->when(!empty($existingPhones), function($q) use ($existingPhones) {
                $q->where(function($sub) use ($existingPhones) {
                    $sub->whereNull('phone')
                        ->orWhere('phone', '')
                        ->orWhereNotIn('phone', $existingPhones);
                });
            })
            ->with('position')
            ->orderBy('first_name')
            ->get();
    }

    public function render()
    {
        // Stats
        $this->totalProfessionals = Professional::forTenant()->count();
        $this->totalActive = Professional::forTenant()->where('is_active', true)->count();

        $professionals = Professional::forTenant()
            ->withCount('services')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage);

        $services = Service::forTenant()->active()->orderBy('name')->get();

        $weekDays = [
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
            0 => 'Domingo',
        ];

        return view('livewire.salon.professionals.professionals', compact('professionals', 'services', 'weekDays'));
    }
}

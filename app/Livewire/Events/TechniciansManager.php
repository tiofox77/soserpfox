<?php

namespace App\Livewire\Events;

use App\Models\Events\Technician;
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
    public $editingId = null;
    
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

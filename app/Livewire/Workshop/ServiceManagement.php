<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Workshop\Service;

#[Layout('layouts.app')]
class ServiceManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $viewMode = 'grid'; // grid ou list
    
    // Modal
    public $showModal = false;
    public $showViewModal = false;
    public $editMode = false;
    public $serviceId;
    public $viewingService = null;
    
    // Form Fields
    public $name = '';
    public $description = '';
    public $category = 'Manutenção';
    public $labor_cost = 0;
    public $estimated_hours = 1;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'category' => 'required|in:Manutenção,Reparação,Inspeção,Pintura,Mecânica,Elétrica,Chapa,Pneus,Outro',
        'labor_cost' => 'required|numeric|min:0',
        'estimated_hours' => 'required|numeric|min:0',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $service = Service::findOrFail($id);
        
        $this->serviceId = $service->id;
        $this->name = $service->name;
        $this->description = $service->description;
        $this->category = $service->category;
        $this->labor_cost = $service->labor_cost;
        $this->estimated_hours = $service->estimated_hours;
        $this->is_active = $service->is_active;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'labor_cost' => $this->labor_cost,
            'estimated_hours' => $this->estimated_hours,
            'is_active' => $this->is_active,
        ];

        if ($this->editMode) {
            $service = Service::findOrFail($this->serviceId);
            $service->update($data);
            session()->flash('success', 'Serviço atualizado com sucesso!');
        } else {
            // Gerar código do serviço
            $data['service_code'] = 'SRV-' . str_pad(Service::count() + 1, 5, '0', STR_PAD_LEFT);
            Service::create($data);
            session()->flash('success', 'Serviço criado com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();
        
        session()->flash('success', 'Serviço removido com sucesso!');
    }

    public function view($id)
    {
        $this->viewingService = Service::with(['workOrderItems.workOrder.vehicle'])
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
        $this->viewingService = null;
    }

    private function resetForm()
    {
        $this->serviceId = null;
        $this->name = '';
        $this->description = '';
        $this->category = 'Manutenção';
        $this->labor_cost = 0;
        $this->estimated_hours = 1;
        $this->is_active = true;
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $services = Service::where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->categoryFilter, function($query) {
                $query->where('category', $this->categoryFilter);
            })
            ->ordered()
            ->paginate(15);

        return view('livewire.workshop.services.services', [
            'services' => $services,
        ]);
    }
}

<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Warehouse;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Armazéns')]
class Warehouses extends Component
{
    use WithPagination;

    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $perPage = 15;

    // Form
    public $warehouseId;
    public $name;
    public $code;
    public $location;
    public $address;
    public $city;
    public $postal_code;
    public $phone;
    public $email;
    public $manager_id;
    public $description;
    public $is_active = true;
    public $is_default = false;

    // Delete
    public $deleteId;
    public $deleteName;

    protected $queryString = ['search', 'statusFilter'];

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function render()
    {
        $query = Warehouse::where('tenant_id', activeTenantId())
            ->with('manager');

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('city', 'like', '%' . $this->search . '%');
            });
        }

        // Status Filter
        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter);
        }

        $warehouses = $query->latest()->paginate($this->perPage);

        // Stats
        $stats = [
            'total' => Warehouse::where('tenant_id', activeTenantId())->count(),
            'active' => Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->count(),
            'inactive' => Warehouse::where('tenant_id', activeTenantId())->where('is_active', false)->count(),
        ];

        $managers = User::whereHas('tenants', function ($q) {
            $q->where('tenants.id', activeTenantId());
        })->get();

        return view('livewire.invoicing.warehouses.warehouses', [
            'warehouses' => $warehouses,
            'stats' => $stats,
            'managers' => $managers,
        ]);
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($id);

        $this->warehouseId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->code = $warehouse->code;
        $this->location = $warehouse->location;
        $this->address = $warehouse->address;
        $this->city = $warehouse->city;
        $this->postal_code = $warehouse->postal_code;
        $this->phone = $warehouse->phone;
        $this->email = $warehouse->email;
        $this->manager_id = $warehouse->manager_id;
        $this->description = $warehouse->description;
        $this->is_active = $warehouse->is_active;
        $this->is_default = $warehouse->is_default;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'manager_id' => $this->manager_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
        ];

        if ($this->editMode) {
            $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($this->warehouseId);
            $warehouse->update($data);
            session()->flash('message', 'Armazém atualizado com sucesso!');
        } else {
            $warehouse = Warehouse::create($data);
            session()->flash('message', 'Armazém criado com sucesso!');
        }

        // Se marcado como default, desmarcar outros
        if ($this->is_default) {
            $warehouse->setAsDefault();
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->deleteId = $warehouse->id;
        $this->deleteName = $warehouse->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($this->deleteId);
        $warehouse->delete();

        session()->flash('message', 'Armazém excluído com sucesso!');
        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function toggleStatus($id)
    {
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($id);
        $warehouse->update(['is_active' => !$warehouse->is_active]);

        session()->flash('message', 'Status atualizado!');
    }

    public function setDefault($id)
    {
        $warehouse = Warehouse::where('tenant_id', activeTenantId())->findOrFail($id);
        $warehouse->setAsDefault();

        session()->flash('message', 'Armazém definido como padrão!');
    }

    private function resetForm()
    {
        $this->warehouseId = null;
        $this->name = '';
        $this->code = '';
        $this->location = '';
        $this->address = '';
        $this->city = '';
        $this->postal_code = '';
        $this->phone = '';
        $this->email = '';
        $this->manager_id = null;
        $this->description = '';
        $this->is_active = true;
        $this->is_default = false;
        $this->resetErrorBag();
    }

    private function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
}

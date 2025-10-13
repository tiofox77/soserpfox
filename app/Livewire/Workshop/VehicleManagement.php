<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Workshop\Vehicle;

#[Layout('layouts.app')]
class VehicleManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    
    // Modal
    public $showModal = false;
    public $editMode = false;
    public $vehicleId;
    
    // Form Fields - Owner
    public $plate = '';
    public $owner_name = '';
    public $owner_phone = '';
    public $owner_email = '';
    public $owner_nif = '';
    public $owner_address = '';
    
    // Vehicle Details
    public $brand = '';
    public $model = '';
    public $year = '';
    public $color = '';
    public $vin = '';
    public $engine_number = '';
    public $fuel_type = 'Gasolina';
    public $mileage = 0;
    
    // Documents
    public $registration_document = '';
    public $registration_expiry = '';
    public $insurance_company = '';
    public $insurance_policy = '';
    public $insurance_expiry = '';
    public $inspection_expiry = '';
    
    public $status = 'active';
    public $notes = '';

    protected $rules = [
        'plate' => 'required|string|max:255',
        'owner_name' => 'required|string|max:255',
        'owner_phone' => 'nullable|string|max:20',
        'owner_email' => 'nullable|email|max:255',
        'brand' => 'required|string|max:255',
        'model' => 'required|string|max:255',
        'year' => 'nullable|integer|min:1900|max:' . PHP_INT_MAX,
        'fuel_type' => 'nullable|in:Gasolina,Diesel,Elétrico,Híbrido,GPL',
        'status' => 'required|in:active,in_service,completed,inactive',
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
        $vehicle = Vehicle::findOrFail($id);
        
        $this->vehicleId = $vehicle->id;
        $this->plate = $vehicle->plate;
        $this->owner_name = $vehicle->owner_name;
        $this->owner_phone = $vehicle->owner_phone;
        $this->owner_email = $vehicle->owner_email;
        $this->owner_nif = $vehicle->owner_nif;
        $this->owner_address = $vehicle->owner_address;
        $this->brand = $vehicle->brand;
        $this->model = $vehicle->model;
        $this->year = $vehicle->year;
        $this->color = $vehicle->color;
        $this->vin = $vehicle->vin;
        $this->engine_number = $vehicle->engine_number;
        $this->fuel_type = $vehicle->fuel_type;
        $this->mileage = $vehicle->mileage;
        $this->registration_document = $vehicle->registration_document;
        $this->registration_expiry = $vehicle->registration_expiry ? $vehicle->registration_expiry->format('Y-m-d') : '';
        $this->insurance_company = $vehicle->insurance_company;
        $this->insurance_policy = $vehicle->insurance_policy;
        $this->insurance_expiry = $vehicle->insurance_expiry ? $vehicle->insurance_expiry->format('Y-m-d') : '';
        $this->inspection_expiry = $vehicle->inspection_expiry ? $vehicle->inspection_expiry->format('Y-m-d') : '';
        $this->status = $vehicle->status;
        $this->notes = $vehicle->notes;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'plate' => $this->plate,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'owner_email' => $this->owner_email,
            'owner_nif' => $this->owner_nif,
            'owner_address' => $this->owner_address,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'vin' => $this->vin,
            'engine_number' => $this->engine_number,
            'fuel_type' => $this->fuel_type,
            'mileage' => $this->mileage,
            'registration_document' => $this->registration_document,
            'registration_expiry' => $this->registration_expiry,
            'insurance_company' => $this->insurance_company,
            'insurance_policy' => $this->insurance_policy,
            'insurance_expiry' => $this->insurance_expiry,
            'inspection_expiry' => $this->inspection_expiry,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        if ($this->editMode) {
            $vehicle = Vehicle::findOrFail($this->vehicleId);
            $vehicle->update($data);
            session()->flash('success', 'Veículo atualizado com sucesso!');
        } else {
            // Gerar número de veículo
            $data['vehicle_number'] = 'VEH-' . str_pad(Vehicle::count() + 1, 5, '0', STR_PAD_LEFT);
            Vehicle::create($data);
            session()->flash('success', 'Veículo criado com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();
        
        session()->flash('success', 'Veículo removido com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->vehicleId = null;
        $this->plate = '';
        $this->owner_name = '';
        $this->owner_phone = '';
        $this->owner_email = '';
        $this->owner_nif = '';
        $this->owner_address = '';
        $this->brand = '';
        $this->model = '';
        $this->year = '';
        $this->color = '';
        $this->vin = '';
        $this->engine_number = '';
        $this->fuel_type = 'Gasolina';
        $this->mileage = 0;
        $this->registration_document = '';
        $this->registration_expiry = '';
        $this->insurance_company = '';
        $this->insurance_policy = '';
        $this->insurance_expiry = '';
        $this->inspection_expiry = '';
        $this->status = 'active';
        $this->notes = '';
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $vehicles = Vehicle::where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('plate', 'like', '%' . $this->search . '%')
                      ->orWhere('owner_name', 'like', '%' . $this->search . '%')
                      ->orWhere('brand', 'like', '%' . $this->search . '%')
                      ->orWhere('model', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(10);

        return view('livewire.workshop.vehicles.vehicles', [
            'vehicles' => $vehicles,
        ]);
    }
}

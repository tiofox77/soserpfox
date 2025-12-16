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
    
    // Modals
    public $showModal = false;
    public $showViewModal = false;
    public $editMode = false;
    public $vehicleId;
    public $viewingVehicle;
    
    // Form Fields - Owner
    public $plate = '';
    public $client_id = '';
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

    protected function rules()
    {
        return [
            'plate' => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('workshop_vehicles', 'plate')
                    ->where('tenant_id', auth()->user()->activeTenantId())
                    ->ignore($this->vehicleId)
            ],
            'owner_name' => 'required|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|email|max:255',
            'owner_nif' => 'nullable|string|max:20',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'fuel_type' => 'nullable|in:Gasolina,Diesel,Elétrico,Híbrido,GPL',
            'status' => 'required|in:active,in_service,completed,inactive',
        ];
    }
    
    protected $messages = [
        'plate.required' => 'A matrícula é obrigatória.',
        'plate.max' => 'A matrícula não pode ter mais de 20 caracteres.',
        'plate.unique' => 'Esta matrícula já está cadastrada.',
        'owner_name.required' => 'O nome do proprietário é obrigatório.',
        'owner_name.max' => 'O nome não pode ter mais de 255 caracteres.',
        'owner_email.email' => 'O email deve ser válido.',
        'owner_nif.max' => 'O NIF não pode ter mais de 20 caracteres.',
        'owner_phone.max' => 'O telefone não pode ter mais de 20 caracteres.',
        'brand.required' => 'A marca é obrigatória.',
        'brand.max' => 'A marca não pode ter mais de 100 caracteres.',
        'model.required' => 'O modelo é obrigatório.',
        'model.max' => 'O modelo não pode ter mais de 100 caracteres.',
        'year.integer' => 'O ano deve ser um número.',
        'year.min' => 'O ano deve ser maior que 1900.',
        'year.max' => 'O ano não pode ser maior que 2100.',
        'fuel_type.in' => 'Tipo de combustível inválido.',
        'status.required' => 'O status é obrigatório.',
    ];
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatedClientId($value)
    {
        if ($value) {
            $client = \App\Models\Client::find($value);
            if ($client) {
                $this->owner_name = $client->name;
                $this->owner_phone = $client->phone;
                $this->owner_email = $client->email;
                $this->owner_nif = $client->nif;
                $this->owner_address = $client->address;
            }
        }
    }
    
    // Validação em tempo real para campos principais
    public function updated($propertyName)
    {
        // Não validar campos de busca e client_id
        if (in_array($propertyName, ['search', 'statusFilter', 'client_id'])) {
            return;
        }
        
        $this->validateOnly($propertyName);
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
        $this->client_id = $vehicle->client_id;
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
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('error', message: 'Por favor, corrija os erros no formulário.');
            throw $e;
        }

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'plate' => $this->plate,
            'client_id' => $this->client_id ?: null,
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
            'registration_expiry' => $this->registration_expiry ?: null,
            'insurance_company' => $this->insurance_company,
            'insurance_policy' => $this->insurance_policy,
            'insurance_expiry' => $this->insurance_expiry ?: null,
            'inspection_expiry' => $this->inspection_expiry ?: null,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        if ($this->editMode) {
            $vehicle = Vehicle::findOrFail($this->vehicleId);
            $vehicle->update($data);
            $this->dispatch('success', message: 'Veículo atualizado com sucesso!');
        } else {
            // Gerar número de veículo
            $data['vehicle_number'] = 'VEH-' . str_pad(Vehicle::count() + 1, 5, '0', STR_PAD_LEFT);
            Vehicle::create($data);
            $this->dispatch('success', message: 'Veículo criado com sucesso!');
        }

        $this->closeModal();
    }

    public function view($id)
    {
        $this->viewingVehicle = Vehicle::with(['workOrders.mechanic', 'workOrders.items'])
            ->findOrFail($id);
        $this->showViewModal = true;
    }
    
    public function delete($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();
        
        $this->dispatch('success', message: 'Veículo removido com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingVehicle = null;
    }

    private function resetForm()
    {
        $this->vehicleId = null;
        $this->plate = '';
        $this->client_id = '';
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
        
        // Buscar clientes para o select
        $clients = \App\Models\Client::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        return view('livewire.workshop.vehicles.vehicles', [
            'vehicles' => $vehicles,
            'clients' => $clients,
        ]);
    }
}

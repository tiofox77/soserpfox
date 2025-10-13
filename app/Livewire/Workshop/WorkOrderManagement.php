<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\Vehicle;
use App\Models\HR\Employee;

#[Layout('layouts.app')]
class WorkOrderManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $priorityFilter = '';
    
    // Modal
    public $showModal = false;
    public $editMode = false;
    public $workOrderId;
    
    // Form Fields
    public $vehicle_id = '';
    public $mechanic_id = '';
    public $received_at = '';
    public $scheduled_for = '';
    public $mileage_in = 0;
    public $problem_description = '';
    public $diagnosis = '';
    public $work_performed = '';
    public $recommendations = '';
    public $status = 'pending';
    public $priority = 'normal';
    public $notes = '';

    protected $rules = [
        'vehicle_id' => 'required|exists:workshop_vehicles,id',
        'received_at' => 'required|date',
        'problem_description' => 'required|string',
        'status' => 'required|in:pending,scheduled,in_progress,waiting_parts,completed,delivered,cancelled',
        'priority' => 'required|in:low,normal,high,urgent',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->received_at = now()->format('Y-m-d H:i');
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $workOrder = WorkOrder::with(['vehicle', 'mechanic'])->findOrFail($id);
        
        $this->workOrderId = $workOrder->id;
        $this->vehicle_id = $workOrder->vehicle_id;
        $this->mechanic_id = $workOrder->mechanic_id;
        $this->received_at = $workOrder->received_at ? $workOrder->received_at->format('Y-m-d H:i') : '';
        $this->scheduled_for = $workOrder->scheduled_for ? $workOrder->scheduled_for->format('Y-m-d H:i') : '';
        $this->mileage_in = $workOrder->mileage_in;
        $this->problem_description = $workOrder->problem_description;
        $this->diagnosis = $workOrder->diagnosis;
        $this->work_performed = $workOrder->work_performed;
        $this->recommendations = $workOrder->recommendations;
        $this->status = $workOrder->status;
        $this->priority = $workOrder->priority;
        $this->notes = $workOrder->notes;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'vehicle_id' => $this->vehicle_id,
            'mechanic_id' => $this->mechanic_id,
            'received_at' => $this->received_at,
            'scheduled_for' => $this->scheduled_for,
            'mileage_in' => $this->mileage_in,
            'problem_description' => $this->problem_description,
            'diagnosis' => $this->diagnosis,
            'work_performed' => $this->work_performed,
            'recommendations' => $this->recommendations,
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
        ];

        if ($this->editMode) {
            $workOrder = WorkOrder::findOrFail($this->workOrderId);
            $workOrder->update($data);
            session()->flash('success', 'Ordem de Serviço atualizada com sucesso!');
        } else {
            // Gerar número da OS
            $data['order_number'] = 'OS-' . str_pad(WorkOrder::count() + 1, 5, '0', STR_PAD_LEFT);
            WorkOrder::create($data);
            session()->flash('success', 'Ordem de Serviço criada com sucesso!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        $workOrder = WorkOrder::findOrFail($id);
        $workOrder->delete();
        
        session()->flash('success', 'Ordem de Serviço removida com sucesso!');
    }
    
    public function updateStatus($id, $newStatus)
    {
        $workOrder = WorkOrder::findOrFail($id);
        
        $data = ['status' => $newStatus];
        
        if ($newStatus === 'in_progress' && !$workOrder->started_at) {
            $data['started_at'] = now();
        } elseif ($newStatus === 'completed' && !$workOrder->completed_at) {
            $data['completed_at'] = now();
            $data['warranty_expires'] = now()->addDays($workOrder->warranty_days ?? 30);
        } elseif ($newStatus === 'delivered' && !$workOrder->delivered_at) {
            $data['delivered_at'] = now();
        }
        
        $workOrder->update($data);
        session()->flash('success', 'Status atualizado com sucesso!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->workOrderId = null;
        $this->vehicle_id = '';
        $this->mechanic_id = '';
        $this->received_at = '';
        $this->scheduled_for = '';
        $this->mileage_in = 0;
        $this->problem_description = '';
        $this->diagnosis = '';
        $this->work_performed = '';
        $this->recommendations = '';
        $this->status = 'pending';
        $this->priority = 'normal';
        $this->notes = '';
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $workOrders = WorkOrder::with(['vehicle', 'mechanic'])
            ->where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('order_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('vehicle', function($q) {
                          $q->where('plate', 'like', '%' . $this->search . '%')
                            ->orWhere('owner_name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->priorityFilter, function($query) {
                $query->where('priority', $this->priorityFilter);
            })
            ->latest()
            ->paginate(10);

        $vehicles = Vehicle::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('plate')
            ->get();
            
        $mechanics = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('livewire.workshop.work-orders.work-orders', [
            'workOrders' => $workOrders,
            'vehicles' => $vehicles,
            'mechanics' => $mechanics,
        ]);
    }
}

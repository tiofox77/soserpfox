<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Room;
use App\Models\Hotel\Staff;

class MaintenanceManagement extends Component
{
    use WithPagination;

    public $viewMode = 'list'; // list, kanban
    
    // Filtros
    public $search = '';
    public $filterStatus = '';
    public $filterPriority = '';
    public $filterCategory = '';
    public $filterRoom = '';
    public $filterAssignee = '';

    // Modal
    public $showModal = false;
    public $editingId = null;
    public $roomId = '';
    public $assignedTo = '';
    public $type = 'corrective';
    public $priority = 'normal';
    public $category = 'other';
    public $title = '';
    public $description = '';
    public $location = '';
    public $estimatedCost = '';
    public $estimatedTime = '';
    public $scheduledDate = '';

    // View Modal
    public $showViewModal = false;
    public $viewingOrder = null;
    public $resolutionNotes = '';
    public $actualCost = '';

    protected $rules = [
        'title' => 'required|string|max:255',
        'type' => 'required|in:preventive,corrective,emergency',
        'priority' => 'required|in:low,normal,high,urgent',
        'category' => 'required|in:electrical,plumbing,hvac,furniture,appliance,structural,other',
    ];

    public function openModal($id = null)
    {
        $this->resetForm();
        
        if ($id) {
            $order = MaintenanceOrder::find($id);
            if ($order) {
                $this->editingId = $id;
                $this->roomId = $order->room_id;
                $this->assignedTo = $order->assigned_to;
                $this->type = $order->type;
                $this->priority = $order->priority;
                $this->category = $order->category;
                $this->title = $order->title;
                $this->description = $order->description;
                $this->location = $order->location;
                $this->estimatedCost = $order->estimated_cost;
                $this->estimatedTime = $order->estimated_time;
                $this->scheduledDate = $order->scheduled_date?->format('Y-m-d\TH:i');
            }
        }
        
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->roomId = '';
        $this->assignedTo = '';
        $this->type = 'corrective';
        $this->priority = 'normal';
        $this->category = 'other';
        $this->title = '';
        $this->description = '';
        $this->location = '';
        $this->estimatedCost = '';
        $this->estimatedTime = '';
        $this->scheduledDate = '';
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'room_id' => $this->roomId ?: null,
            'assigned_to' => $this->assignedTo ?: null,
            'type' => $this->type,
            'priority' => $this->priority,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'estimated_cost' => $this->estimatedCost ?: null,
            'estimated_time' => $this->estimatedTime ?: null,
            'scheduled_date' => $this->scheduledDate ?: null,
        ];

        if ($this->editingId) {
            MaintenanceOrder::find($this->editingId)->update($data);
            session()->flash('success', 'Ordem atualizada!');
        } else {
            $data['reported_by'] = Staff::where('tenant_id', auth()->user()->tenant_id)
                ->where('user_id', auth()->id())
                ->first()?->id;
            MaintenanceOrder::create($data);
            session()->flash('success', 'Ordem criada!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function viewOrder($id)
    {
        $this->viewingOrder = MaintenanceOrder::with(['room', 'assignee', 'reporter'])->find($id);
        $this->resolutionNotes = $this->viewingOrder->resolution_notes ?? '';
        $this->actualCost = $this->viewingOrder->actual_cost ?? '';
        $this->showViewModal = true;
    }

    public function updateStatus($id, $status)
    {
        $order = MaintenanceOrder::find($id);
        if (!$order) return;

        $data = ['status' => $status];

        if ($status === 'in_progress' && !$order->started_at) {
            $data['started_at'] = now();
        }

        if ($status === 'completed') {
            $data['completed_at'] = now();
            if ($order->started_at) {
                $data['actual_time'] = now()->diffInMinutes($order->started_at);
            }
        }

        $order->update($data);
        
        if ($this->viewingOrder && $this->viewingOrder->id === $id) {
            $this->viewingOrder = $order->fresh(['room', 'assignee', 'reporter']);
        }

        session()->flash('success', 'Status atualizado!');
    }

    public function completeOrder()
    {
        if (!$this->viewingOrder) return;

        $this->viewingOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_time' => $this->viewingOrder->started_at 
                ? now()->diffInMinutes($this->viewingOrder->started_at) 
                : null,
            'resolution_notes' => $this->resolutionNotes,
            'actual_cost' => $this->actualCost ?: null,
        ]);

        session()->flash('success', 'Ordem concluída!');
        $this->showViewModal = false;
    }

    public function delete($id)
    {
        MaintenanceOrder::where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->delete();
        session()->flash('success', 'Ordem eliminada!');
    }

    public function assignToMe($id)
    {
        $staffId = Staff::where('tenant_id', auth()->user()->tenant_id)
            ->where('user_id', auth()->id())
            ->first()?->id;

        if ($staffId) {
            MaintenanceOrder::find($id)?->update(['assigned_to' => $staffId]);
            session()->flash('success', 'Ordem atribuída a si!');
        }
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $orders = MaintenanceOrder::where('tenant_id', $tenantId)
            ->with(['room', 'assignee'])
            ->when($this->search, fn($q) => $q->where(function($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('order_number', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterCategory, fn($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterRoom, fn($q) => $q->where('room_id', $this->filterRoom))
            ->when($this->filterAssignee, fn($q) => $q->where('assigned_to', $this->filterAssignee))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('created_at')
            ->paginate(15);

        // Para Kanban
        $ordersByStatus = [
            'pending' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'pending')->with(['room', 'assignee'])->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->get(),
            'in_progress' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'in_progress')->with(['room', 'assignee'])->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->get(),
            'waiting_parts' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'waiting_parts')->with(['room', 'assignee'])->get(),
            'completed' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'completed')->with(['room', 'assignee'])->orderByDesc('completed_at')->take(20)->get(),
        ];

        $rooms = Room::where('tenant_id', $tenantId)->orderBy('number')->get();
        $staff = Staff::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();

        // Stats
        $stats = [
            'total' => MaintenanceOrder::where('tenant_id', $tenantId)->count(),
            'pending' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'in_progress' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'in_progress')->count(),
            'urgent' => MaintenanceOrder::where('tenant_id', $tenantId)->where('priority', 'urgent')->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed_today' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];

        return view('livewire.hotel.maintenance-management', [
            'orders' => $orders,
            'ordersByStatus' => $ordersByStatus,
            'rooms' => $rooms,
            'staff' => $staff,
            'stats' => $stats,
        ])->layout('layouts.app');
    }
}

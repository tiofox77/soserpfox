<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Hotel\HousekeepingTask;
use App\Models\Hotel\Room;
use App\Models\Hotel\Reservation;
use App\Models\User;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Housekeeping - Hotel')]
class HousekeepingDashboard extends Component
{
    use WithPagination;

    public $viewMode = 'board'; // 'board', 'list', 'rooms'
    public $filterStatus = '';
    public $filterPriority = '';
    public $filterUser = '';
    public $filterFloor = '';
    public $selectedDate;
    
    // Modal de tarefa
    public $showTaskModal = false;
    public $editingTask = null;
    public $taskRoomId = '';
    public $taskType = 'checkout_clean';
    public $taskPriority = 'normal';
    public $taskAssignedTo = '';
    public $taskScheduledDate = '';
    public $taskScheduledTime = '';
    public $taskNotes = '';
    
    // Modal de detalhes/checklist
    public $showDetailsModal = false;
    public $viewingTask = null;
    
    // Modal de atribuição rápida
    public $showAssignModal = false;
    public $assigningTaskId = null;
    public $assignToUser = '';

    public function mount()
    {
        $this->selectedDate = today()->toDateString();
        $this->taskScheduledDate = today()->toDateString();
    }

    /**
     * Gerar tarefas automáticas baseadas em check-outs
     */
    public function generateTasks()
    {
        // Buscar check-outs de hoje que ainda não têm tarefa
        $checkoutsToday = Reservation::where('tenant_id', activeTenantId())
            ->whereDate('check_out_date', today())
            ->where('status', 'checked_in')
            ->whereNotNull('room_id')
            ->get();

        $created = 0;
        foreach ($checkoutsToday as $reservation) {
            // Verificar se já existe tarefa para este quarto hoje
            $exists = HousekeepingTask::where('tenant_id', activeTenantId())
                ->where('room_id', $reservation->room_id)
                ->whereDate('scheduled_date', today())
                ->exists();

            if (!$exists) {
                HousekeepingTask::create([
                    'room_id' => $reservation->room_id,
                    'reservation_id' => $reservation->id,
                    'task_type' => 'checkout_clean',
                    'priority' => 'high',
                    'scheduled_date' => today(),
                    'estimated_duration' => 45,
                ]);
                
                // Marcar quarto como sujo
                Room::where('id', $reservation->room_id)->update(['housekeeping_status' => 'dirty']);
                $created++;
            }
        }

        // Buscar quartos com hóspedes para limpeza de estadia
        $stayRooms = Reservation::where('tenant_id', activeTenantId())
            ->where('status', 'checked_in')
            ->whereDate('check_out_date', '>', today())
            ->whereNotNull('room_id')
            ->get();

        foreach ($stayRooms as $reservation) {
            $exists = HousekeepingTask::where('tenant_id', activeTenantId())
                ->where('room_id', $reservation->room_id)
                ->whereDate('scheduled_date', today())
                ->exists();

            if (!$exists) {
                HousekeepingTask::create([
                    'room_id' => $reservation->room_id,
                    'reservation_id' => $reservation->id,
                    'task_type' => 'stay_clean',
                    'priority' => 'normal',
                    'scheduled_date' => today(),
                    'estimated_duration' => 20,
                ]);
                $created++;
            }
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $created > 0 ? "{$created} tarefa(s) gerada(s)!" : 'Todas as tarefas já foram geradas.'
        ]);
    }

    /**
     * Estatísticas do dia
     */
    public function getStatsProperty()
    {
        $date = Carbon::parse($this->selectedDate);
        
        $tasks = HousekeepingTask::forTenant()
            ->whereDate('scheduled_date', $date)
            ->get();

        $totalRooms = Room::where('tenant_id', activeTenantId())->where('is_active', true)->count();
        $dirtyRooms = Room::where('tenant_id', activeTenantId())->where('housekeeping_status', 'dirty')->count();
        $cleanRooms = Room::where('tenant_id', activeTenantId())->where('housekeeping_status', 'clean')->count();
        $inProgressRooms = Room::where('tenant_id', activeTenantId())->where('housekeeping_status', 'in_progress')->count();

        return [
            'total_tasks' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->whereIn('status', ['completed', 'verified'])->count(),
            'with_issues' => $tasks->where('status', 'issue')->count(),
            'overdue' => HousekeepingTask::forTenant()->overdue()->count(),
            'total_rooms' => $totalRooms,
            'dirty_rooms' => $dirtyRooms,
            'clean_rooms' => $cleanRooms,
            'in_progress_rooms' => $inProgressRooms,
            'clean_rate' => $totalRooms > 0 ? round(($cleanRooms / $totalRooms) * 100) : 0,
        ];
    }

    /**
     * Tarefas agrupadas por status (para vista board)
     */
    public function getTasksByStatusProperty()
    {
        $query = HousekeepingTask::forTenant()
            ->whereDate('scheduled_date', $this->selectedDate)
            ->with(['room.roomType', 'assignedUser', 'reservation.guest'])
            ->orderBy('priority', 'asc')
            ->orderBy('scheduled_time');

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }
        if ($this->filterUser) {
            $query->where('assigned_to', $this->filterUser);
        }

        $tasks = $query->get();

        return [
            'pending' => $tasks->where('status', 'pending')->values(),
            'in_progress' => $tasks->where('status', 'in_progress')->values(),
            'completed' => $tasks->where('status', 'completed')->values(),
            'verified' => $tasks->where('status', 'verified')->values(),
            'issue' => $tasks->where('status', 'issue')->values(),
        ];
    }

    /**
     * Quartos com status de housekeeping (para vista rooms)
     */
    public function getRoomsByFloorProperty()
    {
        $query = Room::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with(['roomType', 'housekeepingTasks' => function ($q) {
                $q->whereDate('scheduled_date', $this->selectedDate);
            }]);

        if ($this->filterFloor) {
            $query->where('floor', $this->filterFloor);
        }

        $rooms = $query->orderBy('floor')->orderBy('number')->get();

        return $rooms->groupBy('floor');
    }

    /**
     * Andares disponíveis
     */
    public function getFloorsProperty()
    {
        return Room::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->distinct()
            ->pluck('floor')
            ->sort();
    }

    /**
     * Utilizadores para atribuição
     */
    public function getUsersProperty()
    {
        return User::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    // Modal de tarefa
    public function openTaskModal($roomId = null)
    {
        $this->resetTaskForm();
        if ($roomId) {
            $this->taskRoomId = $roomId;
        }
        $this->showTaskModal = true;
    }

    public function editTask($taskId)
    {
        $task = HousekeepingTask::find($taskId);
        if ($task) {
            $this->editingTask = $task;
            $this->taskRoomId = $task->room_id;
            $this->taskType = $task->task_type;
            $this->taskPriority = $task->priority;
            $this->taskAssignedTo = $task->assigned_to ?? '';
            $this->taskScheduledDate = $task->scheduled_date->toDateString();
            $this->taskScheduledTime = $task->scheduled_time;
            $this->taskNotes = $task->notes ?? '';
            $this->showTaskModal = true;
        }
    }

    public function saveTask()
    {
        $this->validate([
            'taskRoomId' => 'required|exists:hotel_rooms,id',
            'taskType' => 'required|string',
            'taskPriority' => 'required|string',
            'taskScheduledDate' => 'required|date',
        ]);

        $data = [
            'room_id' => $this->taskRoomId,
            'task_type' => $this->taskType,
            'priority' => $this->taskPriority,
            'assigned_to' => $this->taskAssignedTo ?: null,
            'scheduled_date' => $this->taskScheduledDate,
            'scheduled_time' => $this->taskScheduledTime ?: null,
            'notes' => $this->taskNotes,
        ];

        if ($this->editingTask) {
            $this->editingTask->update($data);
            $message = 'Tarefa atualizada!';
        } else {
            HousekeepingTask::create($data);
            // Marcar quarto como sujo
            Room::where('id', $this->taskRoomId)->update(['housekeeping_status' => 'dirty']);
            $message = 'Tarefa criada!';
        }

        $this->closeTaskModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
    }

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
        $this->resetTaskForm();
    }

    private function resetTaskForm()
    {
        $this->editingTask = null;
        $this->taskRoomId = '';
        $this->taskType = 'checkout_clean';
        $this->taskPriority = 'normal';
        $this->taskAssignedTo = '';
        $this->taskScheduledDate = today()->toDateString();
        $this->taskScheduledTime = '';
        $this->taskNotes = '';
    }

    // Modal de detalhes
    public function viewTask($taskId)
    {
        $this->viewingTask = HousekeepingTask::with(['room.roomType', 'assignedUser', 'reservation.guest'])
            ->find($taskId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->viewingTask = null;
    }

    // Ações de tarefa
    public function startTask($taskId)
    {
        $task = HousekeepingTask::find($taskId);
        if ($task) {
            $task->start();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Tarefa iniciada!']);
        }
    }

    public function completeTask($taskId)
    {
        $task = HousekeepingTask::find($taskId);
        if ($task) {
            $task->complete();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Tarefa concluída!']);
            $this->closeDetailsModal();
        }
    }

    public function verifyTask($taskId)
    {
        $task = HousekeepingTask::find($taskId);
        if ($task) {
            $task->verify(auth()->id());
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Tarefa verificada!']);
            $this->closeDetailsModal();
        }
    }

    public function toggleChecklistItem($taskId, $index)
    {
        $task = HousekeepingTask::find($taskId);
        if ($task && isset($task->checklist[$index])) {
            $checklist = $task->checklist;
            $checklist[$index]['done'] = !$checklist[$index]['done'];
            $task->update(['checklist' => $checklist]);
        }
    }

    // Atribuição rápida
    public function openAssignModal($taskId)
    {
        $this->assigningTaskId = $taskId;
        $this->assignToUser = '';
        $this->showAssignModal = true;
    }

    public function assignTask()
    {
        if ($this->assigningTaskId && $this->assignToUser) {
            HousekeepingTask::where('id', $this->assigningTaskId)
                ->update(['assigned_to' => $this->assignToUser]);
            
            $this->showAssignModal = false;
            $this->assigningTaskId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Tarefa atribuída!']);
        }
    }

    public function deleteTask($taskId)
    {
        HousekeepingTask::where('id', $taskId)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Tarefa removida!']);
        $this->closeDetailsModal();
    }

    public function render()
    {
        $rooms = Room::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with('roomType')
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        return view('livewire.hotel.housekeeping.housekeeping', [
            'rooms' => $rooms,
            'stats' => $this->stats,
            'tasksByStatus' => $this->tasksByStatus,
            'roomsByFloor' => $this->roomsByFloor,
            'floors' => $this->floors,
            'users' => $this->users,
            'taskTypes' => HousekeepingTask::TASK_TYPES,
            'priorities' => HousekeepingTask::PRIORITIES,
            'statuses' => HousekeepingTask::STATUSES,
        ]);
    }
}

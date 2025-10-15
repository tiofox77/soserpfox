<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Enviar notificação se task foi atribuída a alguém
        if ($task->assigned_to) {
            try {
                $notificationService = new ImmediateNotificationService($task->tenant_id);
                
                // Buscar usuário atribuído
                $assignedUser = \App\Models\User::find($task->assigned_to);
                
                if ($assignedUser) {
                    $notificationService->notifyTaskAssigned($task, $assignedUser);
                    
                    Log::info('TaskObserver: Task assigned notification triggered', [
                        'task_id' => $task->id,
                        'assigned_to' => $task->assigned_to,
                        'tenant_id' => $task->tenant_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('TaskObserver: Failed to send notification', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Se mudou quem está atribuído, enviar notificação para novo responsável
        if ($task->isDirty('assigned_to') && $task->assigned_to) {
            try {
                $notificationService = new ImmediateNotificationService($task->tenant_id);
                
                // Buscar usuário atribuído
                $assignedUser = \App\Models\User::find($task->assigned_to);
                
                if ($assignedUser) {
                    $notificationService->notifyTaskAssigned($task, $assignedUser);
                    
                    Log::info('TaskObserver: Task reassigned notification triggered', [
                        'task_id' => $task->id,
                        'assigned_to' => $task->assigned_to,
                        'tenant_id' => $task->tenant_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('TaskObserver: Failed to send reassigned notification', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

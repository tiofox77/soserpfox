<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\Log;

class MeetingObserver
{
    /**
     * Handle the Meeting "created" event.
     */
    public function created(Meeting $meeting): void
    {
        try {
            $notificationService = new ImmediateNotificationService($meeting->tenant_id);
            
            // Buscar participantes da reunião
            $participants = $meeting->participants ?? [];
            
            // Se não tiver relação participants, buscar da tabela pivot
            if (empty($participants) && method_exists($meeting, 'users')) {
                $participants = $meeting->users;
            }
            
            // Converter para array se for collection
            if ($participants instanceof \Illuminate\Support\Collection) {
                $participants = $participants->all();
            }
            
            $notificationService->notifyMeetingScheduled($meeting, $participants);
            
            Log::info('MeetingObserver: Meeting scheduled notification triggered', [
                'meeting_id' => $meeting->id,
                'participants_count' => count($participants),
                'tenant_id' => $meeting->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('MeetingObserver: Failed to send notification', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Handle the Meeting "updated" event.
     */
    public function updated(Meeting $meeting): void
    {
        // Se mudou a data/hora, enviar notificação de atualização
        if ($meeting->isDirty('start_time') || $meeting->isDirty('end_time')) {
            try {
                $notificationService = new ImmediateNotificationService($meeting->tenant_id);
                
                // Buscar participantes da reunião
                $participants = $meeting->participants ?? [];
                
                if (empty($participants) && method_exists($meeting, 'users')) {
                    $participants = $meeting->users;
                }
                
                if ($participants instanceof \Illuminate\Support\Collection) {
                    $participants = $participants->all();
                }
                
                $notificationService->notifyMeetingScheduled($meeting, $participants);
                
                Log::info('MeetingObserver: Meeting updated notification triggered', [
                    'meeting_id' => $meeting->id,
                    'participants_count' => count($participants),
                    'tenant_id' => $meeting->tenant_id,
                ]);
            } catch (\Exception $e) {
                Log::error('MeetingObserver: Failed to send update notification', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

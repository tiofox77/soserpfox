<?php

namespace App\Observers;

use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventTechnicianObserver
{
    /**
     * Handle the EventTechnician "created" event.
     */
    public function created($eventTechnician): void
    {
        try {
            // Buscar dados do evento
            $event = DB::table('events_events')
                ->where('id', $eventTechnician->event_id)
                ->first();
            
            if (!$event) return;
            
            // Buscar dados do técnico direto da events_technicians
            $technician = DB::table('events_technicians')
                ->where('id', $eventTechnician->technician_id)
                ->first();
            
            if ($technician) {
                $notificationService = new ImmediateNotificationService($event->tenant_id);
                $notificationService->notifyTechnicianAssigned(
                    (object)$event,
                    (object)$technician
                );
            }
        } catch (\Exception $e) {
            Log::error('EventTechnicianObserver: Failed to send notification', [
                'event_id' => $eventTechnician->event_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

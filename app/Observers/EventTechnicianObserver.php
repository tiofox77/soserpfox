<?php

namespace App\Observers;

use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\DB;

class EventTechnicianObserver
{
    protected $notificationService;
    
    public function __construct(ImmediateNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Handle the EventTechnician "created" event.
     */
    public function created($eventTechnician): void
    {
        // Buscar dados do evento
        $event = DB::table('events_events')
            ->where('id', $eventTechnician->event_id)
            ->first();
        
        // Buscar dados do técnico direto da events_technicians
        $technician = DB::table('events_technicians')
            ->where('id', $eventTechnician->technician_id)
            ->first();
        
        if ($event && $technician) {
            $this->notificationService->notifyTechnicianAssigned(
                (object)$event,
                (object)$technician
            );
        }
    }
}

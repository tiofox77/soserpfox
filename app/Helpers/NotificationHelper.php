<?php

namespace App\Helpers;

use App\Services\ImmediateNotificationService;

class NotificationHelper
{
    /**
     * Envia notificação de evento criado
     */
    public static function notifyEventCreated($event, array $technicians = [])
    {
        $service = app(ImmediateNotificationService::class);
        return $service->notifyEventCreated($event, $technicians);
    }
    
    /**
     * Envia notificação de técnico designado
     */
    public static function notifyTechnicianAssigned($event, $technician)
    {
        $service = app(ImmediateNotificationService::class);
        return $service->notifyTechnicianAssigned($event, $technician);
    }
    
    /**
     * Envia notificação de evento cancelado
     */
    public static function notifyEventCancelled($event, array $technicians = [])
    {
        $service = app(ImmediateNotificationService::class);
        return $service->notifyEventCancelled($event, $technicians);
    }
    
    /**
     * Envia notificação de tarefa atribuída
     */
    public static function notifyTaskAssigned($task, $assignedUser)
    {
        $service = app(ImmediateNotificationService::class);
        return $service->notifyTaskAssigned($task, $assignedUser);
    }
    
    /**
     * Envia notificação de reunião agendada
     */
    public static function notifyMeetingScheduled($meeting, array $participants = [])
    {
        $service = app(ImmediateNotificationService::class);
        return $service->notifyMeetingScheduled($meeting, $participants);
    }
    
    /**
     * Uso manual em controllers ou qualquer lugar
     * 
     * Exemplo:
     * NotificationHelper::notifyEventCreated($event, $technicians);
     */
}

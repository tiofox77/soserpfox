<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TechnicianAssignedNotification extends Notification
{
    use Queueable;

    public $event;
    public $assignedBy;

    public function __construct($event, $assignedBy)
    {
        $this->event = $event;
        $this->assignedBy = $assignedBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'technician_assigned',
            'title' => 'Você foi Adicionado a um Evento',
            'message' => "Você foi designado para o evento '{$this->event->title}' por {$this->assignedBy->name}",
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'assigned_by' => $this->assignedBy->name,
            'icon' => 'fa-user-plus',
            'color' => 'purple',
            'url' => route('events.show', $this->event->id),
        ];
    }
}

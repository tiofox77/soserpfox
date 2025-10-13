<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventStatusChangedNotification extends Notification
{
    use Queueable;

    public $event;
    public $oldStatus;
    public $newStatus;
    public $changedBy;

    public function __construct($event, $oldStatus, $newStatus, $changedBy)
    {
        $this->event = $event;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->changedBy = $changedBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'event_status_changed',
            'title' => 'Status do Evento Alterado',
            'message' => "Evento '{$this->event->title}' mudou de {$this->oldStatus} para {$this->newStatus}",
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by' => $this->changedBy->name,
            'icon' => 'fa-sync-alt',
            'color' => 'cyan',
            'url' => route('events.show', $this->event->id),
        ];
    }
}

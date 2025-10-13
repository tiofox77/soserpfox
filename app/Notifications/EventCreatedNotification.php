<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCreatedNotification extends Notification
{
    use Queueable;

    public $event;
    public $createdBy;

    public function __construct($event, $createdBy)
    {
        $this->event = $event;
        $this->createdBy = $createdBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'event_created',
            'title' => 'Novo Evento Criado',
            'message' => "Evento '{$this->event->title}' foi criado por {$this->createdBy->name}",
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'created_by' => $this->createdBy->name,
            'icon' => 'fa-calendar-plus',
            'color' => 'blue',
            'url' => route('events.show', $this->event->id),
        ];
    }
}

<?php

namespace App\Notifications\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\HotelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreArrivalReminder extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reservation;
        $settings = HotelSettings::getForTenant($r->tenant_id);
        $hotel = $settings->hotel_name ?? 'Hotel';
        $daysUntil = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($r->check_in_date)->startOfDay(), false);

        return (new MailMessage)
            ->subject("A sua chegada ao {$hotel} aproxima-se!")
            ->greeting("Olá {$notifiable->name},")
            ->line("Faltam apenas **{$daysUntil} dia(s)** para a sua estadia connosco!")
            ->line("**Reserva:** {$r->reservation_number}")
            ->line("**Check-in:** " . $r->check_in_date->format('d/m/Y') . " (a partir das " . ($settings->default_check_in_time ?? '14:00') . ")")
            ->line('**Documentos necessários:** BI / Passaporte')
            ->line('Em caso de dúvida, contacte-nos: ' . ($settings->hotel_phone ?? '—'))
            ->salutation("Até breve,\n{$hotel}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'reservation_number' => $this->reservation->reservation_number,
        ];
    }
}

<?php

namespace App\Notifications\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\HotelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmed extends Notification
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

        return (new MailMessage)
            ->subject("Reserva Confirmada — {$r->reservation_number}")
            ->greeting("Olá {$notifiable->name},")
            ->line("A sua reserva no **{$hotel}** foi confirmada com sucesso!")
            ->line("**Nº da Reserva:** {$r->reservation_number}")
            ->line("**Código de Confirmação:** {$r->confirmation_code}")
            ->line("**Check-in:** " . $r->check_in_date->format('d/m/Y') . " (a partir das " . ($settings->default_check_in_time ?? '14:00') . ")")
            ->line("**Check-out:** " . $r->check_out_date->format('d/m/Y') . " (até às " . ($settings->default_check_out_time ?? '12:00') . ")")
            ->line("**Tipo de Quarto:** " . ($r->roomType?->name ?? '—'))
            ->line("**Total:** " . number_format($r->total, 2, ',', '.') . ' Kz')
            ->line('Aguardamos a sua chegada!')
            ->salutation("Com os melhores cumprimentos,\n{$hotel}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'reservation_number' => $this->reservation->reservation_number,
            'check_in' => $this->reservation->check_in_date,
            'check_out' => $this->reservation->check_out_date,
        ];
    }
}

<?php

namespace App\Notifications\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\HotelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostStayThankYou extends Notification
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

        $mail = (new MailMessage)
            ->subject("Obrigado pela sua estadia — {$hotel}")
            ->greeting("Olá {$notifiable->name},")
            ->line("Muito obrigado por escolher o **{$hotel}**. Esperamos que tenha tido uma estadia memorável!");

        // Loyalty info
        if ($r->guest && ($settings->loyalty_enabled ?? true)) {
            $mail->line("🌟 **Programa de Fidelidade:**");
            $mail->line("Pontos acumulados: **{$r->guest->loyalty_points}** · Nível: **" . ucfirst($r->guest->loyalty_tier ?? 'bronze') . "**");
        }

        return $mail
            ->line('Adoraríamos ouvir a sua opinião! Avalie-nos online.')
            ->line('Esperamos vê-lo em breve novamente.')
            ->salutation("Até à próxima,\n{$hotel}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'reservation_number' => $this->reservation->reservation_number,
        ];
    }
}

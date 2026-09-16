<?php

namespace App\Notifications\Revenda;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** O email para escolher uma senha nova no portal do revendedor. */
class NovaSenhaDoRevendedor extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('revendedor.nova-senha', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutos = (int) config('auth.passwords.revendedores.expire', 60);

        return (new MailMessage())
            ->subject(__('Nova senha do portal do revendedor') . ' — ' . app_name())
            ->greeting(__('Olá, :nome', ['nome' => $notifiable->name]))
            ->line(__('Recebemos um pedido para mudar a senha do seu portal de revendedor.'))
            ->action(__('Escolher nova senha'), $url)
            ->line(__('O link vale :n minutos. Se não foi você que pediu, pode ignorar este email.', ['n' => $minutos]));
    }
}

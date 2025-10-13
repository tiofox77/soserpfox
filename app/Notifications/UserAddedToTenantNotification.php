<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Tenant;
use App\Models\User;

class UserAddedToTenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tenant;
    public $addedBy;
    public $roleName;

    /**
     * Create a new notification instance.
     */
    public function __construct(Tenant $tenant, User $addedBy, $roleName = null)
    {
        $this->tenant = $tenant;
        $this->addedBy = $addedBy;
        $this->roleName = $roleName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('🏢 Você foi adicionado a uma nova empresa')
                    ->greeting('Olá, ' . $notifiable->name . '!')
                    ->line('Você foi adicionado à empresa **' . $this->tenant->name . '**.')
                    ->line('**Adicionado por:** ' . $this->addedBy->name)
                    ->when($this->roleName, function($mail) {
                        return $mail->line('**Seu perfil:** ' . $this->roleName);
                    })
                    ->line('Agora você tem acesso a esta empresa e pode alternar entre suas empresas no sistema.')
                    ->action('Acessar Empresa', url('/'))
                    ->line('Para alternar entre empresas, use o seletor no topo da página.')
                    ->line('Bem-vindo à equipe!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'added_by' => $this->addedBy->name,
            'role_name' => $this->roleName,
        ];
    }
}

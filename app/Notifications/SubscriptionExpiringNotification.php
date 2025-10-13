<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public $subscription;
    public $daysRemaining;

    public function __construct($subscription, $daysRemaining)
    {
        $this->subscription = $subscription;
        $this->daysRemaining = $daysRemaining;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'subscription_expiring',
            'title' => 'Plano Expirando',
            'message' => "Seu plano '{$this->subscription->plan->name}' expira em {$this->daysRemaining} dia(s). Renove agora!",
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'days_remaining' => $this->daysRemaining,
            'expires_at' => $this->subscription->current_period_end->format('d/m/Y'),
            'icon' => 'fa-crown',
            'color' => 'red',
            'url' => route('my-account') . '?tab=plan',
        ];
    }
}

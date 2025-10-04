<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;

class SubscriptionTimer extends Component
{
    #[Computed]
    public function subscriptionData()
    {
        $user = auth()->user();
        
        // Super Admin não precisa ver timer
        if (!$user || $user->is_super_admin) {
            return null;
        }
        
        $tenant = $user->activeTenant();
        
        if (!$tenant) {
            return null;
        }
        
        $subscription = $tenant->activeSubscription;
        
        if (!$subscription || !$subscription->ends_at) {
            return null;
        }
        
        $now = now();
        $endsAt = $subscription->ends_at;
        
        // Se já expirou
        if ($endsAt->isPast()) {
            return [
                'expired' => true,
                'days' => 0,
                'status' => 'expired',
                'color' => 'red',
                'message' => 'Expirado',
                'plan_name' => $subscription->plan->name ?? 'N/A',
            ];
        }
        
        $daysRemaining = abs((int) $endsAt->diffInDays($now));
        $hoursRemaining = abs((int) ($endsAt->diffInHours($now) % 24));
        
        // Limitar a 999 dias máximo para exibição
        if ($daysRemaining > 999) {
            $daysRemaining = 999;
        }
        
        // Determinar cor e status baseado nos dias restantes
        if ($daysRemaining <= 3) {
            $color = 'red';
            $status = 'critical';
        } elseif ($daysRemaining <= 7) {
            $color = 'orange';
            $status = 'warning';
        } elseif ($daysRemaining <= 15) {
            $color = 'yellow';
            $status = 'attention';
        } else {
            $color = 'green';
            $status = 'good';
        }
        
        return [
            'expired' => false,
            'days' => $daysRemaining,
            'hours' => $hoursRemaining,
            'status' => $status,
            'color' => $color,
            'ends_at' => $endsAt->format('d/m/Y H:i'),
            'plan_name' => $subscription->plan->name ?? 'N/A',
            'billing_cycle' => $subscription->billing_cycle,
        ];
    }
    
    public function render()
    {
        return view('livewire.subscription-timer');
    }
}

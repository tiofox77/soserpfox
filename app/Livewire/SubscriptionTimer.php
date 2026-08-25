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
        
        if (!$subscription) {
            return null;
        }
        
        $now = now();
        $endsAt = null;
        $subscriptionType = 'plan'; // 'trial' ou 'plan'
        
        // Determinar qual data usar baseado no status
        if ($subscription->status === 'trial' && $subscription->trial_ends_at) {
            // TRIAL: usar trial_ends_at
            $endsAt = $subscription->trial_ends_at;
            $subscriptionType = 'trial';
        } elseif ($subscription->status === 'active' && $subscription->current_period_end) {
            // ATIVO: usar current_period_end
            $endsAt = $subscription->current_period_end;
            $subscriptionType = 'plan';
        } elseif ($subscription->status === 'pending' && $subscription->current_period_end) {
            // PENDENTE: usar current_period_end
            $endsAt = $subscription->current_period_end;
            $subscriptionType = 'plan';
        } elseif ($subscription->ends_at) {
            // CANCELADO ou outro: usar ends_at
            $endsAt = $subscription->ends_at;
            $subscriptionType = 'plan';
        }
        
        // BUILD OFFLINE: quem manda no prazo é a LICENÇA, não a subscrição.
        //
        // A subscrição local é longa de propósito (o assistente cria-a com
        // anos) só para satisfazer o CheckSubscription — o prazo verdadeiro
        // vem da licença e renova-se por check-in. Sem isto, o contador do
        // topo mostrava os anos da subscrição local: uma licença de 1 dia
        // aparecia como "999d 23h" (999 é o tecto de exibição), que é o
        // oposto do que o cliente precisa de ver.
        if (function_exists('licenca_enforce') && licenca_enforce()) {
            $expiraLicenca = licenca_estado()->payload?->expiraEm();

            if ($expiraLicenca) {
                $endsAt = \Carbon\Carbon::instance($expiraLicenca->toDateTime());
                $subscriptionType = 'licenca';
            }
        }

        // Se não há data definida
        if (!$endsAt) {
            return null;
        }
        
        // Se já expirou
        if ($endsAt->isPast()) {
            return [
                'expired' => true,
                'days' => 0,
                'status' => 'expired',
                'color' => 'red',
                'message' => $subscriptionType === 'trial' ? 'Trial Expirado' : 'Expirado',
                'plan_name' => $subscription->plan->name ?? 'N/A',
                'subscription_type' => $subscriptionType,
            ];
        }
        
        $daysRemaining = abs((int) $endsAt->diffInDays($now));
        $hoursRemaining = abs((int) ($endsAt->diffInHours($now) % 24));
        $minutesRemaining = abs((int) ($endsAt->diffInMinutes($now) % 60));

        // Texto curto para o crachá do topo. Faltando menos de um dia, dizer
        // "0d 5h" esconde a urgência — mostra-se as horas, e no último dia
        // até os minutos.
        if ($daysRemaining >= 1) {
            $resumo = $daysRemaining . 'd ' . $hoursRemaining . 'h';
        } elseif ($hoursRemaining >= 1) {
            $resumo = $hoursRemaining . 'h ' . $minutesRemaining . 'm';
        } else {
            $resumo = $minutesRemaining . 'm';
        }

        // Limitar a 999 dias máximo para exibição
        if ($daysRemaining > 999) {
            $daysRemaining = 999;
            $resumo = '999d+';
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
            'minutes' => $minutesRemaining,
            'resumo' => $resumo,
            'status' => $status,
            'color' => $color,
            'ends_at' => $endsAt->format('d/m/Y H:i'),
            'plan_name' => $subscription->plan->name ?? 'N/A',
            'billing_cycle' => $subscription->billing_cycle,
            'subscription_type' => $subscriptionType, // 'trial' ou 'plan'
            'subscription_status' => $subscription->status, // trial, active, pending, cancelled
        ];
    }
    
    public function render()
    {
        return view('livewire.subscription-timer');
    }
}

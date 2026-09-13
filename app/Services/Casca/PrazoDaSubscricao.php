<?php

namespace App\Services\Casca;

use App\Models\User;
use Carbon\Carbon;

/**
 * QUANTO FALTA PARA A SUBSCRIÇÃO (OU A LICENÇA) ACABAR — o contador do topo.
 *
 * Era a computed do componente SubscriptionTimer. Passou a serviço porque há
 * duas perguntas iguais no sistema — o contador e a notificação «lembre-se de
 * renovar» — e respondiam de maneira diferente: o contador lia
 * `current_period_end` numa subscrição activa, a notificação lia `ends_at`,
 * que numa activa costuma estar vazio. O contador dizia «faltam 3 dias» e o
 * sino ficava calado. Agora respondem os dois daqui.
 */
class PrazoDaSubscricao
{
    /** @return array<string, mixed>|null */
    public function para(?User $user): ?array
    {
        // O dono da plataforma não é subscritor de nada.
        if (! $user || $user->is_super_admin) {
            return null;
        }

        $tenant = $user->activeTenant();
        $subscription = $tenant?->activeSubscription;

        if (! $subscription) {
            return null;
        }

        [$fim, $tipo] = $this->fim($subscription);

        if (! $fim) {
            return null;
        }

        $plano = $subscription->plan->name ?? 'N/A';

        if ($fim->isPast()) {
            return [
                'expirado' => true,
                'dias' => 0,
                'horas' => 0,
                'minutos' => 0,
                'resumo' => '0d',
                'estado' => 'expired',
                'cor' => 'red',
                'termina_em' => $fim->toIso8601String(),
                'plano' => $plano,
                'ciclo' => $subscription->billing_cycle,
                'tipo' => $tipo,
            ];
        }

        $agora = now();
        $dias = abs((int) $fim->diffInDays($agora));
        $horas = abs((int) ($fim->diffInHours($agora) % 24));
        $minutos = abs((int) ($fim->diffInMinutes($agora) % 60));

        // Faltando menos de um dia, «0d 5h» esconde a urgência: mostra-se as
        // horas, e no último dia até os minutos.
        $resumo = $dias >= 1 ? "{$dias}d {$horas}h" : ($horas >= 1 ? "{$horas}h {$minutos}m" : "{$minutos}m");

        if ($dias > 999) {
            $dias = 999;
            $resumo = '999d+';
        }

        [$cor, $estado] = match (true) {
            $dias <= 3 => ['red', 'critical'],
            $dias <= 7 => ['orange', 'warning'],
            $dias <= 15 => ['yellow', 'attention'],
            default => ['green', 'good'],
        };

        return [
            'expirado' => false,
            'dias' => $dias,
            'horas' => $horas,
            'minutos' => $minutos,
            'resumo' => $resumo,
            'estado' => $estado,
            'cor' => $cor,
            'termina_em' => $fim->toIso8601String(),
            'plano' => $plano,
            'ciclo' => $subscription->billing_cycle,
            'tipo' => $tipo,
            'estado_da_subscricao' => $subscription->status,
        ];
    }

    /** @return array{0: ?Carbon, 1: string} */
    private function fim($subscription): array
    {
        $fim = null;
        $tipo = 'plan';

        if ($subscription->status === 'trial' && $subscription->trial_ends_at) {
            $fim = $subscription->trial_ends_at;
            $tipo = 'trial';
        } elseif (in_array($subscription->status, ['active', 'pending'], true) && $subscription->current_period_end) {
            $fim = $subscription->current_period_end;
        } elseif ($subscription->ends_at) {
            $fim = $subscription->ends_at;
        }

        // INSTALAÇÃO OFFLINE: quem manda no prazo é a LICENÇA. A subscrição
        // local é longa de propósito (só para o CheckSubscription deixar
        // passar) e uma licença de um dia aparecia como «999d+».
        if (function_exists('licenca_enforce') && licenca_enforce()) {
            $expira = licenca_estado()->payload?->expiraEm();

            if ($expira) {
                // Em hora de Angola: a licença guarda em UTC, e a data mostrada
                // ao cliente ficava uma hora atrás da que o fornecedor vê.
                $fim = Carbon::instance($expira->toDateTime())->setTimezone(config('app.timezone', 'UTC'));
                $tipo = 'licenca';
            }
        }

        return [$fim ? Carbon::instance($fim) : null, $tipo];
    }
}

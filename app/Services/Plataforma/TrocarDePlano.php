<?php

namespace App\Services\Plataforma;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Trocar o plano de uma empresa: o antigo morre, o novo nasce limpo.
 *
 * O ecrã de administração reaproveitava a subscrição existente e só lhe
 * trocava o plano. Ficava lá tudo do plano anterior — o fim do período de
 * teste, as datas, o valor — e a empresa acabava com um plano novo a correr
 * com as contas do velho. Era isso que se via na modal: "herdou outras
 * informações".
 *
 * A regra é a de quem gere a plataforma:
 *
 *   1. Tudo o que estava vivo é CANCELADO, com data de fim hoje.
 *   2. Nasce uma subscrição nova, com dias novos a contar de hoje.
 *   3. Primeiro plano da empresa e o plano tem teste → começa em TESTE.
 *   4. Já usou algum plano antes → entra ACTIVO, sem teste.
 *
 * A quarta é a que evita o furo: sem ela, bastava trocar de plano para
 * ganhar outro período de teste, e outro, e outro.
 */
class TrocarDePlano
{
    /**
     * @param  string  $ciclo  monthly|quarterly|semiannual|yearly
     */
    public function aplicar(Tenant $empresa, Plan $plano, string $ciclo = 'monthly'): Subscription
    {
        return DB::transaction(function () use ($empresa, $plano, $ciclo) {
            // Já teve algum plano? Conta o histórico TODO, não só o que está
            // vivo — quem cancelou e voltou não recomeça com um teste novo.
            $jaTevePlano = $empresa->subscriptions()->exists();

            $this->cancelarTudo($empresa);

            $inicio = now();
            $fim = $this->fimDoPeriodo($inicio, $ciclo);
            $diasDeTeste = (int) ($plano->trial_days ?? 0);

            // Só a PRIMEIRA vez. E só se o plano tiver teste configurado.
            $emTeste = !$jaTevePlano && $diasDeTeste > 0;
            $fimDoTeste = $emTeste ? $inicio->copy()->addDays($diasDeTeste) : null;

            $nova = $empresa->subscriptions()->create([
                'plan_id'              => $plano->id,
                'billing_cycle'        => $ciclo,
                'amount'               => $plano->getPrice($ciclo),
                'status'               => $emTeste ? 'trial' : 'active',
                'trial_ends_at'        => $fimDoTeste,
                'current_period_start' => $inicio,
                'current_period_end'   => $emTeste ? $fimDoTeste : $fim,
                'ends_at'              => $emTeste ? $fimDoTeste : $fim,
            ]);

            // Os limites da ficha acompanham o plano, senão a empresa fica com
            // o plano novo e os tectos do antigo.
            $empresa->update([
                'max_users'      => $plano->max_users,
                'max_storage_mb' => $plano->max_storage_mb,
            ]);

            return $nova;
        });
    }

    /**
     * Tudo o que não esteja já morto sai, com o contador a zero.
     *
     * Zera-se o fim do teste de propósito: uma subscrição cancelada que
     * guarde uma data de teste no futuro continua a aparecer nos relatórios
     * como se estivesse em período de teste.
     */
    private function cancelarTudo(Tenant $empresa): int
    {
        return $empresa->subscriptions()
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->update([
                'status'             => 'cancelled',
                'ends_at'            => now(),
                'current_period_end' => now(),
                'trial_ends_at'      => null,
            ]);
    }

    private function fimDoPeriodo(\Carbon\Carbon $inicio, string $ciclo): \Carbon\Carbon
    {
        return match ($ciclo) {
            // Anual leva dois meses de oferta, como no resto do sistema.
            'yearly'     => $inicio->copy()->addMonths(14),
            'semiannual' => $inicio->copy()->addMonths(6),
            'quarterly'  => $inicio->copy()->addMonths(3),
            default      => $inicio->copy()->addMonth(),
        };
    }
}

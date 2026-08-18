<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Em que pé está a subscrição de uma empresa, em linguagem de quem decide.
 *
 * A lista de empresas dizia o plano e mais nada. "Pacote Vendas · Monthly"
 * não responde ao que interessa a quem gere a plataforma: isto está em
 * período de teste? falta quanto? já passou do prazo e continua a usar?
 *
 * Sem isto, um teste que expira passa despercebido até alguém reparar por
 * acaso — e uma empresa a usar de graça há dois meses é receita perdida que
 * ninguém contabilizou.
 */
class EstadoDaSubscricao
{
    /**
     * @return array{rotulo:string, cor:string, dias:?int, icone:string, detalhe:string}
     */
    public static function para(Tenant $empresa): array
    {
        $s = $empresa->activeSubscription;

        if (!$s) {
            return [
                'rotulo'  => 'Sem subscrição',
                'cor'     => 'gray',
                'dias'    => null,
                'icone'   => 'fa-circle-minus',
                'detalhe' => 'Nunca teve plano activo.',
            ];
        }

        $agora = now();

        // O teste manda enquanto durar: uma subscrição pode estar 'active' e
        // ainda dentro do período de teste, e nesse caso ainda não pagou nada.
        if ($s->trial_ends_at) {
            $dias = (int) ceil($agora->floatDiffInDays($s->trial_ends_at, false));

            if ($s->trial_ends_at->isFuture()) {
                return [
                    'rotulo'  => 'Em teste',
                    'cor'     => $dias <= 3 ? 'amber' : 'blue',
                    'dias'    => $dias,
                    'icone'   => 'fa-hourglass-half',
                    'detalhe' => 'Teste acaba em ' . $s->trial_ends_at->format('d/m/Y') . '.',
                ];
            }

            if ($s->status === 'trial') {
                // Ficou em 'trial' com a data já passada: está a usar sem pagar.
                return [
                    'rotulo'  => 'Teste expirado',
                    'cor'     => 'red',
                    'dias'    => $dias,
                    'icone'   => 'fa-triangle-exclamation',
                    'detalhe' => 'Acabou em ' . $s->trial_ends_at->format('d/m/Y') . ' e continua activa.',
                ];
            }
        }

        if ($s->ends_at) {
            $dias = (int) ceil($agora->floatDiffInDays($s->ends_at, false));

            if ($s->ends_at->isPast()) {
                return [
                    'rotulo'  => 'Expirado',
                    'cor'     => 'red',
                    'dias'    => $dias,
                    'icone'   => 'fa-circle-xmark',
                    'detalhe' => 'Terminou em ' . $s->ends_at->format('d/m/Y') . '.',
                ];
            }

            return [
                'rotulo'  => 'Activo',
                'cor'     => $dias <= 7 ? 'amber' : 'emerald',
                'dias'    => $dias,
                'icone'   => 'fa-circle-check',
                'detalhe' => 'Renova em ' . $s->ends_at->format('d/m/Y') . '.',
            ];
        }

        return [
            'rotulo'  => 'Activo',
            'cor'     => 'emerald',
            'dias'    => null,
            'icone'   => 'fa-circle-check',
            'detalhe' => 'Sem data de fim.',
        ];
    }
}

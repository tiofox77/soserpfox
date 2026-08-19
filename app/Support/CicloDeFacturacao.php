<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Quanto dura um ciclo de facturação, e como se chama.
 *
 * Isto estava copiado em CINCO sítios — OrderObserver, Order, Billing,
 * TrocarDePlano e Subscription::renew — todos com o mesmo `match`. Estavam
 * de acordo, mas bastava uma mudança de política (deixar de oferecer os
 * dois meses do anual, por exemplo) para ficarem a discordar em silêncio,
 * e o cliente receberia um período diferente conforme a porta por onde
 * entrasse: registo, aprovação de pedido, ou o painel.
 *
 * O ANUAL DÁ 14 MESES: doze pagos mais dois de oferta. Não é engano — é
 * política comercial, e é a razão pela qual não se pode escrever 12 aqui.
 */
class CicloDeFacturacao
{
    /** Os ciclos que o sistema aceita. */
    public const CICLOS = ['monthly', 'quarterly', 'semiannual', 'yearly'];

    /** Meses de período que cada ciclo dá. */
    public static function meses(?string $ciclo): int
    {
        return match ($ciclo) {
            'yearly'     => 14,   // 12 pagos + 2 de oferta
            'semiannual' => 6,
            'quarterly'  => 3,
            default      => 1,
        };
    }

    /** O fim do período, a partir de um início. */
    public static function fim(Carbon $inicio, ?string $ciclo): Carbon
    {
        return $inicio->copy()->addMonths(self::meses($ciclo));
    }

    /** O nome do ciclo, para quem o lê. */
    public static function nome(?string $ciclo): string
    {
        return match ($ciclo) {
            'yearly'     => 'Anual',
            'semiannual' => 'Semestral',
            'quarterly'  => 'Trimestral',
            default      => 'Mensal',
        };
    }

    /** Um ciclo que o sistema conheça — qualquer outra coisa é mensal. */
    public static function normalizar(?string $ciclo): string
    {
        return in_array($ciclo, self::CICLOS, true) ? $ciclo : 'monthly';
    }
}

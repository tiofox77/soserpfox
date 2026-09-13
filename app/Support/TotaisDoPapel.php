<?php

namespace App\Support;

/**
 * OS TOTAIS QUE O PAPEL MOSTRA, quando há retenção na fonte.
 *
 * Todos os sítios que gravam documentos guardam o `total` JÁ SEM a retenção —
 * o emissor de facturas, o de compras, o POS, os módulos, o PWA e as propostas
 * (ver InvoiceCalculationHelper, PASSO 8). É o valor que o cliente paga.
 *
 * Os modelos de papel faziam «Total a Pagar = total − retenção» e voltavam a
 * descontá-la: uma factura de serviço de 2.000 Kz + 280 de IVA com 130 de IRT
 * dizia «Total a Pagar 2.020,00» (e o extenso atrás) quando vale 2.150,00; e
 * o «Total da Fatura» mostrava 2.150,00, que já é depois de reter. As contas
 * ficam aqui, uma vez, e os nove modelos lêem daqui.
 */
final class TotaisDoPapel
{
    /** O total do documento ANTES da retenção — o «Total da Fatura / da Proforma». */
    public static function doDocumento(object $documento): float
    {
        return round((float) ($documento->total ?? 0) + self::retencao($documento), 2);
    }

    /** O que se paga: o total gravado, que já leva a retenção descontada. */
    public static function aPagar(object $documento): float
    {
        return round((float) ($documento->total ?? 0), 2);
    }

    public static function retencao(object $documento): float
    {
        return round((float) ($documento->irt_amount ?? 0), 2);
    }
}

<?php

namespace App\Services\POS;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\PosShiftTransaction;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * OS DOCUMENTOS DO ECRÃ «DOCUMENTOS» NO FECHO DE TURNO (26/09/2026).
 *
 * Quem está ao balcão com o turno aberto também emite pelo ecrã dos
 * Documentos: a factura-recibo em A4 que o cliente pede, a factura a prazo, a
 * nota de crédito ou de débito, o adiantamento. O fecho só via o POS. A FR A4
 * punha o dinheiro na caixa do operador e o turno não sabia dele, e o fecho
 * acusava uma sobra que não existia.
 *
 * É uma OPÇÃO DA EMPRESA (`turno_inclui_documentos`, ligada por omissão):
 *
 *  · LIGADA — o que o operador emite com o turno aberto sai no fecho dele:
 *      - a FR A4 conta como venda, pela forma de pagamento (entra na gaveta
 *        quando é numerário);
 *      - a FT, a ND e a NC de uma factura por pagar entram como A PRAZO:
 *        aparecem na lista e nos artigos, mas não mexem na gaveta;
 *      - a NC que devolve dinheiro e o adiantamento entram como sempre
 *        entraram (devolução e recebimento).
 *  · DESLIGADA — nenhum destes documentos vai ao turno. O dinheiro continua a
 *    entrar na tesouraria, e o fecho de caixa só conta o que passou pelo POS.
 *
 * Os recibos ficam FORA da opção: entram sempre, porque são o pagamento do
 * que se vendeu a prazo e a gaveta não bate sem eles.
 *
 * TUDO AQUI É BEST-EFFORT e corre depois de o documento existir: uma falha a
 * escrever no turno nunca desfaz um documento fiscal já numerado e assinado.
 */
final class DocumentosNoTurno
{
    /** A opção da empresa. Nunca configurada é LIGADA. */
    public static function ligado(int $tenantId): bool
    {
        try {
            return (bool) (InvoicingSettings::forTenant($tenantId)->turno_inclui_documentos ?? true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * A FACTURA-RECIBO A4: dinheiro que entrou, na forma em que entrou.
     *
     * É um movimento `invoice` como o de uma venda do POS: conta no balde da
     * forma de pagamento e, em numerário, no dinheiro esperado.
     */
    public static function facturaRecibo(SalesInvoice $factura, string $forma, ?int $userId): ?PosShiftTransaction
    {
        $numero = (string) $factura->invoice_number;

        return self::registar($factura, $userId, [
            'type' => 'invoice',
            'reference_number' => $numero,
            'payment_method' => $forma ?: 'cash',
            'amount' => round((float) $factura->total, 2),
            'description' => __('Fatura-Recibo :n (Documentos)', ['n' => $numero]),
            'metadata' => ['origem' => 'documentos', 'documento' => 'FR'],
        ]);
    }

    /**
     * UM DOCUMENTO A PRAZO: sai no fecho, não mexe na gaveta.
     *
     * @param  string  $sigla  FT, ND ou NC — o que o fecho escreve ao lado
     * @param  float  $valor  com sinal: a NC entra negativa
     */
    public static function aPrazo(Model $documento, string $sigla, string $numero, float $valor, ?int $userId): ?PosShiftTransaction
    {
        if (round($valor, 2) == 0.0) {
            return null;
        }

        return self::registar($documento, $userId, [
            'type' => 'a_prazo',
            'reference_number' => $numero,
            'payment_method' => 'a_prazo',
            'amount' => round($valor, 2),
            'description' => __(':sigla :n a prazo (Documentos)', ['sigla' => $sigla, 'n' => $numero]),
            'metadata' => ['origem' => 'documentos', 'documento' => $sigla],
        ]);
    }

    /**
     * No turno ABERTO de quem emitiu, uma vez só por documento, e só com a
     * opção ligada.
     */
    private static function registar(Model $documento, ?int $userId, array $movimento): ?PosShiftTransaction
    {
        $tenantId = (int) $documento->tenant_id;

        if (! self::ligado($tenantId)) {
            return null;
        }

        try {
            $turno = PosShift::abertoDe($tenantId, $userId);

            if (! $turno) {
                return null;
            }

            $jaLa = PosShiftTransaction::withoutGlobalScopes()
                ->where('shift_id', $turno->id)
                ->where('reference_type', $documento::class)
                ->where('reference_id', $documento->getKey())
                ->exists();

            if ($jaLa) {
                return null;
            }

            return $turno->addTransaction($movimento + [
                'reference_type' => $documento::class,
                'reference_id' => $documento->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('DocumentosNoTurno: falha ao registar no turno', [
                'documento' => $documento::class . '#' . $documento->getKey(),
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Receipt;
use App\Models\Treasury\Transaction;

/**
 * O QUE UM RECIBO MOVE.
 *
 * O recibo já sabia lançar-se na FACTURA (os ganchos do `Receipt` mexem no
 * `paid_amount`). O que faltava era o resto do caminho do dinheiro:
 *
 *  · a TESOURARIA — e só o modal de pagamento o fazia. Um recibo emitido pelo
 *    ecrã de Recibos criava-se, baixava a dívida da factura, e o dinheiro não
 *    aparecia em lado nenhum da tesouraria.
 *
 *  · o TURNO do balcão — que ninguém fazia. O `PosShift` até conta os
 *    movimentos `receipt`, mas nunca havia nenhum: o «esperado em caixa» do
 *    fecho ignorava tudo o que entrasse por recibo.
 *
 * As regras — uma vez só, a data do documento, o destino pela forma de
 * pagamento — vivem no `LancamentoDeDinheiro`, que é a mesma porta que o
 * adiantamento, o sinal do hotel e a nota de crédito usam. Aqui fica só o que
 * é próprio do recibo.
 */
class LancamentoDoRecibo
{
    public function __construct(private readonly LancamentoDeDinheiro $dinheiro) {}

    /**
     * @param  array{account_id?: int|null, cash_register_id?: int|null}  $destinoEscolhido
     *         Conta/caixa escolhidas à mão (o modal deixa escolher); vazio = decide o serviço.
     * @param  bool  $comTurno  A reposição de recibos antigos (`contas:verificar
     *         --aplicar`) põe isto a falso: o dinheiro é de outro dia e de
     *         outra pessoa, e metê-lo no turno de quem está agora ao balcão
     *         fazia essa pessoa responder por uma falta que não é dela.
     */
    public function lancar(Receipt $recibo, array $destinoEscolhido = [], ?int $userId = null, bool $comTurno = true): ?Transaction
    {
        $venda = $recibo->type === 'sale';
        $numero = $recibo->receipt_number ?: ('#' . $recibo->id);
        $factura = $recibo->invoice_id ?: $recibo->purchase_invoice_id;

        $movimento = $this->dinheiro->lancar($recibo, [
            'valor' => (float) $recibo->amount_paid,
            'forma' => (string) $recibo->payment_method,
            'sentido' => $venda ? 'income' : 'expense',
            'categoria' => $venda ? 'customer_payment' : 'supplier_payment',
            'data' => $recibo->payment_date,
            'invoice_id' => $venda ? $recibo->invoice_id : null,
            'purchase_id' => $venda ? null : $recibo->purchase_invoice_id,
            'referencia' => $recibo->reference ?: ('Recibo ' . $numero),
            'descricao' => ($venda ? 'Recebimento' : 'Pagamento') . ' — recibo ' . $numero
                . ($factura ? ' (factura #' . $factura . ')' : ''),
            'notas' => $recibo->notes,
            'destino' => $destinoEscolhido,
            // O TURNO leva o que entra ao balcão como recibo. Um pagamento de
            // compra NÃO é uma venda — mas, pago em numerário, sai de uma gaveta
            // (a de quem paga, pela regra da caixa do operador), e vai ao turno
            // dessa gaveta como SAÍDA, logo abaixo (23/09/2026).
            'turno' => $venda && $comTurno ? [
                'type' => 'receipt',
                'reference_number' => $recibo->receipt_number,
                'description' => __('Recibo :n', ['n' => $numero]),
                'metadata' => ['invoice_id' => $recibo->invoice_id],
            ] : null,
        ], $userId);

        if (! $venda && $comTurno && $movimento) {
            \App\Services\POS\GavetaDoTurno::registar($movimento);
        }

        return $movimento;
    }

    /** O estorno de um recibo apagado — ver `LancamentoDeDinheiro::estornar`. */
    public function estornar(Receipt $recibo): int
    {
        return $this->dinheiro->estornar($recibo);
    }
}

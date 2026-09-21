<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\Receipt;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Treasury\TreasuryMovementService;
use Illuminate\Support\Facades\Log;

/**
 * O QUE UM RECIBO MOVE — numa porta só.
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
 * LANÇA UMA VEZ SÓ. A ligação fica em `related_type`/`related_id` do movimento
 * de tesouraria, e é por ela que se sabe que este recibo já foi lançado — dois
 * caminhos a chamar isto não duplicam dinheiro.
 *
 * A DATA é a do recibo, não a de agora: um recibo com data de ontem tem de
 * cair em ontem na tesouraria, senão os mapas não cruzam.
 */
class LancamentoDoRecibo
{
    /**
     * @param  array{account_id?: int|null, cash_register_id?: int|null}  $destinoEscolhido
     *         Conta/caixa escolhidas à mão (o modal deixa escolher); vazio = decide o serviço.
     */
    public function lancar(Receipt $recibo, array $destinoEscolhido = [], ?int $userId = null): ?Transaction
    {
        $tenantId = (int) $recibo->tenant_id;
        $valor = round((float) $recibo->amount_paid, 2);

        if ($valor <= 0) {
            return null;
        }

        if ($this->jaLancado($recibo, $tenantId)) {
            return null;
        }

        $venda = $recibo->type === 'sale';
        $metodo = $this->metodoDeTesouraria((string) $recibo->payment_method, $tenantId);
        $tesouraria = app(TreasuryMovementService::class);

        $destino = $tesouraria->destination(
            $metodo,
            $tenantId,
            ! empty($destinoEscolhido['account_id']) ? (int) $destinoEscolhido['account_id'] : null,
            ! empty($destinoEscolhido['cash_register_id']) ? (int) $destinoEscolhido['cash_register_id'] : null,
            $userId,
        );

        $numero = $recibo->receipt_number ?: ('#' . $recibo->id);
        $factura = $recibo->invoice_id ?: $recibo->purchase_invoice_id;

        $movimento = $tesouraria->post([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $venda ? 'income' : 'expense',
            'category' => $venda ? 'customer_payment' : 'supplier_payment',
            'amount' => $valor,
            'currency' => 'AOA',
            'transaction_date' => $recibo->payment_date ?: now(),
            'payment_method_id' => $metodo->id,
            'account_id' => $destino['account_id'],
            'cash_register_id' => $destino['cash_register_id'],
            'invoice_id' => $venda ? $recibo->invoice_id : null,
            'purchase_id' => $venda ? null : $recibo->purchase_invoice_id,
            // A ligação ao recibo: é o que impede lançar duas vezes.
            'related_type' => Receipt::class,
            'related_id' => $recibo->id,
            'reference' => $recibo->reference ?: ('Recibo ' . $numero),
            'description' => ($venda ? 'Recebimento' : 'Pagamento') . ' — recibo ' . $numero
                . ($factura ? ' (factura #' . $factura . ')' : ''),
            'notes' => $recibo->notes,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);

        // O TURNO só leva o que entra ao balcão: um recibo de compra é dinheiro
        // que sai da empresa, não da gaveta de quem está a vender.
        if ($venda) {
            $this->lancarNoTurno($recibo, $tenantId, $userId, $valor, $numero);
        }

        return $movimento;
    }

    /** Um recibo lança-se uma vez — a marca está no movimento de tesouraria. */
    private function jaLancado(Receipt $recibo, int $tenantId): bool
    {
        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('related_type', Receipt::class)
            ->where('related_id', $recibo->id)
            ->exists();
    }

    /**
     * O movimento no turno aberto de quem recebeu. É o que faz o recibo contar
     * no fecho de caixa. Falhar aqui nunca desfaz o recibo nem a tesouraria.
     */
    private function lancarNoTurno(Receipt $recibo, int $tenantId, ?int $userId, float $valor, string $numero): void
    {
        $turno = PosShift::abertoDe($tenantId, $userId);

        if (! $turno) {
            return;
        }

        try {
            $turno->addTransaction([
                'type' => 'receipt',
                'reference_type' => Receipt::class,
                'reference_id' => $recibo->id,
                'reference_number' => $recibo->receipt_number,
                'payment_method' => (string) $recibo->payment_method,
                'amount' => $valor,
                'description' => __('Recibo :n', ['n' => $numero]),
                'metadata' => ['invoice_id' => $recibo->invoice_id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Recibo: falha ao registar no turno', [
                'recibo' => $recibo->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Procura pelo CÓDIGO (o índice único real): procurar pelo nome rebentava
     * com "Duplicate entry" assim que o cliente renomeasse o método.
     */
    private function metodoDeTesouraria(string $forma, int $tenantId): PaymentMethod
    {
        $mapa = RegistoDePagamento::MAPA_METODOS;
        [$codigo, $nome, $tipo] = $mapa[$forma] ?? $mapa['cash'];

        return PaymentMethod::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('code', $codigo)->orWhere('name', $nome))
            ->first()
            ?? PaymentMethod::create([
                'tenant_id' => $tenantId,
                'code' => $codigo,
                'name' => $nome,
                'type' => $tipo,
                'is_active' => true,
            ]);
    }
}

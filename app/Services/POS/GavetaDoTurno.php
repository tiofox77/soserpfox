<?php

namespace App\Services\POS;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\PosShiftTransaction;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * O DINHEIRO QUE ENTRA E SAI DA GAVETA PELA TESOURARIA (23/09/2026).
 *
 * O turno só via o que passava pelo balcão: vendas, recibos, devoluções. O
 * que o gerente recolhia a meio do dia (Caixa Cleiton → Caixa Gerente), a
 * despesa paga com dinheiro da gaveta, o reforço de troco ou o fornecedor pago
 * em numerário mexiam na caixa da tesouraria e NÃO no turno — e o operador
 * fechava com uma falta, ou uma sobra, que não era dele.
 *
 * Aqui esse movimento entra no turno aberto do DONO DA CAIXA como retirada
 * (`withdrawal`, negativa) ou entrada (`deposit`, positiva), em numerário.
 * Não conta como venda: fica fora do «total vendido» e só mexe no dinheiro
 * esperado. É do dono da caixa e não de quem carregou no botão — o gerente
 * que recolhe da gaveta do Cleiton tira dinheiro do turno do Cleiton.
 *
 * Só para movimentos que NÃO vêm de um documento de venda: esses já entram no
 * turno pela sua porta (LancamentoDeDinheiro, PosSaleService, restaurante), e
 * contá-los aqui era contá-los duas vezes. Quem chama é que decide — ver
 * `eDaGaveta`.
 */
class GavetaDoTurno
{
    /**
     * Um movimento da tesouraria que pode ir ao turno: manual (sem documento),
     * de uma transferência, ou um pagamento de compra. As vendas, os recibos
     * de venda, as notas de crédito e os adiantamentos já lá estão.
     */
    public static function eDaGaveta(Transaction $t): bool
    {
        return $t->related_type === null
            || $t->related_type === \App\Models\Treasury\Transfer::class
            || ($t->related_type === \App\Models\Invoicing\Receipt::class && $t->type === 'expense');
    }

    /** Regista o movimento no turno aberto do dono da caixa. Nunca rebenta. */
    public static function registar(Transaction $t): ?PosShiftTransaction
    {
        try {
            if (($t->status ?? 'completed') !== 'completed' || ! $t->cash_register_id || (float) $t->amount <= 0) {
                return null;
            }

            $turno = self::turnoDaCaixa($t);
            if (! $turno || self::doMovimento($t)->exists()) {
                return null;
            }

            $entrada = $t->type === 'income';

            return $turno->addTransaction([
                'type' => $entrada ? 'deposit' : 'withdrawal',
                'reference_type' => Transaction::class,
                'reference_id' => $t->id,
                'reference_number' => $t->transaction_number,
                'payment_method' => 'cash',
                'amount' => $entrada ? round((float) $t->amount, 2) : -round((float) $t->amount, 2),
                'description' => $t->description ?: ($entrada ? __('Entrada na gaveta') : __('Saída da gaveta')),
                'metadata' => ['treasury_transaction_id' => $t->id, 'category' => $t->category],
            ]);
        } catch (\Throwable $e) {
            // O movimento da tesouraria já está feito: falhar aqui não o desfaz.
            Log::warning('Gaveta do turno: não foi possível registar', ['transaction_id' => $t->id, 'erro' => $e->getMessage()]);

            return null;
        }
    }

    /** Regista e devolve o próprio movimento — para encadear num `post()`. */
    public static function comRegisto(Transaction $t): Transaction
    {
        self::registar($t);

        return $t;
    }

    /**
     * Tira do turno o que este movimento lá pôs — quando é apagado, anulado ou
     * editado. Só num turno ainda ABERTO: um turno fechado é o que foi contado
     * e assinado, e reescrevê-lo mudava o fecho de outra pessoa.
     */
    public static function desfazer(Transaction $t): void
    {
        try {
            foreach (self::doMovimento($t)->get() as $linha) {
                $turno = $linha->shift()->withoutGlobalScopes()->first();
                if (! $turno || $turno->status !== 'open') {
                    continue;
                }

                $linha->delete();
                $turno->recalculateTotals();
            }
        } catch (\Throwable $e) {
            Log::warning('Gaveta do turno: não foi possível desfazer', ['transaction_id' => $t->id, 'erro' => $e->getMessage()]);
        }
    }

    /** O turno aberto de quem tem a caixa — e só se for a caixa desse turno. */
    private static function turnoDaCaixa(Transaction $t): ?PosShift
    {
        $caixa = CashRegister::withoutGlobalScopes()
            ->where('tenant_id', $t->tenant_id)
            ->find($t->cash_register_id);

        if (! $caixa || ! $caixa->user_id) {
            return null;
        }

        $turno = PosShift::abertoDe((int) $t->tenant_id, (int) $caixa->user_id);
        if (! $turno) {
            return null;
        }

        // Quem tem duas caixas abre o turno numa só (a atribuída): o dinheiro
        // da outra não é desta gaveta.
        $atribuida = (new TurnosDoPos((int) $t->tenant_id, (int) $caixa->user_id))->caixaAtribuida();

        return $atribuida && $atribuida->id === $caixa->id ? $turno : null;
    }

    private static function doMovimento(Transaction $t)
    {
        return PosShiftTransaction::withoutGlobalScopes()
            ->where('tenant_id', $t->tenant_id)
            ->where('reference_type', Transaction::class)
            ->where('reference_id', $t->id);
    }
}

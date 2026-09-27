<?php

namespace App\Services\Compras;

use App\Models\Compras\DefinicoesDasCompras;
use App\Models\Compras\Encomenda;
use App\Models\Compras\PedidoDePagamento;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Treasury\Transaction;
use App\Models\User;
use App\Services\Invoicing\LancamentoDoRecibo;
use Illuminate\Support\Facades\DB;

/**
 * DA ENCOMENDA À TESOURARIA: pedir, aprovar, pagar (27/09/2026).
 *
 * O circuito que o cliente pediu — «Encomenda → Solicitação de Pagamento →
 * Pagamento → Recepção → Fatura» — com cada passo em nome de quem o fez:
 *
 *  · QUEM COMPRA PEDE o pagamento, com o valor, o prazo e a forma sugerida.
 *    Pode pedir em parcelas (um sinal e o resto), nunca mais do que se deve.
 *  · A APROVAÇÃO, se a empresa a exige, é de outras pessoas (`Aprovacoes`).
 *  · O TESOUREIRO PAGA — e não mexe na encomenda: escolhe a conta ou a caixa,
 *    a forma e a data. O dinheiro SAI DA TESOURARIA de verdade: o pagamento é
 *    um RECIBO DE COMPRA, lançado pelo `LancamentoDoRecibo`, a mesma porta de
 *    todo o dinheiro das compras (movimento de saída, caixa do operador no
 *    numerário, uma vez só).
 *  · PAGO ANTES DE HAVER FACTURA, é um adiantamento ao fornecedor: o recibo
 *    nasce sem factura e é ligado a ela quando a factura da encomenda é
 *    emitida (`aplicarNaFactura`) — a dívida nasce já paga, e o mesmo
 *    dinheiro não se paga duas vezes.
 */
class FluxoDoPagamento
{
    public function __construct(private readonly Aprovacoes $aprovacoes) {}

    /**
     * @param  array{valor: mixed, data_limite?: string|null, forma?: string|null, notas?: string|null, tesoureiro_id?: int|null}  $dados
     */
    public function pedir(Encomenda $enc, int $tenantId, int $userId, array $dados): PedidoDePagamento
    {
        if ((int) $enc->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Encomenda de outra empresa.');
        }

        if (! in_array($enc->estado, Encomenda::PAGAVEIS, true)) {
            throw new \InvalidArgumentException(match ($enc->estado) {
                'rascunho' => app(Aprovacoes::class)->necessarias($enc) > 0
                    ? 'A encomenda tem de ser aprovada antes de se pedir o pagamento.'
                    : 'Envie primeiro a encomenda ao fornecedor — num rascunho o preço ainda pode mudar.',
                'em_aprovacao' => 'A encomenda ainda está à espera de aprovação.',
                default => 'Esta encomenda está cancelada.',
            });
        }

        $valor = round((float) ($dados['valor'] ?? 0), 2);

        if ($valor <= 0) {
            throw new \InvalidArgumentException('Indique o valor a pagar.');
        }

        return DB::transaction(function () use ($enc, $tenantId, $userId, $dados, $valor) {
            // Trancar a encomenda: dois pedidos ao mesmo tempo não passam juntos do total.
            Encomenda::withoutGlobalScopes()->whereKey($enc->id)->lockForUpdate()->first();

            $falta = round($enc->valorAPagar() - $enc->valorPedido(), 2);

            if ($valor > $falta + 0.005) {
                throw new \InvalidArgumentException($falta > 0
                    ? 'O valor passa do que falta pedir por esta encomenda ('.number_format($falta, 2, ',', '.').' Kz).'
                    : 'Esta encomenda já tem pedido de pagamento pelo valor inteiro.');
            }

            $regras = DefinicoesDasCompras::da($tenantId);
            $tesoureiro = $dados['tesoureiro_id'] ?? $regras->tesoureiro_id;

            return PedidoDePagamento::create([
                'tenant_id' => $tenantId,
                'encomenda_id' => $enc->id,
                'supplier_id' => $enc->supplier_id,
                'valor' => $valor,
                'forma_sugerida' => $dados['forma'] ?? null,
                'data_limite' => $dados['data_limite'] ?? null,
                'notas' => trim((string) ($dados['notas'] ?? '')) ?: null,
                // Sem aprovação exigida, vai direito para a tesouraria.
                'estado' => (int) $regras->aprovacoes_pagamento > 0 ? 'em_aprovacao' : 'por_pagar',
                'ronda' => 1,
                'pedido_por' => $userId,
                'tesoureiro_id' => $tesoureiro ?: null,
            ]);
        });
    }

    public function decidir(PedidoDePagamento $p, int $tenantId, User $user, bool $aprova, ?string $comentario): PedidoDePagamento
    {
        $this->meu($p, $tenantId);

        if ($p->estado !== 'em_aprovacao') {
            throw new \InvalidArgumentException('Este pedido não está à espera de aprovação.');
        }

        return DB::transaction(function () use ($p, $user, $aprova, $comentario) {
            $r = $this->aprovacoes->votar($p, $user, $aprova, $comentario, (int) $p->ronda);

            if ($r['recusada']) {
                $p->update([
                    'estado' => 'recusado', 'motivo_recusa' => trim((string) $comentario),
                    'decidido_por' => $user->id, 'decidido_em' => now(),
                ]);
            } elseif ($r['aprovada']) {
                $p->update(['estado' => 'por_pagar', 'decidido_por' => $user->id, 'decidido_em' => now()]);
            }

            return $p;
        });
    }

    /**
     * RECUSAR NA TESOURARIA — o tesoureiro devolve o pedido (valor errado,
     * sem fundos, falta um documento). Com motivo: quem pediu tem de saber.
     */
    public function recusarNaTesouraria(PedidoDePagamento $p, int $tenantId, User $user, string $motivo): PedidoDePagamento
    {
        $this->meu($p, $tenantId);

        if (! in_array($p->estado, ['em_aprovacao', 'por_pagar'], true)) {
            throw new \InvalidArgumentException('Este pedido já não está por pagar.');
        }

        if (trim($motivo) === '') {
            throw new \InvalidArgumentException('Diga porque está a devolver o pedido — quem pediu tem de saber.');
        }

        $p->update([
            'estado' => 'recusado', 'motivo_recusa' => trim($motivo),
            'decidido_por' => $user->id, 'decidido_em' => now(),
        ]);

        return $p;
    }

    public function cancelar(PedidoDePagamento $p, int $tenantId): PedidoDePagamento
    {
        $this->meu($p, $tenantId);

        if (! in_array($p->estado, ['em_aprovacao', 'por_pagar'], true)) {
            throw new \InvalidArgumentException($p->estado === 'pago'
                ? 'Este pedido já foi pago. Um pagamento feito desfaz-se na tesouraria, com o estorno do recibo.'
                : 'Este pedido já está fechado.');
        }

        $p->update(['estado' => 'cancelado']);

        return $p;
    }

    /**
     * PAGAR — é aqui que o dinheiro sai da tesouraria.
     *
     * @param  array{forma: string, account_id?: int|null, cash_register_id?: int|null, data?: string|null, referencia?: string|null}  $dados
     */
    public function pagar(PedidoDePagamento $p, int $tenantId, User $user, array $dados): PedidoDePagamento
    {
        $this->meu($p, $tenantId);

        return DB::transaction(function () use ($p, $tenantId, $user, $dados) {
            // O pedido trancado: dois cliques (ou dois tesoureiros) não pagam duas vezes.
            $p = PedidoDePagamento::withoutGlobalScopes()->whereKey($p->id)->lockForUpdate()->first();

            if ($p->estado !== 'por_pagar') {
                throw new \InvalidArgumentException(match ($p->estado) {
                    'pago' => 'Este pedido já foi pago.',
                    'em_aprovacao' => 'Este pedido ainda está à espera de aprovação.',
                    default => 'Este pedido já não está por pagar.',
                });
            }

            $enc = Encomenda::withoutGlobalScopes()->with('factura')->findOrFail($p->encomenda_id);

            if ($enc->estado === 'cancelada') {
                throw new \InvalidArgumentException('A encomenda deste pedido foi cancelada.');
            }

            // Já há factura emitida? O pagamento vai logo para ela. Senão, é um
            // adiantamento: o recibo liga-se à factura quando ela for emitida.
            $factura = $enc->factura && ! in_array($enc->factura->status, ['draft', 'cancelled'], true)
                ? $enc->factura : null;

            $data = ($dados['data'] ?? null) ?: now()->toDateString();
            $forma = (string) ($dados['forma'] ?? 'transfer');

            $recibo = Receipt::create([
                'tenant_id' => $tenantId,
                'type' => 'purchase',
                'purchase_invoice_id' => $factura?->id,
                'supplier_id' => $enc->supplier_id,
                'payment_date' => $data,
                'payment_method' => $forma,
                'amount_paid' => (float) $p->valor,
                'reference' => trim((string) ($dados['referencia'] ?? '')) ?: $p->numero,
                'notes' => "Pagamento {$p->numero} da encomenda {$enc->numero}"
                    .($factura ? '' : ' (adiantamento — liga-se à factura quando for emitida)'),
                'status' => 'issued',
                'created_by' => $user->id,
            ]);

            $movimento = app(LancamentoDoRecibo::class)->lancar($recibo, [
                'account_id' => $dados['account_id'] ?? null,
                'cash_register_id' => $dados['cash_register_id'] ?? null,
            ], $user->id);

            $p->update([
                'estado' => 'pago',
                'pago_por' => $user->id,
                'pago_em' => now(),
                'forma_paga' => $forma,
                'referencia' => trim((string) ($dados['referencia'] ?? '')) ?: null,
                'receipt_id' => $recibo->id,
                'transaction_id' => $movimento?->id,
            ]);

            return $p;
        });
    }

    /**
     * OS ADIANTAMENTOS CHEGAM À FACTURA quando ela é emitida.
     *
     * Chamado pelo `PurchaseInvoiceObserver` quando a factura de uma encomenda
     * sai de rascunho. Cada recibo pago antes (sem factura) liga-se a ela: o
     * gancho do `Receipt` lança o valor em `paid_amount`, e o movimento de
     * tesouraria passa a apontar a factura — o extracto do fornecedor fica
     * certo e a dívida não se paga outra vez.
     *
     * @return int quantos recibos foram ligados
     */
    public function aplicarNaFactura(PurchaseInvoice $factura): int
    {
        $enc = Encomenda::withoutGlobalScopes()
            ->where('tenant_id', $factura->tenant_id)
            ->where('purchase_invoice_id', $factura->id)
            ->first();

        if (! $enc) {
            return 0;
        }

        $pedidos = PedidoDePagamento::withoutGlobalScopes()
            ->where('encomenda_id', $enc->id)
            ->where('estado', 'pago')
            ->whereNotNull('receipt_id')
            ->get();

        $ligados = 0;

        foreach ($pedidos as $p) {
            $recibo = Receipt::withoutGlobalScopes()->find($p->receipt_id);

            if (! $recibo || $recibo->purchase_invoice_id || $recibo->status !== 'issued') {
                continue;
            }

            $recibo->update(['purchase_invoice_id' => $factura->id]);

            Transaction::withoutGlobalScopes()
                ->where('tenant_id', $factura->tenant_id)
                ->where('related_type', Receipt::class)
                ->where('related_id', $recibo->id)
                ->update(['purchase_id' => $factura->id]);

            $ligados++;
        }

        return $ligados;
    }

    /**
     * «REGISTAR COMO PAGA» uma factura de compra (27/09/2026).
     *
     * O botão punha a factura em «paga» e mais nada: nem recibo, nem um
     * cêntimo a sair da tesouraria — a dívida desaparecia e o dinheiro nunca
     * saía de lado nenhum. Agora paga o que falta (depois dos adiantamentos
     * que a encomenda já tinha) com um recibo de compra, pela mesma porta.
     *
     * @param  array{forma: string, account_id?: int|null, cash_register_id?: int|null, data?: string|null, referencia?: string|null}  $dados
     */
    public function pagarFactura(PurchaseInvoice $factura, User $user, array $dados): ?Receipt
    {
        $factura = PurchaseInvoice::withoutGlobalScopes()->findOrFail($factura->id);
        $falta = round((float) $factura->total - (float) $factura->paid_amount, 2);

        if ($falta <= 0.005) {
            return null;
        }

        $forma = (string) ($dados['forma'] ?? 'transfer');

        $recibo = Receipt::create([
            'tenant_id' => $factura->tenant_id,
            'type' => 'purchase',
            'purchase_invoice_id' => $factura->id,
            'supplier_id' => $factura->supplier_id,
            'payment_date' => ($dados['data'] ?? null)
                ?: ($factura->invoice_date ? \Carbon\Carbon::parse($factura->invoice_date)->toDateString() : now()->toDateString()),
            'payment_method' => $forma,
            'amount_paid' => $falta,
            'reference' => trim((string) ($dados['referencia'] ?? '')) ?: null,
            'notes' => 'Pago ao registar a factura de compra '.$factura->invoice_number,
            'status' => 'issued',
            'created_by' => $user->id,
        ]);

        app(LancamentoDoRecibo::class)->lancar($recibo, [
            'account_id' => $dados['account_id'] ?? null,
            'cash_register_id' => $dados['cash_register_id'] ?? null,
        ], $user->id);

        return $recibo;
    }

    private function meu(PedidoDePagamento $p, int $tenantId): void
    {
        if ((int) $p->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Pedido de outra empresa.');
        }
    }
}

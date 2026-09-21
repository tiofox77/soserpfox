<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Services\AGT\AutoSubmissao;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * REGISTAR O PAGAMENTO DE UMA FACTURA — num sítio só.
 *
 * Vivia dentro do `PaymentModal`, que sete ecrãs Livewire incluem. Ao migrar
 * as listas para React, saiu para aqui; o modal Livewire e a API chamam o
 * mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE, e a ordem importa:
 *
 *  · A FACTURA NÃO SE SOMA AQUI. O recibo lança o seu valor por si (os
 *    ganchos do Receipt); somar aqui também fazia o pagamento contar duas
 *    vezes. O adiantamento não passa por recibo e é lançado à mão, uma vez.
 *
 *  · CADA TIPO NA SUA COLUNA: a factura de compra vai em
 *    `purchase_invoice_id`, nunca em `invoice_id` (que tem chave estrangeira
 *    para as vendas).
 *
 *  · O EXCEDENTE de uma venda vira adiantamento do cliente, automaticamente.
 *
 *  · SÓ O RECIBO DE VENDA VAI À AGT, e só depois do commit: um recibo de
 *    compra é dinheiro que a empresa pagou, não documento fiscal emitido por
 *    ela — submetê-lo era declarar uma despesa como receita.
 *
 *  · A TESOURARIA recebe o movimento pelo método de pagamento certo, com o
 *    `type` real (cash, card, bank_transfer…) — é por ele que decide se o
 *    valor entra na caixa.
 */
class RegistoDePagamento
{
    /**
     * Forma de pagamento do ecrã → método de tesouraria: [código, nome, tipo].
     *
     * TODAS AS FORMAS QUE OS ECRÃS OFERECEM TÊM DE ESTAR AQUI. Faltava o
     * `card`, que o ecrã dos Recibos oferece como «Cartão»: sem entrada no
     * mapa caía no `cash` por omissão, e um recibo pago com cartão entrava na
     * GAVETA em vez da conta — o numerário do fecho de caixa vinha inflado com
     * dinheiro que nunca lá esteve.
     */
    public const MAPA_METODOS = [
        'cash' => ['CASH', 'Dinheiro', 'cash'],
        'transfer' => ['TRANSFER', 'Transferência Bancária', 'bank_transfer'],
        'multicaixa' => ['MULTICAIXA', 'Multicaixa', 'card'],
        // `mcx` e `mobile` são os códigos que o balcão grava nos documentos.
        // Sem entrada aqui, um recibo sobre uma venda feita com eles caía no
        // `cash` por omissão e o valor entrava na GAVETA.
        'mcx' => ['MULTICAIXA', 'Multicaixa', 'card'],
        'card' => ['CARD', 'Cartão', 'card'],
        'tpa' => ['TPA', 'TPA', 'card'],
        'check' => ['CHECK', 'Cheque', 'check'],
        'mbway' => ['MBWAY', 'MB Way', 'digital_wallet'],
        'mobile' => ['MBWAY', 'MB Way', 'digital_wallet'],
        'other' => ['OTHER', 'Outro', 'other'],
    ];

    /**
     * O que o modal precisa de saber antes de pagar: a factura, quanto
     * falta, os adiantamentos do cliente, as contas e as caixas.
     *
     * @return array{factura: Model, por_pagar: float, adiantamentos: \Illuminate\Support\Collection, contas: \Illuminate\Support\Collection, caixas: \Illuminate\Support\Collection, conta_padrao: ?int, caixa_padrao: ?int}
     */
    public function contexto(string $tipo, int $facturaId, int $tenantId, ?int $userId = null): array
    {
        $factura = $this->factura($tipo, $facturaId, $tenantId);

        $adiantamentos = $tipo === 'sale'
            ? Advance::where('tenant_id', $tenantId)
                ->where('client_id', $factura->client_id)
                ->where('status', 'available')
                ->where('remaining_amount', '>', 0)
                ->get()
            : collect();

        $contas = Account::where('tenant_id', $tenantId)->where('is_active', true)->with('bank')
            ->orderByDesc('is_default')->orderBy('account_name')->get();

        $caixas = CashRegister::where('tenant_id', $tenantId)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->get();

        return [
            'factura' => $factura,
            'por_pagar' => round((float) $factura->total - (float) ($factura->paid_amount ?? 0), 2),
            'adiantamentos' => $adiantamentos,
            'contas' => $contas,
            'caixas' => $caixas,
            'conta_padrao' => $contas->firstWhere('is_default', true)?->id,
            /*
             * A CAIXA DE QUEM RECEBE (21/09/2026), e não a caixa por omissão da
             * empresa. O que o modal propõe vai como escolha à mão, e a escolha
             * à mão passa à frente da caixa do operador: propor a da empresa
             * mandava o numerário de todos os operadores para a mesma gaveta.
             * Sem caixa aberta, fica vazio — «a que a tesouraria decidir».
             */
            'caixa_padrao' => app(\App\Services\Treasury\TreasuryMovementService::class)->caixaDoOperador($tenantId, $userId),
        ];
    }

    /**
     * @param  array $d  amount, payment_method, account_id, cash_register_id,
     *                   reference, notes, advance_id, advance_amount
     * @return array{recibo: ?Receipt, excedente: float, adiantamento_criado: ?Advance, aviso_agt: ?string, factura: Model}
     *
     * @throws DomainException
     */
    public function registar(string $tipo, int $facturaId, array $d, int $tenantId, ?int $userId): array
    {
        $ctx = $this->contexto($tipo, $facturaId, $tenantId, $userId);
        $factura = $ctx['factura'];
        $porPagar = $ctx['por_pagar'];

        $valor = round((float) ($d['amount'] ?? 0), 2);
        $doAdiantamento = round((float) ($d['advance_amount'] ?? 0), 2);
        $adiantamentoId = ! empty($d['advance_id']) ? (int) $d['advance_id'] : null;

        if ($valor <= 0 && $doAdiantamento <= 0) {
            throw new DomainException(__('Indique um valor a pagar.'));
        }

        if ($doAdiantamento > 0 && ! $adiantamentoId) {
            throw new DomainException(__('Escolha o adiantamento a usar.'));
        }

        $recibo = null;
        $novo = null;
        $excedente = round(($valor + $doAdiantamento) - $porPagar, 2);

        DB::transaction(function () use ($tipo, $facturaId, $d, $tenantId, $userId, $factura, $porPagar, $valor, $doAdiantamento, $adiantamentoId, $excedente, &$recibo, &$novo) {
            if ($valor > 0) {
                $recibo = Receipt::create([
                    'tenant_id' => $tenantId,
                    'type' => $tipo,
                    'client_id' => $tipo === 'sale' ? $factura->client_id : null,
                    'supplier_id' => $tipo === 'purchase' ? $factura->supplier_id : null,
                    'invoice_id' => $tipo === 'sale' ? $facturaId : null,
                    'purchase_invoice_id' => $tipo === 'purchase' ? $facturaId : null,
                    'payment_date' => now(),
                    'payment_method' => $d['payment_method'],
                    'amount_paid' => $valor,
                    'reference' => $d['reference'] ?? null,
                    'notes' => $d['notes'] ?? null,
                    'status' => 'issued',
                    'created_by' => $userId,
                ]);

                // A TESOURARIA E O TURNO por uma porta só — a mesma que o ecrã
                // dos Recibos usa. Antes era aqui, e por isso o recibo emitido
                // fora deste modal não chegava à tesouraria.
                app(LancamentoDoRecibo::class)->lancar($recibo, [
                    'account_id' => $d['account_id'] ?? null,
                    'cash_register_id' => $d['cash_register_id'] ?? null,
                ], $userId);
            }

            if ($adiantamentoId && $doAdiantamento > 0) {
                $adiantamento = Advance::where('tenant_id', $tenantId)->findOrFail($adiantamentoId);
                $adiantamento->use($doAdiantamento, $facturaId);
            }

            // O excedente de uma venda vira adiantamento do cliente.
            if ($excedente > 0 && $tipo === 'sale') {
                $novo = Advance::create([
                    'tenant_id' => $tenantId,
                    'client_id' => $factura->client_id,
                    'payment_date' => now(),
                    'payment_method' => $d['payment_method'],
                    'amount' => $excedente,
                    'used_amount' => 0,
                    'remaining_amount' => $excedente,
                    'purpose' => 'Excedente do pagamento da fatura ' . $factura->invoice_number,
                    'notes' => 'Adiantamento criado automaticamente - Pagamento de ' . number_format($valor + $doAdiantamento, 2) . ' AOA para fatura de ' . number_format($porPagar, 2) . ' AOA',
                    'status' => 'available',
                    'created_by' => $userId,
                ]);
            }

            // O adiantamento também é dinheiro recebido, e não passa por
            // recibo: lança-se à mão, uma única vez, depois de aplicado.
            if ($tipo === 'sale' && $doAdiantamento > 0) {
                $factura->refresh();
                $factura->aplicarPagamento($doAdiantamento);
            }
        });

        // Só o recibo de VENDA vai à AGT, e só depois do commit.
        $avisoAgt = null;

        if ($recibo && $tipo === 'sale') {
            $agt = AutoSubmissao::submeter($recibo);

            if ($agt['enviado']) {
                $avisoAgt = __('Recibo submetido à AGT');
            } elseif ($agt['erro']) {
                $avisoAgt = __('Recibo por submeter à AGT: :erro', ['erro' => $agt['erro']]);
            }
        }

        return [
            'recibo' => $recibo,
            'excedente' => max(0.0, $excedente),
            'adiantamento_criado' => $novo,
            'aviso_agt' => $avisoAgt,
            'factura' => $factura->fresh(),
        ];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function factura(string $tipo, int $id, int $tenantId): Model
    {
        return $tipo === 'sale'
            ? SalesInvoice::with('client')->where('tenant_id', $tenantId)->findOrFail($id)
            : PurchaseInvoice::with('supplier')->where('tenant_id', $tenantId)->findOrFail($id);
    }
}

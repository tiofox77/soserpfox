<?php

namespace App\Observers;

use App\Models\Accounting\Move;
use App\Models\Invoicing\PurchaseInvoice;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

/**
 * Integração Compras → Contabilidade.
 *
 * O espelho do SalesInvoiceAccountingObserver. Faltava: o mapeamento `purchase`
 * existia e ninguém o usava, pelo que as faturas de compra nunca chegavam à
 * contabilidade.
 *
 * Dispara quando a fatura deixa de ser RASCUNHO (draft → pending/sent/paid…),
 * nunca num rascunho nem numa anulada. Também apanha a fatura já definitiva cujo
 * total só chega depois (os totais do cabeçalho saem das linhas, gravadas a
 * seguir ao create). A referência do lançamento é o número da fatura, e é por
 * ela que não se lança duas vezes.
 */
class PurchaseInvoiceAccountingObserver
{
    private const SEM_LANCAMENTO = ['draft', 'cancelled'];

    public function __construct(protected IntegrationService $integrationService)
    {
    }

    public function created(PurchaseInvoice $invoice): void
    {
        if ($this->definitiva($invoice) && (float) $invoice->total > 0) {
            $this->lancar($invoice);
        }
    }

    public function updated(PurchaseInvoice $invoice): void
    {
        if (!$invoice->wasChanged('status') && !$invoice->wasChanged('total')) {
            return;
        }

        if ($this->definitiva($invoice) && (float) $invoice->total > 0) {
            $this->lancar($invoice);
        }
    }

    private function definitiva(PurchaseInvoice $invoice): bool
    {
        return !in_array($invoice->status, self::SEM_LANCAMENTO, true);
    }

    /**
     * Cria o lançamento, uma única vez por fatura. Nunca deixa rebentar as
     * compras por causa da contabilidade.
     */
    protected function lancar(PurchaseInvoice $invoice): void
    {
        try {
            if (!$this->integrationService->isEnabled($invoice->tenant_id)) {
                return;
            }

            $jaExiste = Move::withoutGlobalScopes()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('ref', $invoice->invoice_number)
                ->exists();

            if ($jaExiste) {
                return;
            }

            $move = $this->integrationService->createMoveFromPurchaseInvoice($invoice);

            if ($move) {
                Log::info('Lançamento contabilístico criado da fatura de compra', [
                    'purchase_invoice_id' => $invoice->id,
                    'invoice_number'      => $invoice->invoice_number,
                    'move_id'             => $move->id,
                    'tenant_id'           => $invoice->tenant_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Falha ao integrar fatura de compra na contabilidade', [
                'purchase_invoice_id' => $invoice->id,
                'tenant_id'           => $invoice->tenant_id,
                'error'               => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Accounting\Move;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

/**
 * Integração Faturação → Contabilidade para as faturas REAIS.
 *
 * O InvoiceObserver estava ligado a App\Models\Invoice (tabela `invoices`,
 * legado, 0 registos), pelo que nenhuma venda gerava lançamento. As faturas a
 * sério vivem em invoicing_sales_invoices (SalesInvoice) — é aqui que a
 * integração tem de estar.
 *
 * Dispara quando a fatura fica DEFINITIVA (invoice_status = 'F'), nunca na
 * criação: no momento do create() a fatura ainda não tem linhas nem totais.
 */
class SalesInvoiceAccountingObserver
{
    public function __construct(protected IntegrationService $integrationService)
    {
    }

    public function created(SalesInvoice $invoice): void
    {
        // POS/PWA criam a fatura já finalizada e com totais preenchidos
        if ($invoice->invoice_status === 'F' && (float) $invoice->total > 0) {
            $this->lancar($invoice);
        }
    }

    public function updated(SalesInvoice $invoice): void
    {
        // Só no momento da transição para definitiva
        if (!$invoice->wasChanged('invoice_status') || $invoice->invoice_status !== 'F') {
            return;
        }

        $this->lancar($invoice);
    }

    /**
     * Cria o lançamento contabilístico, uma única vez por fatura.
     * Nunca deixa rebentar a faturação por causa da contabilidade.
     */
    protected function lancar(SalesInvoice $invoice): void
    {
        try {
            if (!$this->integrationService->isEnabled($invoice->tenant_id)) {
                return;
            }

            // Idempotência: a mesma fatura não pode gerar dois movimentos
            $jaExiste = Move::where('tenant_id', $invoice->tenant_id)
                ->where('ref', $invoice->invoice_number)
                ->exists();

            if ($jaExiste) {
                return;
            }

            $move = $this->integrationService->createMoveFromInvoice($invoice);

            if ($move) {
                Log::info('Lançamento contabilístico criado da fatura de venda', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'move_id'        => $move->id,
                    'tenant_id'      => $invoice->tenant_id,
                ]);
            }
        } catch (\Throwable $e) {
            // A venda já está fechada e assinada — não a podemos reverter por
            // causa de um lançamento. Fica registado para reprocessamento.
            Log::error('Falha ao integrar fatura na contabilidade', [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $invoice->tenant_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}

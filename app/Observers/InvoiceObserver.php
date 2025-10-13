<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

class InvoiceObserver
{
    protected $integrationService;
    
    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }
    
    /**
     * Handle the Invoice "created" event.
     * Cria lançamento contabilístico automático quando fatura é criada
     */
    public function created(Invoice $invoice): void
    {
        // Verificar se integração está ativada
        if (!$this->integrationService->isEnabled($invoice->tenant_id)) {
            Log::info('Integração contabilística desativada, fatura não será integrada', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id
            ]);
            return;
        }
        
        // Criar lançamento contabilístico
        try {
            $move = $this->integrationService->createMoveFromInvoice($invoice);
            
            if ($move) {
                Log::info('✅ Lançamento contabilístico criado automaticamente da fatura', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'move_id' => $move->id,
                    'move_ref' => $move->ref
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar lançamento contabilístico da fatura', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Futuramente: atualizar lançamento se fatura for alterada
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        // Futuramente: reverter lançamento se fatura for deletada
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}

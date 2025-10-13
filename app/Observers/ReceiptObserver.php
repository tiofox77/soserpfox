<?php

namespace App\Observers;

use App\Models\Invoicing\Receipt;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

class ReceiptObserver
{
    protected $integrationService;
    
    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }
    
    /**
     * Handle the Receipt "created" event.
     * Cria lançamento contabilístico automático quando recebimento é criado
     */
    public function created(Receipt $receipt): void
    {
        // Verificar se integração está ativada
        if (!$this->integrationService->isEnabled($receipt->tenant_id)) {
            Log::info('Integração contabilística desativada, recebimento não será integrado', [
                'receipt_id' => $receipt->id,
                'tenant_id' => $receipt->tenant_id
            ]);
            return;
        }
        
        // Criar lançamento contabilístico
        try {
            $move = $this->integrationService->createMoveFromReceipt($receipt);
            
            if ($move) {
                Log::info('✅ Lançamento contabilístico criado automaticamente do recebimento', [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'move_id' => $move->id,
                    'move_ref' => $move->ref,
                    'amount' => $receipt->amount_paid
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar lançamento contabilístico do recebimento', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the Receipt "updated" event.
     */
    public function updated(Receipt $receipt): void
    {
        // Futuramente: atualizar lançamento se recebimento for alterado
    }

    /**
     * Handle the Receipt "deleted" event.
     */
    public function deleted(Receipt $receipt): void
    {
        // Futuramente: reverter lançamento se recebimento for deletado
    }

    /**
     * Handle the Receipt "restored" event.
     */
    public function restored(Receipt $receipt): void
    {
        //
    }

    /**
     * Handle the Receipt "force deleted" event.
     */
    public function forceDeleted(Receipt $receipt): void
    {
        //
    }
}

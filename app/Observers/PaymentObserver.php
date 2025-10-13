<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    protected $integrationService;
    
    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }
    
    public function created(Payment $payment): void
    {
        if (!$this->integrationService->isEnabled($payment->tenant_id)) {
            return;
        }
        
        try {
            // Futuramente: $move = $this->integrationService->createMoveFromPayment($payment);
            Log::info('Payment observer ativo', ['payment_id' => $payment->id]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar lançamento do pagamento', ['error' => $e->getMessage()]);
        }
    }
}

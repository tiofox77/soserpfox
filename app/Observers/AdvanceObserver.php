<?php

namespace App\Observers;

use App\Models\HR\Advance;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\Log;

class AdvanceObserver
{
    /**
     * Handle the Advance "updated" event.
     */
    public function updated(Advance $advance): void
    {
        // Verificar se o status mudou para aprovado
        if ($advance->isDirty('status') && $advance->status === 'approved') {
            try {
                $notificationService = new ImmediateNotificationService($advance->tenant_id);
                $notificationService->notifyAdvanceApproved($advance);
                
                Log::info('AdvanceObserver: Approved notification triggered', [
                    'advance_id' => $advance->id,
                    'employee_id' => $advance->employee_id,
                    'tenant_id' => $advance->tenant_id,
                ]);
            } catch (\Exception $e) {
                Log::error('AdvanceObserver: Failed to send approved notification', [
                    'advance_id' => $advance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Verificar se foi rejeitado
        if ($advance->isDirty('status') && $advance->status === 'rejected') {
            try {
                $notificationService = new ImmediateNotificationService($advance->tenant_id);
                $notificationService->notifyAdvanceRejected($advance);
                
                Log::info('AdvanceObserver: Rejected notification triggered', [
                    'advance_id' => $advance->id,
                    'employee_id' => $advance->employee_id,
                    'tenant_id' => $advance->tenant_id,
                ]);
            } catch (\Exception $e) {
                Log::error('AdvanceObserver: Failed to send rejected notification', [
                    'advance_id' => $advance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

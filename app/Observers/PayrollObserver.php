<?php

namespace App\Observers;

use App\Models\HR\Payroll;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\Log;

class PayrollObserver
{
    /**
     * Handle the Payroll "created" event.
     */
    public function created(Payroll $payroll): void
    {
        try {
            $notificationService = new ImmediateNotificationService($payroll->tenant_id);
            $notificationService->notifyPayslipReady($payroll);
            
            Log::info('PayrollObserver: Payslip notification triggered', [
                'payroll_id' => $payroll->id,
                'employee_id' => $payroll->employee_id,
                'tenant_id' => $payroll->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('PayrollObserver: Failed to send notification', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Handle the Payroll "updated" event - quando status muda para "paid".
     */
    public function updated(Payroll $payroll): void
    {
        // Se mudou para pago, pode enviar notificação também
        if ($payroll->isDirty('status') && $payroll->status === 'paid') {
            try {
                $notificationService = new ImmediateNotificationService($payroll->tenant_id);
                $notificationService->notifyPayslipReady($payroll);
                
                Log::info('PayrollObserver: Payslip paid notification triggered', [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $payroll->employee_id,
                    'tenant_id' => $payroll->tenant_id,
                ]);
            } catch (\Exception $e) {
                Log::error('PayrollObserver: Failed to send paid notification', [
                    'payroll_id' => $payroll->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

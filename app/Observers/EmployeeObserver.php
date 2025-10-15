<?php

namespace App\Observers;

use App\Models\HR\Employee;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\Log;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        try {
            $notificationService = new ImmediateNotificationService($employee->tenant_id);
            $notificationService->notifyEmployeeCreated($employee);
            
            Log::info('EmployeeObserver: Notification triggered', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'tenant_id' => $employee->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('EmployeeObserver: Failed to send notification', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

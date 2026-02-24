<?php

namespace App\Observers;

use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;

class WorkOrderObserver
{
    /**
     * Handle the WorkOrder "created" event.
     */
    public function created(WorkOrder $workOrder): void
    {
        WorkOrderHistory::logAction(
            $workOrder->id,
            WorkOrderHistory::ACTION_CREATED,
            "Ordem de Serviço #{$workOrder->order_number} criada",
            [
                'vehicle_id' => $workOrder->vehicle_id,
                'status' => $workOrder->status,
                'total' => $workOrder->total,
            ]
        );
    }

    /**
     * Handle the WorkOrder "updated" event.
     */
    public function updated(WorkOrder $workOrder): void
    {
        // Rastrear mudanças de status
        if ($workOrder->isDirty('status')) {
            $oldStatus = $workOrder->getOriginal('status');
            $newStatus = $workOrder->status;
            
            $statusLabels = [
                'pending' => 'Pendente',
                'in_progress' => 'Em Andamento',
                'waiting_parts' => 'Aguardando Peças',
                'completed' => 'Concluída',
                'delivered' => 'Entregue',
                'cancelled' => 'Cancelada',
            ];
            
            WorkOrderHistory::create([
                'work_order_id' => $workOrder->id,
                'user_id' => auth()->id(),
                'action' => WorkOrderHistory::ACTION_STATUS_CHANGED,
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'description' => "Status alterado de '" . ($statusLabels[$oldStatus] ?? $oldStatus) . "' para '" . ($statusLabels[$newStatus] ?? $newStatus) . "'",
            ]);
        }
        
        // Rastrear mudança de faturamento
        if ($workOrder->isDirty('invoice_id') && $workOrder->invoice_id) {
            WorkOrderHistory::logAction(
                $workOrder->id,
                WorkOrderHistory::ACTION_INVOICED,
                "Fatura gerada (ID: {$workOrder->invoice_id})",
                ['invoice_id' => $workOrder->invoice_id]
            );
        }
        
        // Rastrear mudanças de pagamento
        if ($workOrder->isDirty('paid_amount')) {
            $oldAmount = $workOrder->getOriginal('paid_amount') ?? 0;
            $newAmount = $workOrder->paid_amount;
            $difference = $newAmount - $oldAmount;
            
            if ($difference > 0) {
                WorkOrderHistory::logAction(
                    $workOrder->id,
                    WorkOrderHistory::ACTION_PAYMENT_ADDED,
                    "Pagamento registrado: " . number_format($difference, 2, ',', '.') . " Kz",
                    [
                        'amount' => $difference,
                        'total_paid' => $newAmount,
                        'balance' => $workOrder->balance_due,
                    ]
                );
            }
        }
    }

    /**
     * Handle the WorkOrder "deleted" event.
     */
    public function deleted(WorkOrder $workOrder): void
    {
        //
    }

    /**
     * Handle the WorkOrder "restored" event.
     */
    public function restored(WorkOrder $workOrder): void
    {
        //
    }

    /**
     * Handle the WorkOrder "force deleted" event.
     */
    public function forceDeleted(WorkOrder $workOrder): void
    {
        //
    }
}

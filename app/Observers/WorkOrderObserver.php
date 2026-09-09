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
        /*
         * A MUDANÇA DE ESTADO, escrita venha ela de onde vier.
         *
         * OS RÓTULOS VÊM DE `OrdensDeServico::ESTADOS`, que é a lista única do
         * módulo. A que aqui estava era uma segunda cópia: dizia «Em Andamento»
         * (o resto do produto diz «Em curso»), não estava traduzida, e não
         * conhecia o estado `scheduled` — uma ordem que passasse a Agendada
         * ficava com «Status alterado de 'Pendente' para 'scheduled'» escrito
         * no histórico.
         */
        if ($workOrder->isDirty('status')) {
            $antigo = $workOrder->getOriginal('status');
            $novo = $workOrder->status;

            $rotulo = fn (?string $e) => $e === null
                ? '—'
                : __(\App\Services\Workshop\OrdensDeServico::ESTADOS[$e] ?? $e);

            WorkOrderHistory::create([
                'work_order_id' => $workOrder->id,
                'user_id' => auth()->id(),
                'action' => WorkOrderHistory::ACTION_STATUS_CHANGED,
                'field_name' => 'status',
                'old_value' => $antigo,
                'new_value' => $novo,
                'description' => __('Estado: :antes → :depois', [
                    'antes' => $rotulo($antigo),
                    'depois' => $rotulo($novo),
                ]),
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

<?php

namespace App\Observers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\BatchAllocation;
use App\Services\BatchAllocationService;

class SalesInvoiceObserver
{
    /**
     * Handle the SalesInvoice "created" event.
     */
    public function created(SalesInvoice $invoice): void
    {
        // Quando uma fatura é criada no status 'sent' ou 'paid', reduz stock
        if (in_array($invoice->status, ['sent', 'paid'])) {
            $this->reduceStock($invoice);
        }
    }

    /**
     * Handle the SalesInvoice "updated" event.
     */
    public function updated(SalesInvoice $invoice): void
    {
        // Quando status muda para 'sent' ou 'paid', reduz stock
        if ($invoice->isDirty('status') && in_array($invoice->status, ['sent', 'paid'])) {
            $oldStatus = $invoice->getOriginal('status');
            if (!in_array($oldStatus, ['sent', 'paid'])) {
                $this->reduceStock($invoice);
            }
        }

        // Quando status muda para 'cancelled', devolve stock
        if ($invoice->isDirty('status') && $invoice->status === 'cancelled') {
            $this->returnStock($invoice);
        }
    }

    /**
     * Reduz o stock baseado nos items da fatura usando FIFO
     */
    private function reduceStock(SalesInvoice $invoice): void
    {
        $batchService = app(BatchAllocationService::class);
        
        foreach ($invoice->items as $item) {
            if ($item->product_id) {
                // Tentar alocar usando FIFO
                $allocation = $batchService->allocateFIFO(
                    $item->product_id,
                    $invoice->warehouse_id,
                    $item->quantity
                );
                
                if ($allocation['success']) {
                    // Confirmar alocação e registrar
                    $batchService->confirmAllocation($allocation['allocations']);
                    
                    // Registrar alocações no banco
                    foreach ($allocation['allocations'] as $alloc) {
                        BatchAllocation::create([
                            'tenant_id' => $invoice->tenant_id,
                            'document_type' => SalesInvoice::class,
                            'document_id' => $invoice->id,
                            'document_item_id' => $item->id,
                            'product_batch_id' => $alloc['batch_id'],
                            'product_id' => $item->product_id,
                            'quantity_allocated' => $alloc['quantity'],
                            'expiry_date_snapshot' => $alloc['expiry_date'],
                            'batch_number_snapshot' => $alloc['batch_number'],
                            'status' => 'confirmed',
                        ]);
                    }
                }
                
                // Atualiza stock (total)
                $stock = Stock::firstOrCreate([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                ], [
                    'quantity' => 0,
                ]);

                $stock->decrement('quantity', $item->quantity);

                // Registra movimento
                $notes = "Venda - Fatura {$invoice->invoice_number}";
                if ($allocation['success']) {
                    $batchNumbers = collect($allocation['allocations'])->pluck('batch_number')->filter()->join(', ');
                    if ($batchNumbers) {
                        $notes .= " (Lotes: {$batchNumbers})";
                    }
                }
                
                StockMovement::create([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => 'out',
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'notes' => $notes,
                    'movement_date' => $invoice->invoice_date,
                    'created_by' => $invoice->created_by,
                ]);
            }
        }
    }

    /**
     * Devolve o stock quando fatura é cancelada
     */
    private function returnStock(SalesInvoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            if ($item->product_id) {
                // Atualiza stock
                $stock = Stock::where([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                ])->first();

                if ($stock) {
                    $stock->increment('quantity', $item->quantity);

                    // Registra movimento de devolução
                    StockMovement::create([
                        'tenant_id' => $invoice->tenant_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'product_id' => $item->product_id,
                        'type' => 'in',
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'notes' => "Devolução - Fatura {$invoice->invoice_number} cancelada",
                        'movement_date' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
    }
}

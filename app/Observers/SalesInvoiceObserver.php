<?php

namespace App\Observers;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;

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
     * Reduz o stock baseado nos items da fatura
     */
    private function reduceStock(SalesInvoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            if ($item->product_id) {
                // Atualiza stock
                $stock = Stock::firstOrCreate([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                ], [
                    'quantity' => 0,
                ]);

                $stock->decrement('quantity', $item->quantity);

                // Registra movimento
                StockMovement::create([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => 'out',
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'notes' => "Venda - Fatura {$invoice->invoice_number}",
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

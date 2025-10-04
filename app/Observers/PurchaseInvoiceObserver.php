<?php

namespace App\Observers;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;

class PurchaseInvoiceObserver
{
    /**
     * Handle the PurchaseInvoice "created" event.
     */
    public function created(PurchaseInvoice $invoice): void
    {
        // Quando uma fatura de compra é criada, aumenta stock
        if ($invoice->status === 'paid') {
            $this->increaseStock($invoice);
        }
    }

    /**
     * Handle the PurchaseInvoice "updated" event.
     */
    public function updated(PurchaseInvoice $invoice): void
    {
        // Quando status muda para 'paid', aumenta stock
        if ($invoice->isDirty('status') && $invoice->status === 'paid') {
            $oldStatus = $invoice->getOriginal('status');
            if ($oldStatus !== 'paid') {
                $this->increaseStock($invoice);
            }
        }

        // Quando status muda para 'cancelled', remove stock
        if ($invoice->isDirty('status') && $invoice->status === 'cancelled') {
            $this->removeStock($invoice);
        }
    }

    /**
     * Aumenta o stock baseado nos items da fatura de compra
     */
    private function increaseStock(PurchaseInvoice $invoice): void
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

                $stock->increment('quantity', $item->quantity);

                // Registra movimento
                StockMovement::create([
                    'tenant_id' => $invoice->tenant_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => 'in',
                    'reference_type' => PurchaseInvoice::class,
                    'reference_id' => $invoice->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'notes' => "Compra - Fatura {$invoice->invoice_number}",
                    'movement_date' => $invoice->invoice_date,
                    'created_by' => $invoice->created_by,
                ]);
            }
        }
    }

    /**
     * Remove o stock quando fatura de compra é cancelada
     */
    private function removeStock(PurchaseInvoice $invoice): void
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
                    $stock->decrement('quantity', $item->quantity);

                    // Registra movimento de remoção
                    StockMovement::create([
                        'tenant_id' => $invoice->tenant_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'product_id' => $item->product_id,
                        'type' => 'out',
                        'reference_type' => PurchaseInvoice::class,
                        'reference_id' => $invoice->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'notes' => "Remoção - Fatura {$invoice->invoice_number} cancelada",
                        'movement_date' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
    }
}

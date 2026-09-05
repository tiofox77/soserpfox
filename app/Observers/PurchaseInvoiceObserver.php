<?php

namespace App\Observers;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\ProductBatch;

class PurchaseInvoiceObserver
{
    /**
     * Statuses que devem gerar stock (mercadoria recebida). A lista vive no
     * modelo — o formulário de compras também precisa dela.
     */
    private array $stockStatuses = PurchaseInvoice::ESTADOS_COM_STOCK;

    /**
     * Handle the PurchaseInvoice "created" event.
     */
    public function created(PurchaseInvoice $invoice): void
    {
        if (in_array($invoice->status, $this->stockStatuses)) {
            $this->increaseStock($invoice);
        }
    }

    /**
     * Handle the PurchaseInvoice "updated" event.
     */
    public function updated(PurchaseInvoice $invoice): void
    {
        if ($invoice->isDirty('status')) {
            $oldStatus = $invoice->getOriginal('status');
            $newStatus = $invoice->status;
            
            $wasStocked = in_array($oldStatus, $this->stockStatuses);
            $shouldStock = in_array($newStatus, $this->stockStatuses);

            // De draft → status com stock: aumentar stock
            if (!$wasStocked && $shouldStock) {
                $this->increaseStock($invoice);
            }

            // De status com stock → cancelled: remover stock
            if ($wasStocked && $newStatus === 'cancelled') {
                $this->removeStock($invoice);
            }
        }
    }

    /**
     * Aumenta o stock baseado nos items da fatura de compra.
     * NOTA: Não incrementar stock diretamente aqui pois StockMovement::create()
     * já dispara updateStock() no boot que chama Stock::addStock().
     */
    private function increaseStock(PurchaseInvoice $invoice): void
    {
        // A mercadoria desta factura já entrou na RECEPÇÃO da encomenda (módulo
        // Compras). Dar entrada outra vez punha no sistema o dobro do que
        // chegou ao armazém — e ninguém daria pela diferença até à contagem.
        // O custo do artigo também já foi actualizado nessa altura.
        if ($invoice->stock_ja_entrou) {
            return;
        }

        $invoice->loadMissing(['items.product', 'supplier']);

        // O preço a que se comprou passa a ser o custo do artigo. Fica aqui —
        // no momento em que a mercadoria entra — e não ao gravar o rascunho:
        // um rascunho ainda pode ser corrigido ou deitado fora. Nunca pode
        // partir a entrada de stock, daí o try.
        try {
            app(\App\Services\Invoicing\ActualizarCustoDeCompra::class)->aplicar($invoice);
        } catch (\Throwable $e) {
            \Log::error('Falhou a actualização do custo pela compra', [
                'factura' => $invoice->id,
                'erro'    => $e->getMessage(),
            ]);
        }

        foreach ($invoice->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            // Registra movimento de entrada — Stock::addStock() é chamado automaticamente
            // pelo boot do StockMovement (evita double-counting)
            StockMovement::create([
                'tenant_id' => $invoice->tenant_id,
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $item->product_id,
                'type' => 'in',
                'reference_type' => PurchaseInvoice::class,
                'reference_id' => $invoice->id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_price,
                'total_cost' => $item->unit_price * $item->quantity,
                'notes' => "Compra - Fatura {$invoice->invoice_number}",
                // user_id é NOT NULL. Uma compra sem `created_by` (importação,
                // API) rebentava aqui e a mercadoria não entrava — o mesmo
                // fallback que o removeStock() já usava.
                'user_id' => $invoice->created_by ?? auth()->id(),
            ]);

            // Criar/Atualizar lote se produto rastreia lotes
            $product = $item->product;
            if ($product && $product->track_batches) {
                if ($item->batch_number) {
                    $batch = ProductBatch::firstOrCreate([
                        'tenant_id' => $invoice->tenant_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'batch_number' => $item->batch_number,
                    ], [
                        'manufacturing_date' => $item->manufacturing_date,
                        'expiry_date' => $item->expiry_date,
                        'quantity' => 0,
                        'quantity_available' => 0,
                        'purchase_invoice_id' => $invoice->id,
                        'supplier_name' => $invoice->supplier->name ?? null,
                        'cost_price' => $item->unit_price,
                        'alert_days' => $item->alert_days ?? 30,
                        'status' => 'active',
                        'notes' => "Lote criado da fatura {$invoice->invoice_number}",
                    ]);
                    
                    $batch->increment('quantity', $item->quantity);
                    $batch->increment('quantity_available', $item->quantity);
                    $batch->updateStatus();
                } elseif ($item->expiry_date) {
                    ProductBatch::create([
                        'tenant_id' => $invoice->tenant_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'batch_number' => 'AUTO-' . $invoice->invoice_number . '-' . $item->id,
                        'manufacturing_date' => $item->manufacturing_date,
                        'expiry_date' => $item->expiry_date,
                        'quantity' => $item->quantity,
                        'quantity_available' => $item->quantity,
                        'purchase_invoice_id' => $invoice->id,
                        'supplier_name' => $invoice->supplier->name ?? null,
                        'cost_price' => $item->unit_price,
                        'alert_days' => $item->alert_days ?? 30,
                        'status' => 'active',
                        'notes' => "Lote gerado automaticamente (sem número de lote informado)",
                    ]);
                }
                
                \Log::info('Lote criado/atualizado para produto rastreável', [
                    'product_id' => $item->product_id,
                    'batch_number' => $item->batch_number,
                    'quantity' => $item->quantity,
                    'invoice' => $invoice->invoice_number,
                ]);
            }
        }
    }

    /**
     * Remove o stock quando fatura de compra é cancelada.
     * Usa manipulação directa do Stock (sem StockMovement boot)
     * para lidar com stock insuficiente de forma segura.
     */
    private function removeStock(PurchaseInvoice $invoice): void
    {
        // Simétrico do increaseStock: se o stock não entrou por aqui, não sai
        // por aqui. Anular a factura de uma encomenda já recebida é um acto
        // administrativo — a mercadoria continua no armazém. Quem a quiser
        // fazer sair devolve-a ou acerta-a por contagem.
        if ($invoice->stock_ja_entrou) {
            return;
        }

        $invoice->loadMissing(['items.product']);

        foreach ($invoice->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $stock = Stock::where([
                'tenant_id' => $invoice->tenant_id,
                'warehouse_id' => $invoice->warehouse_id,
                'product_id' => $item->product_id,
            ])->first();

            $quantityToRemove = $stock ? min($item->quantity, $stock->quantity) : 0;

            if ($quantityToRemove > 0) {
                if ($quantityToRemove < $item->quantity) {
                    \Log::warning('PurchaseInvoiceObserver: Stock insuficiente para reverter totalmente', [
                        'product_id' => $item->product_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'stock_atual' => $stock->quantity,
                        'quantidade_a_reverter' => $item->quantity,
                        'quantidade_revertida' => $quantityToRemove,
                    ]);
                }

                // save() Eloquent (NÃO decrement): decrement só dispara
                // updating/updated — nunca saved — pelo que o StockObserver não
                // ressincronizava products.stock_quantity e o agregado ficava
                // acima da realidade após anular uma compra.
                $stock->quantity = (float) $stock->quantity - (float) $quantityToRemove;
                $stock->save();

                // Ledger da reversão (semAplicarStock: o stock já foi corrigido acima)
                StockMovement::semAplicarStock(function () use ($invoice, $item, $quantityToRemove) {
                    StockMovement::create([
                        'tenant_id'      => $invoice->tenant_id,
                        'warehouse_id'   => $invoice->warehouse_id,
                        'product_id'     => $item->product_id,
                        'type'           => 'out',
                        'quantity'       => $quantityToRemove,
                        'unit_cost'      => $item->unit_price,
                        'reference_type' => PurchaseInvoice::class,
                        'reference_id'   => $invoice->id,
                        'user_id'        => auth()->id() ?? $invoice->created_by,
                        'notes'          => 'Anulação de compra - ' . $invoice->invoice_number,
                    ]);
                });
            }

            // Reverter lotes associados
            $product = $item->product;
            if ($product && $product->track_batches && $item->batch_number) {
                $batch = ProductBatch::where([
                    'tenant_id' => $invoice->tenant_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'batch_number' => $item->batch_number,
                ])->first();

                if ($batch) {
                    $batchQtyToRemove = min($item->quantity, $batch->quantity_available);
                    $batch->decrement('quantity', $batchQtyToRemove);
                    $batch->decrement('quantity_available', $batchQtyToRemove);
                    $batch->updateStatus();
                }
            }
        }
    }
}

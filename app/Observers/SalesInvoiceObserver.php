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
    /**
     * Reduz o stock da factura. IDEMPOTENTE e seguro para chamar de fora.
     *
     * Público de propósito: o InvoiceCreate tem de o chamar DEPOIS de criar as
     * linhas. No evento `created` a colecção $invoice->items está vazia (a
     * factura é gravada antes dos itens), pelo que emitir uma factura de venda
     * NÃO descontava stock nenhum — medido: 6 unidades antes, 6 depois, zero
     * movimentos.
     *
     * A guarda de idempotência é o que torna isto seguro. O POS e a sincronização
     * do PWA criam a factura já como 'paid' e descontam explicitamente a seguir;
     * hoje só não descontam a dobrar porque o observer apanha a colecção vazia.
     * Sem esta guarda, corrigir o observer trocava "não desconta" por "desconta
     * a dobrar", que é pior.
     */
    public function reduceStock(SalesInvoice $invoice): void
    {
        // Já foi descontado? O movimento de saída referencia a factura.
        $jaDescontado = StockMovement::where('reference_type', SalesInvoice::class)
            ->where('reference_id', $invoice->id)
            ->where('type', 'out')
            ->exists();

        if ($jaDescontado) {
            return;
        }

        // Factura gerada a partir de uma Ordem de Serviço da oficina: as peças
        // já saíram do stock quando a OS foi concluída
        // (WorkOrder::processStockMovement). Esses movimentos têm
        // reference_type='WorkOrder', que a guarda acima — presa a
        // SalesInvoice::class — nunca via: a mesma peça saía duas vezes, uma na
        // conclusão da OS e outra ao emitir a factura.
        if ($this->pecasJaSairamPelaOficina($invoice)) {
            \Illuminate\Support\Facades\Log::info('Stock não descontado: peças já baixadas pela Ordem de Serviço', [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            return;
        }

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
                } else {
                    \Log::warning('SalesInvoiceObserver: Alocação FIFO falhou, stock reduzido sem lote', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                    ]);
                }
                
                // Atualiza stock (total).
                // first() e NÃO firstOrCreate(): criar a linha a zero e depois
                // descontar min(qtd, 0) = 0 não descontava nada E ainda punha o
                // produto em regime multi-armazém, o que faz o POS deixar de
                // usar o agregado legado — o produto passava a aparecer
                // esgotado na caixa por causa de uma venda.
                $stock = Stock::where('tenant_id', $invoice->tenant_id)
                    ->where('warehouse_id', $invoice->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (!$stock) {
                    \Log::warning('SalesInvoiceObserver: produto sem linha neste armazém — saída não registada', [
                        'invoice_id'   => $invoice->id,
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $invoice->warehouse_id,
                    ]);
                    continue;
                }

                $disponivel = (float) $stock->quantity;
                $quantityToReduce = min((float) $item->quantity, $disponivel);

                if ($quantityToReduce > 0) {
                    // save() Eloquent (não decrement): dispara o StockObserver que
                    // ressincroniza o agregado e o hook saving (available_quantity).
                    $stock->quantity = $disponivel - $quantityToReduce;
                    $stock->save();
                }

                if ($quantityToReduce < (float) $item->quantity) {
                    // O documento fiscal sai com a quantidade toda mas o stock só
                    // desce o que havia: fica registado para se poder acertar.
                    \Log::warning('SalesInvoiceObserver: Stock insuficiente', [
                        'invoice_id'            => $invoice->id,
                        'product_id'            => $item->product_id,
                        'stock_disponivel'      => $disponivel,
                        'quantidade_solicitada' => (float) $item->quantity,
                        'quantidade_descontada' => $quantityToReduce,
                    ]);
                }

                // Registra movimento
                $notes = "Venda - Fatura {$invoice->invoice_number}";
                if ($allocation['success']) {
                    $batchNumbers = collect($allocation['allocations'])->pluck('batch_number')->filter()->join(', ');
                    if ($batchNumbers) {
                        $notes .= " (Lotes: {$batchNumbers})";
                    }
                }
                
                // semAplicarStock: o hook created do StockMovement chama removeStock()
                // e voltava a debitar o stock uma SEGUNDA vez (o débito real já foi
                // feito acima). Campos alinhados ao schema real (user_id/unit_cost;
                // unit_price/movement_date/created_by não existem na tabela).
                StockMovement::semAplicarStock(function () use ($invoice, $item, $notes) {
                    StockMovement::create([
                        'tenant_id' => $invoice->tenant_id,
                        'warehouse_id' => $invoice->warehouse_id,
                        'product_id' => $item->product_id,
                        'type' => 'out',
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_price,
                        'user_id' => $invoice->created_by,
                        'notes' => $notes,
                    ]);
                });
            }
        }
    }

    /**
     * A factura veio de uma Ordem de Serviço que já deu baixa das peças?
     *
     * A OS regista os movimentos com reference_type='WorkOrder' e o id DA OS,
     * por isso não são encontráveis pela referência à factura.
     */
    private function pecasJaSairamPelaOficina(SalesInvoice $invoice): bool
    {
        $osId = \App\Models\Workshop\WorkOrder::withTrashed()
            ->where('invoice_id', $invoice->id)
            ->value('id');

        // A ligação `invoice_id` só é gravada DEPOIS de a factura ser emitida —
        // durante a emissão ainda não existe. A origem, essa, é gravada na
        // própria factura no momento da criação, por isso é ela que serve para
        // reconhecer a OS enquanto o documento está a ser feito.
        if (!$osId && $invoice->source_module === 'oficina' && $invoice->source_reference) {
            $osId = \App\Models\Workshop\WorkOrder::withTrashed()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('order_number', $invoice->source_reference)
                ->value('id');
        }

        if (!$osId) {
            return false;
        }

        return StockMovement::where('reference_type', 'WorkOrder')
            ->where('reference_id', $osId)
            ->where('type', StockMovement::TYPE_OUT)
            ->exists();
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
                    // save() Eloquent (não increment): dispara o StockObserver.
                    $stock->quantity = (float) $stock->quantity + (float) $item->quantity;
                    $stock->save();

                    // Registra movimento de devolução. semAplicarStock: o hook created
                    // chamava addStock() e repunha o stock uma SEGUNDA vez — cada
                    // anulação de fatura DUPLICAVA a devolução ("stock aumenta sozinho").
                    StockMovement::semAplicarStock(function () use ($invoice, $item) {
                        StockMovement::create([
                            'tenant_id' => $invoice->tenant_id,
                            'warehouse_id' => $invoice->warehouse_id,
                            'product_id' => $item->product_id,
                            'type' => 'in',
                            'reference_type' => SalesInvoice::class,
                            'reference_id' => $invoice->id,
                            'quantity' => $item->quantity,
                            'unit_cost' => $item->unit_price,
                            'user_id' => auth()->id(),
                            'notes' => "Devolução - Fatura {$invoice->invoice_number} cancelada",
                        ]);
                    });
                }
            }
        }
    }
}

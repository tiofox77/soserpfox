<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Transferências e Ajustes de Stock')]
class WarehouseTransfer extends Component
{
    use WithPagination;

    public $showTransferModal = false;
    public $showAdjustModal = false;
    public $showQuantityModal = false;
    public $showDetailsModal = false;
    public $selectedBatchDetails = [];
    public $selectedBatchId;
    public $selectedBatchRef;

    // Transfer Modal
    public $transferFromWarehouse = '';
    public $transferToWarehouse = '';
    public $transferNotes = '';
    
    // Add Product to Transfer
    public $selectedProduct = '';
    public $selectedProductName = '';
    public $selectedProductCode = '';
    public $productQuantity = '';
    public $availableStock = 0;
    public $productSearch = '';
    
    // Transfer Items Cart
    public $transferItems = [];

    // Adjust Modal
    public $adjustWarehouse = '';
    public $adjustType = 'in'; // in ou out
    public $adjustReason = '';
    public $adjustSearch = '';
    
    // Selected product for adjust
    public $adjustSelectedProduct = '';
    public $adjustSelectedProductName = '';
    public $adjustSelectedProductCode = '';
    public $adjustProductQuantity = '';
    public $adjustAvailableStock = 0;
    public $showAdjustQuantityModal = false;
    
    // Adjust Items Cart
    public $adjustItems = [];

    // Filters
    public $search = '';
    public $warehouseFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    /**
     * Painel de confirmação, com a referência do lote e o resumo por produto.
     *
     * Substitui o fecho imediato do modal: quem transferiu tem de ficar com a
     * referência à vista e com o comprovativo a um clique — abri-lo por JS
     * seria bloqueado como popup, por não vir de um gesto directo.
     */
    public $batchReference = null;
    public $batchResumo = [];

    public function openTransferModal()
    {
        abort_unless(auth()->user()?->can('invoicing.warehouse-transfer.create'), 403, 'Sem permissão para criar transferências.');
        $this->reset(['transferFromWarehouse', 'transferToWarehouse', 'transferNotes', 'selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'transferItems', 'productSearch', 'batchReference', 'batchResumo']);
        $this->showTransferModal = true;
    }

    /** Fecha o painel de confirmação e limpa o formulário. */
    public function fecharPainelLote(): void
    {
        $this->reset(['batchReference', 'batchResumo']);
        $this->showTransferModal = false;
        $this->showAdjustModal   = false;
    }

    public function selectProductForTransfer($productId)
    {
        if (!$this->transferFromWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém de origem primeiro.');
            return;
        }

        $product = Product::find($productId);
        $this->selectedProduct = $productId;
        $this->selectedProductName = $product->name;
        $this->selectedProductCode = $product->code;
        
        // Buscar stock disponível
        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->transferFromWarehouse)
            ->where('product_id', $productId)
            ->first();
        
        $this->availableStock = $stock ? $stock->quantity : 0;
        $this->productQuantity = '';
        $this->showQuantityModal = true;
    }

    public function addProductToTransfer()
    {
        if (!$this->transferFromWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém de origem primeiro.');
            return;
        }

        if (!$this->selectedProduct || !$this->productQuantity) {
            $this->dispatch('error', message: 'Selecione um produto e quantidade.');
            return;
        }

        // Quantidade tem de ser numérica e positiva (um valor negativo passava as
        // validações seguintes e INVERTIA a operação: aumentava a origem e
        // diminuía o destino).
        if (!is_numeric($this->productQuantity) || (float) $this->productQuantity <= 0) {
            $this->dispatch('error', message: 'A quantidade deve ser maior que zero.');
            return;
        }

        if ($this->productQuantity > $this->availableStock) {
            $this->dispatch('error', message: 'Quantidade maior que o stock disponível.');
            return;
        }

        // Uma actualização atrasada pode ter deixado uma linha sem produto, e o
        // ciclo abaixo lê `product_id` sem perguntar.
        $this->limparLinhas('transferItems');

        // Verificar se produto já está na lista
        $exists = false;
        foreach ($this->transferItems as $index => $item) {
            if ($item['product_id'] == $this->selectedProduct) {
                $somada = (float) $item['quantity'] + (float) $this->productQuantity;

                $this->transferItems[$index]['quantity']      = $somada;
                $this->transferItems[$index]['ultima_valida'] = $somada;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $product = Product::find($this->selectedProduct);
            $this->transferItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'quantity' => (float) $this->productQuantity,
                // Guardado para a edição no carrinho ter a que repor quando o
                // utilizador apaga o campo ou escreve um disparate.
                'ultima_valida' => (float) $this->productQuantity,
            ];
        }

        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock']);
        $this->showQuantityModal = false;
        $this->dispatch('success', message: 'Produto adicionado à transferência!');
    }

    /**
     * Tira uma linha SEM reindexar as outras.
     *
     * O `array_values` que aqui estava reindexava, e as ligações do formulário
     * são por ÍNDICE — `transferItems.3.quantity`. Uma quantidade escrita e
     * ainda a caminho, com uma linha acima a ser removida ao mesmo tempo, ia
     * parar ao produto errado; ou, se o índice ficasse para lá do fim, o
     * Livewire criava uma linha só com a quantidade e sem `product_id`, e o
     * código seguinte rebentava.
     *
     * Foi o erro visto em produção no ecrã de movimentação de stock. Estes
     * ecrãs têm exactamente a mesma forma.
     */
    public function removeProductFromTransfer($index)
    {
        unset($this->transferItems[$index]);
    }

    /**
     * Deita fora as linhas que o Livewire criou sem produto.
     *
     * Não há nada a aproveitar nelas — nem se sabe de que produto eram.
     */
    private function limparLinhas(string $propriedade): void
    {
        $this->{$propriedade} = array_filter(
            $this->{$propriedade},
            fn ($item) => is_array($item) && !empty($item['product_id'])
        );
    }

    /**
     * Corrige a quantidade de uma linha já no carrinho.
     *
     * Enganar-se a escrever a quantidade é o erro mais banal deste ecrã, e a
     * única saída era apagar a linha e voltar a procurar o artigo no meio de
     * cinco mil. Agora edita-se ali mesmo.
     *
     * A validação é a MESMA de quando se adiciona — tem de ser, senão a
     * correcção seria a porta por onde entrava o que a adição recusa: uma
     * quantidade negativa INVERTE a transferência (aumenta a origem e diminui o
     * destino), e uma quantidade acima do stock só rebentaria lá ao fundo, no
     * gravar, com o modal já fechado.
     *
     * Livewire chama isto sempre que `transferItems.N.quantity` muda.
     */
    public function updatedTransferItems($valor, $chave): void
    {
        [$indice, $campo] = array_pad(explode('.', (string) $chave), 2, null);

        if ($campo !== 'quantity' || !isset($this->transferItems[$indice])) {
            return;
        }

        $item      = $this->transferItems[$indice];
        $anterior  = (float) ($item['ultima_valida'] ?? 1);
        $corrigida = $this->quantidadeValidada($valor, $anterior, $this->disponivelNaOrigem($item['product_id']), $item['product_name']);

        $this->transferItems[$indice]['quantity']      = $corrigida;
        $this->transferItems[$indice]['ultima_valida'] = $corrigida;
    }

    /**
     * Uma quantidade aceitável, ou a que lá estava.
     *
     * Repõe em vez de deixar em branco: uma linha de carrinho sem quantidade é
     * um documento por gravar à espera de rebentar mais à frente. E limita ao
     * disponível em vez de recusar, porque quem escreveu 50 quando há 9 quer
     * transferir o que houver — dizer-lhe quanto é mais útil do que recusar.
     */
    private function quantidadeValidada($valor, float $anterior, ?float $maximo, string $artigo): float
    {
        if (!is_numeric($valor) || (float) $valor <= 0) {
            $this->dispatch('error', message: 'A quantidade deve ser um número maior que zero.');

            return $anterior;
        }

        $nova = (float) $valor;

        if ($maximo !== null && $nova > $maximo) {
            $this->dispatch('error', message: "Só há {$maximo} de {$artigo} neste armazém. Ajustado para o disponível.");

            return $maximo;
        }

        return $nova;
    }

    /** Stock do artigo no armazém de origem, ou null se não houver origem escolhida. */
    private function disponivelNaOrigem($productId): ?float
    {
        if (!$this->transferFromWarehouse) {
            return null;
        }

        return (float) Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->transferFromWarehouse)
            ->where('product_id', $productId)
            ->value('quantity');
    }

    public function openAdjustModal()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        $this->reset(['adjustWarehouse', 'adjustType', 'adjustReason', 'adjustSearch', 'adjustSelectedProduct', 'adjustSelectedProductName', 'adjustSelectedProductCode', 'adjustProductQuantity', 'adjustAvailableStock', 'adjustItems']);
        $this->showAdjustModal = true;
    }

    public function selectProductForAdjust($productId)
    {
        if (!$this->adjustWarehouse) {
            $this->dispatch('error', message: 'Selecione o armazém primeiro.');
            return;
        }

        $product = Product::find($productId);
        $this->adjustSelectedProduct = $productId;
        $this->adjustSelectedProductName = $product->name;
        $this->adjustSelectedProductCode = $product->code;
        
        // Buscar stock atual
        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->adjustWarehouse)
            ->where('product_id', $productId)
            ->first();
        
        $this->adjustAvailableStock = $stock ? $stock->quantity : 0;
        $this->adjustProductQuantity = '';
        $this->showAdjustQuantityModal = true;
    }

    public function addProductToAdjust()
    {
        if (!$this->adjustSelectedProduct || !$this->adjustProductQuantity) {
            $this->dispatch('error', message: 'Selecione um produto e quantidade.');
            return;
        }

        // Quantidade numérica e positiva (negativos invertiam o sentido do ajuste)
        if (!is_numeric($this->adjustProductQuantity) || (float) $this->adjustProductQuantity <= 0) {
            $this->dispatch('error', message: 'A quantidade deve ser maior que zero.');
            return;
        }

        $this->limparLinhas('adjustItems');

        // Verificar se produto já está na lista
        $exists = false;
        foreach ($this->adjustItems as $index => $item) {
            if ($item['product_id'] == $this->adjustSelectedProduct) {
                $this->adjustItems[$index]['quantity']      = (float) $this->adjustProductQuantity;
                $this->adjustItems[$index]['ultima_valida'] = (float) $this->adjustProductQuantity;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $product = Product::find($this->adjustSelectedProduct);
            $this->adjustItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'quantity' => (float) $this->adjustProductQuantity,
                // Guardado para a edição no carrinho ter a que repor quando o
                // utilizador apaga o campo ou escreve um disparate.
                'ultima_valida' => (float) $this->adjustProductQuantity,
            ];
        }

        $this->reset(['adjustSelectedProduct', 'adjustSelectedProductName', 'adjustSelectedProductCode', 'adjustProductQuantity', 'adjustAvailableStock']);
        $this->showAdjustQuantityModal = false;
        $this->dispatch('success', message: 'Produto adicionado ao ajuste!');
    }

    public function removeProductFromAdjust($index)
    {
        unset($this->adjustItems[$index]);
    }

    /**
     * Corrige a quantidade de uma linha já no carrinho de ajuste.
     *
     * O tecto só existe num ajuste de SAÍDA: numa entrada acrescenta-se o que
     * for preciso, e limitar ao stock actual impediria justamente a correcção
     * mais comum, que é dar entrada do que faltava.
     */
    public function updatedAdjustItems($valor, $chave): void
    {
        [$indice, $campo] = array_pad(explode('.', (string) $chave), 2, null);

        if ($campo !== 'quantity' || !isset($this->adjustItems[$indice])) {
            return;
        }

        $item     = $this->adjustItems[$indice];
        $anterior = (float) ($item['ultima_valida'] ?? 1);

        $maximo = $this->adjustType === 'out'
            ? $this->stockNoArmazemDoAjuste($item['product_id'])
            : null;

        $corrigida = $this->quantidadeValidada($valor, $anterior, $maximo, $item['product_name']);

        $this->adjustItems[$indice]['quantity']      = $corrigida;
        $this->adjustItems[$indice]['ultima_valida'] = $corrigida;
    }

    /** Stock do artigo no armazém do ajuste, ou null se não houver armazém escolhido. */
    private function stockNoArmazemDoAjuste($productId): ?float
    {
        if (!$this->adjustWarehouse) {
            return null;
        }

        return (float) Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->adjustWarehouse)
            ->where('product_id', $productId)
            ->value('quantity');
    }

    /**
     * Detalhe de um lote.
     *
     * Procura pela referência MOV/ quando existe. O `reference_id` é um crc32
     * e dois lotes diferentes da mesma empresa podem, em teoria, cair no mesmo
     * inteiro — e aí o detalhe misturava duas movimentações. Continua a servir
     * de recurso para o histórico antigo, que não tem referência nenhuma.
     */
    public function openDetailsModal()
    {
        $consulta = StockMovement::where('tenant_id', activeTenantId())
            ->with(['product', 'warehouse', 'user']);

        if ($this->selectedBatchRef) {
            $consulta->where('batch_reference', $this->selectedBatchRef);
        } elseif ($this->selectedBatchId) {
            $consulta->whereNull('batch_reference')
                     ->where('reference_id', (int) $this->selectedBatchId);
        } else {
            return;
        }

        $this->selectedBatchDetails = $consulta->orderBy('id')->get()->toArray();

        $this->showDetailsModal = true;
    }

    public function updatedSelectedProduct()
    {
        if ($this->selectedProduct && $this->transferFromWarehouse) {
            $stock = Stock::where('tenant_id', activeTenantId())
                ->where('warehouse_id', $this->transferFromWarehouse)
                ->where('product_id', $this->selectedProduct)
                ->first();
            
            $this->availableStock = $stock ? $stock->quantity : 0;
        }
    }

    public function updatedTransferFromWarehouse()
    {
        $this->availableStock = 0;
        if ($this->selectedProduct) {
            $this->updatedSelectedProduct();
        }
    }

    public function saveTransfer()
    {
        abort_unless(auth()->user()?->can('invoicing.warehouse-transfer.create'), 403, 'Sem permissão para criar transferências.');
        if (empty($this->transferItems)) {
            $this->dispatch('error', message: 'Adicione pelo menos um produto à transferência.');
            return;
        }

        if (!$this->transferFromWarehouse || !$this->transferToWarehouse) {
            $this->dispatch('error', message: 'Selecione os armazéns de origem e destino.');
            return;
        }

        try {
            $resumo = [];

            // Referência MOV/AAAA/NNNNNN, sequencial por empresa — a mesma que o
            // ecrã de movimentação de stock usa. Substitui o `crc32(uniqid())`
            // que aqui esteve: era um inteiro opaco, não se dizia ao telefone,
            // não se escrevia num papel e não se procurava no histórico.
            $referencia = null;

            StockMovement::comLoteReservado(activeTenantId(), function (string $ref) use (&$referencia, &$resumo) {
                $referencia = $ref;

                DB::transaction(function () use ($ref, &$resumo) {
                    $warehouseFrom = Warehouse::find($this->transferFromWarehouse);
                    $warehouseTo   = Warehouse::find($this->transferToWarehouse);

                    // Mantido para o histórico antigo continuar a agrupar; a
                    // referência a sério é a $ref acima.
                    $batchId = crc32($ref);

                    foreach ($this->transferItems as $item) {
                        // Reduzir stock origem
                        $stockFrom = Stock::where('tenant_id', activeTenantId())
                            ->where('warehouse_id', $this->transferFromWarehouse)
                            ->where('product_id', $item['product_id'])
                            ->first();

                        if (!$stockFrom || $stockFrom->quantity < $item['quantity']) {
                            throw new \Exception("Stock insuficiente para {$item['product_name']}");
                        }

                        $qtd = (float) $item['quantity'];

                        // Os quatro saldos que dão sentido à transferência: quanto
                        // havia e quanto ficou de cada lado. Lidos ANTES de mexer.
                        $origemAntes  = (float) $stockFrom->quantity;
                        $origemDepois = $origemAntes - $qtd;

                        // save() Eloquent (NÃO increment/decrement): increment/decrement só dispara
                        // updating/updated — nunca saved — logo o StockObserver não ressincronizava
                        // o agregado e o hook saving não recalculava available_quantity.
                        $stockFrom->quantity = $origemDepois;
                        $stockFrom->save();

                        // Aumentar stock destino
                        $stockTo = Stock::firstOrCreate([
                            'tenant_id' => activeTenantId(),
                            'warehouse_id' => $this->transferToWarehouse,
                            'product_id' => $item['product_id'],
                        ], ['quantity' => 0]);

                        $destinoAntes  = (float) $stockTo->quantity;
                        $destinoDepois = $destinoAntes + $qtd;

                        $stockTo->quantity = $destinoDepois;
                        $stockTo->save();

                        $product = Product::find($item['product_id']);

                        // Transferir lotes (FEFO) se o produto rastreia lotes
                        if ($product && $product->track_batches) {
                            $this->transferBatches(
                                $item['product_id'],
                                $this->transferFromWarehouse,
                                $this->transferToWarehouse,
                                $qtd
                            );
                        }

                        // Registrar movimentos sem disparar boot (stock já foi atualizado manualmente)
                        StockMovement::semAplicarStock(function () use ($item, $batchId, $ref, $product, $warehouseTo, $warehouseFrom, $qtd, $origemAntes, $origemDepois, $destinoAntes, $destinoDepois) {
                            // Comum às duas pernas. from/to_warehouse_id NÃO eram
                            // preenchidos, e sem eles carimbarSaldos() não sabe
                            // qual das pernas está a olhar: derivava o saldo
                            // anterior do destino somando em vez de subtrair, e
                            // escrevia um "antes" que nunca existiu.
                            $comum = [
                                'tenant_id'         => activeTenantId(),
                                'product_id'        => $item['product_id'],
                                'type'              => 'transfer',
                                'unit_cost'         => $product->cost,
                                'reference_type'    => 'transfer_batch',
                                'reference_id'      => $batchId,
                                'batch_reference'   => $ref,
                                'from_warehouse_id' => $this->transferFromWarehouse,
                                'to_warehouse_id'   => $this->transferToWarehouse,
                                'user_id'           => auth()->id(),
                            ];

                            // Perna de saída (origem)
                            StockMovement::create($comum + [
                                'warehouse_id'   => $this->transferFromWarehouse,
                                'quantity'       => -$qtd,
                                'balance_before' => $origemAntes,
                                'balance_after'  => $origemDepois,
                                'notes'          => "Transfer para {$warehouseTo->name}. {$this->transferNotes}",
                            ]);

                            // Perna de entrada (destino)
                            StockMovement::create($comum + [
                                'warehouse_id'   => $this->transferToWarehouse,
                                'quantity'       => $qtd,
                                'balance_before' => $destinoAntes,
                                'balance_after'  => $destinoDepois,
                                'notes'          => "Transfer de {$warehouseFrom->name}. {$this->transferNotes}",
                            ]);
                        });

                        $resumo[] = [
                            'produto'        => $item['product_name'],
                            'codigo'         => $item['product_code'] ?? null,
                            'quantidade'     => $qtd,
                            'origem'         => $warehouseFrom->name ?? '—',
                            'origem_antes'   => $origemAntes,
                            'origem_depois'  => $origemDepois,
                            'destino'        => $warehouseTo->name ?? '—',
                            'destino_antes'  => $destinoAntes,
                            'destino_depois' => $destinoDepois,
                        ];
                    }
                });
            });

            $this->batchReference = $referencia;
            $this->batchResumo    = $resumo;

            // O formulário fecha e dá lugar ao comprovativo. O modal não se
            // fecha todo de uma vez como antes: quem transferiu tem de ficar
            // com a referência à vista e com o documento a um clique.
            $this->showTransferModal = false;

            $this->dispatch('success', message: "Transferência {$referencia}: " . count($resumo) . ' produto(s) transferido(s).');
            $this->reset(['transferItems']);

        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function saveAdjust()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        if (empty($this->adjustItems)) {
            $this->dispatch('error', message: 'Adicione pelo menos um produto ao ajuste.');
            return;
        }

        if (!$this->adjustWarehouse || !$this->adjustReason) {
            $this->dispatch('error', message: 'Selecione o armazém e informe o motivo.');
            return;
        }

        try {
            $resumo     = [];
            $referencia = null;

            StockMovement::comLoteReservado(activeTenantId(), function (string $ref) use (&$referencia, &$resumo) {
                $referencia = $ref;

                DB::transaction(function () use ($ref, &$resumo) {
                    $armazem = Warehouse::find($this->adjustWarehouse);
                    $batchId = crc32($ref);

                    foreach ($this->adjustItems as $item) {
                        $product = Product::find($item['product_id']);

                        // Atualizar stock
                        $stock = Stock::firstOrCreate([
                            'tenant_id' => activeTenantId(),
                            'warehouse_id' => $this->adjustWarehouse,
                            'product_id' => $item['product_id'],
                        ], ['quantity' => 0]);

                        $qtd   = (float) $item['quantity'];
                        $antes = (float) $stock->quantity;

                        // save() Eloquent (dispara StockObserver + recálculo de available_quantity);
                        // no ajuste de saída, validar saldo — antes era possível negativar o armazém.
                        if ($this->adjustType == 'in') {
                            $depois = $antes + $qtd;
                        } else {
                            if ($antes < $qtd) {
                                $name = $item['product_name'] ?? ('#' . $item['product_id']);
                                throw new \Exception("Stock insuficiente para {$name} (disponível: {$antes})");
                            }
                            $depois = $antes - $qtd;
                        }

                        $stock->quantity = $depois;
                        $stock->save();

                        // Registrar movimento sem disparar boot (stock já foi atualizado manualmente)
                        StockMovement::semAplicarStock(function () use ($item, $batchId, $ref, $product, $qtd, $antes, $depois) {
                            StockMovement::create([
                                'tenant_id'       => activeTenantId(),
                                'warehouse_id'    => $this->adjustWarehouse,
                                'product_id'      => $item['product_id'],
                                'type'            => 'adjustment',
                                'quantity'        => $this->adjustType == 'in' ? $qtd : -$qtd,
                                // Num ajuste o saldo anterior não se deriva da
                                // quantidade — tem de vir de quem o gravou. Sem
                                // ele a linha do histórico é ilegível: não se
                                // sabe se "18" subiu ou desceu, nem de quanto.
                                'balance_before'  => $antes,
                                'balance_after'   => $depois,
                                'unit_cost'       => $product->cost,
                                'reference_type'  => 'adjustment_batch',
                                'reference_id'    => $batchId,
                                'batch_reference' => $ref,
                                'notes'           => "Ajuste manual ({$this->adjustType}): {$this->adjustReason}",
                                'user_id'         => auth()->id(),
                            ]);
                        });

                        $resumo[] = [
                            'produto'    => $item['product_name'],
                            'codigo'     => $item['product_code'] ?? null,
                            'quantidade' => $qtd,
                            'armazem'    => $armazem->name ?? '—',
                            'antes'      => $antes,
                            'depois'     => $depois,
                        ];
                    }
                });
            });

            $this->batchReference = $referencia;
            $this->batchResumo    = $resumo;
            $this->showAdjustModal = false;

            $this->dispatch('success', message: "Ajuste {$referencia}: " . count($resumo) . ' produto(s) ajustado(s).');
            $this->reset(['adjustItems']);

        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Erro: ' . $e->getMessage());
        }
    }

    private function transferBatches($productId, $fromWarehouseId, $toWarehouseId, $quantity)
    {
        $remaining = $quantity;

        // FEFO: buscar lotes do armazém origem, ordenados por validade (mais próximo primeiro)
        $sourceBatches = ProductBatch::where('tenant_id', activeTenantId())
            ->where('product_id', $productId)
            ->where('warehouse_id', $fromWarehouseId)
            ->where('quantity_available', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($sourceBatches as $sourceBatch) {
            if ($remaining <= 0) break;

            $take = min($remaining, $sourceBatch->quantity_available);

            // Diminuir quantidade no lote origem
            $sourceBatch->quantity_available -= $take;
            $sourceBatch->updateStatus();

            // Encontrar ou criar lote no armazém destino
            $destBatch = ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $productId)
                ->where('warehouse_id', $toWarehouseId)
                ->where('batch_number', $sourceBatch->batch_number)
                ->first();

            if ($destBatch) {
                $destBatch->quantity += $take;
                $destBatch->quantity_available += $take;
                $destBatch->updateStatus();
            } else {
                ProductBatch::create([
                    'tenant_id' => activeTenantId(),
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                    'batch_number' => $sourceBatch->batch_number,
                    'manufacturing_date' => $sourceBatch->manufacturing_date,
                    'expiry_date' => $sourceBatch->expiry_date,
                    'quantity' => $take,
                    'quantity_available' => $take,
                    'cost_price' => $sourceBatch->cost_price,
                    'alert_days' => $sourceBatch->alert_days,
                    'status' => 'active',
                    'notes' => 'Transferido de armazém',
                ]);
            }

            $remaining -= $take;
        }
    }

    /**
     * Artigos mostrados de cada vez na grelha de escolha.
     *
     * A grelha tem três colunas e altura fixa com deslocamento; cinquenta
     * cartões já são mais do que alguém percorre antes de escrever no campo de
     * pesquisa.
     */
    private const ARTIGOS_POR_ECRA = 50;

    /**
     * Os artigos da grelha, e o stock deles no armazém em causa.
     *
     * ESTE MÉTODO É A RAZÃO DE O ECRÃ TER FICADO USÁVEL. O que aqui estava
     * carregava o catálogo INTEIRO — 5.729 artigos na Farmácia Luk Simões — em
     * cada render, filtrava-o em PHP, e a vista fazia mais uma consulta de
     * stock POR ARTIGO. Dava perto de 5.730 consultas e 5.729 cartões no ecrã
     * a cada tecla premida no campo de pesquisa.
     *
     * Agora são duas coisas: a pesquisa é feita em SQL com limite, e o stock
     * vem na MESMA consulta por subconsulta correlacionada. Uma consulta, no
     * máximo cinquenta linhas.
     *
     * A subconsulta e não um JOIN de propósito: o filtro por empresa é um
     * global scope que escreve `where tenant_id = ?` sem qualificar a tabela, e
     * `invoicing_stocks` também tem essa coluna — com JOIN o MySQL recusa a
     * consulta por ambiguidade.
     */
    private function artigosParaEscolher()
    {
        // Nenhum dos modais aberto: a listagem do histórico não precisa de
        // artigo nenhum. Antes carregava o catálogo à mesma, só para o deitar
        // fora — e isso sozinho tornava lenta a página de entrada.
        $paraTransferir = $this->showTransferModal || $this->showQuantityModal;
        $paraAjustar    = $this->showAdjustModal || $this->showAdjustQuantityModal;

        if (!$paraTransferir && !$paraAjustar) {
            return collect();
        }

        $armazem = $paraTransferir ? $this->transferFromWarehouse : $this->adjustWarehouse;
        $termo   = trim($paraTransferir ? $this->productSearch : $this->adjustSearch);

        // Nomes de tabela pedidos aos modelos e não escritos à mão: `products`
        // e `stocks` parecem os nomes certos e nenhum deles é.
        $tArtigos = (new Product)->getTable();
        $tStock   = (new Stock)->getTable();

        $consulta = Product::where('is_active', true)
            ->select('id', 'name', 'code', 'barcode', 'unit')
            ->orderBy('name')
            ->limit(self::ARTIGOS_POR_ECRA);

        if ($armazem) {
            $consulta->addSelect([
                'stock_no_armazem' => Stock::select('quantity')
                    ->whereColumn('product_id', "{$tArtigos}.id")
                    ->where('warehouse_id', $armazem)
                    ->limit(1),
            ]);
        }

        if ($termo !== '') {
            // Código de barras incluído: numa farmácia lê-se o artigo pelo
            // leitor, e a pesquisa antiga só olhava para o nome e o código.
            $consulta->where(function ($q) use ($termo) {
                $q->where('name', 'like', "%{$termo}%")
                  ->orWhere('code', 'like', "%{$termo}%")
                  ->orWhere('barcode', 'like', "%{$termo}%");
            });
        } elseif ($paraTransferir && $armazem) {
            // Sem pesquisa, numa transferência, só interessam os artigos que
            // EXISTEM na origem — os outros não se podem transferir. Sem isto,
            // os cinquenta primeiros por ordem alfabética eram quase todos
            // artigos a zero e a grelha parecia vazia de coisas úteis.
            $consulta->whereExists(function ($q) use ($armazem, $tArtigos, $tStock) {
                $q->select(DB::raw(1))
                  ->from($tStock)
                  ->whereColumn("{$tStock}.product_id", "{$tArtigos}.id")
                  ->where("{$tStock}.warehouse_id", $armazem)
                  ->where("{$tStock}.quantity", '>', 0);
            });
        }

        return $consulta->get();
    }

    public function render()
    {
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $products = $this->artigosParaEscolher();

        // Histórico de movimentos - Agrupar por lote (reference_id)
        $query = StockMovement::where('tenant_id', activeTenantId())
            ->whereIn('type', ['transfer', 'adjustment'])
            ->select(
                'reference_id',
                'reference_type',
                DB::raw('MAX(id) as id'),
                DB::raw('MAX(tenant_id) as tenant_id'),
                DB::raw('MAX(warehouse_id) as warehouse_id'),
                DB::raw('MAX(product_id) as product_id'),
                DB::raw('MAX(type) as type'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('MAX(notes) as notes'),
                DB::raw('MAX(created_at) as created_at'),
                DB::raw('MAX(batch_reference) as batch_reference'),
                DB::raw('COUNT(*) as products_count'),
                DB::raw('SUM(ABS(quantity)) as total_quantity')
            );

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->search) {
            $query->whereHas('product', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Agrupar por reference_id para mostrar apenas um registro por lote
        $movements = $query->groupBy('reference_id', 'reference_type')
              ->orderBy('created_at', 'desc')
              ->paginate(15);
              
        // Carregar relacionamentos após a query
        $movements->load(['warehouse', 'product', 'user']);

        return view('livewire.invoicing.warehouse-transfer.warehouse-transfer', [
            'warehouses' => $warehouses,
            'products' => $products,
            'movements' => $movements,
        ]);
    }
}

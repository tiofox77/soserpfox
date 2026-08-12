<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Transferências Inter-Empresas')]
class InterCompanyTransfer extends Component
{
    use WithPagination;

    public $showTransferModal = false;

    // Warehouses & Tenant
    public $warehouseFromId = '';
    public $tenantToId = '';
    public $warehouseToId = '';
    public $notes = '';

    // Product search
    public $productSearch = '';

    // Quantity modal
    public $showQuantityModal = false;
    public $selectedProduct = '';
    public $selectedProductName = '';
    public $selectedProductCode = '';
    public $productQuantity = '';
    public $availableStock = 0;
    public $selectedUnitCost = 0;

    // Cart
    public $transferItems = [];

    /**
     * Comprovativo da transferência acabada de registar.
     *
     * São DUAS referências porque são dois documentos: cada empresa fica com o
     * seu, na sua própria sequência. O da empresa destino é dado a conhecer mas
     * não é abrível a partir daqui — pertence à outra empresa.
     */
    public $batchReference = null;
    public $batchReferenceDestino = null;
    public $batchDestinoNome = null;
    public $batchResumo = [];

    /** Fecha o comprovativo e limpa o formulário. */
    public function fecharPainelLote(): void
    {
        $this->reset(['batchReference', 'batchReferenceDestino', 'batchDestinoNome', 'batchResumo']);
        $this->showTransferModal = false;
    }

    /** Artigos mostrados de cada vez na grelha de escolha. */
    private const ARTIGOS_POR_ECRA = 50;

    /**
     * Os artigos da grelha, e o stock deles no armazém de origem.
     *
     * O que aqui estava carregava TODOS os artigos com stock e, com
     * `with('stocks')`, todas as linhas de stock de cada um — de todos os
     * armazéns. Numa farmácia com 5.729 artigos são dezenas de milhares de
     * linhas em memória a cada render, e a pesquisa era feita em PHP sobre
     * essa colecção, ou seja nunca chegava à base.
     *
     * Agora: uma consulta, no máximo cinquenta linhas, com o disponível do
     * armazém de origem por subconsulta correlacionada. Subconsulta e não JOIN
     * porque o filtro por empresa é um global scope que não qualifica a tabela
     * e `invoicing_stocks` também tem `tenant_id` — com JOIN o MySQL recusa por
     * ambiguidade.
     */
    private function artigosParaEscolher()
    {
        // Nenhum modal aberto: a listagem do histórico não precisa de artigos.
        if (!$this->showTransferModal && !$this->showQuantityModal) {
            return collect();
        }

        // Nomes de tabela pedidos aos modelos e não escritos à mão.
        $tArtigos = (new Product)->getTable();
        $tStock   = (new Stock)->getTable();

        $termo = trim($this->productSearch);

        $consulta = Product::where('is_active', true)
            ->select('id', 'name', 'code', 'barcode', 'unit')
            ->orderBy('name')
            ->limit(self::ARTIGOS_POR_ECRA);

        if ($this->warehouseFromId) {
            $consulta->addSelect([
                // `available_quantity` e não `quantity`: é o que se pode mesmo
                // transferir, e é o que a validação usa.
                'disponivel_na_origem' => Stock::select('available_quantity')
                    ->whereColumn('product_id', "{$tArtigos}.id")
                    ->where('warehouse_id', $this->warehouseFromId)
                    ->limit(1),
            ]);
        }

        if ($termo !== '') {
            // Código de barras incluído: numa farmácia lê-se pelo leitor.
            $consulta->where(function ($q) use ($termo) {
                $q->where('name', 'like', "%{$termo}%")
                  ->orWhere('code', 'like', "%{$termo}%")
                  ->orWhere('barcode', 'like', "%{$termo}%");
            });
        } elseif ($this->warehouseFromId) {
            // Sem pesquisa só interessa o que EXISTE na origem — o resto não se
            // transfere. Sem isto, os cinquenta primeiros por ordem alfabética
            // eram quase todos artigos a zero.
            $consulta->whereExists(function ($q) use ($tArtigos, $tStock) {
                $q->select(DB::raw(1))
                  ->from($tStock)
                  ->whereColumn("{$tStock}.product_id", "{$tArtigos}.id")
                  ->where("{$tStock}.warehouse_id", $this->warehouseFromId)
                  ->where("{$tStock}.quantity", '>', 0);
            });
        }

        return $consulta->get();
    }

    public function render()
    {
        $myTenants = auth()->user()->tenants()
            ->where('tenants.id', '!=', activeTenantId())
            ->get();

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $products = $this->artigosParaEscolher();

        $transfers = StockMovement::where('tenant_id', activeTenantId())
            ->where('reference_type', 'inter_company')
            ->whereIn('type', ['transfer', 'in'])
            ->with([
                'product',
                'warehouse',
                'toWarehouse' => fn($q) => $q->withoutGlobalScope('tenant'),
                'fromWarehouse' => fn($q) => $q->withoutGlobalScope('tenant'),
                'user',
            ])
            ->latest()
            ->paginate(10);

        return view('livewire.invoicing.stock.inter-company-transfer', [
            'myTenants' => $myTenants,
            'warehouses' => $warehouses,
            'products' => $products,
            'transfers' => $transfers,
        ]);
    }

    public function updatedTenantToId()
    {
        $this->warehouseToId = '';
    }

    public function getWarehousesForTenant()
    {
        if (!$this->tenantToId) {
            return collect();
        }

        return Warehouse::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantToId)
            ->where('is_active', true)
            ->get();
    }

    public function openModal()
    {
        $this->resetForm();
        // Limpar o comprovativo da anterior: sem isto, abrir uma nova
        // transferência mostrava o recibo da última por cima do formulário.
        $this->reset(['batchReference', 'batchReferenceDestino', 'batchDestinoNome', 'batchResumo']);
        $this->showTransferModal = true;
    }

    public function selectProduct($productId)
    {
        if (!$this->warehouseFromId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém de origem primeiro.']);
            return;
        }

        $product = Product::find($productId);
        if (!$product) return;

        $this->selectedProduct = $productId;
        $this->selectedProductName = $product->name;
        $this->selectedProductCode = $product->code;

        $stock = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->warehouseFromId)
            ->where('product_id', $productId)
            ->first();

        $this->availableStock = $stock ? $stock->available_quantity : 0;
        $this->selectedUnitCost = $stock ? $stock->unit_cost : 0;
        $this->productQuantity = '';
        $this->showQuantityModal = true;
    }

    public function addProductToTransfer()
    {
        if (!$this->selectedProduct || !$this->productQuantity || $this->productQuantity <= 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione um produto e quantidade válida.']);
            return;
        }

        if ($this->productQuantity > $this->availableStock) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Quantidade maior que o stock disponível.']);
            return;
        }

        // Check if product already in cart
        $exists = false;
        // Uma actualização atrasada pode ter deixado uma linha sem produto, e o
        // ciclo abaixo lê `product_id` sem perguntar.
        $this->limparLinhas();

        foreach ($this->transferItems as $index => $item) {
            if ($item['product_id'] == $this->selectedProduct) {
                $newQty = (float) $this->transferItems[$index]['quantity'] + (float) $this->productQuantity;
                if ($newQty > $this->availableStock) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => 'Quantidade total excede o stock disponível.']);
                    return;
                }
                $this->transferItems[$index]['quantity']      = $newQty;
                $this->transferItems[$index]['ultima_valida'] = $newQty;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $this->transferItems[] = [
                'product_id' => $this->selectedProduct,
                'product_name' => $this->selectedProductName,
                'product_code' => $this->selectedProductCode,
                'quantity' => (float) $this->productQuantity,
                'unit_cost' => $this->selectedUnitCost,
                // Guardado para a edição no carrinho ter a que repor quando o
                // utilizador apaga o campo ou escreve um disparate.
                'ultima_valida' => (float) $this->productQuantity,
            ];
        }

        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'selectedUnitCost']);
        $this->showQuantityModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Produto adicionado!']);
    }

    /**
     * Tira uma linha SEM reindexar as outras.
     *
     * O `array_values` reindexava, e as ligações são por ÍNDICE. Uma
     * quantidade a caminho, com uma linha acima a ser removida, ia parar ao
     * produto errado — ou criava uma linha sem `product_id` que rebentava a
     * seguir. Foi o erro visto em produção no ecrã de movimentação de stock,
     * e este tem a mesma forma.
     */
    public function removeProduct($index)
    {
        unset($this->transferItems[$index]);
    }

    /** Deita fora as linhas que o Livewire criou sem produto. */
    private function limparLinhas(): void
    {
        $this->transferItems = array_filter(
            $this->transferItems,
            fn ($item) => is_array($item) && !empty($item['product_id'])
        );
    }

    /**
     * Corrige a quantidade de uma linha já no carrinho.
     *
     * A única saída para uma quantidade errada era apagar a linha e voltar a
     * procurar o artigo. A validação é a MESMA da adição, e tem de ser: uma
     * quantidade negativa numa transferência entre empresas tira stock ao
     * destino e dá-o à origem — o contrário do que se pediu, em duas empresas
     * ao mesmo tempo.
     */
    public function updatedTransferItems($valor, $chave): void
    {
        [$indice, $campo] = array_pad(explode('.', (string) $chave), 2, null);

        if ($campo !== 'quantity' || !isset($this->transferItems[$indice])) {
            return;
        }

        $item     = $this->transferItems[$indice];
        $anterior = (float) ($item['ultima_valida'] ?? 1);

        if (!is_numeric($valor) || (float) $valor <= 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'A quantidade deve ser um número maior que zero.']);
            $nova = $anterior;
        } else {
            $nova       = (float) $valor;
            $disponivel = $this->disponivelNaOrigem($item['product_id']);

            if ($disponivel !== null && $nova > $disponivel) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => "Só há {$disponivel} de {$item['product_name']} neste armazém. Ajustado para o disponível.",
                ]);
                $nova = $disponivel;
            }
        }

        $this->transferItems[$indice]['quantity']      = $nova;
        $this->transferItems[$indice]['ultima_valida'] = $nova;
    }

    /** Disponível do artigo no armazém de origem, ou null sem origem escolhida. */
    private function disponivelNaOrigem($productId): ?float
    {
        if (!$this->warehouseFromId) {
            return null;
        }

        return (float) Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->warehouseFromId)
            ->where('product_id', $productId)
            ->value('available_quantity');
    }

    public function saveTransfer()
    {
        if (!$this->warehouseFromId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém de origem.']);
            return;
        }
        if (!$this->tenantToId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione a empresa destino.']);
            return;
        }
        if (!$this->warehouseToId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecione o armazém destino.']);
            return;
        }
        if (empty($this->transferItems)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Adicione pelo menos um produto.']);
            return;
        }
        if (!$this->notes) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Informe o motivo da transferência.']);
            return;
        }

        try {
            // Resolver o destino a partir das empresas DO UTILIZADOR: um id
            // arbitrário permitia escrever stock/produtos em qualquer empresa
            // da plataforma.
            $tenantTo = auth()->user()->tenants()
                ->where('tenants.id', $this->tenantToId)
                ->where('tenants.id', '!=', activeTenantId())
                ->first();
            if (!$tenantTo) {
                throw new \Exception('Empresa destino inválida ou sem acesso.');
            }

            // DUAS referências, uma por empresa, e não uma partilhada.
            //
            // MOV/AAAA/NNNNNN é sequencial POR EMPRESA. Escrever a referência
            // da origem nas linhas do destino corrompia a sequência do destino:
            // se o destino já tivesse ido mais longe, passavam a existir lá
            // dois documentos com o mesmo número — e o ecrã do documento
            // juntava-os num só. Cada empresa recebe o seu número na sua
            // sequência, e o `reference_id` comum é o que os liga.
            $refOrigem  = null;
            $refDestino = null;

            StockMovement::comLoteReservado(activeTenantId(), function (string $rOrigem) use (&$refOrigem, &$refDestino) {
                $refOrigem = $rOrigem;

                StockMovement::comLoteReservado($this->tenantToId, function (string $rDestino) use (&$refDestino) {
                    $refDestino = $rDestino;
                });
            });

            $batchId = time() . rand(1000, 9999);
            $originTenantName = activeTenant()->name;
            $resumo = [];

            // DB::transaction() e não beginTransaction/rollBack à mão.
            //
            // A validação da empresa destino passou para ANTES da transacção, e
            // com isso o `DB::rollBack()` do catch ficou sem par: quando a
            // validação recusava, revertia a transacção de QUEM CHAMOU. Nos
            // testes isso apagava tudo o que o teste tinha criado; em produção
            // reverteria o que estivesse aberto à volta. Com o closure é o
            // Laravel que trata do nível e só desfaz o que abriu.
            DB::transaction(function () use ($tenantTo, $batchId, $originTenantName, $refOrigem, $refDestino, &$resumo) {
                foreach ($this->transferItems as $item) {
                $sourceProduct = Product::find($item['product_id']);
                if (!$sourceProduct) {
                    throw new \Exception('Produto não encontrado: ' . $item['product_name']);
                }

                $unitCost = $item['unit_cost'] ?? 0;

                // 1. Encontrar ou criar o produto no tenant destino
                $destProduct = $this->findOrCreateProductInTenant($sourceProduct, $this->tenantToId);

                // 2. Remover stock da empresa origem, guardando os saldos.
                $origemAntes  = (float) Stock::where('tenant_id', activeTenantId())
                    ->where('warehouse_id', $this->warehouseFromId)
                    ->where('product_id', $sourceProduct->id)
                    ->value('quantity');

                Stock::removeStock($this->warehouseFromId, $sourceProduct->id, $item['quantity']);

                $origemDepois = $origemAntes - (float) $item['quantity'];

                // 3. Adicionar stock na empresa destino (usando o product_id do destino)
                $destStock = Stock::withoutGlobalScope('tenant')
                    ->where('tenant_id', $this->tenantToId)
                    ->where('warehouse_id', $this->warehouseToId)
                    ->where('product_id', $destProduct->id)
                    ->first();

                $destinoAntes = (float) ($destStock->quantity ?? 0);

                if ($destStock) {
                    if ($unitCost > 0) {
                        $totalCost = ($destStock->quantity * $destStock->unit_cost) + ($item['quantity'] * $unitCost);
                        $totalQty = $destStock->quantity + $item['quantity'];
                        $destStock->unit_cost = $totalQty > 0 ? $totalCost / $totalQty : $unitCost;
                    }
                    $destStock->quantity += $item['quantity'];
                    $destStock->save();
                } else {
                    $newStock = new Stock();
                    $newStock->tenant_id = $this->tenantToId;
                    $newStock->warehouse_id = $this->warehouseToId;
                    $newStock->product_id = $destProduct->id;
                    $newStock->quantity = $item['quantity'];
                    $newStock->reserved_quantity = 0;
                    $newStock->unit_cost = $unitCost;

                    // save() e NÃO saveQuietly(). O saveQuietly que aqui estava
                    // saltava o StockObserver, que é quem mantém
                    // `invoicing_products.stock_quantity` igual à soma das
                    // linhas. Resultado: um artigo que chegava pela primeira vez
                    // à empresa destino ficava com linha de stock preenchida e
                    // agregado a zero — invisível no POS e na gestão de stock,
                    // que é exactamente a divergência que este sistema já pagou
                    // caro. O observer resolve o `tenant_id` da própria linha,
                    // por isso funciona entre empresas.
                    $newStock->save();
                }

                $destinoDepois = $destinoAntes + (float) $item['quantity'];

                // 4. Transferir lotes se o produto rastreia lotes
                if ($sourceProduct->track_batches) {
                    $this->transferBatchesInterCompany(
                        $sourceProduct->id,
                        $destProduct->id,
                        $this->warehouseFromId,
                        $this->warehouseToId,
                        $this->tenantToId,
                        $item['quantity']
                    );
                }

                // 5. Registrar movimentos sem disparar boot
                //
                // Os saldos vão EXPLÍCITOS nas duas pernas. Deixá-los derivar
                // não funciona aqui: o carimbo automático lê o stock com
                // `Stock::where(...)`, que traz o global scope da empresa
                // ACTIVA — a origem. Para a perna do destino a leitura não
                // devolvia nada e o movimento ficava sem saldo nenhum.
                StockMovement::semAplicarStock(function () use (
                    $item, $tenantTo, $unitCost, $batchId, $sourceProduct, $destProduct, $originTenantName,
                    $refOrigem, $refDestino, $origemAntes, $origemDepois, $destinoAntes, $destinoDepois
                ) {
                    // Movimento saída (origem) - usa product_id da origem
                    StockMovement::create([
                        'tenant_id' => activeTenantId(),
                        'warehouse_id' => $this->warehouseFromId,
                        'product_id' => $sourceProduct->id,
                        'type' => 'transfer',
                        'quantity' => -$item['quantity'],
                        'balance_before' => $origemAntes,
                        'balance_after' => $origemDepois,
                        'batch_reference' => $refOrigem,
                        'from_warehouse_id' => $this->warehouseFromId,
                        'unit_cost' => $unitCost,
                        'reference_type' => 'inter_company',
                        'reference_id' => $batchId,
                        'to_warehouse_id' => $this->warehouseToId,
                        'user_id' => auth()->id(),
                        'notes' => $this->notes . ' (Transferido para: ' . $tenantTo->name . ')',
                    ]);

                    // Movimento entrada (destino) - usa product_id do destino
                    StockMovement::create([
                        'tenant_id' => $this->tenantToId,
                        'warehouse_id' => $this->warehouseToId,
                        'product_id' => $destProduct->id,
                        'type' => 'in',
                        'quantity' => $item['quantity'],
                        'balance_before' => $destinoAntes,
                        'balance_after' => $destinoDepois,
                        'batch_reference' => $refDestino,
                        'to_warehouse_id' => $this->warehouseToId,
                        'unit_cost' => $unitCost,
                        'reference_type' => 'inter_company',
                        'reference_id' => $batchId,
                        'from_warehouse_id' => $this->warehouseFromId,
                        'user_id' => auth()->id(),
                        'notes' => $this->notes . ' (Recebido de: ' . $originTenantName . ')',
                    ]);
                });

                    $resumo[] = [
                        'produto'        => $item['product_name'],
                        'codigo'         => $item['product_code'] ?? null,
                        'quantidade'     => (float) $item['quantity'],
                        'origem_antes'   => $origemAntes,
                        'origem_depois'  => $origemDepois,
                        'destino_antes'  => $destinoAntes,
                        'destino_depois' => $destinoDepois,
                    ];
                }
            });

            $count = count($this->transferItems);
            $this->dispatch('notify', ['type' => 'success', 'message' => $count . ' produto(s) transferido(s) para ' . $tenantTo->name . '!']);

            // O formulário dá lugar ao comprovativo: quem transferiu tem de
            // ficar com as referências à vista e com o documento a um clique.
            // Guardado ANTES do resetForm, que limpa o carrinho.
            $this->batchResumo           = $resumo;
            $this->batchReference        = $refOrigem;
            $this->batchReferenceDestino = $refDestino;
            $this->batchDestinoNome      = $tenantTo->name;

            $this->resetForm();
        } catch (\Throwable $e) {
            // Sem rollBack à mão: o DB::transaction() acima já desfez o que
            // abriu, e chamá-lo aqui reverteria a transacção de quem chamou.
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        }
    }

    private function findOrCreateProductInTenant(Product $sourceProduct, $tenantId): Product
    {
        // Procurar produto existente no tenant destino por nome exacto
        $existing = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($sourceProduct) {
                $q->where('name', $sourceProduct->name);
                if ($sourceProduct->sku) {
                    $q->orWhere('sku', $sourceProduct->sku);
                }
                if ($sourceProduct->barcode) {
                    $q->orWhere('barcode', $sourceProduct->barcode);
                }
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        // Criar cópia do produto no tenant destino
        $newProduct = new Product();
        $newProduct->tenant_id = $tenantId;
        $newProduct->name = $sourceProduct->name;
        $newProduct->description = $sourceProduct->description;
        $newProduct->type = $sourceProduct->type ?? 'produto';
        $newProduct->sku = $sourceProduct->sku;
        $newProduct->barcode = $sourceProduct->barcode;
        $newProduct->price = $sourceProduct->price;
        $newProduct->cost = $sourceProduct->cost;
        $newProduct->tax_type = $sourceProduct->tax_type;
        $newProduct->tax_rate_id = $sourceProduct->tax_rate_id;
        $newProduct->exemption_reason = $sourceProduct->exemption_reason;
        $newProduct->unit = $sourceProduct->unit;
        $newProduct->manage_stock = $sourceProduct->manage_stock;
        $newProduct->track_batches = $sourceProduct->track_batches;
        $newProduct->track_expiry = $sourceProduct->track_expiry;
        $newProduct->is_active = true;
        $newProduct->featured_image = $sourceProduct->featured_image;
        $newProduct->save();

        return $newProduct;
    }

    private function transferBatchesInterCompany($sourceProductId, $destProductId, $fromWarehouseId, $toWarehouseId, $tenantToId, $quantity)
    {
        $remaining = $quantity;

        // FEFO: buscar lotes do armazém origem, ordenados por validade
        $sourceBatches = ProductBatch::where('tenant_id', activeTenantId())
            ->where('product_id', $sourceProductId)
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

            // Criar lote no tenant destino (sem global scope)
            $destBatch = ProductBatch::withoutGlobalScopes()
                ->where('tenant_id', $tenantToId)
                ->where('product_id', $destProductId)
                ->where('warehouse_id', $toWarehouseId)
                ->where('batch_number', $sourceBatch->batch_number)
                ->first();

            if ($destBatch) {
                $destBatch->quantity += $take;
                $destBatch->quantity_available += $take;
                $destBatch->updateStatus();
            } else {
                $newBatch = new ProductBatch();
                $newBatch->tenant_id = $tenantToId;
                $newBatch->product_id = $destProductId;
                $newBatch->warehouse_id = $toWarehouseId;
                $newBatch->batch_number = $sourceBatch->batch_number;
                $newBatch->manufacturing_date = $sourceBatch->manufacturing_date;
                $newBatch->expiry_date = $sourceBatch->expiry_date;
                $newBatch->quantity = $take;
                $newBatch->quantity_available = $take;
                $newBatch->cost_price = $sourceBatch->cost_price;
                $newBatch->alert_days = $sourceBatch->alert_days;
                $newBatch->status = 'active';
                $newBatch->notes = 'Transferência inter-empresas';
                $newBatch->save();
            }

            $remaining -= $take;
        }
    }

    private function resetForm()
    {
        $this->warehouseFromId = '';
        $this->tenantToId = '';
        $this->warehouseToId = '';
        $this->notes = '';
        $this->productSearch = '';
        $this->transferItems = [];
        $this->reset(['selectedProduct', 'selectedProductName', 'selectedProductCode', 'productQuantity', 'availableStock', 'selectedUnitCost']);
        $this->resetErrorBag();
    }
}

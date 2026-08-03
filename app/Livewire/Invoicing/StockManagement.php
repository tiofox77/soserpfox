<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Stock')]
class StockManagement extends Component
{
    use WithPagination;

    public $showAdjustModal = false;
    public $showTransferModal = false;
    public $showMovementsModal = false;
    public $showEntryModal = false;

    // Filters
    public $search = '';
    public $warehouseFilter = '';
    public $lowStockFilter = false;
    public $perPage = 15;

    // Entry Form (entrada de stock em LOTE — cria/incrementa linhas em invoicing_stocks)
    public $entryProductSearch = '';
    public $entryWarehouseId = '';
    public $entryNotes = '';
    /**
     * Lista de produtos da entrada. Cada item:
     *   ['product_id', 'product_name', 'product_code', 'unit', 'quantity', 'unit_cost']
     */
    public array $entryItems = [];

    /**
     * Resultado da última movimentação gravada — é o que faz o modal mostrar o
     * painel de sucesso com o documento em vez de fechar sem deixar rasto.
     */
    public ?string $batchReference = null;
    public int $batchOk = 0;
    public array $batchErrors = [];

    // Adjust Form
    public $adjustStockId;
    public $adjustWarehouseId;
    public $adjustProductId;
    public $adjustProductName;
    public $adjustCurrentQty = 0;
    public $adjustNewQty;
    public $adjustNotes;

    // Transfer Form
    public $transferProductId;
    public $transferProductName;
    public $transferFromWarehouse;
    public $transferToWarehouse;
    public $transferQuantity;
    public $transferNotes;
    public $transferMaxQty = 0;

    // Movements
    public $movementsProductId;
    public $movementsProductName;

    protected $queryString = ['search', 'warehouseFilter'];

    public function render()
    {
        $query = Stock::where('tenant_id', activeTenantId())
            ->whereHas('product')
            ->whereHas('warehouse')
            ->with(['warehouse', 'product']);

        // Warehouse Filter
        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        // Search
        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        // Low Stock Filter
        if ($this->lowStockFilter) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min');
            });
        }

        $stocks = $query->paginate($this->perPage);

        $tenantId = activeTenantId();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        // Stats — uma única query agregada (em vez de 4) + 1 query para low_stock.
        // Reduz substancialmente a latência da página.
        $agg = Stock::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(DISTINCT product_id) AS total_products,
                COALESCE(SUM(quantity), 0)               AS total_quantity,
                COALESCE(SUM(quantity * unit_cost), 0)   AS total_value
            ')
            ->first();

        // Usa query builder directo para evitar a ambiguidade introduzida
        // pelo global scope BelongsToTenant (`tenant_id = X` sem qualificar a tabela)
        // quando se faz JOIN com invoicing_products (que também tem tenant_id).
        $lowStockCount = \DB::table('invoicing_stocks')
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
            ->where('invoicing_stocks.tenant_id', $tenantId)
            ->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min')
            ->count();

        $stats = [
            'total_products' => (int) ($agg->total_products ?? 0),
            'total_quantity' => (float) ($agg->total_quantity ?? 0),
            'total_value'    => (float) ($agg->total_value ?? 0),
            'low_stock'      => (int) $lowStockCount,
        ];

        return view('livewire.invoicing.stock.stock-management', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    public function openAdjustModal($stockId)
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        $stock = Stock::where('tenant_id', activeTenantId())
            ->with('product')
            ->findOrFail($stockId);

        $this->adjustStockId = $stock->id;
        $this->adjustWarehouseId = $stock->warehouse_id;
        $this->adjustProductId = $stock->product_id;
        $this->adjustProductName = $stock->product->name;
        $this->adjustCurrentQty = (int) $stock->quantity;
        $this->adjustNewQty = (int) $stock->quantity;
        $this->adjustNotes = '';

        $this->showAdjustModal = true;
    }

    public function saveAdjustment()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        $this->validate([
            'adjustNewQty' => 'required|numeric|min:0',
            'adjustNotes' => 'nullable|string|max:500',
        ]);

        StockMovement::createAdjustment(
            $this->adjustWarehouseId,
            $this->adjustProductId,
            $this->adjustNewQty,
            $this->adjustNotes
        );

        session()->flash('message', 'Stock ajustado com sucesso!');
        $this->showAdjustModal = false;
        $this->resetAdjustForm();
    }

    public function openTransferModal($stockId)
    {
        abort_unless(auth()->user()?->can('invoicing.warehouse-transfer.create'), 403, 'Sem permissão para criar transferências.');
        $stock = Stock::where('tenant_id', activeTenantId())
            ->with('product')
            ->findOrFail($stockId);

        $this->transferProductId = $stock->product_id;
        $this->transferProductName = $stock->product->name;
        $this->transferFromWarehouse = $stock->warehouse_id;
        $this->transferToWarehouse = '';
        $this->transferQuantity = '';
        $this->transferNotes = '';
        $this->transferMaxQty = (int) $stock->available_quantity;

        $this->showTransferModal = true;
    }

    public function saveTransfer()
    {
        abort_unless(auth()->user()?->can('invoicing.warehouse-transfer.create'), 403, 'Sem permissão para criar transferências.');
        $this->validate([
            'transferFromWarehouse' => 'required|exists:invoicing_warehouses,id',
            'transferToWarehouse' => 'required|exists:invoicing_warehouses,id|different:transferFromWarehouse',
            'transferQuantity' => 'required|numeric|min:0.001|max:' . $this->transferMaxQty,
            'transferNotes' => 'nullable|string|max:500',
        ]);

        try {
            StockMovement::createTransfer(
                $this->transferFromWarehouse,
                $this->transferToWarehouse,
                $this->transferProductId,
                $this->transferQuantity,
                $this->transferNotes
            );

            session()->flash('message', 'Transferência realizada com sucesso!');
            $this->showTransferModal = false;
            $this->resetTransferForm();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function showMovements($productId, $productName)
    {
        $this->movementsProductId = $productId;
        $this->movementsProductName = $productName;
        $this->showMovementsModal = true;
    }

    // ============================================================
    //  Entrada de Stock (cria linha em invoicing_stocks se não existir)
    // ============================================================

    /**
     * Abre o modal de entrada em LOTE.
     * Se a busca atual corresponder a UM único produto (ex.: ?search=08904306706630),
     * adiciona-o automaticamente como primeiro item.
     */
    public function openEntryModal()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para criar entradas de stock.');

        $this->resetEntryForm();

        // Pré-selecção do armazém — filtro ativo > default do tenant
        $this->entryWarehouseId = $this->warehouseFilter ?: defaultWarehouseId();

        // Pré-popular o primeiro item se a busca apontar a UM único produto
        $term = trim((string) $this->search);
        if ($term !== '') {
            $matches = Product::where('tenant_id', activeTenantId())
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('code', 'like', "%{$term}%")
                      ->orWhere('sku', 'like', "%{$term}%")
                      ->orWhere('barcode', 'like', "%{$term}%");
                })
                ->limit(2)->get();

            if ($matches->count() === 1) {
                $this->addEntryItem($matches->first()->id);
            }
        }

        $this->showEntryModal = true;
    }

    /**
     * Resultados de pesquisa de produtos para o picker do modal (computed).
     * Exclui produtos já adicionados à lista.
     */
    public function getEntrySearchResultsProperty()
    {
        $term = trim((string) $this->entryProductSearch);
        if ($term === '') {
            return collect();
        }
        $alreadyIds = collect($this->entryItems)->pluck('product_id')->all();

        return Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->whereNotIn('id', $alreadyIds)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'code', 'sku', 'barcode', 'unit', 'cost']);
    }

    /**
     * Adiciona um produto à lista de entrada. Se já existir, ignora silenciosamente.
     */
    public function addEntryItem($productId)
    {
        $product = Product::where('tenant_id', activeTenantId())->find($productId);
        if (!$product) {
            return;
        }
        // Evitar duplicados
        foreach ($this->entryItems as $it) {
            if ((int) $it['product_id'] === (int) $product->id) {
                $this->entryProductSearch = '';
                return;
            }
        }

        // Stock atual no armazém selecionado (informativo + validação de saídas)
        $current = 0;
        if ($this->entryWarehouseId) {
            $row = Stock::where('tenant_id', activeTenantId())
                ->where('warehouse_id', $this->entryWarehouseId)
                ->where('product_id', $product->id)
                ->first();
            $current = $row ? (float) $row->quantity : (float) ($product->stock_quantity ?? 0);
        }

        $this->entryItems[] = [
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code ?: ($product->sku ?: $product->barcode),
            'unit'         => $product->unit,
            'op'           => 'add',  // 'add' = soma | 'sub' = subtrai
            'quantity'     => 1,
            'unit_cost'    => (float) ($product->cost ?? 0) ?: null,
            'current_qty'  => $current,
        ];

        $this->entryProductSearch = '';
    }

    public function removeEntryItem(int $index)
    {
        if (isset($this->entryItems[$index])) {
            array_splice($this->entryItems, $index, 1);
        }
    }

    public function clearEntryItems()
    {
        $this->entryItems = [];
    }

    public function saveEntry()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para criar entradas de stock.');

        $this->validate([
            'entryWarehouseId'         => 'required|exists:invoicing_warehouses,id',
            'entryItems'               => 'required|array|min:1',
            'entryItems.*.product_id'  => 'required|exists:invoicing_products,id',
            'entryItems.*.op'          => 'required|in:add,sub',
            // 0,01 e não 0,001: a coluna `quantity` é decimal(10,2). Aceitar
            // três casas fazia com que 0,001 gravasse 0,00 — o utilizador via a
            // linha aceite e o stock não mexia.
            'entryItems.*.quantity'    => 'required|numeric|min:0.01',
            'entryItems.*.unit_cost'   => 'nullable|numeric|min:0',
            'entryNotes'               => 'nullable|string|max:500',
        ], [
            'entryWarehouseId.required'        => 'Selecione o armazém.',
            'entryItems.required'              => 'Adicione pelo menos um produto.',
            'entryItems.min'                   => 'Adicione pelo menos um produto.',
            'entryItems.*.quantity.required'   => 'Informe a quantidade de cada produto.',
            'entryItems.*.quantity.min'        => 'A quantidade deve ser maior que zero.',
        ]);

        $ok = 0; $errors = []; $referencia = null;

        // Transacção exterior: serve para o lock da referência do lote valer
        // alguma coisa. Fora de uma transacção, `lockForUpdate` é ignorado e
        // dois operadores a gravar ao mesmo tempo levavam a mesma referência —
        // os movimentos de um apareciam no documento do outro.
        DB::transaction(function () use (&$ok, &$errors, &$referencia) {
            $referencia = StockMovement::gerarReferenciaLote(activeTenantId());

            foreach ($this->entryItems as $idx => $it) {
                try {
                    // Transacção por linha (savepoint). Sem ela, uma saída sem
                    // stock deixava o movimento GRAVADO e o stock intacto: o
                    // livro passava a dizer que a mercadoria saiu quando não
                    // saiu. `createExit` insere a linha primeiro e só depois o
                    // hook chama removeStock(), que é quem lança.
                    DB::transaction(function () use ($it, $referencia, &$ok) {
                        $isSub = ($it['op'] ?? 'add') === 'sub';
                        $qtd   = (float) $it['quantity'];

                        // Saldo resultante calculado ANTES de criar o movimento.
                        //
                        // A alternativa — criar e depois reler o stock para
                        // gravar o saldo — obrigava a um segundo save() por
                        // linha, e como StockMovement é auditado isso duplicava
                        // as linhas da trilha (um `created` e um `updated` por
                        // produto). A trilha é append-only: o que lá entra fica.
                        $saldoActual = (float) Stock::where('tenant_id', activeTenantId())
                            ->where('warehouse_id', $this->entryWarehouseId)
                            ->where('product_id', $it['product_id'])
                            ->value('quantity');

                        $dados = [
                            'warehouse_id'    => $this->entryWarehouseId,
                            'product_id'      => $it['product_id'],
                            'quantity'        => $qtd,
                            'balance_after'   => $isSub ? $saldoActual - $qtd : $saldoActual + $qtd,
                            'unit_cost'       => !empty($it['unit_cost']) ? $it['unit_cost'] : null,
                            'batch_reference' => $referencia,
                            'notes'           => $this->entryNotes ?: ($isSub ? 'Saída manual em lote' : 'Entrada manual em lote'),
                        ];

                        // Se o stock não chegar, `createExit` lança dentro do
                        // savepoint e nada disto fica — nem o movimento, nem o
                        // saldo calculado acima.
                        $isSub
                            ? StockMovement::createExit($dados)
                            : StockMovement::createEntry($dados);

                        // NOTA: o agregado products.stock_quantity é mantido pelo StockObserver
                        // (createEntry/createExit → Stock::addStock/removeStock → save Eloquent).
                        // A sincronização manual que existia aqui aplicava o delta uma SEGUNDA
                        // vez sobre o valor já sincronizado — dupla contagem em cada entrada
                        // (comprovada em 88 entradas do tenant 17) e dupla subtração nas saídas.
                        $ok++;
                    });
                } catch (\Throwable $e) {
                    $errors[] = ($it['product_name'] ?? '#' . $idx) . ': ' . $e->getMessage();
                }
            }
        });

        if ($ok === 0) {
            session()->flash('error', 'Não foi possível registar nenhum produto. ' . implode(' | ', $errors));

            return;
        }

        // Painel de sucesso com o documento. Substitui o fecho imediato do
        // modal: o utilizador tem de ficar com a referência do lote à vista e
        // com o PDF a um clique — abri-lo por JS seria bloqueado como popup,
        // por não vir de um gesto directo.
        $this->batchReference = $referencia;
        $this->batchOk        = $ok;
        $this->batchErrors    = $errors;

        session()->flash('message', empty($errors)
            ? "Movimentação {$referencia} registada: {$ok} produto(s) actualizado(s)."
            : "Movimentação {$referencia} parcialmente registada: {$ok} produto(s) OK. Falhas: " . implode(' | ', $errors));
    }

    /** Fecha o painel de sucesso e limpa o formulário para a movimentação seguinte. */
    public function closeEntryModal(): void
    {
        $this->showEntryModal = false;
        $this->resetEntryForm();
    }

    /** Regista outra movimentação sem sair do modal. */
    public function novaMovimentacao(): void
    {
        $armazem = $this->entryWarehouseId;

        $this->resetEntryForm();

        // O armazém mantém-se: quem está a dar entrada a um contentor inteiro
        // não quer voltar a escolhê-lo a cada lote.
        $this->entryWarehouseId = $armazem;
    }

    private function resetEntryForm()
    {
        $this->entryProductSearch = '';
        $this->entryWarehouseId   = '';
        $this->entryNotes         = '';
        $this->entryItems         = [];
        $this->batchReference     = null;
        $this->batchOk            = 0;
        $this->batchErrors        = [];
        $this->resetErrorBag();
    }

    private function resetAdjustForm()
    {
        $this->adjustStockId = null;
        $this->adjustWarehouseId = null;
        $this->adjustProductId = null;
        $this->adjustProductName = '';
        $this->adjustCurrentQty = 0;
        $this->adjustNewQty = null;
        $this->adjustNotes = '';
        $this->resetErrorBag();
    }

    private function resetTransferForm()
    {
        $this->transferProductId = null;
        $this->transferProductName = '';
        $this->transferFromWarehouse = '';
        $this->transferToWarehouse = '';
        $this->transferQuantity = '';
        $this->transferNotes = '';
        $this->transferMaxQty = 0;
        $this->resetErrorBag();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingWarehouseFilter()
    {
        $this->resetPage();
    }
}

<?php

namespace App\Livewire\Invoicing;

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
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Selecione o armazém de origem primeiro.')]);
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
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Selecione um produto e quantidade válida.')]);
            return;
        }

        if ($this->productQuantity > $this->availableStock) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Quantidade maior que o stock disponível.')]);
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
                    $this->dispatch('notify', ['type' => 'error', 'message' => __('Quantidade total excede o stock disponível.')]);
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
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Produto adicionado!')]);
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
            $this->dispatch('notify', ['type' => 'error', 'message' => __('A quantidade deve ser um número maior que zero.')]);
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

    /*
     * A TRANSFERÊNCIA VIVE NO `TransferenciaDeStock`: a empresa destino
     * resolvida pelas empresas do utilizador, o artigo copiado quando não
     * existe lá, duas referências (uma por empresa), os lotes por FEFO. Este
     * ecrã e o ecrã em React chamam o mesmo.
     */
    public function saveTransfer()
    {
        try {
            $r = app(\App\Services\Invoicing\TransferenciaDeStock::class)->entreEmpresas(
                (int) $this->warehouseFromId,
                (int) $this->tenantToId,
                (int) $this->warehouseToId,
                $this->transferItems,
                $this->notes,
                activeTenantId(),
                auth()->user()
            );
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Erro: :erro', ['erro' => $e->getMessage()])]);

            return;
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => count($r['resumo']) . ' produto(s) transferido(s) para ' . $r['destino_nome'] . '!']);

        // O formulário dá lugar ao comprovativo. Guardado ANTES do resetForm.
        $this->batchResumo           = $r['resumo'];
        $this->batchReference        = $r['referencia_origem'];
        $this->batchReferenceDestino = $r['referencia_destino'];
        $this->batchDestinoNome      = $r['destino_nome'];

        $this->resetForm();
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

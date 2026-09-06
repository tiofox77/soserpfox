<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    /**
     * Filtro por conservação (ambiente / refrigerado / congelado).
     *
     * Quem recebe um contentor precisa de separar o que vai ao frio do que vai
     * à prateleira ANTES de o descarregar. Abrir a ficha de cada artigo para o
     * descobrir é trabalho a dobrar e engano garantido.
     */
    public $filterConservacao = '';

    /**
     * Perfis de negócio da empresa (Definições de Faturação).
     *
     * Decide apenas o que APARECE nesta lista por omissão. Não filtra stock,
     * não esconde artigos e não muda nada do que está gravado — desligar um
     * perfil nunca faz desaparecer mercadoria do ecrã.
     *
     * #[Locked] porque isto vem das Definições e nunca do formulário: uma
     * definição da empresa não se altera a partir do navegador.
     */
    #[Locked]
    public array $perfis = [
        InvoicingSettings::PERFIL_COSMETICA => false,
        InvoicingSettings::PERFIL_MERCEARIA => false,
    ];

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

    /**
     * Contexto do ajuste: em QUE armazém se está a mexer e quanto o artigo tem
     * ao todo.
     *
     * O modal mostrava só "Stock Atual" — o da linha — sem dizer de que armazém
     * era nem quanto o artigo tinha no total. Com seis armazéns, ajustar às
     * cegas é como o operador acaba a corrigir a prateleira errada.
     */
    public $adjustWarehouseName = '';
    public $adjustTotalOutros = 0;

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

    public function mount(): void
    {
        $tenantId = activeTenantId();

        // Sem empresa activa não há perfil nenhum para ler — e forTenant() é um
        // firstOrCreate: chamá-lo aqui criaria uma linha de definições órfã.
        if (!$tenantId) {
            return;
        }

        // Lido UMA única vez à entrada: forTenant() vai à base de dados, e o
        // render() volta a correr a cada tecla escrita na pesquisa e a cada
        // mudança de página.
        //
        // Pelos slugs que o modelo publica e não pelos nomes das colunas: é o
        // contrato das Definições, e aguenta a coluna mudar de nome.
        $activos = InvoicingSettings::forTenant($tenantId)->perfisActivos();

        $this->perfis = [
            InvoicingSettings::PERFIL_COSMETICA => in_array(InvoicingSettings::PERFIL_COSMETICA, $activos, true),
            InvoicingSettings::PERFIL_MERCEARIA => in_array(InvoicingSettings::PERFIL_MERCEARIA, $activos, true),
        ];
    }

    /**
     * Há artigos com conservação gravada nesta empresa?
     *
     * É a segunda metade da regra do OU: quem desligue o perfil de mercearia
     * continua a ter mercadoria que só pode ir para a arca, e tem de continuar
     * a conseguir vê-la e a separá-la na lista.
     *
     * `exists()` e não a lista de valores distintos: as opções do filtro são
     * uma lista fechada e conhecida — daqui só se precisa de sim ou não, que
     * pára no primeiro registo encontrado.
     */
    public function getHaConservacaoNoCatalogoProperty(): bool
    {
        return Product::where('tenant_id', activeTenantId())
            ->whereNotNull('storage_conditions')
            ->where('storage_conditions', '<>', '')
            ->exists();
    }

    /**
     * Como cada conservação se mostra ao utilizador.
     *
     * O valor gravado é a CHAVE e nunca o que aparece no ecrã: assim o rótulo
     * traduz-se sem tocar nos dados dos artigos. Um valor fora desta lista
     * mostra-se como está gravado, em vez de desaparecer do ecrã.
     */
    public function rotulosDeConservacao(): array
    {
        return [
            'ambiente'    => __('Ambiente'),
            'refrigerado' => __('Refrigerado'),
            'congelado'   => __('Congelado'),
        ];
    }

    /**
     * A consulta de stock com os filtros do ecrã aplicados.
     *
     * A lista E os cartões saem daqui. Estavam separados, e os cartões
     * contavam a empresa toda: escolher um armazém filtrava as linhas e
     * deixava os totais quietos, a dizer outra coisa. Quem confere a
     * prateleira lê o número grande, não conta as linhas.
     */
    private function consultaComFiltros()
    {
        $query = Stock::where('tenant_id', activeTenantId())
            ->whereHas('product')
            ->whereHas('warehouse');

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->lowStockFilter) {
            $query->whereHas('product', $this->condicaoStockBaixo());
        }

        // Conservação. `whereHas` e não JOIN de propósito: o global scope de
        // BelongsToTenant é do STOCK, e escreve `tenant_id` sem qualificar a
        // tabela — junte-se invoicing_products, que também tem tenant_id, e a
        // condição fica ambígua.
        //
        // O filtro de empresa vai à mão porque o ARTIGO não é tenant-scoped:
        // Product não usa BelongsToTenant, e uma subconsulta sem esta linha
        // olharia para o catálogo de toda a gente. Qualificado com a tabela
        // para não depender de qual das duas o MySQL resolve primeiro.
        if ($this->filterConservacao !== '') {
            $query->whereHas('product', function ($q) {
                $q->where('invoicing_products.tenant_id', activeTenantId())
                  ->porConservacao($this->filterConservacao);
            });
        }

        return $query;
    }

    /** Abaixo do mínimo, como subconsulta correlacionada — sem JOIN, sem ambiguidade. */
    private function condicaoStockBaixo(): \Closure
    {
        return function ($q) {
            $q->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min');
        };
    }

    public function render()
    {
        $base = $this->consultaComFiltros();

        // O eager load do artigo já traz `net_content` e `storage_conditions`
        // com o resto da ficha: a lista mostra-os sem uma consulta por linha.
        $stocks = (clone $base)
            ->with(['warehouse', 'product'])
            ->paginate($this->perPage);

        $tenantId = activeTenantId();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        // Stats — uma única query agregada (em vez de 4) + 1 query para low_stock.
        $agg = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT product_id) AS total_products,
                COALESCE(SUM(quantity), 0)               AS total_quantity,
                COALESCE(SUM(quantity * unit_cost), 0)   AS total_value
            ')
            ->first();

        // Sobre o mesmo conjunto filtrado. Se o filtro de stock baixo já está
        // ligado, isto não muda nada — e é isso mesmo que se quer: o cartão
        // passa a valer o total da lista.
        $lowStockCount = (clone $base)
            ->whereHas('product', $this->condicaoStockBaixo())
            ->count();

        $stats = [
            'total_products' => (int) ($agg->total_products ?? 0),
            'total_quantity' => (float) ($agg->total_quantity ?? 0),
            'total_value'    => (float) ($agg->total_value ?? 0),
            'low_stock'      => (int) $lowStockCount,
        ];

        // A regra do OU, a mesma do resto do sistema: mostra-se a conservação a
        // quem diz trabalhar com mercearia OU a quem já tem artigos marcados.
        // Num restaurante ou numa oficina — sem perfil e sem dados — a coluna
        // não aparece e a lista fica exactamente como estava.
        $mostraConservacao = ($this->perfis[InvoicingSettings::PERFIL_MERCEARIA] ?? false)
            || $this->haConservacaoNoCatalogo;

        return view('livewire.invoicing.stock.stock-management', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
            'stats' => $stats,
            'mostraConservacao' => $mostraConservacao,
            'rotulosConservacao' => $this->rotulosDeConservacao(),
        ]);
    }

    public function openAdjustModal($stockId)
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        $stock = Stock::where('tenant_id', activeTenantId())
            ->with(['product', 'warehouse'])
            ->findOrFail($stockId);

        $this->adjustStockId = $stock->id;
        $this->adjustWarehouseId = $stock->warehouse_id;
        $this->adjustProductId = $stock->product_id;
        $this->adjustProductName = $stock->product->name;
        $this->adjustCurrentQty = (int) $stock->quantity;
        $this->adjustNewQty = (int) $stock->quantity;
        $this->adjustNotes = '';

        $this->adjustWarehouseName = $stock->warehouse->name ?? ('Armazém #' . $stock->warehouse_id);

        // O que o artigo tem nos OUTROS armazéns. Somado à quantidade nova dá o
        // total do artigo depois do ajuste — que é o número que interessa a
        // quem está a conferir a prateleira e não vê os outros depósitos.
        $this->adjustTotalOutros = (float) Stock::where('tenant_id', activeTenantId())
            ->where('product_id', $stock->product_id)
            ->where('id', '<>', $stock->id)
            ->sum('quantity');

        $this->showAdjustModal = true;
    }

    public function saveAdjustment()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para ajustar stock.');
        $this->validate([
            'adjustNewQty' => 'required|numeric|min:0',
            'adjustNotes' => 'nullable|string|max:500',
        ]);

        // O mesmo caminho do ecrã em React.
        app(\App\Services\Invoicing\MovimentacaoDeStock::class)
            ->ajustar((int) $this->adjustWarehouseId, (int) $this->adjustProductId, (float) $this->adjustNewQty, $this->adjustNotes);

        session()->flash('message', __('Stock ajustado com sucesso!'));
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
            // Com filtro de empresa: sem ele, um id de armazém alheio passava a
            // validação e a transferência saía do stock desta empresa para o
            // armazém de outra.
            'transferFromWarehouse' => ['required', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
            'transferToWarehouse' => ['required', 'different:transferFromWarehouse', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
            // 0,01 e não 0,001: `invoicing_stock_movements.quantity` é
            // decimal(10,2). Uma transferência de 0,004 era aceite, gravava um
            // movimento de 0,00, deixava a origem intacta — e ainda criava no
            // destino uma linha de stock a zero. Essa linha fantasma põe o
            // produto em regime multi-armazém e faz o POS deixar de usar o
            // agregado legado: o artigo passa a aparecer esgotado na caixa por
            // causa de uma transferência que nunca aconteceu.
            'transferQuantity' => 'required|numeric|min:0.01|max:' . $this->transferMaxQty,
            'transferNotes' => 'nullable|string|max:500',
        ], [
            'transferQuantity.min' => 'A quantidade mínima a transferir é 0,01.',
        ]);

        try {
            app(\App\Services\Invoicing\MovimentacaoDeStock::class)->transferir(
                (int) $this->transferFromWarehouse,
                (int) $this->transferToWarehouse,
                (int) $this->transferProductId,
                (float) $this->transferQuantity,
                $this->transferNotes
            );

            session()->flash('message', __('Transferência realizada com sucesso!'));
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
            // `net_content` acrescentado ao select que já existia: duas
            // embalagens do mesmo artigo têm o mesmo nome e o mesmo código de
            // família, e só o conteúdo líquido as separa. Sem ele, escolher
            // entre dois "Leite" é adivinhar.
            ->get(['id', 'name', 'code', 'sku', 'barcode', 'unit', 'cost', 'net_content']);
    }

    /**
     * Adiciona um produto à lista de entrada. Se já existir, ignora silenciosamente.
     */
    public function addEntryItem($productId)
    {
        // Antes de tudo: uma actualização atrasada pode ter deixado uma linha
        // sem produto, e o ciclo abaixo lê `product_id` sem perguntar.
        $this->limparEntryItems();

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
            // Fica na linha para quem confere o lote continuar a distinguir as
            // duas embalagens depois de as adicionar, e não só ao escolhê-las.
            'net_content'  => $product->net_content,
            'unit'         => $product->unit,
            'op'           => 'add',  // 'add' = soma | 'sub' = subtrai
            'quantity'     => 1,
            'unit_cost'    => (float) ($product->cost ?? 0) ?: null,
            'current_qty'  => $current,
        ];

        $this->entryProductSearch = '';
    }

    /**
     * Tira uma linha SEM reindexar as outras.
     *
     * Era `array_splice`, que reindexa — e as ligações do formulário são por
     * ÍNDICE (`entryItems.20.quantity`), com meio segundo de espera antes de
     * enviarem. Escrever uma quantidade e, antes desse meio segundo, remover
     * uma linha acima: a remoção chegava primeiro e deslocava tudo; a
     * quantidade chegava a seguir para um índice que já era de outro produto.
     *
     * Dava duas coisas, e a segunda é pior:
     *
     *   · se o índice ficasse para lá do fim, o Livewire CRIAVA a entrada só
     *     com a quantidade — sem `product_id` — e o código seguinte rebentava
     *     com "Undefined array key product_id". Foi o erro visto em produção.
     *   · se o índice ainda existisse, a quantidade ia parar ao PRODUTO
     *     ERRADO, sem erro nenhum.
     *
     * Com `unset` os índices que sobram continuam a apontar para os mesmos
     * produtos. A lista fica com buracos, e é por isso que tudo o que a
     * percorre usa as chaves em vez de assumir 0..n-1.
     */
    public function removeEntryItem(int $index)
    {
        unset($this->entryItems[$index]);
    }

    /**
     * Deita fora as linhas que o Livewire criou sem produto.
     *
     * Uma actualização atrasada para um índice que já não existe faz nascer
     * uma linha só com `quantity`. Não há nada a aproveitar nela — não se sabe
     * de que produto era — e o que não pode acontecer é chegar a lado nenhum
     * que leia `product_id`.
     *
     * @return int quantas foram descartadas
     */
    protected function limparEntryItems(): int
    {
        $antes = count($this->entryItems);

        $this->entryItems = array_filter(
            $this->entryItems,
            fn ($item) => is_array($item) && !empty($item['product_id'])
        );

        return $antes - count($this->entryItems);
    }

    public function clearEntryItems()
    {
        $this->entryItems = [];
    }

    public function saveEntry()
    {
        abort_unless(auth()->user()?->can('invoicing.stock.edit'), 403, 'Sem permissão para criar entradas de stock.');

        // Uma linha sem produto faria a validação abaixo falhar com uma
        // mensagem que ninguém entende ("entryItems.7.product_id é
        // obrigatório", de uma linha que não está no ecrã). Descarta-se antes:
        // não há nada a aproveitar nela, porque nem se sabe de que produto era.
        $this->limparEntryItems();

        $empresa = activeTenantId();

        $this->validate([
            // O `exists:` LEVA o filtro de empresa. Sem ele, bastava adulterar
            // o pedido do Livewire com o id de um armazém ou de um artigo de
            // outra empresa para lá escrever stock — os ids são sequenciais e
            // globais, e nada mais os validava. O `render()` filtra por empresa,
            // por isso as linhas fantasma nem sequer apareciam no ecrã: entravam
            // no agregado e ficavam invisíveis.
            'entryWarehouseId'         => ['required', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $empresa)],
            'entryItems'               => 'required|array|min:1',
            'entryItems.*.product_id'  => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $empresa)],
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

        // O LOTE REGISTA-SE NO `MovimentacaoDeStock`: uma transacção por
        // linha e a referência reservada até ao fim. Este ecrã e o ecrã em
        // React chamam o mesmo.
        ['referencia' => $referencia, 'ok' => $ok, 'erros' => $errors] = app(\App\Services\Invoicing\MovimentacaoDeStock::class)
            ->registarLote((int) $this->entryWarehouseId, $this->entryItems, $this->entryNotes, activeTenantId());

        if ($ok === 0) {
            session()->flash('error', __('Não foi possível registar nenhum produto. :erros', [
                'erros' => implode(' | ', $errors),
            ]));

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

    public function updatingLowStockFilter()
    {
        // Faltava: ligar o filtro na página 3 deixava-a na página 3, que já
        // não existe no conjunto filtrado — e o ecrã vinha vazio.
        $this->resetPage();
    }

    public function updatingFilterConservacao()
    {
        $this->resetPage();
    }
}

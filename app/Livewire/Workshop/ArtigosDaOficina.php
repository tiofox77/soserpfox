<?php

// COPIADO DO ECRÃ DE ARTIGOS DA FACTURAÇÃO no dia em que a facturação passou a React.
// A oficina (Livewire) herda daqui; quando a oficina migrar, isto vai com ela.

namespace App\Livewire\Workshop;

use App\Models\{Product};
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\AGT\AGTTaxExemptionCode;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Produtos')]
class ArtigosDaOficina extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $editingProductId = null;
    
    // View modal
    public $showViewModal = false;
    public $viewingProduct = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingProductId = null;
    public $deletingProductName = '';
    
    // Filters
    public $typeFilter = '';
    public $stockFilter = '';
    public $categoryFilter = '';
    public $statusFilter = '';
    // Filtros de qualidade do catálogo: encontram o que está por preencher.
    // "300 artigos sem preço" é uma pergunta que se faz muito e que antes só
    // se respondia a olho, linha a linha.
    public $qualidadeFilter = '';

    // Filtros de catálogo especializado. Só estes quatro porque são os que se
    // usam ao balcão e no armazém: "isto precisa de receita?", "há este modelo
    // no tamanho M / em azul?" e "o que é que vai para o frigorífico?". Os
    // restantes campos consultam-se na ficha do artigo.
    public $filterPrescricao = '';
    public $filterTamanho = '';
    public $filterCor = '';
    public $filterConservacao = '';

    /**
     * Perfis de catálogo da empresa (Definições de Faturação).
     *
     * Isto decide apenas o que APARECE POR OMISSÃO. Nunca esconde valores já
     * gravados nem desliga avisos: uma protecção que se apaga com uma
     * definição de visualização não é protecção nenhuma.
     *
     * #[Locked] porque isto vem das Definições e nunca do formulário: sem ele,
     * o navegador podia devolver o perfil ligado numa empresa que o tem
     * desligado. Não desbloqueia nada (os campos são opcionais e já revelaveis
     * no formulário), mas uma definição da empresa não se altera pelo cliente.
     */
    #[Locked]
    public array $perfis = [
        InvoicingSettings::PERFIL_FARMACIA => false,
        InvoicingSettings::PERFIL_VESTUARIO => false,
        InvoicingSettings::PERFIL_COSMETICA => false,
        InvoicingSettings::PERFIL_MERCEARIA => false,
    ];

    public function mount(): void
    {
        $tenantId = activeTenantId();

        // Sem empresa activa não há perfil nenhum para ler — e forTenant() é um
        // firstOrCreate: chamá-lo aqui criaria uma linha de definições órfã.
        if (!$tenantId) {
            return;
        }

        // Lido uma única vez à entrada: forTenant() vai à base de dados e o
        // render() volta a correr a cada tecla escrita na pesquisa.
        //
        // Pelos slugs do modelo e não pelos nomes das colunas: é o contrato que
        // as definições publicam, e aguenta a coluna mudar de nome.
        $activos = InvoicingSettings::forTenant($tenantId)->perfisActivos();

        $this->perfis = [
            InvoicingSettings::PERFIL_FARMACIA => in_array(InvoicingSettings::PERFIL_FARMACIA, $activos, true),
            InvoicingSettings::PERFIL_VESTUARIO => in_array(InvoicingSettings::PERFIL_VESTUARIO, $activos, true),
            InvoicingSettings::PERFIL_COSMETICA => in_array(InvoicingSettings::PERFIL_COSMETICA, $activos, true),
            InvoicingSettings::PERFIL_MERCEARIA => in_array(InvoicingSettings::PERFIL_MERCEARIA, $activos, true),
        ];
    }

    /** Mostrar a lixeira (produtos eliminados, recuperáveis). */
    public bool $mostrarEliminados = false;

    public function updatingMostrarEliminados()
    {
        $this->resetPage();
    }

    public function alternarEliminados()
    {
        $this->mostrarEliminados = !$this->mostrarEliminados;
        $this->resetPage();
    }
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;
    
    // Form fields
    public $code, $sku, $barcode, $name, $description;
    public $price = 0, $cost = 0;
    public $unit = 'UN';
    public $type = 'produto';
    public $category_id = null;
    public $brand_id = null;
    public $supplier_id = null;
    /**
     * Um artigo FÍSICO conta stock por omissão.
     *
     * Isto nascia a `false`, e por isso quem criasse um artigo sem reparar na
     * caixa ficava com um artigo que se vendia e nunca descia. Nada no ecrã o
     * avisava, e o sintoma só aparecia semanas depois, com as contagens já
     * fora e sem se saber quais os artigos afectados. Numa farmácia estava
     * errado para praticamente todos.
     *
     * O `updatedType` desliga-o sozinho quando o tipo passa a serviço — um
     * serviço com esta bandeira ligada é recusado no POS por "esgotado".
     */
    public $manage_stock = true;

    /** No POS, o preço é escrito na hora da venda em vez de vir da ficha. */
    public $preco_no_pos = false;
    public $stock_quantity = 0;
    public $stock_min = 0;
    public $stock_max = null;
    
    // Batch tracking fields
    public $track_batches = false;
    public $track_expiry = false;
    public $require_batch_on_purchase = false;
    public $require_batch_on_sale = false;

    // Medicamento (farmácia). Todos opcionais: a esmagadora maioria dos artigos
    // do sistema não é medicamento, e torná-los obrigatórios partia o catálogo
    // de toda a gente.
    public $requires_prescription = false;
    public $is_controlled = false;
    public $active_ingredient = null;
    public $dosage = null;
    public $pharmaceutical_form = null;
    public $armed_registration = null;

    // Vestuário. Mesma lógica: opcionais para não afectar quem vende outra coisa.
    public $size = null;
    public $color = null;
    public $gender = null;
    public $material = null;

    // Cosmética e mercearia. O conteúdo líquido pertence aos dois: é o que
    // distingue duas embalagens do mesmo produto ("Champô X 200ml" e "Champô X
    // 750ml" são artigos diferentes, com preço e stock próprios).
    //
    // O tom da cosmética é a cor, que já veio do vestuário — não há campo novo
    // para a mesma coisa.
    public $net_content = null;
    public $pao_months = null;
    public $inci_ingredients = null;
    public $storage_conditions = null;
    public $allergens = null;
    public $origin_country = null;

    // Tax fields
    public $tax_type = 'iva';
    public $tax_rate_id = null;
    public $exemption_reason = null;
    
    // Image fields
    public $featured_image; // Upload file
    public $currentFeaturedImage; // Existing image path
    public $gallery = []; // Upload files array
    public $currentGallery = []; // Existing gallery images

    protected function rules()
    {
        $rules = [
            'name' => 'required|min:3',
            'type' => 'required|in:produto,servico',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'featured_image' => 'nullable|image|max:2048',
            'gallery.*' => 'nullable|image|max:2048',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'unit' => 'required|string',
            'category_id' => 'required|exists:invoicing_categories,id',
            'brand_id' => 'nullable|exists:invoicing_brands,id',
            'supplier_id' => 'nullable|exists:invoicing_suppliers,id',
            'tax_type' => 'required|in:iva,isento',
            'tax_rate_id' => 'required_if:tax_type,iva|nullable|exists:invoicing_taxes,id',
            'exemption_reason' => 'required_if:tax_type,isento|nullable|string',
            'stock_min' => 'nullable|integer|min:0',
            'stock_max' => 'nullable|integer|min:0|gte:stock_min',

            // Medicamento e vestuário: NENHUM obrigatório. Um artigo comum não
            // preenche nada disto e tem de continuar a poder ser gravado.
            'requires_prescription' => 'nullable|boolean',
            'is_controlled' => 'nullable|boolean',
            'active_ingredient' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:60',
            'pharmaceutical_form' => 'nullable|string|max:40',
            'armed_registration' => 'nullable|string|max:60',
            'size' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:40',
            // Lista fechada porque o género alimenta filtros e relatórios: texto
            // livre daria "M", "masc" e "Homem" a significarem o mesmo.
            'gender' => 'nullable|in:masculino,feminino,unissexo,crianca',
            'material' => 'nullable|string|max:120',

            // Cosmética e mercearia: também nenhum obrigatório, pela mesma razão.
            'net_content' => 'nullable|string|max:40',
            // Os meses depois de aberto (o frasco aberto com "12M" no rótulo)
            // são um prazo real: 0 meses não quer dizer nada e 120 (10 anos) já
            // é sinal de engano — quem não sabe deixa em branco.
            'pao_months' => 'nullable|integer|min:1|max:120',
            'inci_ingredients' => 'nullable|string',
            // Lista fechada porque isto manda no stock: diz a quem arruma se o
            // artigo vai para a prateleira, para o frigorífico ou para a arca.
            'storage_conditions' => 'nullable|in:ambiente,refrigerado,congelado',
            'allergens' => 'nullable|string|max:255',
            'origin_country' => 'nullable|string|max:60',
        ];

        // Validar código: único POR TENANT (suporta o esquema multi-tenant)
        $tenantId = activeTenantId();
        $codeUnique = Rule::unique('invoicing_products', 'code')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId));
        if ($this->editingProductId) {
            $codeUnique = $codeUnique->ignore($this->editingProductId);
        }
        $rules['code'] = ['required', 'string', 'max:50', $codeUnique];

        return $rules;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingStockFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingQualidadeFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function updatingFilterPrescricao()
    {
        $this->resetPage();
    }

    public function updatingFilterTamanho()
    {
        $this->resetPage();
    }

    public function updatingFilterCor()
    {
        $this->resetPage();
    }

    public function updatingFilterConservacao()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset([
            'typeFilter', 'stockFilter', 'categoryFilter', 'statusFilter', 'qualidadeFilter',
            'dateFrom', 'dateTo', 'search',
            'filterPrescricao', 'filterTamanho', 'filterCor', 'filterConservacao',
        ]);
        $this->resetPage();
    }

    public function create()
    {
        if (!auth()->user()->can('invoicing.products.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar produtos'));
            return;
        }
        
        $this->resetForm();
        // Gerar código automaticamente para exibição (já protegido contra colisões)
        $this->code = Product::generateProductCode(activeTenantId(), $this->type);

        // Pré-selecionar regime de IVA com base no estado fiscal do tenant.
        // Triggers que activam "isento por defeito":
        //  a) tenant.regime ∈ {regime_isencao, regime_nao_sujeicao}
        //  b) tax DEFAULT do tenant tem saft_type=ISE (regime de exclusão configurado)
        $tenant = \App\Models\Tenant::find(activeTenantId());
        if ($tenant) {
            $defaultIsentTax = Tax::where('tenant_id', $tenant->id)
                ->where('saft_type', 'ISE')
                ->where('is_default', true)
                ->first();

            $isExemptRegime = in_array($tenant->regime, ['regime_isencao', 'regime_nao_sujeicao'], true);

            if ($isExemptRegime || $defaultIsentTax) {
                $this->tax_type = 'isento';
                $this->tax_rate_id = null;
                // O regime (invoicing_taxes) é a fonte canónica: exemption_code é o
                // CÓDIGO AGT, exemption_reason é a descrição. O produto guarda o
                // CÓDIGO (o select usa códigos e items.tax_exemption_code é
                // varchar(10)). Antes copiava-se a descrição → venda falhava com
                // "Data too long for column 'tax_exemption_code'".
                if ($defaultIsentTax) {
                    $this->exemption_reason = $defaultIsentTax->exemption_code
                        ?: Product::normalizeExemptionCode($defaultIsentTax->exemption_reason)
                        ?: \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE;
                }
            }
        }

        $this->showModal = true;
    }
    
    /**
     * O tipo manda no código e na gestão de stock.
     *
     * Um SERVIÇO não tem stock: deixar-lhe a bandeira ligada faz o POS
     * recusá-lo por "esgotado" — já aconteceu com os serviços do salão. E um
     * artigo físico conta stock, que é o caso normal.
     *
     * Só em criação: a editar, quem manda é o que está gravado, e mexer nisso
     * por trocar o tipo apagaria uma decisão do utilizador.
     */
    public function updatedType($value)
    {
        if ($this->editingProductId) {
            return;
        }

        $this->code = Product::generateProductCode(activeTenantId(), $value);
        $this->manage_stock = ($value !== 'servico');
    }

    /** Produto cujo rastreio está aberto. */
    public $rastreioProdutoId = null;

    /** Período do rastreio, em dias (0 = tudo). */
    public $rastreioDias = 90;

    public function verRastreio($id): void
    {
        $this->rastreioProdutoId = $id;
        $this->rastreioDias = 90;
    }

    public function fecharRastreio(): void
    {
        $this->rastreioProdutoId = null;
    }

    /**
     * Rastreio completo de um artigo: o que foi vendido e como o stock se moveu.
     *
     * As duas coisas juntas de propósito. As vendas dizem para quem foi e por
     * quanto; os movimentos dizem de que armazém saiu e por que documento — e é
     * a discrepância entre os dois que denuncia problemas (venda sem saída de
     * stock, saída sem venda, ajuste manual sem justificação).
     */
    public function getRastreioProperty(): ?array
    {
        if (!$this->rastreioProdutoId) {
            return null;
        }

        $produto = Product::withTrashed()
            ->where('tenant_id', activeTenantId())
            ->find($this->rastreioProdutoId);

        if (!$produto) {
            return null;
        }

        $desde = $this->rastreioDias > 0 ? now()->subDays((int) $this->rastreioDias) : null;

        // ── Linhas de venda ──
        $vendas = \App\Models\Invoicing\SalesInvoiceItem::query()
            ->where('product_id', $produto->id)
            ->whereHas('invoice', function ($q) use ($desde) {
                $q->where('tenant_id', activeTenantId())
                  ->when($desde, fn ($s) => $s->where('invoice_date', '>=', $desde));
            })
            ->with(['invoice:id,invoice_number,invoice_date,client_id,status,invoice_type', 'invoice.client:id,name'])
            ->latest('id')
            ->limit(300)
            ->get();

        // ── Movimentos de stock ──
        $movimentos = \App\Models\Invoicing\StockMovement::query()
            ->where('tenant_id', activeTenantId())
            ->where('product_id', $produto->id)
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))
            ->with(['warehouse:id,name'])
            ->latest('id')
            ->limit(300)
            ->get();

        // ── Stock actual, por armazém ──
        $porArmazem = \App\Models\Invoicing\Stock::where('tenant_id', activeTenantId())
            ->where('product_id', $produto->id)
            ->with('warehouse:id,name')
            ->get();

        $vendido = (float) $vendas->sum('quantity');
        $saidas  = (float) $movimentos->where('type', 'out')->sum('quantity');

        return [
            'produto'    => $produto,
            'vendas'     => $vendas,
            'movimentos' => $movimentos,
            'porArmazem' => $porArmazem,
            'resumo'     => [
                'qtd_vendida'   => $vendido,
                'valor_vendido' => (float) $vendas->sum('total'),
                'documentos'    => $vendas->pluck('sales_invoice_id')->unique()->count(),
                'entradas'      => (float) $movimentos->where('type', 'in')->sum('quantity'),
                'saidas'        => $saidas,
                'stock_total'   => (float) $porArmazem->sum('quantity'),
                // Vendido sem saída de stock correspondente é o sintoma de
                // "produto aparece disponível mas a baixa falha".
                'divergencia'   => round($vendido - $saidas, 3),
            ],
        ];
    }

    public function view($id)
    {
        $product = Product::with(['category.parent', 'brand', 'supplier', 'taxRate'])
            ->findOrFail($id);

        if ((int) $product->tenant_id !== (int) activeTenantId()) {
            abort(403);
        }
        
        $this->viewingProduct = $product;
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingProduct = null;
    }
    
    public function edit($id)
    {
        if (!auth()->user()->can('invoicing.products.edit')) {
            $this->dispatch('error', message: __('Sem permissão para editar produtos'));
            return;
        }
        
        $this->closeViewModal(); // Fechar modal de visualização se estiver aberta
        $product = Product::findOrFail($id);
        
        if ((int) $product->tenant_id !== (int) activeTenantId()) {
            abort(403);
        }
        
        $this->editingProductId = $id;
        $this->code = $product->code;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->currentFeaturedImage = $product->featured_image;
        $this->currentGallery = $product->gallery ?? [];
        $this->price = $product->price;
        $this->cost = $product->cost;
        $this->unit = $product->unit;
        $this->type = $product->type;
        $this->category_id = $product->category_id;
        $this->brand_id = $product->brand_id;
        $this->supplier_id = $product->supplier_id;
        $this->tax_type = $product->tax_type ?? 'iva';
        $this->tax_rate_id = $product->tax_rate_id;
        $this->exemption_reason = $product->exemption_reason;
        $this->manage_stock = $product->manage_stock;
        $this->preco_no_pos = (bool) $product->preco_no_pos;
        $this->stock_quantity = $product->stock_quantity;
        $this->stock_min = $product->stock_min ?? 0;
        $this->stock_max = $product->stock_max;
        $this->track_batches = $product->track_batches ?? false;
        $this->track_expiry = $product->track_expiry ?? false;
        $this->require_batch_on_purchase = $product->require_batch_on_purchase ?? false;
        $this->require_batch_on_sale = $product->require_batch_on_sale ?? false;
        $this->requires_prescription = $product->requires_prescription ?? false;
        $this->is_controlled = $product->is_controlled ?? false;
        $this->active_ingredient = $product->active_ingredient;
        $this->dosage = $product->dosage;
        $this->pharmaceutical_form = $product->pharmaceutical_form;
        $this->armed_registration = $product->armed_registration;
        $this->size = $product->size;
        $this->color = $product->color;
        $this->gender = $product->gender;
        $this->material = $product->material;
        $this->net_content = $product->net_content;
        $this->pao_months = $product->pao_months;
        $this->inci_ingredients = $product->inci_ingredients;
        $this->storage_conditions = $product->storage_conditions;
        $this->allergens = $product->allergens;
        $this->origin_country = $product->origin_country;
        $this->showModal = true;
    }

    public function save()
    {
        // Verificar permissão apropriada
        if ($this->editingProductId) {
            if (!auth()->user()->can('invoicing.products.edit')) {
                $this->dispatch('error', message: __('Sem permissão para editar produtos'));
                return;
            }
        } else {
            if (!auth()->user()->can('invoicing.products.create')) {
                $this->dispatch('error', message: __('Sem permissão para criar produtos'));
                return;
            }
            // Garantir auto-code: se ficou vazio, regenerar
            if (empty(trim((string) $this->code))) {
                $this->code = Product::generateProductCode(activeTenantId(), $this->type ?: 'produto');
            }
        }
        
        // Campo em branco é ausência: gravar '' em vez de NULL enchia a lista
        // de tamanhos e cores distintos com uma opção fantasma vazia, e " M "
        // com espaços a mais passava a ser um tamanho diferente de "M".
        $this->normalizarCamposOpcionais();

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Se o erro for em 'code' (colisão de código auto-gerado), regerar e revalidar uma vez
            if (!$this->editingProductId && array_key_exists('code', $e->errors())) {
                $this->code = Product::generateProductCode(activeTenantId(), $this->type ?: 'produto');
                try {
                    $this->validate();
                } catch (\Illuminate\Validation\ValidationException $e2) {
                    $this->dispatch('focus-first-error', field: array_key_first($e2->errors()));
                    throw $e2;
                }
            } else {
                $this->dispatch('focus-first-error', field: array_key_first($e->errors()));
                throw $e;
            }
        }

        $data = [
            'tenant_id' => activeTenantId(),
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'cost' => $this->cost,
            'unit' => $this->unit,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'supplier_id' => $this->supplier_id,
            'tax_type' => $this->tax_type,
            'tax_rate_id' => $this->tax_type === 'iva' ? $this->tax_rate_id : null,
            'exemption_reason' => $this->tax_type === 'isento' ? $this->exemption_reason : null,
            'manage_stock' => $this->manage_stock,
            'preco_no_pos' => (bool) $this->preco_no_pos,
            'stock_quantity' => $this->stock_quantity,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            'track_batches' => $this->track_batches,
            'track_expiry' => $this->track_expiry,
            'require_batch_on_purchase' => $this->require_batch_on_purchase,
            'require_batch_on_sale' => $this->require_batch_on_sale,
            'requires_prescription' => (bool) $this->requires_prescription,
            'is_controlled' => (bool) $this->is_controlled,
            'active_ingredient' => $this->active_ingredient,
            'dosage' => $this->dosage,
            'pharmaceutical_form' => $this->pharmaceutical_form,
            'armed_registration' => $this->armed_registration,
            'size' => $this->size,
            'color' => $this->color,
            'gender' => $this->gender,
            'material' => $this->material,
            'net_content' => $this->net_content,
            'pao_months' => $this->pao_months,
            'inci_ingredients' => $this->inci_ingredients,
            'storage_conditions' => $this->storage_conditions,
            'allergens' => $this->allergens,
            'origin_country' => $this->origin_country,
        ];

        // A gravação vai dentro de um try: uma falha da base de dados subia
        // como excepção, o Livewire devolvia 500 e o formulário ficava
        // exactamente na mesma — sem gravar e sem dizer porquê. Quem está do
        // outro lado carrega em "Criar" outra vez, e outra, sem perceber nada.
        try {

        if ($this->editingProductId) {
            // NUNCA escrever o agregado stock_quantity na edição: é derivado
            // (StockObserver mantém = SUM(invoicing_stocks)). Escrevê-lo aqui
            // devolvia o snapshot carregado ao abrir o modal (revertia vendas
            // concorrentes) e criava "ajustes" fantasma sem linhas nem ledger.
            // Stock ajusta-se na Gestão de Stock (com movimento registado).
            unset($data['stock_quantity']);

            $product = Product::findOrFail($this->editingProductId);

            if ((int) $product->tenant_id !== (int) activeTenantId()) {
                abort(403);
            }
            
            $productFolder = 'products/' . $product->id;
            
            // Handle featured image upload
            if ($this->featured_image) {
                $fileName = 'featured_' . \Str::slug($product->name) . '.' . $this->featured_image->getClientOriginalExtension();
                $imagePath = $this->featured_image->storeAs($productFolder, $fileName, 'public');
                $data['featured_image'] = $imagePath;
                
                // Delete old featured image if exists
                if ($product->featured_image && \Storage::disk('public')->exists($product->featured_image)) {
                    \Storage::disk('public')->delete($product->featured_image);
                }
            }

            // Handle gallery upload
            if (!empty($this->gallery)) {
                $galleryPaths = [];
                $galleryFolder = $productFolder . '/gallery';
                
                foreach ($this->gallery as $index => $image) {
                    $fileName = 'gallery_' . ($index + 1) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $galleryPaths[] = $image->storeAs($galleryFolder, $fileName, 'public');
                }
                
                // Merge with existing gallery
                $existingGallery = $product->gallery ?? [];
                $data['gallery'] = array_merge($existingGallery, $galleryPaths);
            }
            
            $product->update($data);
            $this->dispatch('success', message: __('Produto atualizado com sucesso!'));
        } else {
            // Create product first to get ID
            $newProduct = Product::create($data);

            // Stock inicial: materializar como linha de armazém + movimento no
            // ledger (senão o agregado nasce "órfão" — sem suporte em
            // invoicing_stocks — e é zerado pelo StockObserver ao primeiro
            // movimento Eloquent do produto).
            $initialQty = (float) ($this->stock_quantity ?: 0);
            if ($this->manage_stock && $initialQty > 0 && $newProduct->type !== 'servico') {
                $defaultWh = \App\Models\Invoicing\Warehouse::where('tenant_id', activeTenantId())
                    ->where('is_default', true)->where('is_active', true)->first()
                    ?? \App\Models\Invoicing\Warehouse::where('tenant_id', activeTenantId())
                        ->where('is_active', true)->first();
                // Empresa ainda sem armazém nenhum (acontece a quem só tem o
                // módulo Oficina, que não dá acesso à Gestão de Stock): criar o
                // armazém principal em vez de deixar o agregado órfão.
                //
                // O ramo legado "agregado-only" que aqui estava é a origem do
                // "o produto aparece disponível mas a baixa falha": o produto
                // nascia com stock_quantity>0 e ZERO linhas, o POS listava-o
                // como disponível e a saída não encontrava linha nenhuma para
                // descontar — e o primeiro movimento a sério zerava o agregado.
                if (!$defaultWh) {
                    $defaultWh = \App\Models\Invoicing\Warehouse::create([
                        'tenant_id'  => activeTenantId(),
                        'name'       => 'Armazém Principal',
                        'code'       => 'PRINCIPAL',
                        'is_default' => true,
                        'is_active'  => true,
                    ]);
                }

                // createEntry → Stock::addStock (Eloquent) → StockObserver
                // ressincroniza o agregado para o MESMO valor, agora com suporte.
                \App\Models\Invoicing\StockMovement::createEntry([
                    'warehouse_id' => $defaultWh->id,
                    'product_id'   => $newProduct->id,
                    'quantity'     => $initialQty,
                    'unit_cost'    => $this->cost ?: null,
                    'notes'        => 'Stock inicial (criação de produto)',
                ]);
            }

            $productFolder = 'products/' . $newProduct->id;
            
            // Handle featured image upload
            if ($this->featured_image) {
                $fileName = 'featured_' . \Str::slug($newProduct->name) . '.' . $this->featured_image->getClientOriginalExtension();
                $imagePath = $this->featured_image->storeAs($productFolder, $fileName, 'public');
                $newProduct->update(['featured_image' => $imagePath]);
            }

            // Handle gallery upload
            if (!empty($this->gallery)) {
                $galleryPaths = [];
                $galleryFolder = $productFolder . '/gallery';
                
                foreach ($this->gallery as $index => $image) {
                    $fileName = 'gallery_' . ($index + 1) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $galleryPaths[] = $image->storeAs($galleryFolder, $fileName, 'public');
                }
                
                $newProduct->update(['gallery' => $galleryPaths]);
            }
            
            $this->dispatch('success', message: __('Produto criado com sucesso!'));
        }

        } catch (\Throwable $e) {
            \Log::error('Products::save falhou', [
                'tenant_id' => activeTenantId(),
                'editing'   => $this->editingProductId,
                'error'     => $e->getMessage(),
            ]);

            // A mensagem técnica não vai para o ecrã — mas o utilizador tem de
            // saber que NÃO gravou, em vez de ficar a olhar para o formulário.
            $this->dispatch('error', message: __('Não foi possível gravar o produto. Verifique os campos e tente de novo.'));

            return;
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $product = Product::findOrFail($id);
        
        if ((int) $product->tenant_id !== (int) activeTenantId()) {
            abort(403);
        }
        
        $this->deletingProductId = $id;
        $this->deletingProductName = $product->name;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if (!auth()->user()->can('invoicing.products.delete')) {
            $this->dispatch('error', message: __('Sem permissão para eliminar produtos'));
            return;
        }
        
        try {
            $product = Product::findOrFail($this->deletingProductId);
            
            if ((int) $product->tenant_id !== (int) activeTenantId()) {
                abort(403);
            }
            
            // As imagens NÃO são apagadas: a eliminação é recuperável (soft
            // delete) e apagar a pasta do disco tornava o restauro inútil — o
            // produto voltava sem fotografia nenhuma, sem forma de a recuperar.
            // Os ficheiros só devem desaparecer numa eliminação definitiva.
            $product->delete();

            $this->showDeleteModal = false;
            $this->reset(['deletingProductId', 'deletingProductName']);
            $this->dispatch('success', message: __('Produto eliminado. Pode restaurá-lo no filtro "Eliminados".'));
        } catch (\Exception $e) {
            \Log::error('Products: falha ao eliminar', [
                'product_id' => $this->deletingProductId,
                'erro'       => $e->getMessage(),
            ]);
            $this->dispatch('error', message: __('Erro ao excluir produto!'));
        }
    }

    /**
     * Restaura um produto eliminado.
     *
     * A eliminação sempre foi recuperável (o modelo usa SoftDeletes), mas não
     * havia forma de o fazer pela aplicação — só com SQL directo.
     */
    public function restore($id)
    {
        if (!auth()->user()->can('invoicing.products.delete')) {
            $this->dispatch('error', message: __('Sem permissão para restaurar produtos'));
            return;
        }

        $product = Product::onlyTrashed()
            ->where('tenant_id', activeTenantId())
            ->find($id);

        if (!$product) {
            $this->dispatch('error', message: __('Produto não encontrado nesta empresa.'));
            return;
        }

        $product->restore();
        $this->dispatch('success', message: __('Produto restaurado: :nome', ['nome' => $product->name]));
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingProductId', 'deletingProductName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Campos opcionais de catálogo especializado: em branco é ausência (NULL).
     *
     * Guardar '' faria com que a lista de tamanhos e cores distintos do
     * catálogo tivesse uma opção fantasma vazia nos filtros.
     */
    private function normalizarCamposOpcionais(): void
    {
        foreach ([
            'active_ingredient', 'dosage', 'pharmaceutical_form', 'armed_registration',
            'size', 'color', 'gender', 'material',
            'net_content', 'inci_ingredients', 'storage_conditions', 'allergens', 'origin_country',
        ] as $campo) {
            $valor = trim((string) $this->{$campo});
            $this->{$campo} = $valor === '' ? null : $valor;
        }

        // Os meses depois de aberto vêm do formulário como texto. Campo vazio é
        // "não indicado" e não zero meses — gravar 0 seria dizer que o produto
        // se estraga no dia em que se abre.
        $pao = trim((string) $this->pao_months);
        $this->pao_months = $pao === '' ? null : (int) $pao;

        // Os campos de stock também vêm como TEXTO, e um campo limpo chega
        // aqui como ''. A validação deixa passar (são nullable), mas o MySQL
        // recusa '' numa coluna inteira e o INSERT rebentava — o formulário
        // não gravava e não dizia porquê. O mínimo em branco é 0 (não há
        // mínimo); o máximo em branco é "sem máximo", ou seja null.
        $min = trim((string) $this->stock_min);
        $this->stock_min = $min === '' ? 0 : (int) $min;

        $max = trim((string) $this->stock_max);
        $this->stock_max = $max === '' ? null : (int) $max;

        $qtd = trim((string) $this->stock_quantity);
        $this->stock_quantity = $qtd === '' ? 0 : (float) $qtd;
    }

    private function resetForm()
    {
        $this->reset([
            'code', 'name', 'description', 'featured_image', 'currentFeaturedImage',
            'gallery', 'currentGallery', 'editingProductId',
            // Os lotes têm de cair aqui como tudo o resto. Sem isto, quem edita
            // um medicamento com lote, fecha a janela e carrega em "Novo
            // Produto" recebe o formulário com "Exigir lote na venda" já ligado
            // — e o artigo nasce a exigir um lote que nunca vai ter, ficando
            // impossível de vender ao balcão.
            'track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale',
            'requires_prescription', 'is_controlled', 'active_ingredient', 'dosage',
            'pharmaceutical_form', 'armed_registration',
            'size', 'color', 'gender', 'material',
            'net_content', 'pao_months', 'inci_ingredients',
            'storage_conditions', 'allergens', 'origin_country',
        ]);
        $this->price = 0;
        $this->cost = 0;
        $this->unit = 'UN';
        $this->type = 'produto';
        $this->category_id = null;
        $this->brand_id = null;
        $this->supplier_id = null;
        $this->tax_type = 'iva';
        $this->tax_rate_id = null;
        $this->exemption_reason = null;
        // Mesmo valor por omissão da propriedade: artigo físico conta stock.
        $this->manage_stock = true;
        $this->preco_no_pos = false;
        $this->stock_quantity = 0;
        $this->stock_min = 0;
        $this->stock_max = null;
    }

    /**
     * Tamanhos e cores que o catálogo desta empresa usa de facto.
     *
     * Os filtros são selects e não caixas de texto porque ninguém se lembra de
     * como escreveu "azul-marinho" da última vez — e um filtro por texto livre
     * que devolve zero resultados por causa de um hífen parece uma avaria.
     */
    public function getVariantesCatalogoProperty(): array
    {
        $catalogo = Product::where('tenant_id', activeTenantId());

        return [
            'tamanhos' => (clone $catalogo)
                ->whereNotNull('size')->where('size', '<>', '')
                ->distinct()->orderBy('size')->pluck('size')->all(),
            'cores' => (clone $catalogo)
                ->whereNotNull('color')->where('color', '<>', '')
                ->distinct()->orderBy('color')->pluck('color')->all(),
            // Serve para MOSTRAR os filtros a quem já tem artigos assim
            // marcados mesmo com o perfil de farmácia desligado — senão quem
            // desligasse o perfil ficava sem forma de filtrar os dados que
            // continuam gravados. Numa oficina (sem perfil e sem dados) os
            // filtros continuam escondidos, que é o que se pretende.
            'ha_receituario' => (clone $catalogo)->where(function ($q) {
                $q->where('requires_prescription', true)->orWhere('is_controlled', true);
            })->exists(),
            // Mesma razão do 'ha_receituario': quem desligue o perfil de
            // mercearia continua a ter artigos que só podem ir para o
            // frigorífico, e tem de continuar a conseguir encontrá-los.
            'ha_conservacao' => (clone $catalogo)
                ->whereNotNull('storage_conditions')->where('storage_conditions', '<>', '')
                ->exists(),
        ];
    }

    public function render()
    {
        $products = Product::where('tenant_id', activeTenantId())
            // Eliminados só aparecem quando pedidos: a eliminação é recuperável
            // e o administrador precisa de os poder ver para os restaurar.
            ->when($this->mostrarEliminados, fn ($q) => $q->onlyTrashed())
            ->withSum('stocks as stocks_total_quantity', 'quantity')
            // A taxa é lida em cada linha da lista ($product->taxRate->rate).
            // Sem isto era uma consulta por artigo: com 100 por página, 100
            // consultas a cada tecla escrita na pesquisa.
            ->with('taxRate')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->when($this->stockFilter, function ($query) {
                if ($this->stockFilter === 'com_stock') {
                    $query->where('manage_stock', true)->where('stock_quantity', '>', 0);
                } elseif ($this->stockFilter === 'sem_stock') {
                    $query->where('manage_stock', true)->where('stock_quantity', '<=', 0);
                } elseif ($this->stockFilter === 'gerenciado') {
                    // Todos os que gerem stock, tenham ou não existência. Era o
                    // oposto do "não gerenciado" e faltava: não havia forma de
                    // ver de uma vez o universo que conta para inventário.
                    $query->where('manage_stock', true);
                } elseif ($this->stockFilter === 'nao_gerenciado') {
                    $query->where('manage_stock', false);
                } elseif ($this->stockFilter === 'stock_baixo') {
                    // Abaixo do mínimo definido — só faz sentido para quem gere
                    // stock e tem mínimo configurado (>0).
                    $query->where('manage_stock', true)
                          ->whereNotNull('stock_min')->where('stock_min', '>', 0)
                          ->whereColumn('stock_quantity', '<=', 'stock_min');
                }
            })
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('is_active', $this->statusFilter === 'activo');
            })
            ->when($this->qualidadeFilter, function ($query) {
                if ($this->qualidadeFilter === 'sem_preco') {
                    $query->where(fn ($q) => $q->whereNull('price')->orWhere('price', '<=', 0));
                } elseif ($this->qualidadeFilter === 'sem_codigo_barras') {
                    $query->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''));
                } elseif ($this->qualidadeFilter === 'sem_categoria') {
                    $query->whereNull('category_id');
                }
            })
            // Comparação explícita com '': "não filtrar" é a string vazia, e
            // filterPrescricao='nao' tem de continuar a filtrar (when() sozinho
            // trata qualquer valor falsy como "sem filtro").
            ->when($this->filterPrescricao !== '', function ($query) {
                if ($this->filterPrescricao === 'sim') {
                    $query->comReceita();
                } else {
                    // NULL conta como "não exige": a coluna tem default false,
                    // mas artigos importados de sistemas antigos chegam sem
                    // valor e desapareceriam da lista com um where simples.
                    $query->where(function ($q) {
                        $q->where('requires_prescription', false)
                          ->orWhereNull('requires_prescription');
                    });
                }
            })
            ->when($this->filterTamanho !== '', fn ($query) => $query->porTamanho($this->filterTamanho))
            ->when($this->filterCor !== '', fn ($query) => $query->porCor($this->filterCor))
            ->when($this->filterConservacao !== '', fn ($query) => $query->porConservacao($this->filterConservacao))
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->latest()
            ->paginate($this->perPage);

        // Obter taxas ativas do tenant da tabela CORRETA: invoicing_taxes
        $taxRates = Tax::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('type', 'iva')
            ->orderBy('rate')
            ->get();

        // Códigos oficiais de isenção AGT (DS.120 §9.5 — M01–M93, S01–S03, I01–I16)
        $exemptionCodes = AGTTaxExemptionCode::query()
            ->where('is_active', true)
            ->orderBy('tax_type')
            ->orderBy('code')
            ->get();

        // Cartões do topo. Estavam a ser calculados DENTRO da blade e os três
        // estavam errados:
        //  - "Serviços" contava `unit = 'SRV'`, mas serviço é `type = 'servico'`
        //    (uma empresa com 13 serviços mostrava 0);
        //  - "Valor Médio" usava auth()->user()->tenant_id em vez da empresa
        //    ACTIVA — quem tivesse empresa activa diferente via a média de outra;
        //  - "Total Produtos" mostrava o total FILTRADO, apesar de dizer
        //    "No catálogo": com um filtro activo mostrava 0.
        $catalogo = Product::where('tenant_id', activeTenantId());

        $estatisticas = [
            'produtos'    => (clone $catalogo)->where('type', 'produto')->count(),
            'servicos'    => (clone $catalogo)->where('type', 'servico')->count(),
            'preco_medio' => (float) ((clone $catalogo)->avg('price') ?? 0),
        ];

        // Rastreio passado explicitamente: aceder a $this->rastreio dentro de um
        // bloco @php da vista é ambíguo (o $this ali nem sempre é o componente)
        // e devolvia null depois de o @if ter passado.
        $rastreio = $this->rastreio;

        // Passado explicitamente pela mesma razão que $rastreio: dentro de um
        // bloco @php da vista o $this nem sempre é o componente.
        $variantes = $this->variantesCatalogo;

        // Categorias para o filtro (só as do tenant, por nome).
        $categorias = \App\Models\Category::where('tenant_id', activeTenantId())
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.workshop.artigos.products', compact('products', 'taxRates', 'exemptionCodes', 'estatisticas', 'rastreio', 'variantes', 'categorias'));
    }
}

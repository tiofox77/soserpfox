<?php

namespace App\Livewire\Invoicing;

use App\Models\{Product};
use App\Models\Invoicing\Tax;
use App\Models\AGT\AGTTaxExemptionCode;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Produtos')]
class Products extends Component
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
    public $manage_stock = false;
    public $stock_quantity = 0;
    public $stock_min = 0;
    public $stock_max = null;
    
    // Batch tracking fields
    public $track_batches = false;
    public $track_expiry = false;
    public $require_batch_on_purchase = false;
    public $require_batch_on_sale = false;
    
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

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['typeFilter', 'stockFilter', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        if (!auth()->user()->can('invoicing.products.create')) {
            $this->dispatch('error', message: 'Sem permissão para criar produtos');
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
    
    // Atualizar cÃ³digo quando o tipo mudar
    public function updatedType($value)
    {
        if (!$this->editingProductId) {
            $this->code = Product::generateProductCode(activeTenantId(), $value);
        }
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
            $this->dispatch('error', message: 'Sem permissÃ£o para editar produtos');
            return;
        }
        
        $this->closeViewModal(); // Fechar modal de visualizaÃ§Ã£o se estiver aberta
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
        $this->stock_quantity = $product->stock_quantity;
        $this->stock_min = $product->stock_min ?? 0;
        $this->stock_max = $product->stock_max;
        $this->track_batches = $product->track_batches ?? false;
        $this->track_expiry = $product->track_expiry ?? false;
        $this->require_batch_on_purchase = $product->require_batch_on_purchase ?? false;
        $this->require_batch_on_sale = $product->require_batch_on_sale ?? false;
        $this->showModal = true;
    }

    public function save()
    {
        // Verificar permissão apropriada
        if ($this->editingProductId) {
            if (!auth()->user()->can('invoicing.products.edit')) {
                $this->dispatch('error', message: 'Sem permissão para editar produtos');
                return;
            }
        } else {
            if (!auth()->user()->can('invoicing.products.create')) {
                $this->dispatch('error', message: 'Sem permissão para criar produtos');
                return;
            }
            // Garantir auto-code: se ficou vazio, regenerar
            if (empty(trim((string) $this->code))) {
                $this->code = Product::generateProductCode(activeTenantId(), $this->type ?: 'produto');
            }
        }
        
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
            'stock_quantity' => $this->stock_quantity,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            'track_batches' => $this->track_batches,
            'track_expiry' => $this->track_expiry,
            'require_batch_on_purchase' => $this->require_batch_on_purchase,
            'require_batch_on_sale' => $this->require_batch_on_sale,
        ];

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
            $this->dispatch('success', message: 'Produto atualizado com sucesso!');
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
            
            $this->dispatch('success', message: 'Produto criado com sucesso!');
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
            $this->dispatch('error', message: 'Sem permissÃ£o para eliminar produtos');
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
            $this->dispatch('success', message: 'Produto eliminado. Pode restaurá-lo no filtro "Eliminados".');
        } catch (\Exception $e) {
            \Log::error('Products: falha ao eliminar', [
                'product_id' => $this->deletingProductId,
                'erro'       => $e->getMessage(),
            ]);
            $this->dispatch('error', message: 'Erro ao excluir produto!');
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
            $this->dispatch('error', message: 'Sem permissão para restaurar produtos');
            return;
        }

        $product = Product::onlyTrashed()
            ->where('tenant_id', activeTenantId())
            ->find($id);

        if (!$product) {
            $this->dispatch('error', message: 'Produto não encontrado nesta empresa.');
            return;
        }

        $product->restore();
        $this->dispatch('success', message: 'Produto restaurado: ' . $product->name);
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

    private function resetForm()
    {
        $this->reset(['code', 'name', 'description', 'featured_image', 'currentFeaturedImage', 'gallery', 'currentGallery', 'editingProductId']);
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
        $this->manage_stock = false;
        $this->stock_quantity = 0;
        $this->stock_min = 0;
        $this->stock_max = null;
    }

    public function render()
    {
        $products = Product::where('tenant_id', activeTenantId())
            // Eliminados só aparecem quando pedidos: a eliminação é recuperável
            // e o administrador precisa de os poder ver para os restaurar.
            ->when($this->mostrarEliminados, fn ($q) => $q->onlyTrashed())
            ->withSum('stocks as stocks_total_quantity', 'quantity')
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
                } elseif ($this->stockFilter === 'nao_gerenciado') {
                    $query->where('manage_stock', false);
                }
            })
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

        // CÃ³digos oficiais de isenÃ§Ã£o AGT (DS.120 Â§9.5 â€” M01â€“M93, S01â€“S03, I01â€“I16)
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

        return view('livewire.invoicing.products.products', compact('products', 'taxRates', 'exemptionCodes', 'estatisticas', 'rastreio'));
    }
}

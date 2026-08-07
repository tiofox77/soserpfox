<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Client;
use App\Models\Product;
use App\Helpers\InvoiceCalculationHelper;
use App\Helpers\DiscountHelper;
use App\Helpers\DocumentConfigHelper;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Nova Fatura de Venda')]
class InvoiceCreate extends Component
{
    public $invoiceId = null;
    public $isEdit = false;

    // Form fields
    public $client_id = '';
    public $warehouse_id = '';
    public $invoice_date;
    public $due_date;
    public $notes = '';
    public $terms = '';
    public $discount_amount = 0;
    public $discount_commercial = 0; // Desconto Comercial (antes IVA)
    public $discount_financial = 0;  // Desconto Financeiro (após IVA)
    public $is_service = false;      // Se é prestação de serviço (sujeito a IRT)
    public $delivery_date = '';      // Data disponibilização bens (Art. 6º RJF)
    public $delivery_location = '';  // Local disponibilização bens (Art. 6º RJF)

    // Tipo de documento (AGT):
    //  FT = Fatura        → série 'invoice' (FT), a pagar depois
    //  FR = Fatura-Recibo → série 'pos' (FR, MESMA sequência do POS), paga no ato
    public $invoice_type = 'FT';
    public $series_id = '';
    public $payment_method = '';     // obrigatório na Fatura-Recibo
    public $amount_received = null;  // valor entregue (FR) — para troco

    // ── Impostos AGT além do IVA (Decreto 71/25) ──────────────────────────
    // Uma linha pode acumular IVA + IEC (bebidas, tabaco, combustíveis) ou
    // IVA + IS (verbas do Imposto de Selo). Guardam-se em invoicing_line_taxes;
    // aqui só o que o utilizador escolheu, indexado pelo id da linha do carrinho.
    public array $lineIec = [];   // [itemId => pautal_code]
    public array $lineIs  = [];   // [itemId => verba_no]

    // ── Retenção na fonte / cativação ─────────────────────────────────────
    // Adquirentes obrigados a cativar retêm parte do valor. Vai em
    // withholdingTaxList no payload e em invoicing_withholding_taxes.
    public $withholding_type = '';
    public $withholding_percentage = '';
    public $withholding_amount = 0;

    /**
     * Região fiscal do documento, quando diferente da província do adquirente.
     *
     * O regime de Cabinda depende do local da operação, não apenas da sede do
     * cliente: a mesma entidade pode comprar em Luanda e em Cabinda. Vazio =
     * deriva da província do cliente.
     */
    public $tax_country_region = '';

    /**
     * Tipos de retenção ACEITES pela AGT, com a descrição oficial.
     *
     * A lista é fechada: [IRT, II, IS, IVA, IP, IAC, OU]. Qualquer outro valor
     * é recusado na validação do payload — foi o que aconteceu com 'IPC'
     * (Prestação de Capitais), que na nomenclatura da AGT é 'IAC' (Aplicação de
     * Capitais), e com 'IPU', que é simplesmente 'IP'.
     */
    public const DESCRICOES_RETENCAO = [
        'IRT' => 'Imposto sobre o Rendimento do Trabalho',
        'II'  => 'Imposto Industrial',
        'IS'  => 'Imposto de Selo',
        'IVA' => 'IVA cativado',
        'IP'  => 'Imposto Predial',
        'IAC' => 'Imposto sobre a Aplicação de Capitais',
        'OU'  => 'Outras retenções',
    ];

    // Product selection
    public $showProductModal = false;
    public $searchProduct = '';
    public $selectedCategory = '';
    
    // Client search
    public $searchClient = '';
    
    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->reset('searchClient');
        
        // Salvar na sessão para persistir entre reloads
        $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $clientId]);
        
        // Get client name
        $client = Client::find($clientId);
        
        // Force clear input visually
        $this->dispatch('client-selected');
        
        // Toast notification
        $client = Client::find($clientId);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Cliente selecionado: ' . ($client ? $client->name : '')
        ]);
    }
    
    public function clearClient()
    {
        $this->client_id = '';
        $this->searchClient = '';
        
        // Remover da sessão
        $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
        session()->forget($sessionKey);
        
        // Restaurar cliente padrão (Consumidor Final)
        $defaultClient = Client::where('tenant_id', activeTenantId())
            ->where('nif', '999999999')
            ->first();
        
        if ($defaultClient) {
            $this->client_id = $defaultClient->id;
        }
    }
    
    // Quick Client creation
    public $showQuickClientModal = false;
    public $quickClientName = '';
    public $quickClientTaxId = '';
    public $quickClientEmail = '';
    public $quickClientPhone = '';
    public $quickClientAddress = '';

    // Cart identifier
    public $cartInstance;

    protected $rules = [
        'client_id' => 'required|exists:invoicing_clients,id',
        'warehouse_id' => 'nullable|exists:invoicing_warehouses,id',
        'invoice_date' => 'required|date',
        'due_date' => 'nullable|date|after:invoice_date',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_commercial' => 'nullable|numeric|min:0',
        'discount_financial' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
        'terms' => 'nullable|string|max:1000',
        'invoice_type' => 'required|in:FT,FR',
        'series_id' => 'nullable|integer',
    ];

    /** Regras dinâmicas: a Fatura-Recibo exige método de pagamento. */
    protected function rules(): array
    {
        $rules = $this->rules;
        if ($this->isFaturaRecibo()) {
            $rules['payment_method'] = 'required|string|max:50';
        }
        return $rules;
    }

    protected function messages(): array
    {
        return [
            'payment_method.required' => 'A Fatura-Recibo é paga no acto — selecione a forma de pagamento.',
            'invoice_type.in'         => 'Tipo de documento inválido.',
        ];
    }

    public function updatedInvoiceType(): void
    {
        // Mudar de FT para FR muda o conjunto de séries: a que estava
        // escolhida deixa de servir. Escolhe-se a por omissão do tipo novo.
        $this->series_id = '';
        $this->preSeleccionarSerie();
    }

    /**
     * Deixa a série por omissão ESCOLHIDA no campo, em vez de implícita.
     *
     * O selector abria em "Série AGT padrão" — um valor vazio. O documento
     * saía na série por omissão de qualquer maneira, mas o ecrã não dizia
     * qual, e quem emitia não tinha como saber em que série ia ficar sem ir
     * procurar às definições. Numa empresa com três séries de factura, isso é
     * uma escolha fiscal feita às escuras.
     *
     * Continua a poder trocar-se; passa é a estar à vista.
     */
    public function preSeleccionarSerie(): void
    {
        if ($this->series_id) {
            return;
        }

        $tipo = strtoupper((string) $this->invoice_type) === 'FR' ? 'pos' : 'invoice';

        $serie = InvoicingSeries::where('tenant_id', activeTenantId())
            ->where('document_type', $tipo)
            ->where('is_active', true)
            ->when(
                \Illuminate\Support\Facades\Storage::disk('local')
                    ->exists('agt/tenants/' . activeTenantId() . '/private_key.pem'),
                fn ($q) => $q->whereNotNull('agt_series_id')
            )
            ->orderByDesc('is_default')
            ->orderBy('series_code')
            ->first();

        if ($serie) {
            $this->series_id = (string) $serie->id;
        }
    }

    /**
     * Verifica se o carrinho contém produtos físicos (não serviços)
     */
    public function hasPhysicalProducts(): bool
    {
        $cartItems = Cart::session($this->cartInstance)->getContent();
        foreach ($cartItems as $item) {
            $type = $item->attributes['type'] ?? 'produto';
            if ($type !== 'servico') {
                return true;
            }
        }
        return false;
    }
    
    public function updated($propertyName)
    {
        // Quando Client_id é alterado, salvar na sessão
        if ($propertyName === 'client_id' && $this->client_id) {
            $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
            session([$sessionKey => $this->client_id]);
        }
        
        // Validar desconto comercial
        if ($propertyName === 'discount_commercial' && $this->discount_commercial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_commercial, 'commercial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->discount_commercial = 0;
            }
        }
        
        // Validar desconto financeiro
        if ($propertyName === 'discount_financial' && $this->discount_financial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_financial, 'financial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->discount_financial = 0;
            }
        }
    }

    public function mount($id = null, $type = null)
    {
        // Tipo pré-seleccionado pelo menu/URL (?type=FR → Fatura-Recibo)
        $tipoUrl = strtoupper((string) ($type ?: request()->query('type')));
        if (!$id && in_array($tipoUrl, ['FT', 'FR'], true)) {
            $this->invoice_type = $tipoUrl;
        }

        $this->invoice_date = now()->format('Y-m-d');
        // Usar configuração de dias para vencimento
        $this->due_date = DocumentConfigHelper::getInvoiceDueDate()->format('Y-m-d');
        
        // Unique cart instance per user and tenant (persiste entre reloads)
        $this->cartInstance = 'invoice_' . activeTenantId() . '_' . auth()->id();
        
        // Set default warehouse
        $defaultWarehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->first();
        
        if ($defaultWarehouse) {
            $this->warehouse_id = $defaultWarehouse->id;
        }
        
        // A série fica escolhida à vista, não implícita (ver preSeleccionarSerie).
        $this->preSeleccionarSerie();

        if ($id) {
            $this->isEdit = true;
            $this->invoiceId = $id;
            $this->loadInvoice($id);
        } else {
            // Restaurar cliente da sessão se existir
            $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
            $savedClientId = session($sessionKey);
            
            if ($savedClientId && Client::where('id', $savedClientId)->where('tenant_id', activeTenantId())->exists()) {
                $this->client_id = $savedClientId;
            } else {
                // Set default client (Consumidor Final)
                $defaultClient = Client::where('tenant_id', activeTenantId())
                    ->where('nif', '999999999')
                    ->first();
                
                if ($defaultClient) {
                    $this->client_id = $defaultClient->id;
                }
            }
        }
        
        $this->searchClient = '';
    }

    public function loadInvoice($id)
    {
        $invoice = SalesInvoice::where('tenant_id', activeTenantId())
            ->with('items.product')
            ->findOrFail($id);

        $this->client_id = $invoice->client_id;
        $this->warehouse_id = $invoice->warehouse_id;
        $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
        $this->due_date = $invoice->due_date?->format('Y-m-d');
        $this->notes = $invoice->notes;
        $this->terms = $invoice->terms;
        $this->discount_amount = $invoice->discount_amount;
        $this->discount_commercial = $invoice->discount_commercial ?? 0;
        $this->discount_financial = $invoice->discount_financial ?? 0;
        $this->is_service = $invoice->is_service ?? false;
        $this->invoice_type = $invoice->invoice_type ?: 'FT';
        $this->payment_method = $invoice->payment_method ?: '';

        // Load items into cart
        Cart::session($this->cartInstance)->clear();
        foreach ($invoice->items as $item) {
            Cart::session($this->cartInstance)->add([
                'id' => $item->product_id,
                'name' => $item->product->name,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'attributes' => [
                    'tax_rate' => $item->tax_rate,
                    'discount_percent' => $item->discount_percent,
                    'unit' => $item->unit,
                ]
            ]);
        }
    }

    public function render()
    {
        // Get clients - always load (with optional search filter)
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('email', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchClient . '%');
            });
        }
        
        $clients = $clientsQuery->orderBy('name')->limit(50)->get();

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $seriesType = strtoupper((string) $this->invoice_type) === 'FR' ? 'pos' : 'invoice';
        $issuanceSeries = InvoicingSeries::where('tenant_id', activeTenantId())
            ->where('document_type', $seriesType)
            ->where('is_active', true)
            ->when(
                \Illuminate\Support\Facades\Storage::disk('local')
                    ->exists('agt/tenants/' . activeTenantId() . '/private_key.pem'),
                fn ($query) => $query->whereNotNull('agt_series_id')
            )
            ->orderByDesc('is_default')
            ->orderBy('series_code')
            ->get();

        // Get cart items
        $cartItems = Cart::session($this->cartInstance)->getContent();
        
        // 🎩 CÁLCULO MODELO AGT ANGOLA usando Helper centralizado
        $totals = InvoiceCalculationHelper::calculateTotals(
            $cartItems,
            $this->discount_commercial,
            $this->discount_amount,
            $this->discount_financial,
            $this->is_service
        );

        // Get products for modal with stock info
        $products = [];
        if ($this->showProductModal) {
            $query = Product::where('tenant_id', activeTenantId())
                ->where('is_active', true);

            if ($this->searchProduct) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->searchProduct . '%')
                      ->orWhere('code', 'like', '%' . $this->searchProduct . '%');
                });
            }

            $products = $query->orderBy('name')->limit(50)->get()->map(function($product) {
                // Calculate total stock from all warehouses
                $totalStock = \App\Models\Invoicing\Stock::where('tenant_id', activeTenantId())
                    ->where('product_id', $product->id)
                    ->sum('quantity');
                    
                $product->stock_quantity = $totalStock;
                return $product;
            });
        }

        // Listas oficiais AGT já em base: 29 códigos pautais (IEC) e 24 verbas (IS)
        $iecCodes = \Illuminate\Support\Facades\Cache::remember('agt_iec_codes', 3600, fn () =>
            \Illuminate\Support\Facades\DB::table('agt_iec_pautal_codes')
                ->where('is_active', true)->orderBy('pautal_code')
                ->get(['pautal_code', 'description', 'rate_percentage']));

        $isVerbas = \Illuminate\Support\Facades\Cache::remember('agt_is_verbas', 3600, fn () =>
            \Illuminate\Support\Facades\DB::table('agt_is_verbas')
                ->where('is_active', true)->orderBy('verba_no')
                ->get(['verba_no', 'description', 'rate', 'rate_type']));

        // Região fiscal do adquirente: Cabinda tem regime próprio (AO-CAB)
        $cliente = $this->client_id ? Client::find($this->client_id) : null;
        $regiaoFiscal = in_array($this->tax_country_region, ['AO', 'AO-CAB'], true)
            ? $this->tax_country_region
            : \App\Services\Invoicing\TaxResolver::regionForClient($cliente);

        return view('livewire.invoicing.faturas-venda.create', array_merge([
            'clients' => $clients,
            'warehouses' => $warehouses,
            'cartItems' => $cartItems,
            'products' => $products,
            'issuanceSeries' => $issuanceSeries,
            'iecCodes' => $iecCodes,
            'isVerbas' => $isVerbas,
            'regiaoFiscal' => $regiaoFiscal,
            'clienteProvincia' => $cliente->province ?? null,
            'extraTaxTotal' => $this->extraTaxTotal(),
        ], $totals));
    }

    /**
     * Total de IEC + IS das linhas do carrinho.
     *
     * Estes impostos acrescem ao IVA e TÊM de entrar no total do documento,
     * senão netTotal + taxPayable ≠ grossTotal e a AGT recusa.
     */
    public function extraTaxTotal(): float
    {
        $total = 0.0;

        foreach (Cart::session($this->cartInstance)->getContent() as $item) {
            foreach ($this->lineTaxesFor($item) as $t) {
                $total += (float) $t['tax_amount'];
            }
        }

        return round($total, 2);
    }

    /**
     * Impostos extra (IEC/IS) de um item do carrinho, já calculados sobre a
     * base da linha (preço × quantidade, com desconto aplicado).
     */
    protected function lineTaxesFor($item): array
    {
        // A regra vive no ImpostosDaLinha, partilhada com os outros
        // documentos: o mesmo artigo com IEC tem de sair com o imposto na
        // proforma e na factura, e nao so aqui.
        return \App\Services\Invoicing\ImpostosDaLinha::calcular(
            (float) $item->getPriceSum(),
            $this->lineIec[$item->id] ?? null,
            $this->lineIs[$item->id] ?? null
        );
    }

    /** Impostos extra de uma linha, para a vista mostrar o valor calculado. */
    public function lineTaxesPreview($itemId): array
    {
        $item = Cart::session($this->cartInstance)->get($itemId);

        return $item ? $this->lineTaxesFor($item) : [];
    }

    /** Escolher/limpar o IEC de uma linha. */
    public function setLineIec($itemId, $pautalCode): void
    {
        if (filled($pautalCode)) {
            $this->lineIec[$itemId] = $pautalCode;
        } else {
            unset($this->lineIec[$itemId]);
        }
    }

    /** Escolher/limpar a verba de Imposto de Selo de uma linha. */
    public function setLineIs($itemId, $verbaNo): void
    {
        if (filled($verbaNo)) {
            $this->lineIs[$itemId] = (string) $verbaNo;
        } else {
            unset($this->lineIs[$itemId]);
        }
    }

    /** Recalcular a retenção quando o tipo ou a percentagem mudam. */
    public function updatedWithholdingPercentage(): void
    {
        $this->recalcularRetencao();
    }

    public function updatedWithholdingType(): void
    {
        if (blank($this->withholding_type)) {
            $this->withholding_percentage = '';
            $this->withholding_amount = 0;
            return;
        }
        // IRT sobre prestação de serviços: 6,5% é a taxa corrente
        if (blank($this->withholding_percentage) && $this->withholding_type === 'IRT') {
            $this->withholding_percentage = 6.5;
        }
        $this->recalcularRetencao();
    }

    protected function recalcularRetencao(): void
    {
        $pct = (float) $this->withholding_percentage;
        if ($pct <= 0) {
            $this->withholding_amount = 0;
            return;
        }

        $base = (float) Cart::session($this->cartInstance)->getSubTotal();
        $this->withholding_amount = round($base * $pct / 100, 2);
    }
    public function addProduct($productId)
    {
        $product = Product::with('taxRate')->where('tenant_id', activeTenantId())->findOrFail($productId);

        // Verificar se produto rastreia lotes e se exige lote na venda
        if ($product->track_batches && $product->require_batch_on_sale) {
            // Verificar se há lotes disponíveis
            $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $productId)
                ->where('warehouse_id', $this->warehouse_id)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->get();
            
            if ($availableBatches->isEmpty()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Produto exige lote na venda mas não há lotes disponíveis: ' . $product->name
                ]);
                return;
            }
            
            // Verificar se há lotes expirados
            $expiredBatches = $availableBatches->filter(fn($b) => $b->is_expired);
            if ($expiredBatches->isNotEmpty()) {
                $expiredNumbers = $expiredBatches->pluck('batch_number')->filter()->join(', ');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '⚠️ Lotes expirados encontrados: ' . ($expiredNumbers ?: 'Sem número')
                ]);
                return;
            }
            
            // Verificar se há lotes expirando em breve
            $expiringSoon = $availableBatches->filter(fn($b) => $b->is_expiring_soon && !$b->is_expired);
            if ($expiringSoon->isNotEmpty()) {
                $expiringNumbers = $expiringSoon->pluck('batch_number')->filter()->join(', ');
                $days = $expiringSoon->first()->days_until_expiry ?? 0;
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ Atenção: Lote(s) expirando em ' . $days . ' dias: ' . ($expiringNumbers ?: 'Sem número')
                ]);
            }
            
            // Informar quantidade disponível nos lotes
            $totalAvailable = $availableBatches->sum('quantity_available');
            \Log::info('Lotes disponíveis para venda', [
                'product_id' => $productId,
                'product_name' => $product->name,
                'batches_count' => $availableBatches->count(),
                'total_available' => $totalAvailable,
            ]);
        }

        // Verificar se produto já existe no carrinho
        $existingItem = Cart::session($this->cartInstance)->get($productId);
        
        if ($existingItem) {
            // Se produto rastreia lotes, verificar disponibilidade antes de incrementar
            if ($product->track_batches) {
                $newQuantity = $existingItem->quantity + 1;
                $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->get();
                
                $totalAvailable = $availableBatches->sum('quantity_available');
                
                if ($newQuantity > $totalAvailable) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => '❌ Quantidade insuficiente em lotes. Disponível: ' . $totalAvailable
                    ]);
                    return;
                }
            }
            
            // Se já existe, incrementa quantidade
            Cart::session($this->cartInstance)->update($productId, [
                'quantity' => 1 // Incrementa 1
            ]);
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Quantidade incrementada: ' . $product->name
            ]);
        } else {
            // A taxa vem do TaxResolver, a fonte ÚNICA do imposto por linha:
            // respeita o regime da empresa e tem recurso à taxa por omissão.
            // Antes lia-se $product->taxRate directamente e, quando o produto
            // não tinha taxa atribuída (tax_rate_id nulo), a linha saía a 0% —
            // faturas emitidas sem IVA quando devia ser 14%.
            $lineTx  = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());
            $taxRate = (float) $lineTx['rate'];

            // Adiciona novo item
            Cart::session($this->cartInstance)->add([
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 1,
                'attributes' => [
                    'tax_rate' => $taxRate,
                    'discount_percent' => 0,
                    'unit' => $product->unit ?? 'UN',
                    'type' => $product->type ?? 'produto',
                    'tax_type' => $product->tax_type ?? 'iva',
                    'exemption_reason' => $product->exemption_reason
                        ?? $lineTx['exemption_code'],
                ]
            ]);
            
            $typeLabel = $product->type === 'servico' ? 'Serviço' : 'Produto';
            $message = $typeLabel . ' adicionado: ' . $product->name . ' (IVA: ' . $taxRate . '%)';
            
            // Se rastreia lotes, adicionar info
            if ($product->track_batches) {
                $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->get();
                $totalAvailable = $availableBatches->sum('quantity_available');
                $message .= ' | Lotes: ' . $availableBatches->count() . ' (Total disponível: ' . $totalAvailable . ')';
            }
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);
        }

        $this->showProductModal = false;
        $this->searchProduct = '';
    }
    
    public function clearCart()
    {
        Cart::session($this->cartInstance)->clear();
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Carrinho limpo com sucesso!'
        ]);
    }

    public function removeProduct($productId)
    {
        $item = Cart::session($this->cartInstance)->get($productId);
        $productName = $item ? $item->name : 'Produto';
        
        Cart::session($this->cartInstance)->remove($productId);
        
        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => 'Produto removido: ' . $productName
        ]);
    }

    public function updateQuantity($productId, $quantity)
    {
        if ($quantity > 0) {
            // Verificar se produto rastreia lotes
            $product = Product::where('tenant_id', activeTenantId())->find($productId);
            
            if ($product && $product->track_batches) {
                $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->get();
                
                $totalAvailable = $availableBatches->sum('quantity_available');
                
                if ($quantity > $totalAvailable) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => '❌ Quantidade insuficiente em lotes. Disponível: ' . $totalAvailable
                    ]);
                    return;
                }
            }
            
            Cart::session($this->cartInstance)->update($productId, [
                'quantity' => [
                    'relative' => false,
                    'value' => $quantity
                ]
            ]);
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Quantidade atualizada para: ' . $quantity
            ]);
        }
    }

    public function updatePrice($productId, $price)
    {
        if ($price >= 0) {
            Cart::session($this->cartInstance)->update($productId, [
                'price' => $price
            ]);
        }
    }

    public function updateDiscount($productId, $discountPercent)
    {
        if ($discountPercent >= 0 && $discountPercent <= 100) {
            // Remover condition anterior se existir
            Cart::session($this->cartInstance)->clearItemConditions($productId);
            
            // Aplicar novo desconto usando conditions
            if ($discountPercent > 0) {
                $condition = new \Darryldecode\Cart\CartCondition([
                    'name' => 'DESCONTO',
                    'type' => 'discount',
                    'target' => 'item',
                    'value' => '-' . $discountPercent . '%',
                ]);
                
                Cart::session($this->cartInstance)->addItemCondition($productId, $condition);
            }
            
            // Atualizar atributos
            $item = Cart::session($this->cartInstance)->get($productId);
            if ($item) {
                Cart::session($this->cartInstance)->update($productId, [
                    'attributes' => [
                        'tax_rate' => $item->attributes['tax_rate'] ?? 0,
                        'discount_percent' => $discountPercent,
                        'unit' => $item->attributes['unit'] ?? 'UN',
                    ]
                ]);
            }
        }
    }

    public function createQuickClient()
    {
        $this->validate([
            'quickClientName' => 'required|string|max:255',
            'quickClientTaxId' => 'nullable|string|max:20',
            'quickClientEmail' => 'nullable|email|max:255',
            'quickClientPhone' => 'nullable|string|max:20',
        ]);

        $client = Client::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->quickClientName,
            'nif' => $this->quickClientTaxId ?: '999999999',
            'type' => 'pessoa_juridica',
            'email' => $this->quickClientEmail,
            'phone' => $this->quickClientPhone,
            'address' => $this->quickClientAddress,
            'is_active' => true,
        ]);

        $this->client_id = $client->id;
        $this->searchClient = '';
        $this->showQuickClientModal = false;
        
        // Salvar na sessão
        $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $this->client_id]);
        
        // Reset form
        $this->quickClientName = '';
        $this->quickClientTaxId = '';
        $this->quickClientEmail = '';
        $this->quickClientPhone = '';
        $this->quickClientAddress = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fornecedor criado com sucesso: ' . $Client->name
        ]);
    }

    public function save($status = 'draft')
    {
        $this->validate();

        $cartItems = Cart::session($this->cartInstance)->getContent();

        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Adicione pelo menos um produto à fatura.'
            ]);
            return;
        }

        // Armazém obrigatório apenas se existem produtos físicos (não serviços)
        if ($this->hasPhysicalProducts() && empty($this->warehouse_id)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Selecione um armazém — existem produtos físicos no documento.'
            ]);
            return;
        }

        // Decreto 71/25: Aviso se fatura emitida >5 dias após facto tributário
        if ($this->delivery_date && $status !== 'draft') {
            $deliveryDate = \Carbon\Carbon::parse($this->delivery_date);
            $invoiceDate = \Carbon\Carbon::parse($this->invoice_date);
            $daysDiff = $deliveryDate->diffInDays($invoiceDate, false);
            
            if ($daysDiff > 5) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "Atenção: A fatura está a ser emitida {$daysDiff} dias após a data de entrega. O Decreto 71/25 exige emissão até 5 dias após o facto tributário."
                ]);
            }
        }

        // Validar descontos antes de salvar
        if ($this->discount_commercial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_commercial, 'commercial');
            if (!$validation['valid']) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => $validation['message']
                ]);
                return;
            }
        }
        
        if ($this->discount_financial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_financial, 'financial');
            if (!$validation['valid']) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => $validation['message']
                ]);
                return;
            }
        }
        
        // Validar descontos por linha nos itens
        foreach ($cartItems as $item) {
            if (isset($item->attributes->discount) && $item->attributes->discount > 0) {
                $validation = DiscountHelper::validateDiscount($item->attributes->discount, 'line');
                if (!$validation['valid']) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => 'Item "' . $item->name . '": ' . $validation['message']
                    ]);
                    return;
                }
            }
        }

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $invoice = SalesInvoice::where('tenant_id', activeTenantId())
                    ->findOrFail($this->invoiceId);
                
                if ($invoice->status === 'converted') {
                    throw new \Exception('Não é possível editar uma fatura já convertida.');
                }
                
                // Decreto 71/25: documento finalizado (com hash) não pode ser editado
                // Apenas rectificação via Nota de Crédito é permitida
                if ($invoice->invoice_status === 'F' && !empty($invoice->saft_hash)) {
                    throw new \Exception('Esta fatura já foi finalizada e não pode ser editada. Utilize uma Nota de Crédito para rectificação (Decreto 71/25).');
                }
                
                if (in_array($invoice->status, ['paid', 'cancelled', 'credited'])) {
                    throw new \Exception('Não é possível editar uma fatura com estado: ' . $invoice->status_label);
                }

                // Delete old items
                $invoice->items()->delete();
            } else {
                $invoice = new SalesInvoice();
                $invoice->tenant_id = activeTenantId();
                $invoice->created_by = auth()->id();
            }

            // Tipo do documento — define a série (FR usa a sequência do POS).
            // Só pode ser definido na CRIAÇÃO: mudar o tipo de um documento já
            // numerado quebraria a sequência da série.
            if (!$this->isEdit) {
                $invoice->invoice_type = $this->isFaturaRecibo() ? 'FR' : 'FT';
                $invoice->series_id = $this->series_id ?: null;
            }

            $invoice->client_id = $this->client_id;
            $invoice->warehouse_id = $this->warehouse_id;
            $invoice->invoice_date = $this->invoice_date;
            $invoice->due_date = $this->due_date;
            $invoice->delivery_date = $this->delivery_date ?: null;
            $invoice->delivery_location = $this->delivery_location ?: null;
            $invoice->status = $status;
            $invoice->is_service = $this->is_service;
            $invoice->discount_amount = $this->discount_amount;
            $invoice->discount_commercial = $this->discount_commercial;
            $invoice->discount_financial = $this->discount_financial;
            $invoice->notes = $this->notes;
            $invoice->terms = $this->terms;
            $invoice->save();

            // Add items e calcular totais conforme AGT Angola
            $order = 0;
            $invoice_subtotal = 0;
            $invoice_tax_amount = 0;
            $invoice_line_discounts = 0;   // descontos de linha (antes eram ignorados no total)
            $extraTaxes = 0;               // IEC + IS de todas as linhas

            foreach ($cartItems as $item) {
                // Calcular valores do item conforme AGT Angola
                $valorBrutoLinha = $item->price * $item->quantity;
                $descontoPercent = $item->attributes['discount_percent'] ?? 0;
                $descontoAmount = $valorBrutoLinha * ($descontoPercent / 100);
                $subtotal = $valorBrutoLinha;
                $valorAposDesconto = $valorBrutoLinha - $descontoAmount;

                // Impostos extra desta linha. O IEC entra na BASE DO IVA: a AGT
                // apura o IVA sobre o valor acrescido do Imposto Especial de
                // Consumo. Calcular o IVA só sobre o líquido dava um
                // taxContribution que a AGT recusava ("não corresponde ao
                // imposto apurado"). O Imposto de Selo NÃO entra nessa base.
                $extrasLinha = $this->lineTaxesFor($item);
                $iecLinha = 0.0;
                foreach ($extrasLinha as $ex) {
                    if ($ex['tax_type'] === 'IEC') {
                        $iecLinha += (float) $ex['tax_amount'];
                    }
                }

                $baseIva   = $valorAposDesconto + $iecLinha;
                $taxAmount = $baseIva * (($item->attributes['tax_rate'] ?? 0) / 100);

                $totalExtras = array_sum(array_column($extrasLinha, 'tax_amount'));
                $total = $valorAposDesconto + $taxAmount + $totalExtras;

                // Acumular totais
                $invoice_subtotal += $valorBrutoLinha;
                $invoice_tax_amount += $taxAmount;
                $invoice_line_discounts += $descontoAmount;
                
                // Campos fiscais AGT da linha: uma linha sem imposto TEM de levar
                // motivo de isenção (a AGT rejeita ISE sem código).
                $lineRate = (float) ($item->attributes['tax_rate'] ?? 0);
                $lineTx = \App\Services\Invoicing\TaxResolver::forProductId($item->id, activeTenantId());

                $invoiceItem = SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->attributes['unit'] ?? 'UN',
                    'unit_price' => $item->price,
                    'discount_percent' => $descontoPercent,
                    'discount_amount' => $descontoAmount,
                    'subtotal' => $subtotal,
                    'tax_rate' => $lineRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'order' => ++$order,
                    // AGT DS.120 — a região vem da província do adquirente
                    // (Cabinda ⇒ AO-CAB, regime próprio). Antes era sempre 'AO'.
                    // Região escolhida no documento tem prioridade; senão deriva
                    // da província do adquirente (Cabinda ⇒ AO-CAB).
                    'tax_country_region'   => in_array($this->tax_country_region, ['AO', 'AO-CAB'], true)
                        ? $this->tax_country_region
                        : \App\Services\Invoicing\TaxResolver::regionForClient(
                            $invoice->client ?? Client::find($this->client_id)
                        ),
                    // Código SAFT do TaxResolver: NOR na taxa normal, RED nas
                    // reduzidas (7%, 5%), ISE quando isento. Fixar 'NOR' fazia
                    // as taxas reduzidas irem declaradas como normais.
                    'tax_code'             => $lineRate > 0
                        ? ($lineTx['tax_code'] ?: 'NOR')
                        : 'ISE',
                    'tax_exemption_code'   => $lineRate > 0 ? null
                        : (\App\Models\Product::normalizeExemptionCode($item->attributes['exemption_reason'] ?? null)
                            ?: $lineTx['exemption_code']),
                    'tax_exemption_reason' => $lineRate > 0 ? null
                        : (\App\Models\Product::exemptionReasonText($item->attributes['exemption_reason'] ?? null)
                            ?: $lineTx['exemption_reason']),
                ]);

                // Impostos extra da linha (IEC/IS) — o IVA fica nas colunas acima.
                // Recria-se sempre: numa edição os antigos deixam de valer.
                \App\Models\Invoicing\LineTax::where('line_type', get_class($invoiceItem))
                    ->where('line_id', $invoiceItem->id)->delete();

                foreach ($extrasLinha as $extra) {
                    \App\Models\Invoicing\LineTax::create(array_merge($extra, [
                        'tenant_id'          => activeTenantId(),
                        'line_type'          => get_class($invoiceItem),
                        'line_id'            => $invoiceItem->id,
                        'tax_country_region' => $invoiceItem->tax_country_region ?: 'AO',
                        // No Imposto de Selo o código é a VERBA; 'NOR' é código
                        // de IVA e a AGT recusava a combinação.
                        'tax_code'           => $extra['tax_type'] === 'IS'
                            ? (string) $extra['verba_no']
                            : 'NOR',
                    ]));
                    $extraTaxes += (float) $extra['tax_amount'];
                }

                // Regravar a linha: o IEC só existe depois das linhas de imposto
                // e o calculateTotals() do model precisa dele para apurar o IVA
                // sobre a base acrescida. Sem esta segunda gravação a linha
                // ficava com o IVA calculado só sobre o líquido.
                if (!empty($extrasLinha)) {
                    $invoiceItem->save();
                    $invoiceItem->refresh();
                    $invoice_tax_amount += ((float) $invoiceItem->tax_amount - $taxAmount);
                }
            }

            // Calcular total da fatura conforme AGT Angola.
            // Os descontos de LINHA têm de entrar no total do documento — antes só
            // reduziam o valor do item e o cliente acabava a pagar a mais.
            $desconto_comercial_total = $invoice_line_discounts + $invoice->discount_commercial + $invoice->discount_amount;
            $valor_apos_desc_comercial = $invoice_subtotal - $desconto_comercial_total;
            $incidencia_iva = $valor_apos_desc_comercial - $invoice->discount_financial;
            // Retenção: a explícita do ecrã tem prioridade; o IRT automático de
            // 6,5% sobre serviços mantém-se como antes quando nada é indicado.
            $retencaoExplicita = (float) $this->withholding_amount;
            $irt_amount = $retencaoExplicita > 0
                ? $retencaoExplicita
                : ($invoice->is_service ? $incidencia_iva * 0.065 : 0);

            // IEC e IS acrescem ao IVA no imposto total do documento.
            $imposto_total = $invoice_tax_amount + $extraTaxes;
            $total_final = $incidencia_iva + $imposto_total - $irt_amount;

            // Atualizar totais da fatura
            $invoice->subtotal = $invoice_subtotal;
            $invoice->tax_amount = $invoice_tax_amount;   // só IVA (compatibilidade)
            $invoice->irt_amount = $irt_amount;
            $invoice->total = $total_final;

            // Campos SAFT-AO obrigatórios (Decreto Presidencial 71/25)
            $invoice->net_total = $invoice_subtotal - $desconto_comercial_total - ($invoice->discount_financial ?? 0);
            // taxPayable inclui TODOS os impostos liquidados, senão
            // netTotal + taxPayable ≠ grossTotal e a AGT recusa o documento.
            $invoice->tax_payable = $imposto_total;
            $invoice->gross_total = $invoice->net_total + $imposto_total;
            $invoice->system_entry_date = $invoice->system_entry_date ?? now();
            // invoice_status é ENUM('N','A','F') (Normal/Anulado/Finalizado) — 'R'
            // rebentava o INSERT (SQL 1265). O estado de negócio do rascunho vive
            // em $invoice->status = 'draft'.
            $invoice->invoice_status = ($status === 'draft') ? 'N' : 'F';
            $invoice->invoice_status_date = now();
            $invoice->source_id = auth()->id() ?? 'SYSTEM';
            $invoice->source_billing = 'P';

            // ── Fatura-Recibo: paga no acto de emissão ──
            // O documento vale como fatura E recibo, por isso fica liquidado e o
            // valor entra logo na tesouraria (mesma lógica do POS).
            $isFR = $invoice->invoice_type === 'FR';
            if ($isFR && $status !== 'draft') {
                $invoice->status         = 'paid';
                $invoice->paid_amount    = $invoice->total;
                $invoice->payment_method = $this->payment_method ?: 'cash';
            }

            $invoice->save();

            // ── Retenção na fonte / cativação ──
            // Vai em withholdingTaxList no payload AGT. Recria-se sempre, para
            // uma edição não deixar a retenção antiga pendurada.
            \Illuminate\Support\Facades\DB::table('invoicing_withholding_taxes')
                ->where('document_type', get_class($invoice))
                ->where('document_id', $invoice->id)
                ->delete();

            // Só tipos da lista da AGT: um valor fora dela faz o payload inteiro
            // ser recusado na validação, com os documentos já emitidos.
            $tipoRetencao = array_key_exists($this->withholding_type, self::DESCRICOES_RETENCAO)
                ? $this->withholding_type
                : null;

            if ($tipoRetencao && (float) $this->withholding_amount > 0) {
                \Illuminate\Support\Facades\DB::table('invoicing_withholding_taxes')->insert([
                    'tenant_id'                   => activeTenantId(),
                    'document_type'               => get_class($invoice),
                    'document_id'                 => $invoice->id,
                    'withholding_tax_type'        => $tipoRetencao,
                    'withholding_tax_description' => self::DESCRICOES_RETENCAO[$tipoRetencao],
                    'withholding_tax_percentage'  => (float) $this->withholding_percentage,
                    'withholding_tax_amount'      => (float) $this->withholding_amount,
                    'created_at'                  => now(),
                    'updated_at'                  => now(),
                ]);
            }

            // Gerar HASH SAFT-AO conforme regulamento Angola
            // SAFT-AO exige GrossTotal (total bruto com impostos) para o hash
            $previousInvoice = SalesInvoice::where('tenant_id', activeTenantId())
                ->where('id', '<', $invoice->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();
            
            $hash = \App\Helpers\SAFTHelper::generateHash(
                $invoice->invoice_date->format('Y-m-d'),
                $invoice->system_entry_date->format('Y-m-d H:i:s'),
                $invoice->invoice_number,
                $invoice->gross_total,
                $previousInvoice->saft_hash ?? null
            );
            
            if ($hash) {
                $invoice->saft_hash = $hash;
                $invoice->hash = $hash;
                $invoice->hash_previous = $previousInvoice->saft_hash ?? '';
                $invoice->hash_control = '1';
                $invoice->save();
            }
            
            // Baixa de stock.
            //
            // TEM de ser aqui e não no observer: o `created` do SalesInvoice
            // corre no $invoice->save() lá em cima, quando ainda não existe
            // nenhuma linha — a colecção $invoice->items vem vazia e não se
            // descontava nada. Medido: 6 unidades antes da factura, 6 depois,
            // zero movimentos registados.
            //
            // O reduceStock() é idempotente (verifica se já existe movimento de
            // saída a referenciar esta factura), pelo que chamá-lo aqui não
            // duplica com o observer nem com o desconto explícito do POS.
            if ($status !== 'draft') {
                $invoice->load('items');
                app(\App\Observers\SalesInvoiceObserver::class)->reduceStock($invoice);
            }

            // Fatura-Recibo: registar a entrada na tesouraria (o documento já é
            // o recibo). Best-effort: uma falha aqui não pode anular o documento
            // fiscal já numerado e assinado.
            if ($isFR && $status !== 'draft') {
                try {
                    $this->createTreasuryTransaction($invoice);
                } catch (\Throwable $e) {
                    \Log::error('InvoiceCreate: falha ao criar transação de tesouraria da Fatura-Recibo', [
                        'invoice' => $invoice->invoice_number,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // Clear cart
            Cart::session($this->cartInstance)->clear();
            
            // Clear session
            $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
            session()->forget($sessionKey);

            // Auto-submeter à AGT se habilitado e novo (não edição)
            $agtMessage = '';
            if (!$this->isEdit) {
                $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
                if (!empty($settings->agt_auto_submit)) {
                    try {
                        $agtResult = $invoice->fresh()->submitToAGT();
                        $agtMessage = ($agtResult['success'] ?? false)
                            ? ' · AGT requestID: ' . ($agtResult['requestID'] ?? '—')
                            : ' · AGT erro: ' . ($agtResult['error'] ?? 'desconhecido');
                    } catch (\Throwable $e) {
                        $agtMessage = ' · AGT excepção: ' . $e->getMessage();
                    }
                }
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fatura ' . ($this->isEdit ? 'atualizada' : 'criada') . ' com sucesso!' . $agtMessage,
            ]);
            
            // Verificar se deve imprimir automaticamente
            if (DocumentConfigHelper::shouldAutoPrint()) {
                // Abrir PDF automaticamente em nova aba
                $this->dispatch('auto-print-pdf', [
                    'url' => route('invoicing.sales.invoice.pdf', $invoice->id)
                ]);
            }
            
            // Disparar evento para abrir preview em nova aba
            $this->dispatch('openInvoicePreview', ['invoiceId' => $invoice->id]);
            
            return redirect()->route('invoicing.sales.invoices');

        } catch (\Exception $e) {
            DB::rollback();

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao salvar fatura: ' . $e->getMessage()
            ]);
        }
    }

    /* ─────────────── Fatura-Recibo (FR) ─────────────── */

    /** Documento é Fatura-Recibo? (só aplicável a documentos novos) */
    public function isFaturaRecibo(): bool
    {
        return !$this->isEdit && strtoupper((string) $this->invoice_type) === 'FR';
    }

    /** Rótulo do documento para a UI. */
    public function getDocumentLabelProperty(): string
    {
        return $this->isFaturaRecibo() ? 'Fatura-Recibo' : 'Fatura';
    }

    /** Métodos de pagamento da tesouraria (para o seletor da FR). */
    public function getPaymentMethodsProperty()
    {
        return \App\Models\Treasury\PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Entrada na tesouraria da Fatura-Recibo — mesma lógica do POS: resolve o
     * método pelo código (case-insensitive), deriva a categoria e associa a
     * caixa aberta quando é dinheiro.
     */
    protected function createTreasuryTransaction(SalesInvoice $invoice): void
    {
        $code = strtolower((string) ($this->payment_method ?: 'cash'));

        $method = \App\Models\Treasury\PaymentMethod::where('tenant_id', activeTenantId())
            ->whereRaw('LOWER(code) = ?', [$code])
            ->first();

        $typeToCategory = [
            'cash' => 'cash', 'card' => 'card', 'bank_transfer' => 'bank_transfer',
            'digital_wallet' => 'digital_payment', 'check' => 'bank_transfer',
        ];
        $codeToCategory = [
            'cash' => 'cash', 'transfer' => 'bank_transfer', 'multicaixa' => 'card',
            'mcx' => 'card', 'tpa' => 'card', 'card' => 'card',
            'mbway' => 'digital_payment', 'mobile' => 'digital_payment',
        ];

        $category = $method
            ? ($typeToCategory[$method->type] ?? 'cash')
            : ($codeToCategory[$code] ?? 'cash');

        $isCash = $method ? ($method->type === 'cash') : ($code === 'cash');

        $cashRegisterId = null;
        if ($isCash) {
            $cashRegisterId = \App\Models\Treasury\CashRegister::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('status', 'open')
                ->value('id');
        }

        \App\Models\Treasury\Transaction::create([
            'tenant_id'          => activeTenantId(),
            'user_id'            => auth()->id(),
            'cash_register_id'   => $cashRegisterId,
            'payment_method_id'  => $method?->id,
            'invoice_id'         => $invoice->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type'               => 'income',
            'category'           => $category,
            'amount'             => $invoice->total,
            'currency'           => 'AOA',
            'transaction_date'   => now(),
            'reference'          => $invoice->invoice_number,
            'description'        => 'Fatura-Recibo: ' . $invoice->invoice_number
                . ($invoice->client ? ' - Cliente: ' . $invoice->client->name : ''),
            'notes'              => $this->notes,
            'status'             => 'completed',
            'is_reconciled'      => false,
        ]);
    }
}

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
    // Editar por URL o documento de um colega é vê-lo por inteiro.
    use \App\Traits\EscopoDeAutor;

    // Duplicar documentos: ver o contrato no trait.
    use \App\Livewire\Concerns\DuplicaDocumento;

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
        
        // Get client name (+ condição de pagamento)
        $client = Client::with('paymentTerm')->find($clientId);

        // Vencimento a partir da condição de pagamento do cliente (pronto
        // pagamento = mesma data; 30 dias = +30). É só um valor por omissão —
        // o utilizador pode alterá-lo.
        $dias = $client?->paymentTerm?->days;
        if ($dias !== null) {
            $base = $this->invoice_date ? \Illuminate\Support\Carbon::parse($this->invoice_date) : now();
            $this->due_date = $base->copy()->addDays((int) $dias)->format('Y-m-d');
        }

        // Force clear input visually
        $this->dispatch('client-selected');

        // Toast notification
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Cliente selecionado: :nome', ['nome' => $client ? $client->name : ''])
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
        'notes' => 'nullable|string|max:65535',
        'terms' => 'nullable|string|max:65535',
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
            'payment_method.required' => __('A Fatura-Recibo é paga no acto — selecione a forma de pagamento.'),
            'invoice_type.in'         => __('Tipo de documento inválido.'),
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
        // Os campos de dinheiro chegam mascarados (10.000,23) do input; ler
        // sempre para float antes de validar/calcular.
        if (in_array($propertyName, ['discount_commercial', 'discount_amount', 'discount_financial'], true)) {
            $this->{$propertyName} = \App\Helpers\MoneyHelper::parse($this->{$propertyName});
        }

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
        $this->cartInstance = 'sales_invoice_' . activeTenantId() . '_' . auth()->id();
        
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
        } elseif ($duplicar = $this->idParaDuplicar()) {
            // DUPLICAR: o mesmo carregamento da edição, sem ser uma edição.
            // O que fica de fora — número, série, hash, ATCUD, datas, estado —
            // está explicado no trait DuplicaDocumento.
            $origem = SalesInvoice::where('tenant_id', activeTenantId())->tap(fn ($q) => $this->escoparAoAutor($q))->findOrFail($duplicar);

            $this->loadInvoice($duplicar);
            $this->marcarComoDuplicado($origem->invoice_number);

            // As datas são de HOJE. Herdar as do original punha o duplicado
            // num período fiscal que já fechou.
            $this->invoice_date = now()->format('Y-m-d');
            $this->due_date = DocumentConfigHelper::getInvoiceDueDate()->format('Y-m-d');
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
            ->tap(fn ($q) => $this->escoparAoAutor($q))
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
                    'description' => $item->description,
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
                    'message' => '❌ ' . __('Produto exige lote na venda mas não há lotes disponíveis: :produto', ['produto' => $product->name])
                ]);
                return;
            }
            
            // Verificar se há lotes expirados
            $expiredBatches = $availableBatches->filter(fn($b) => $b->is_expired);
            if ($expiredBatches->isNotEmpty()) {
                $expiredNumbers = $expiredBatches->pluck('batch_number')->filter()->join(', ');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '⚠️ ' . __('Lotes expirados encontrados: :lotes', ['lotes' => $expiredNumbers ?: __('Sem número')])
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
                    'message' => '⚠️ ' . trans_choice(
                        'Atenção: lotes a expirar dentro de :n dia: :lotes|Atenção: lotes a expirar dentro de :n dias: :lotes',
                        $days,
                        ['n' => $days, 'lotes' => $expiringNumbers ?: __('Sem número')]
                    )
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
                        'message' => '❌ ' . __('Quantidade insuficiente em lotes. Disponível: :quantidade', ['quantidade' => $totalAvailable])
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
                'message' => __('Quantidade incrementada: :produto', ['produto' => $product->name])
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
            
            // Frase inteira por tipo: em francês e inglês o particípio não se
            // cola ao substantivo como em português.
            $message = $product->type === 'servico'
                ? __('Serviço adicionado: :nome (IVA: :taxa%)', ['nome' => $product->name, 'taxa' => $taxRate])
                : __('Produto adicionado: :nome (IVA: :taxa%)', ['nome' => $product->name, 'taxa' => $taxRate]);
            
            // Se rastreia lotes, adicionar info
            if ($product->track_batches) {
                $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->get();
                $totalAvailable = $availableBatches->sum('quantity_available');
                $message .= ' | ' . __('Lotes: :n (Total disponível: :total)', [
                    'n'     => $availableBatches->count(),
                    'total' => $totalAvailable,
                ]);
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
            'message' => __('Carrinho limpo com sucesso!')
        ]);
    }

    public function removeProduct($productId)
    {
        $item = Cart::session($this->cartInstance)->get($productId);
        $productName = $item ? $item->name : __('Produto');

        Cart::session($this->cartInstance)->remove($productId);

        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => __('Produto removido: :nome', ['nome' => $productName])
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
                        'message' => '❌ ' . __('Quantidade insuficiente em lotes. Disponível: :quantidade', ['quantidade' => $totalAvailable])
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
                'message' => __('Quantidade atualizada para: :quantidade', ['quantidade' => $quantity])
            ]);
        }
    }

    public function updatePrice($productId, $price)
    {
        // O input pode vir mascarado (10.000,23) ou simples — o preço tem de
        // ser lido sempre certo, é dinheiro num documento.
        $price = \App\Helpers\MoneyHelper::parse($price);
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

    /**
     * Descrição detalhada da linha (abaixo do nome do produto).
     *
     * O mesmo campo que a proforma e o orçamento usam: um serviço não cabe num
     * nome de artigo. Já existia na tabela (…_items.description, TEXT); faltava
     * a via para o preencher.
     */
    public function updateDescription($productId, $description)
    {
        $item = Cart::session($this->cartInstance)->get($productId);
        if (!$item) {
            return;
        }

        $attrs = \App\Helpers\InvoiceCalculationHelper::atributos($item);
        Cart::session($this->cartInstance)->update($productId, [
            'attributes' => array_merge($attrs, [
                'description' => mb_substr((string) $description, 0, 5000),
            ]),
        ]);
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
            'message' => __('Fornecedor criado com sucesso: :nome', ['nome' => $Client->name])
        ]);
    }

    public function save($status = 'draft')
    {
        // Blindagem: os valores de dinheiro podem chegar mascarados.
        $this->discount_commercial = \App\Helpers\MoneyHelper::parse($this->discount_commercial);
        $this->discount_amount     = \App\Helpers\MoneyHelper::parse($this->discount_amount);
        $this->discount_financial  = \App\Helpers\MoneyHelper::parse($this->discount_financial);

        $this->validate();

        $cartItems = Cart::session($this->cartInstance)->getContent();

        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Adicione pelo menos um produto à fatura.')
            ]);
            return;
        }

        // Armazém obrigatório apenas se existem produtos físicos (não serviços)
        if ($this->hasPhysicalProducts() && empty($this->warehouse_id)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Selecione um armazém — existem produtos físicos no documento.')
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
                    // Só entra aqui com $daysDiff > 5, logo nunca há forma
                    // singular a produzir — basta o marcador.
                    'message' => __('Atenção: a fatura está a ser emitida :n dias após a data de entrega. O Decreto 71/25 exige emissão até 5 dias após o facto tributário.', ['n' => $daysDiff])
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
                        'message' => __('Item ":nome": :erro', ['nome' => $item->name, 'erro' => $validation['message']])
                    ]);
                    return;
                }
            }
        }

        /*
         * TUDO O QUE É FISCAL VIVE NO `EmissorDeFacturas`: descontos globais a
         * descer às linhas, IEC/IS, campos SAFT, FR paga no acto, retenção,
         * hash, baixa de stock, tesouraria e fila da AGT. Este ecrã e o ecrã
         * em React chamam o mesmo — foi por isso que saiu daqui.
         */
        $existente = null;

        if ($this->isEdit) {
            $existente = SalesInvoice::where('tenant_id', activeTenantId())
                ->tap(fn ($q) => $this->escoparAoAutor($q))
                ->findOrFail($this->invoiceId);
        }

        try {
            $emitido = app(\App\Services\Invoicing\EmissorDeFacturas::class)->emitir([
                'client_id' => $this->client_id,
                'warehouse_id' => $this->warehouse_id,
                'invoice_date' => $this->invoice_date,
                'due_date' => $this->due_date,
                'delivery_date' => $this->delivery_date,
                'delivery_location' => $this->delivery_location,
                'invoice_type' => $this->isFaturaRecibo() ? 'FR' : 'FT',
                'series_id' => $this->series_id,
                'is_service' => $this->is_service,
                'discount_amount' => $this->discount_amount,
                'discount_commercial' => $this->discount_commercial,
                'discount_financial' => $this->discount_financial,
                'notes' => $this->notes,
                'terms' => $this->terms,
                'tax_country_region' => $this->tax_country_region,
                'withholding_type' => $this->withholding_type,
                'withholding_percentage' => $this->withholding_percentage,
                'withholding_amount' => $this->withholding_amount,
                'payment_method' => $this->payment_method,
                'status' => $status,
            ], collect($cartItems->values()->all()), $this->lineIec, $this->lineIs, $existente);
        } catch (\DomainException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao salvar fatura: :erro', ['erro' => $e->getMessage()])
            ]);

            return;
        }

        $invoice = $emitido['factura'];

        Cart::session($this->cartInstance)->clear();
        session()->forget('invoice_client_' . activeTenantId() . '_' . auth()->id());

        $agtMessage = '';

        if ($emitido['fila']['enfileirado']) {
            $agtMessage = ' · ' . __('a comunicar à AGT');
        } elseif (! empty($emitido['fila']['jaEnviado'])) {
            $agtMessage = ' · ' . __('já comunicada à AGT');
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => ($this->isEdit
                ? __('Fatura atualizada com sucesso!')
                : __('Fatura criada com sucesso!')) . $agtMessage,
        ]);

        if (DocumentConfigHelper::shouldAutoPrint()) {
            $this->dispatch('auto-print-pdf', [
                'url' => route('invoicing.sales.invoices.pdf', $invoice->id)
            ]);
        }

        $this->dispatch('openInvoicePreview', ['invoiceId' => $invoice->id]);

        return redirect()->route('invoicing.sales.invoices');
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
        return $this->isFaturaRecibo() ? __('Fatura-Recibo') : __('Fatura');
    }

    /** Métodos de pagamento da tesouraria (para o seletor da FR). */
    public function getPaymentMethodsProperty()
    {
        return \App\Models\Treasury\PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

}

<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\SalesQuoteItem;
use App\Models\Invoicing\Warehouse;
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

/**
 * Criar/editar um Orçamento de venda.
 *
 * É a mesma interface da proforma (por decisão de produto), mas o orçamento
 * NÃO é documento fiscal: aqui não se gera série, nem hash SAFT-AO, nem se
 * comunica nada à AGT. Cada linha ganha uma descrição longa do serviço.
 */
#[Layout('layouts.app')]
#[Title('Novo Orçamento')]
class QuoteCreate extends Component
{
    // Editar por URL o documento de um colega é vê-lo por inteiro.
    use \App\Traits\EscopoDeAutor;

    public $quoteId = null;
    public $isEdit = false;

    // Form fields
    public $client_id = '';

    /**
     * Região fiscal do documento (AO ou AO-CAB).
     * Vazio = deriva da província do cliente.
     */
    public $tax_country_region = '';
    public $warehouse_id = '';
    public $quote_date;
    public $valid_until;
    public $notes = '';
    public $terms = '';

    // Modelo de proposta e os campos livres que ele pede.
    public $quote_template_id = null;
    public array $campos_proposta = [];
    public $discount_amount = 0;
    public $discount_commercial = 0; // Desconto Comercial (antes IVA)
    public $discount_financial = 0;  // Desconto Financeiro (após IVA)
    public $is_service = false;      // Se é prestação de serviço (sujeito a IRT)

    // Product selection
    public $showProductModal = false;
    public $searchProduct = '';
    public $selectedCategory = '';

    // Client search
    public $searchClient = '';

    // Quick client creation
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
        'quote_date' => 'required|date',
        'valid_until' => 'nullable|date|after:quote_date',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_commercial' => 'nullable|numeric|min:0',
        'discount_financial' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:65535',
        'terms' => 'nullable|string|max:65535',
    ];

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->reset('searchClient');

        $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $clientId]);

        $client = Client::find($clientId);

        $this->dispatch('client-selected');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Cliente selecionado: :nome', ['nome' => $client ? $client->name : ''])
        ]);
    }

    public function clearClient()
    {
        $this->client_id = '';
        $this->searchClient = '';

        $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
        session()->forget($sessionKey);

        $defaultClient = Client::where('tenant_id', activeTenantId())
            ->where('nif', '999999999')
            ->first();

        if ($defaultClient) {
            $this->client_id = $defaultClient->id;
            session([$sessionKey => $defaultClient->id]);
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

        if ($propertyName === 'client_id' && $this->client_id) {
            $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
            session([$sessionKey => $this->client_id]);
        }

        if ($propertyName === 'discount_commercial' && $this->discount_commercial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_commercial, 'commercial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->discount_commercial = 0;
            }
        }

        if ($propertyName === 'discount_financial' && $this->discount_financial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_financial, 'financial');
            if (!$validation['valid']) {
                $this->dispatch('error', message: $validation['message']);
                $this->discount_financial = 0;
            }
        }
    }

    public function mount($id = null)
    {
        $this->quote_date = now()->format('Y-m-d');
        // Mesma janela de validade da proforma (config da empresa).
        $this->valid_until = DocumentConfigHelper::getProformaValidUntil()->format('Y-m-d');

        $this->cartInstance = 'sales_quote_' . activeTenantId() . '_' . auth()->id();

        // Orcamento novo nasce com o modelo padrao da empresa (se houver):
        // ninguem se lembra de o escolher, e sem ele saia o desenho antigo.
        $this->quote_template_id = \App\Models\Invoicing\QuoteTemplate::where('tenant_id', activeTenantId())
            ->where('is_active', true)->where('is_default', true)->value('id');

        $defaultWarehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->first();

        if ($defaultWarehouse) {
            $this->warehouse_id = $defaultWarehouse->id;
        }

        if ($id) {
            $this->isEdit = true;
            $this->quoteId = $id;
            $this->loadQuote($id);
        } else {
            $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
            $savedClientId = session($sessionKey);

            if ($savedClientId && Client::where('id', $savedClientId)->where('tenant_id', activeTenantId())->exists()) {
                $this->client_id = $savedClientId;
            } else {
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

    public function loadQuote($id)
    {
        $quote = SalesQuote::where('tenant_id', activeTenantId())
            ->with('items.product')
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->findOrFail($id);

        $this->client_id = $quote->client_id;
        $this->warehouse_id = $quote->warehouse_id;
        $this->quote_date = $quote->quote_date->format('Y-m-d');
        $this->valid_until = $quote->valid_until?->format('Y-m-d');
        $this->notes = $quote->notes;
        $this->terms = $quote->terms;
        $this->quote_template_id = $quote->quote_template_id;
        $this->campos_proposta = (array) ($quote->campos_proposta ?? []);
        $this->discount_amount = $quote->discount_amount;
        $this->discount_commercial = $quote->discount_commercial ?? 0;
        $this->discount_financial = $quote->discount_financial ?? 0;
        $this->is_service = $quote->is_service ?? false;

        Cart::session($this->cartInstance)->clear();
        foreach ($quote->items as $item) {
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

        $cartItems = Cart::session($this->cartInstance)->getContent();

        $totals = InvoiceCalculationHelper::calculateTotals(
            $cartItems,
            $this->discount_commercial,
            $this->discount_amount,
            $this->discount_financial,
            $this->is_service
        );

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
                $totalStock = \App\Models\Invoicing\Stock::where('tenant_id', activeTenantId())
                    ->where('product_id', $product->id)
                    ->sum('quantity');

                $product->stock_quantity = $totalStock;
                return $product;
            });
        }

        $cliente = $this->client_id ? Client::find($this->client_id) : null;
        $regiaoFiscal = in_array($this->tax_country_region, ['AO', 'AO-CAB'], true)
            ? $this->tax_country_region
            : \App\Services\Invoicing\TaxResolver::regionForClient($cliente);

        return view('livewire.invoicing.orcamentos-venda.create', array_merge([
            'clients' => $clients,
            'warehouses' => $warehouses,
            'cartItems' => $cartItems,
            'products' => $products,
            'regiaoFiscal' => $regiaoFiscal,
            'clienteProvincia' => $cliente->province ?? null,
            'modelosDeProposta' => \App\Models\Invoicing\QuoteTemplate::where('tenant_id', activeTenantId())
                ->where('is_active', true)->orderByDesc('is_default')->orderBy('nome')->get(),
            'camposLivres' => $this->camposLivresDoModelo(),
        ], $totals));
    }

    /** Os campos que o modelo escolhido pede a quem faz este orcamento. */
    private function camposLivresDoModelo(): array
    {
        if (!$this->quote_template_id) {
            return [];
        }

        $modelo = \App\Models\Invoicing\QuoteTemplate::where('tenant_id', activeTenantId())
            ->find($this->quote_template_id);

        return $modelo ? $modelo->camposLivres() : [];
    }

    /**
     * So os campos que o modelo ESCOLHIDO pede. Trocar de modelo a meio
     * deixava aqui textos orfaos de um modelo antigo, que voltariam a
     * aparecer se alguem voltasse a escolhe-lo.
     */
    private function camposDoModeloEscolhido(): ?array
    {
        $pedidos = $this->camposLivresDoModelo();
        if (!$pedidos) {
            return null;
        }

        $guardar = [];
        foreach (array_keys($pedidos) as $chave) {
            $valor = trim((string) ($this->campos_proposta[$chave] ?? ''));
            if ($valor !== '') {
                $guardar[$chave] = $valor;
            }
        }

        return $guardar ?: null;
    }

    public function addProduct($productId)
    {
        $product = Product::with('taxRate')->where('tenant_id', activeTenantId())->findOrFail($productId);

        $existingItem = Cart::session($this->cartInstance)->get($productId);

        if ($existingItem) {
            Cart::session($this->cartInstance)->update($productId, [
                'quantity' => 1
            ]);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => __('Quantidade incrementada: :produto', ['produto' => $product->name])
            ]);
        } else {
            $tx = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());

            Cart::session($this->cartInstance)->add([
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 1,
                'attributes' => [
                    'tax_rate' => $tx['rate'],
                    'discount_percent' => 0,
                    'unit' => $product->unit ?? 'UN',
                    'type' => $product->type ?? 'produto',
                    'tax_type' => $tx['type'],
                    'exemption_reason' => $tx['exemption_code'],
                ]
            ]);

            $message = $product->type === 'servico'
                ? __('Serviço adicionado: :produto (IVA: :taxa%)', ['produto' => $product->name, 'taxa' => $tx['rate']])
                : __('Produto adicionado: :produto (IVA: :taxa%)', ['produto' => $product->name, 'taxa' => $tx['rate']]);

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
            'message' => __('Produto removido: :produto', ['produto' => $productName])
        ]);
    }

    public function updateQuantity($productId, $quantity)
    {
        if ($quantity > 0) {
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
            Cart::session($this->cartInstance)->clearItemConditions($productId);

            if ($discountPercent > 0) {
                $condition = new \Darryldecode\Cart\CartCondition([
                    'name' => 'DESCONTO',
                    'type' => 'discount',
                    'target' => 'item',
                    'value' => '-' . $discountPercent . '%',
                ]);

                Cart::session($this->cartInstance)->addItemCondition($productId, $condition);
            }

            $item = Cart::session($this->cartInstance)->get($productId);
            if ($item) {
                // MERGE (não substituir): substituir os attributes deitava fora
                // tax_type e exemption_reason, e a linha perdia o motivo de isenção.
                $attrs = \App\Helpers\InvoiceCalculationHelper::atributos($item);
                Cart::session($this->cartInstance)->update($productId, [
                    'attributes' => array_merge($attrs, [
                        'tax_rate' => $attrs['tax_rate'] ?? 0,
                        'discount_percent' => $discountPercent,
                        'unit' => $attrs['unit'] ?? 'UN',
                    ])
                ]);
            }
        }
    }

    /**
     * Descrição detalhada da linha (abaixo do nome do produto).
     *
     * É a razão de ser do orçamento: um serviço não cabe num nome de artigo.
     * MERGE dos attributes, como o updateDiscount — um update directo deitava
     * fora o tax_rate e o motivo de isenção da linha.
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
            'type' => 'pessoa_fisica',
            'email' => $this->quickClientEmail,
            'phone' => $this->quickClientPhone,
            'address' => $this->quickClientAddress,
            'is_active' => true,
        ]);

        $this->client_id = $client->id;
        $this->searchClient = '';
        $this->showQuickClientModal = false;

        $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $client->id]);

        $this->quickClientName = '';
        $this->quickClientTaxId = '';
        $this->quickClientEmail = '';
        $this->quickClientPhone = '';
        $this->quickClientAddress = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Cliente criado com sucesso: :nome', ['nome' => $client->name])
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
                'message' => __('Adicione pelo menos um produto ao orçamento.')
            ]);
            return;
        }

        if ($this->hasPhysicalProducts() && empty($this->warehouse_id)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Selecione um armazém — existem produtos físicos no documento.')
            ]);
            return;
        }

        if ($this->discount_commercial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_commercial, 'commercial');
            if (!$validation['valid']) {
                $this->dispatch('notify', ['type' => 'error', 'message' => $validation['message']]);
                return;
            }
        }

        if ($this->discount_financial > 0) {
            $validation = DiscountHelper::validateDiscount($this->discount_financial, 'financial');
            if (!$validation['valid']) {
                $this->dispatch('notify', ['type' => 'error', 'message' => $validation['message']]);
                return;
            }
        }

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $quote = SalesQuote::where('tenant_id', activeTenantId())
                    ->tap(fn ($q) => $this->escoparAoAutor($q))
                    ->findOrFail($this->quoteId);

                if ($quote->status === 'converted') {
                    throw new \Exception(__('Não é possível editar um orçamento já convertido.'));
                }

                $quote->items()->delete();
            } else {
                $quote = new SalesQuote();
                $quote->tenant_id = activeTenantId();
                $quote->created_by = auth()->id();
            }

            $quote->client_id = $this->client_id;
            $quote->warehouse_id = $this->warehouse_id;
            $quote->quote_date = $this->quote_date;
            $quote->valid_until = $this->valid_until;
            $quote->status = $status;
            $quote->is_service = $this->is_service;
            $quote->discount_amount = $this->discount_amount;
            $quote->discount_commercial = $this->discount_commercial;
            $quote->discount_financial = $this->discount_financial;
            $quote->notes = $this->notes;
            $quote->terms = $this->terms;
            $quote->quote_template_id = $this->quote_template_id ?: null;
            // Guarda so os campos que o modelo escolhido pede: trocar de
            // modelo deixava aqui textos orfaos de um modelo antigo.
            $quote->campos_proposta = $this->camposDoModeloEscolhido();
            $quote->save();

            $order = 0;
            $quote_subtotal = 0;
            $quote_tax_amount = 0;

            foreach ($cartItems as $item) {
                $valorBrutoLinha = $item->price * $item->quantity;
                $descontoPercent = $item->attributes['discount_percent'] ?? 0;
                $descontoAmount = $valorBrutoLinha * ($descontoPercent / 100);
                $subtotal = $valorBrutoLinha;
                $valorAposDesconto = $valorBrutoLinha - $descontoAmount;
                $taxAmount = $valorAposDesconto * (($item->attributes["tax_rate"] ?? 0) / 100);
                $total = $valorAposDesconto + $taxAmount;

                $quote_subtotal += $valorBrutoLinha;
                $quote_tax_amount += $taxAmount;

                SalesQuoteItem::create([
                    'sales_quote_id' => $quote->id,
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'description' => $item->attributes['description'] ?? null,
                    'quantity' => $item->quantity,
                    'unit' => $item->attributes['unit'] ?? 'UN',
                    'unit_price' => $item->price,
                    'discount_percent' => $descontoPercent,
                    'discount_amount' => $descontoAmount,
                    'subtotal' => $subtotal,
                    'tax_rate' => $item->attributes['tax_rate'] ?? 0,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'order' => ++$order,
                    // Campos AGT persistidos já no orçamento, para a conversão em
                    // factura não perder o motivo de isenção nem a região fiscal.
                    'tax_country_region'   => in_array($this->tax_country_region, ['AO', 'AO-CAB'], true)
                        ? $this->tax_country_region
                        : \App\Services\Invoicing\TaxResolver::regionForClient(
                            Client::find($this->client_id)
                        ),
                    'tax_code'             => ($item->attributes['tax_rate'] ?? 0) > 0 ? 'NOR' : 'ISE',
                    'tax_exemption_code'   => ($item->attributes['tax_rate'] ?? 0) > 0 ? null
                        : \App\Models\Product::normalizeExemptionCode($item->attributes['exemption_reason'] ?? null),
                    'tax_exemption_reason' => ($item->attributes['tax_rate'] ?? 0) > 0 ? null
                        : \App\Models\Product::exemptionReasonText($item->attributes['exemption_reason'] ?? null),
                ]);
            }

            $desconto_comercial_total = $quote->discount_commercial + $quote->discount_amount;
            $valor_apos_desc_comercial = $quote_subtotal - $desconto_comercial_total;
            $incidencia_iva = $valor_apos_desc_comercial - $quote->discount_financial;
            $irt_amount = $quote->is_service ? $incidencia_iva * 0.065 : 0;
            $total_final = $incidencia_iva + $quote_tax_amount - $irt_amount;

            $quote->subtotal = $quote_subtotal;
            $quote->tax_amount = $quote_tax_amount;
            $quote->irt_amount = $irt_amount;
            $quote->total = $total_final;
            $quote->save();

            // Sem hash SAFT-AO nem série: o orçamento não é documento fiscal.

            DB::commit();

            Cart::session($this->cartInstance)->clear();

            $sessionKey = 'quote_client_' . activeTenantId() . '_' . auth()->id();
            session()->forget($sessionKey);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $this->isEdit
                    ? __('Orçamento atualizado com sucesso!')
                    : __('Orçamento criado com sucesso!')
            ]);

            if (DocumentConfigHelper::shouldAutoPrint()) {
                $this->dispatch('auto-print-pdf', [
                    'url' => route('invoicing.sales.quotes.pdf', $quote->id)
                ]);
            }

            $this->dispatch('openQuotePreview', ['quoteId' => $quote->id]);

            return redirect()->route('invoicing.sales.quotes');

        } catch (\Exception $e) {
            DB::rollback();

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao salvar orçamento: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }
}

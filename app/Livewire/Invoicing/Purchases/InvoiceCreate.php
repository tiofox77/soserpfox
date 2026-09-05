<?php

namespace App\Livewire\Invoicing\Purchases;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Models\Product;
use App\Helpers\InvoiceCalculationHelper;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Nova Fatura de Compra')]
class InvoiceCreate extends Component
{
    // Editar por URL o documento de um colega é vê-lo por inteiro.
    use \App\Traits\EscopoDeAutor;

    // Duplicar documentos: ver o contrato no trait.
    use \App\Livewire\Concerns\DuplicaDocumento;


    public $invoiceId = null;
    public $isEdit = false;

    // Form fields
    public $supplier_id = '';

    /**
     * Região fiscal do documento (AO ou AO-CAB).
     *
     * Cabinda tem regime de IVA próprio e o que o determina é o local da
     * OPERAÇÃO. Uma compra feita lá era registada como continental sem
     * alternativa: o selector não existia, e as linhas nem tinham coluna
     * onde guardar a região.
     */
    public $tax_country_region = '';
    public $warehouse_id = '';
    public $invoice_date;
    public $due_date;
    public $notes = '';
    public $terms = '';
    public $discount_amount = 0;
    public $discount_commercial = 0; // Desconto Comercial (antes IVA)
    public $discount_financial = 0;  // Desconto Financeiro (após IVA)
    public $is_service = false;      // Se é prestação de serviço (sujeito a IRT)

    // Product selection
    public $showProductModal = false;
    public $searchProduct = '';
    public $selectedCategory = '';
    
    // Batch tracking
    public $showBatchModal = false;
    public $batchProductId = null;
    public $batchProductName = '';
    public $batch_number = '';
    public $manufacturing_date = '';
    public $expiry_date = '';
    public $alert_days = 30;
    
    // Supplier search
    public $searchSupplier = '';
    
    public function selectSupplier($supplierId)
    {
        $this->supplier_id = $supplierId;
        $this->searchSupplier = '';
        $this->reset('searchSupplier');
        
        // Salvar na sessão para persistir entre reloads
        $sessionKey = 'invoice_supplier_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $supplierId]);
        
        // Get supplier name
        $supplier = Supplier::find($supplierId);
        
        // Force clear input visually
        $this->dispatch('supplier-selected');
        
        // Toast notification
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Fornecedor selecionado: :detalhe', ['detalhe' => ($supplier ? $supplier->name : '')])]);
    }
    
    public function clearSupplier()
    {
        $this->supplier_id = '';
        $this->searchSupplier = '';
        
        // Remover da sessão
        $sessionKey = 'invoice_supplier_' . activeTenantId() . '_' . auth()->id();
        session()->forget($sessionKey);
    }
    
    // Quick supplier creation
    public $showQuickSupplierModal = false;
    public $quickSupplierName = '';
    public $quickSupplierTaxId = '';
    public $quickSupplierEmail = '';
    public $quickSupplierPhone = '';
    public $quickSupplierAddress = '';

    // Cart identifier
    public $cartInstance;

    protected $rules = [
        'supplier_id' => 'required|exists:invoicing_suppliers,id',
        'warehouse_id' => 'required|exists:invoicing_warehouses,id',
        'invoice_date' => 'required|date',
        'due_date' => 'nullable|date|after:invoice_date',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_commercial' => 'nullable|numeric|min:0',
        'discount_financial' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:65535',
        'terms' => 'nullable|string|max:65535',
    ];
    
    public function updated($propertyName)
    {
        // Os campos de dinheiro chegam mascarados (10.000,23) do input; ler
        // sempre para float antes de validar/calcular.
        if (in_array($propertyName, ['discount_commercial', 'discount_amount', 'discount_financial'], true)) {
            $this->{$propertyName} = \App\Helpers\MoneyHelper::parse($this->{$propertyName});
        }

        // Quando supplier_id é alterado, salvar na sessão
        if ($propertyName === 'supplier_id' && $this->supplier_id) {
            $sessionKey = 'invoice_supplier_' . activeTenantId() . '_' . auth()->id();
            session([$sessionKey => $this->supplier_id]);
        }
    }

    public function mount($id = null)
    {
        $this->invoice_date = now()->format('Y-m-d');
        $this->due_date = now()->addDays(30)->format('Y-m-d');
        
        // Unique cart instance per user and tenant (persiste entre reloads)
        $this->cartInstance = 'purchase_invoice_' . activeTenantId() . '_' . auth()->id();
        
        // Set default warehouse
        $defaultWarehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->first();
        
        if ($defaultWarehouse) {
            $this->warehouse_id = $defaultWarehouse->id;
        }
        
        if ($id) {
            $this->isEdit = true;
            // Era $this->proformaId (propriedade inexistente, copiada da proforma):
            // o save() fazia findOrFail(null) e rebentava sempre com
            // "No query results for model [PurchaseInvoice]".
            $this->invoiceId = $id;
            $this->loadInvoice($id);
        } elseif ($duplicar = $this->idParaDuplicar()) {
            // DUPLICAR: o mesmo carregamento da edição, sem ser uma edição.
            // O que fica de fora está explicado no trait DuplicaDocumento.
            $origem = \App\Models\Invoicing\PurchaseInvoice::where('tenant_id', activeTenantId())
                ->tap(fn ($q) => $this->escoparAoAutor($q))
                ->findOrFail($duplicar);

            $this->loadInvoice($duplicar);
            $this->marcarComoDuplicado($origem->invoice_number);

            // As datas são de HOJE: um duplicado não herda o vencimento de uma
            // factura de compra que já foi paga.
            $this->invoice_date = now()->format('Y-m-d');
            $this->due_date = now()->addDays(30)->format('Y-m-d');
        } else {
            // Restaurar fornecedor da sessão se existir
            $sessionKey = 'invoice_supplier_' . activeTenantId() . '_' . auth()->id();
            $savedSupplierId = session($sessionKey);
            
            if ($savedSupplierId && Supplier::where('id', $savedSupplierId)->where('tenant_id', activeTenantId())->exists()) {
                $this->supplier_id = $savedSupplierId;
            }
        }
        
        $this->searchSupplier = '';
    }

    public function loadInvoice($id)
    {
        $invoice = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->with('items.product')
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->findOrFail($id);

        $this->supplier_id = $invoice->supplier_id;
        $this->warehouse_id = $invoice->warehouse_id;
        $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
        $this->due_date = $invoice->due_date?->format('Y-m-d');
        $this->notes = $invoice->notes;
        $this->terms = $invoice->terms;
        $this->discount_amount = $invoice->discount_amount;
        $this->discount_commercial = $invoice->discount_commercial ?? 0;
        $this->discount_financial = $invoice->discount_financial ?? 0;
        $this->is_service = $invoice->is_service ?? false;

        // Load items into cart
        Cart::session($this->cartInstance)->clear();
        foreach ($invoice->items as $item) {
            Cart::session($this->cartInstance)->add([
                'id' => $item->product_id,
                'name' => $item->product->name,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'attributes' => [
                    // Uma linha gravada sem taxa nao e uma linha isenta nem uma
                    // linha a 14%: reresolve-se pelo TaxResolver. Enquanto isto
                    // passava nulo, o ecra mostrava Isento e o resumo cobrava 14%.
                    'tax_rate' => $item->tax_rate !== null
                        ? (float) $item->tax_rate
                        : (float) \App\Services\Invoicing\TaxResolver::forProductId($item->product_id, activeTenantId())['rate'],
                    'tax_type' => $item->product->tax_type ?? 'iva',
                    'exemption_reason' => $item->product->exemption_reason ?? null,
                    'discount_percent' => $item->discount_percent,
                    'unit' => $item->unit,
                ]
            ]);
        }
    }

    public function render()
    {
        // Get all suppliers first if we have a selected supplier
        $allSuppliers = collect();
        if ($this->supplier_id && !$this->searchSupplier) {
            $allSuppliers = Supplier::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->get();
        }
        
        // Get suppliers with search filter
        $suppliersQuery = Supplier::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchSupplier) {
            $suppliersQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchSupplier . '%')
                  ->orWhere('email', 'like', '%' . $this->searchSupplier . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchSupplier . '%');
            });
        }
        
        $suppliers = $this->searchSupplier ? $suppliersQuery->orderBy('name')->limit(50)->get() : $allSuppliers;

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
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

        $fornecedor = $this->supplier_id ? \App\Models\Supplier::find($this->supplier_id) : null;
        $regiaoFiscal = in_array($this->tax_country_region, ['AO', 'AO-CAB'], true)
            ? $this->tax_country_region
            : 'AO';

        return view('livewire.invoicing.faturas-compra.create', array_merge([
            'regiaoFiscal' => $regiaoFiscal,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'cartItems' => $cartItems,
            'products' => $products,
        ], $totals));
    }
    public function addProduct($productId)
    {
        $product = Product::with('taxRate')->where('tenant_id', activeTenantId())->findOrFail($productId);

        // Verificar se produto rastreia lotes
        if ($product->track_batches) {
            // Abrir modal de lote ao invés de adicionar direto
            $this->batchProductId = $productId;
            $this->batchProductName = $product->name;
            $this->showProductModal = false;
            $this->showBatchModal = true;
            
            // Reset batch fields
            $this->batch_number = '';
            $this->manufacturing_date = '';
            $this->expiry_date = '';
            $this->alert_days = 30;
            
            return;
        }

        // Verificar se produto já existe no carrinho
        $existingItem = Cart::session($this->cartInstance)->get($productId);
        
        if ($existingItem) {
            // Se já existe, incrementa quantidade
            Cart::session($this->cartInstance)->update($productId, [
                'quantity' => 1 // Incrementa 1
            ]);
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => __('Quantidade incrementada: :detalhe', ['detalhe' => $product->name])]);
        } else {
            // Determinar taxa de IVA baseado no produto
            // A taxa vem do TaxResolver, a fonte UNICA do imposto por linha,
            // igual ao ecra de venda: respeita o regime da empresa e tem
            // recurso a taxa por omissao. Ler $product->taxRate em bruto
            // deixava a linha sem taxa quando o produto nao a tinha atribuida.
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
                    'exemption_reason' => $product->exemption_reason ?? null,
                    'batch_number' => null,
                    'manufacturing_date' => null,
                    'expiry_date' => null,
                    'alert_days' => 30,
                ]
            ]);
            
            $typeLabel = $product->type === 'servico' ? 'Serviço' : 'Produto';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $typeLabel . ' adicionado: ' . $product->name . ' (IVA: ' . $taxRate . '%)'
            ]);
        }

        $this->showProductModal = false;
        $this->searchProduct = '';
    }
    
    public function confirmBatchAndAddProduct()
    {
        if (!$this->batchProductId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Produto não selecionado')
            ]);
            return;
        }
        
        $product = Product::with('taxRate')->where('tenant_id', activeTenantId())->findOrFail($this->batchProductId);
        
        // Validar campos obrigatórios se produto exige
        if ($product->require_batch_on_purchase && !$this->batch_number) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Número do lote é obrigatório para este produto')
            ]);
            return;
        }
        
        if ($product->track_expiry && !$this->expiry_date) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => __('Data de validade não informada')
            ]);
        }
        
        // Determinar taxa de IVA
        // Mesma fonte unica do imposto por linha (ver addProduct).
        $lineTx  = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());
        $taxRate = (float) $lineTx['rate'];
        
        // Adicionar ao carrinho com dados do lote
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
                'exemption_reason' => $product->exemption_reason ?? null,
                'batch_number' => $this->batch_number,
                'manufacturing_date' => $this->manufacturing_date,
                'expiry_date' => $this->expiry_date,
                'alert_days' => $this->alert_days,
            ]
        ]);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Produto adicionado com lote: :detalhe', ['detalhe' => $product->name . ($this->batch_number ? " (Lote: {$this->batch_number})" : '')])]);
        
        // Fechar modal e limpar
        $this->showBatchModal = false;
        $this->batchProductId = null;
        $this->batchProductName = '';
        $this->batch_number = '';
        $this->manufacturing_date = '';
        $this->expiry_date = '';
        $this->alert_days = 30;
    }
    
    public function closeBatchModal()
    {
        $this->showBatchModal = false;
        $this->batchProductId = null;
        $this->batchProductName = '';
        $this->batch_number = '';
        $this->manufacturing_date = '';
        $this->expiry_date = '';
        $this->alert_days = 30;
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
        $productName = $item ? $item->name : 'Produto';
        
        Cart::session($this->cartInstance)->remove($productId);
        
        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => __('Produto removido: :detalhe', ['detalhe' => $productName])]);
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
                'message' => __('Quantidade atualizada para: :detalhe', ['detalhe' => $quantity])]);
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

    public function updateBatchData($productId, $batchNumber, $manufacturingDate, $expiryDate, $alertDays = 30)
    {
        $item = Cart::session($this->cartInstance)->get($productId);
        if ($item) {
            $attributes = $item->attributes->toArray();
            $attributes['batch_number'] = $batchNumber;
            $attributes['manufacturing_date'] = $manufacturingDate;
            $attributes['expiry_date'] = $expiryDate;
            $attributes['alert_days'] = $alertDays;
            
            Cart::session($this->cartInstance)->update($productId, [
                'attributes' => $attributes
            ]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Dados de lote atualizados!')
            ]);
        }
    }

    public function createQuickSupplier()
    {
        $this->validate([
            'quickSupplierName' => 'required|string|max:255',
            'quickSupplierTaxId' => 'nullable|string|max:20',
            'quickSupplierEmail' => 'nullable|email|max:255',
            'quickSupplierPhone' => 'nullable|string|max:20',
        ]);

        $supplier = Supplier::create([
            'tenant_id' => activeTenantId(),
            'name' => $this->quickSupplierName,
            'nif' => $this->quickSupplierTaxId ?: '999999999',
            'type' => 'pessoa_juridica',
            'email' => $this->quickSupplierEmail,
            'phone' => $this->quickSupplierPhone,
            'address' => $this->quickSupplierAddress,
            'is_active' => true,
        ]);

        $this->supplier_id = $supplier->id;
        $this->searchSupplier = '';
        $this->showQuickSupplierModal = false;
        
        // Salvar na sessão
        $sessionKey = 'invoice_supplier_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $this->supplier_id]);
        
        // Reset form
        $this->quickSupplierName = '';
        $this->quickSupplierTaxId = '';
        $this->quickSupplierEmail = '';
        $this->quickSupplierPhone = '';
        $this->quickSupplierAddress = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Fornecedor criado com sucesso: :detalhe', ['detalhe' => $supplier->name])]);
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

        /*
         * O REGISTO VIVE NO `EmissorDeCompras`: linhas com lotes, totais, hash,
         * e o estado posto no fim para o observer dar entrada do stock com as
         * linhas já lá. Este ecrã e o ecrã em React chamam o mesmo.
         */
        $existente = null;

        if ($this->isEdit) {
            $existente = PurchaseInvoice::where('tenant_id', activeTenantId())
                ->tap(fn ($q) => $this->escoparAoAutor($q))
                ->findOrFail($this->invoiceId);
        }

        try {
            $invoice = app(\App\Services\Invoicing\EmissorDeCompras::class)->emitir([
                'supplier_id' => $this->supplier_id,
                'warehouse_id' => $this->warehouse_id,
                'invoice_date' => $this->invoice_date,
                'due_date' => $this->due_date,
                'is_service' => $this->is_service,
                'discount_amount' => $this->discount_amount,
                'discount_commercial' => $this->discount_commercial,
                'discount_financial' => $this->discount_financial,
                'notes' => $this->notes,
                'terms' => $this->terms,
                'tax_country_region' => $this->tax_country_region,
                // Numa edição o estado não muda, para o stock não entrar duas vezes.
                'status' => $this->isEdit ? $existente->status : $status,
            ], collect($cartItems->values()->all()), $existente);
        } catch (\DomainException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao salvar fatura: :detalhe', ['detalhe' => $e->getMessage()])]);

            return;
        }

        Cart::session($this->cartInstance)->clear();
        session()->forget('invoice_supplier_' . activeTenantId() . '_' . auth()->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $this->isEdit
                ? __('Fatura de Compra atualizada com sucesso!')
                : __('Fatura de Compra criada com sucesso!')
        ]);

        $this->dispatch('openInvoicePreview', ['invoiceId' => $invoice->id]);

        return redirect()->route('invoicing.purchases.invoices');
    }
}

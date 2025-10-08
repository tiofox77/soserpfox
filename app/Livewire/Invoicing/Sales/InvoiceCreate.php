<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
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
        'warehouse_id' => 'required|exists:invoicing_warehouses,id',
        'invoice_date' => 'required|date',
        'due_date' => 'nullable|date|after:invoice_date',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_commercial' => 'nullable|numeric|min:0',
        'discount_financial' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
        'terms' => 'nullable|string|max:1000',
    ];
    
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

    public function mount($id = null)
    {
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
        // Get all Clients first if we have a selected Client
        $allClients = collect();
        if ($this->client_id && !$this->searchClient) {
            $allClients = Client::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->get();
        }
        
        // Get clients with search filter
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('email', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchClient . '%');
            });
        }
        
        $clients = $this->searchClient ? $clientsQuery->orderBy('name')->limit(50)->get() : $allClients;

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

        return view('livewire.invoicing.faturas-venda.create', array_merge([
            'clients' => $clients,
            'warehouses' => $warehouses,
            'cartItems' => $cartItems,
            'products' => $products,
        ], $totals));
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
            // Determinar taxa de IVA baseado no produto
            $taxRate = 0;
            if ($product->tax_type === 'iva' && $product->taxRate) {
                $taxRate = $product->taxRate->rate;
            }
            
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
                        'tax_rate' => $item->attributes['tax_rate'] ?? 14,
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

        $this->client_id = $Client->id;
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

                // Delete old items
                $invoice->items()->delete();
            } else {
                $invoice = new SalesInvoice();
                $invoice->tenant_id = activeTenantId();
                $invoice->created_by = auth()->id();
            }

            $invoice->client_id = $this->client_id;
            $invoice->warehouse_id = $this->warehouse_id;
            $invoice->invoice_date = $this->invoice_date;
            $invoice->due_date = $this->due_date;
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
            
            foreach ($cartItems as $item) {
                // Calcular valores do item conforme AGT Angola
                $valorBrutoLinha = $item->price * $item->quantity;
                $descontoPercent = $item->attributes['discount_percent'] ?? 0;
                $descontoAmount = $valorBrutoLinha * ($descontoPercent / 100);
                $subtotal = $valorBrutoLinha;
                $valorAposDesconto = $valorBrutoLinha - $descontoAmount;
                $taxAmount = $valorAposDesconto * (($item->attributes['tax_rate'] ?? 14) / 100);
                $total = $valorAposDesconto + $taxAmount;
                
                // Acumular totais
                $invoice_subtotal += $valorBrutoLinha;
                $invoice_tax_amount += $taxAmount;
                
                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->attributes['unit'] ?? 'UN',
                    'unit_price' => $item->price,
                    'discount_percent' => $descontoPercent,
                    'discount_amount' => $descontoAmount,
                    'subtotal' => $subtotal,
                    'tax_rate' => $item->attributes['tax_rate'] ?? 14,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'order' => ++$order,
                ]);
            }

            // Calcular total da fatura conforme AGT Angola
            $desconto_comercial_total = $invoice->discount_commercial + $invoice->discount_amount;
            $valor_apos_desc_comercial = $invoice_subtotal - $desconto_comercial_total;
            $incidencia_iva = $valor_apos_desc_comercial - $invoice->discount_financial;
            $irt_amount = $invoice->is_service ? $incidencia_iva * 0.065 : 0;
            $total_final = $incidencia_iva + $invoice_tax_amount - $irt_amount;
            
            // Atualizar totais da fatura
            $invoice->subtotal = $invoice_subtotal;
            $invoice->tax_amount = $invoice_tax_amount;
            $invoice->irt_amount = $irt_amount;
            $invoice->total = $total_final;
            $invoice->save();
            
            // Gerar HASH SAFT-AO conforme regulamento Angola
            $previousInvoice = SalesInvoice::where('tenant_id', activeTenantId())
                ->where('id', '<', $invoice->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();
            
            $hash = \App\Helpers\SAFTHelper::generateHash(
                $invoice->invoice_date->format('Y-m-d'),
                $invoice->created_at->format('Y-m-d H:i:s'),
                $invoice->invoice_number,
                $invoice->total,
                $previousInvoice->saft_hash ?? null
            );
            
            if ($hash) {
                $invoice->saft_hash = $hash;
                $invoice->save();
            }
            
            DB::commit();

            // Clear cart
            Cart::session($this->cartInstance)->clear();
            
            // Clear session
            $sessionKey = 'invoice_client_' . activeTenantId() . '_' . auth()->id();
            session()->forget($sessionKey);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fatura ' . ($this->isEdit ? 'atualizada' : 'criada') . ' com sucesso!'
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
}

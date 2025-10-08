<?php

namespace App\Livewire\Invoicing\Purchases;

use App\Models\Invoicing\PurchaseProforma;
use App\Models\Invoicing\PurchaseProformaItem;
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
#[Title('Nova Proforma de Compra')]
class ProformaCreate extends Component
{
    public $proformaId = null;
    public $isEdit = false;

    // Form fields
    public $supplier_id = '';
    public $warehouse_id = '';
    public $proforma_date;
    public $valid_until;
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
        $sessionKey = 'proforma_supplier_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $supplierId]);
        
        // Get supplier name
        $supplier = Supplier::find($supplierId);
        
        // Force clear input visually
        $this->dispatch('supplier-selected');
        
        // Toast notification
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fornecedor selecionado: ' . ($supplier ? $supplier->name : '')
        ]);
    }
    
    public function clearSupplier()
    {
        $this->supplier_id = '';
        $this->searchSupplier = '';
        
        // Remover da sessão
        $sessionKey = 'proforma_supplier_' . activeTenantId() . '_' . auth()->id();
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
        'proforma_date' => 'required|date',
        'valid_until' => 'nullable|date|after:proforma_date',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_commercial' => 'nullable|numeric|min:0',
        'discount_financial' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
        'terms' => 'nullable|string|max:1000',
    ];
    
    public function updated($propertyName)
    {
        // Quando supplier_id é alterado, salvar na sessão
        if ($propertyName === 'supplier_id' && $this->supplier_id) {
            $sessionKey = 'proforma_supplier_' . activeTenantId() . '_' . auth()->id();
            session([$sessionKey => $this->supplier_id]);
        }
    }

    public function mount($id = null)
    {
        $this->proforma_date = now()->format('Y-m-d');
        $this->valid_until = now()->addDays(30)->format('Y-m-d');
        
        // Unique cart instance per user and tenant (persiste entre reloads)
        $this->cartInstance = 'proforma_' . activeTenantId() . '_' . auth()->id();
        
        // Set default warehouse
        $defaultWarehouse = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->first();
        
        if ($defaultWarehouse) {
            $this->warehouse_id = $defaultWarehouse->id;
        }
        
        if ($id) {
            $this->isEdit = true;
            $this->proformaId = $id;
            $this->loadProforma($id);
        } else {
            // Restaurar fornecedor da sessão se existir
            $sessionKey = 'proforma_supplier_' . activeTenantId() . '_' . auth()->id();
            $savedSupplierId = session($sessionKey);
            
            if ($savedSupplierId && Supplier::where('id', $savedSupplierId)->where('tenant_id', activeTenantId())->exists()) {
                $this->supplier_id = $savedSupplierId;
            }
        }
        
        $this->searchSupplier = '';
    }

    public function loadProforma($id)
    {
        $proforma = PurchaseProforma::where('tenant_id', activeTenantId())
            ->with('items.product')
            ->findOrFail($id);

        $this->supplier_id = $proforma->supplier_id;
        $this->warehouse_id = $proforma->warehouse_id;
        $this->proforma_date = $proforma->proforma_date->format('Y-m-d');
        $this->valid_until = $proforma->valid_until?->format('Y-m-d');
        $this->notes = $proforma->notes;
        $this->terms = $proforma->terms;
        $this->discount_amount = $proforma->discount_amount;
        $this->discount_commercial = $proforma->discount_commercial ?? 0;
        $this->discount_financial = $proforma->discount_financial ?? 0;
        $this->is_service = $proforma->is_service ?? false;

        // Load items into cart
        Cart::session($this->cartInstance)->clear();
        foreach ($proforma->items as $item) {
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

        return view('livewire.invoicing.proformas-compra.create', array_merge([
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
                'message' => 'Produto não selecionado'
            ]);
            return;
        }
        
        $product = Product::with('taxRate')->where('tenant_id', activeTenantId())->findOrFail($this->batchProductId);
        
        // Validar campos obrigatórios se produto exige
        if ($product->require_batch_on_purchase && !$this->batch_number) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Número do lote é obrigatório para este produto'
            ]);
            return;
        }
        
        if ($product->track_expiry && !$this->expiry_date) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Data de validade não informada'
            ]);
        }
        
        // Determinar taxa de IVA
        $taxRate = 0;
        if ($product->tax_type === 'iva' && $product->taxRate) {
            $taxRate = $product->taxRate->rate;
        }
        
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
            'message' => 'Produto adicionado com lote: ' . $product->name . ($this->batch_number ? " (Lote: {$this->batch_number})" : '')
        ]);
        
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
        $sessionKey = 'proforma_supplier_' . activeTenantId() . '_' . auth()->id();
        session([$sessionKey => $this->supplier_id]);
        
        // Reset form
        $this->quickSupplierName = '';
        $this->quickSupplierTaxId = '';
        $this->quickSupplierEmail = '';
        $this->quickSupplierPhone = '';
        $this->quickSupplierAddress = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Fornecedor criado com sucesso: ' . $supplier->name
        ]);
    }

    public function save($status = 'draft')
    {
        $this->validate();

        $cartItems = Cart::session($this->cartInstance)->getContent();

        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Adicione pelo menos um produto à proforma.'
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $proforma = PurchaseProforma::where('tenant_id', activeTenantId())
                    ->findOrFail($this->proformaId);
                
                if ($proforma->status === 'converted') {
                    throw new \Exception('Não é possível editar uma proforma já convertida.');
                }

                // Delete old items
                $proforma->items()->delete();
            } else {
                $proforma = new PurchaseProforma();
                $proforma->tenant_id = activeTenantId();
                $proforma->created_by = auth()->id();
            }

            $proforma->supplier_id = $this->supplier_id;
            $proforma->warehouse_id = $this->warehouse_id;
            $proforma->proforma_date = $this->proforma_date;
            $proforma->valid_until = $this->valid_until;
            $proforma->status = $status;
            $proforma->is_service = $this->is_service;
            $proforma->discount_amount = $this->discount_amount;
            $proforma->discount_commercial = $this->discount_commercial;
            $proforma->discount_financial = $this->discount_financial;
            $proforma->notes = $this->notes;
            $proforma->terms = $this->terms;
            $proforma->save();

            // Add items e calcular totais conforme AGT Angola
            $order = 0;
            $proforma_subtotal = 0;
            $proforma_tax_amount = 0;
            
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
                $proforma_subtotal += $valorBrutoLinha;
                $proforma_tax_amount += $taxAmount;
                
                PurchaseProformaItem::create([
                    'purchase_proforma_id' => $proforma->id,
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

            // Calcular total da proforma conforme AGT Angola
            $desconto_comercial_total = $proforma->discount_commercial + $proforma->discount_amount;
            $valor_apos_desc_comercial = $proforma_subtotal - $desconto_comercial_total;
            $incidencia_iva = $valor_apos_desc_comercial - $proforma->discount_financial;
            $irt_amount = $proforma->is_service ? $incidencia_iva * 0.065 : 0;
            $total_final = $incidencia_iva + $proforma_tax_amount - $irt_amount;
            
            // Atualizar totais do proforma
            $proforma->subtotal = $proforma_subtotal;
            $proforma->tax_amount = $proforma_tax_amount;
            $proforma->irt_amount = $irt_amount;
            $proforma->total = $total_final;
            $proforma->save();
            
            // Gerar HASH SAFT-AO conforme regulamento Angola
            $previousProforma = PurchaseProforma::where('tenant_id', activeTenantId())
                ->where('id', '<', $proforma->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();
            
            $hash = \App\Helpers\SAFTHelper::generateHash(
                $proforma->proforma_date->format('Y-m-d'),
                $proforma->created_at->format('Y-m-d H:i:s'),
                $proforma->proforma_number,
                $proforma->total,
                $previousProforma->saft_hash ?? null
            );
            
            if ($hash) {
                $proforma->saft_hash = $hash;
                $proforma->save();
            }
            
            DB::commit();

            // Clear cart
            Cart::session($this->cartInstance)->clear();
            
            // Clear session
            $sessionKey = 'proforma_client_' . activeTenantId() . '_' . auth()->id();
            session()->forget($sessionKey);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Proforma ' . ($this->isEdit ? 'atualizada' : 'criada') . ' com sucesso!'
            ]);
            
            // Disparar evento para abrir preview em nova aba
            $this->dispatch('openProformaPreview', ['proformaId' => $proforma->id]);
            
            return redirect()->route('invoicing.purchases.proformas');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao salvar proforma: ' . $e->getMessage()
            ]);
        }
    }
}

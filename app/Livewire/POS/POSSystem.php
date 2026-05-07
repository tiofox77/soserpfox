<?php

namespace App\Livewire\POS;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Product;
use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Category;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\PaymentMethod as TreasuryPaymentMethod;
use App\Models\Treasury\CashRegister;
use Illuminate\Support\Facades\DB;
use Darryldecode\Cart\Facades\CartFacade as Cart;

#[Layout('layouts.app')]
#[Title('POS - Ponto de Venda')]
class POSSystem extends Component
{
    public $currentShift = null;
    public $search = '';
    public $selectedCategory = null;
    public $selectedClient = null;
    public $searchClient = '';
    public $showClientModal = false;
    public $showPaymentModal = false;
    public $showPrintModal = false;
    public $lastInvoice = null;
    public $lastInvoiceQR = null;
    
    // Pagamento
    public $paymentMethod = 'cash';
    public $amountReceived = 0;
    public $notes = '';
    public $discount = 0;
    public $discountType = 'percentage'; // percentage ou fixed
    public $paymentMethods = []; // Métodos de pagamento do Treasury
    
    // Calculados
    public $cartItems = [];
    public $cartTotal = 0;
    public $cartSubtotal = 0;
    public $cartTax = 0;
    public $cartIrt = 0;
    public $cartDiscount = 0;
    public $cartQuantity = 0;
    public $taxRate = 14;
    public $irtRate = 6.5;
    public $change = 0;
    
    // Quick amounts (trocos rápidos)
    public $quickAmounts = [1000, 2000, 5000, 10000, 20000, 50000, 100000];

    public function mount()
    {
        // Verificar se há turno aberto
        $this->currentShift = PosShift::where('tenant_id', activeTenantId())
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();
        
        // Se não houver turno aberto, redirecionar para abrir turno
        if (!$this->currentShift) {
            session()->flash('warning', '⚠️ Você precisa abrir um turno antes de usar o POS!');
            return redirect()->route('invoicing.pos.shifts');
        }
        
        // Definir cliente padrão "Consumidor Final"
        $this->selectedClient = Client::where('tenant_id', activeTenantId())
            ->where('name', 'LIKE', '%Consumidor Final%')
            ->orWhere('nif', '999999999')
            ->first();
        
        // Se não existir, criar
        if (!$this->selectedClient) {
            $this->selectedClient = Client::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Consumidor Final',
                'nif' => '999999999',
                'email' => 'consumidorfinal@pos.local',
                'phone' => '999999999',
                'address' => 'N/A',
                'is_active' => true,
            ]);
        }
        
        // Carregar métodos de pagamento do Treasury
        $this->paymentMethods = TreasuryPaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Carregar carrinho
        $this->loadCart();
    }

    public function loadCart()
    {
        $this->cartItems = Cart::session(auth()->id())->getContent();
        $this->cartSubtotal = Cart::session(auth()->id())->getSubTotal();
        $this->cartQuantity = Cart::session(auth()->id())->getTotalQuantity();
        
        // Obter configurações de impostos
        $settings = InvoicingSettings::forTenant(activeTenantId());
        $defaultTaxRate = $settings->default_tax_rate ?? 14;
        $defaultIrtRate = $settings->default_irt_rate ?? 6.5;
        $applyIrtServices = $settings->apply_irt_services ?? true;
        
        // Guardar taxas para exibição
        $this->taxRate = $defaultTaxRate;
        $this->irtRate = $defaultIrtRate;
        
        // Garantir que desconto seja numérico
        $discount = is_numeric($this->discount) ? floatval($this->discount) : 0;
        
        // Calcular desconto
        if ($this->discountType === 'percentage') {
            $this->cartDiscount = ($this->cartSubtotal * $discount) / 100;
        } else {
            $this->cartDiscount = $discount;
        }
        
        // Calcular impostos por item (IVA e IRT para serviços)
        $subtotalAfterDiscount = $this->cartSubtotal - $this->cartDiscount;
        $this->cartTax = 0;
        $this->cartIrt = 0;
        
        foreach ($this->cartItems as $item) {
            $itemTotal = $item->price * $item->quantity;
            $isService = isset($item->attributes['type']) && $item->attributes['type'] === 'service';
            
            // Taxa de IVA do item ou padrão
            $taxRate = $item->attributes['tax_rate'] ?? $defaultTaxRate;
            $this->cartTax += $itemTotal * ($taxRate / 100);
            
            // IRT para serviços
            if ($isService && $applyIrtServices) {
                $this->cartIrt += $itemTotal * ($defaultIrtRate / 100);
            }
        }
        
        // Total final (subtotal - desconto + IVA - IRT retido)
        $this->cartTotal = $subtotalAfterDiscount + $this->cartTax - $this->cartIrt;
        
        $this->calculateChange();
    }
    
    public function updatedDiscount()
    {
        // Normalizar desconto (se vazio ou inválido, definir como 0)
        if (!is_numeric($this->discount) || $this->discount === '' || $this->discount === null) {
            $this->discount = 0;
        }
        
        $this->loadCart();
    }
    
    public function updatedDiscountType()
    {
        $this->loadCart();
    }
    
    public function setQuickAmount($amount)
    {
        $this->amountReceived = $amount;
        $this->calculateChange();
    }

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        
        if (!$product || $product->stock_quantity <= 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Produto sem stock disponível!'
            ]);
            return;
        }

        // Verificar se já existe no carrinho
        $cartItem = Cart::session(auth()->id())->get($productId);
        $currentQuantity = $cartItem ? $cartItem->quantity : 0;
        $newQuantity = $currentQuantity + 1;
        
        // Se produto rastreia lotes, validar disponibilidade nos lotes
        if ($product->track_batches) {
            $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->get();
            
            $totalAvailable = $availableBatches->sum('quantity_available');
            
            if ($availableBatches->isEmpty()) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Produto exige lote mas não há lotes disponíveis!'
                ]);
                return;
            }
            
            // Verificar lotes expirados
            $expiredBatches = $availableBatches->filter(fn($b) => $b->is_expired);
            if ($expiredBatches->isNotEmpty()) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Lotes expirados encontrados!'
                ]);
                return;
            }
            
            if ($newQuantity > $totalAvailable) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ Quantidade excede lotes disponíveis! Disponível: ' . $totalAvailable . ' un'
                ]);
                return;
            }
        }
        // Validar stock disponível (tradi cional)
        elseif ($newQuantity > $product->stock_quantity) {
            // Disparar som de erro
            $this->dispatch('stock-error');
            
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ Stock insuficiente! Disponível: ' . $product->stock_quantity . ' un'
            ]);
            return;
        }

        Cart::session(auth()->id())->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'attributes' => [
                'image' => $product->image,
                'sku' => $product->sku,
                'tax_rate' => $product->tax_rate ?? 14, // IVA padrão 14% Angola
                'discount_percent' => 0,
            ]
        ]);

        $this->loadCart();
        
        // Disparar evento para tocar som
        $this->dispatch('item-added');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Produto adicionado! (' . $newQuantity . '/' . $product->stock_quantity . ' un)'
        ]);
    }

    public function updateQuantity($itemId, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($itemId);
            return;
        }

        $product = Product::find($itemId);
        
        if (!$product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Produto não encontrado!'
            ]);
            return;
        }
        
        // Se produto rastreia lotes, validar nos lotes
        if ($product->track_batches) {
            $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $itemId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->get();
            
            $totalAvailable = $availableBatches->sum('quantity_available');
            
            if ($quantity > $totalAvailable) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ Quantidade excede lotes! Disponível: ' . $totalAvailable . ' un. Ajustando...'
                ]);
                $quantity = $totalAvailable;
            }
        }
        // Validar stock tradicional
        elseif ($quantity > $product->stock_quantity) {
            // Disparar som de erro
            $this->dispatch('stock-error');
            
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ Quantidade excede stock! Disponível: ' . $product->stock_quantity . ' un. Ajustando...'
            ]);
            // Ajustar para o máximo disponível
            $quantity = $product->stock_quantity;
        }

        Cart::session(auth()->id())->update($itemId, [
            'quantity' => [
                'relative' => false,
                'value' => $quantity
            ]
        ]);

        $this->loadCart();
    }

    public function increaseQuantity($itemId)
    {
        $cartItem = Cart::session(auth()->id())->get($itemId);
        
        if (!$cartItem) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Item não encontrado no carrinho!'
            ]);
            return;
        }
        
        $newQuantity = $cartItem->quantity + 1;
        $isService = isset($cartItem->attributes['type']) && $cartItem->attributes['type'] === 'service';
        
        // Para produtos, validar stock disponível
        if (!$isService) {
            $product = Product::find($itemId);
            
            if (!$product) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Produto não encontrado!'
                ]);
                return;
            }
            
            if ($newQuantity > $product->stock_quantity) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ Stock máximo atingido! Disponível: ' . $product->stock_quantity . ' un'
                ]);
                return;
            }
        }
        
        Cart::session(auth()->id())->update($itemId, [
            'quantity' => 1
        ]);
        $this->loadCart();
        
        $this->dispatch('cart-updated', ['action' => 'add']);
        
        $message = $isService 
            ? '📈 ' . $cartItem->name . ' (' . $newQuantity . 'x)'
            : '📈 Quantidade: ' . $newQuantity . '/' . ($product->stock_quantity ?? 0) . ' un';
            
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => $message
        ]);
    }

    public function decreaseQuantity($itemId)
    {
        $item = Cart::session(auth()->id())->get($itemId);
        if ($item->quantity > 1) {
            Cart::session(auth()->id())->update($itemId, [
                'quantity' => -1
            ]);
            $this->loadCart();
            
            // Disparar evento para tocar som
            $this->dispatch('cart-updated', ['action' => 'remove']);
        } else {
            $this->removeFromCart($itemId);
        }
    }

    public function removeFromCart($itemId)
    {
        Cart::session(auth()->id())->remove($itemId);
        $this->loadCart();
        
        // Disparar evento para tocar som
        $this->dispatch('item-removed');
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Produto removido do carrinho'
        ]);
    }

    public function clearCart()
    {
        Cart::session(auth()->id())->clear();
        $this->loadCart();
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Carrinho limpo'
        ]);
    }

    public function selectClient($clientId)
    {
        $this->selectedClient = Client::find($clientId);
        $this->showClientModal = false;
        $this->searchClient = '';
    }

    public function updatedAmountReceived()
    {
        // Normalizar valor recebido (se vazio ou inválido, definir como 0)
        if (!is_numeric($this->amountReceived) || $this->amountReceived === '' || $this->amountReceived === null) {
            $this->amountReceived = 0;
        }
        
        $this->calculateChange();
    }

    public function calculateChange()
    {
        // Garantir que amountReceived seja numérico
        $amountReceived = is_numeric($this->amountReceived) ? floatval($this->amountReceived) : 0;
        $this->change = $amountReceived - $this->cartTotal;
    }

    public function completeSale()
    {
        if ($this->cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Carrinho vazio!'
            ]);
            return;
        }

        if (!$this->selectedClient) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Selecione um cliente!'
            ]);
            return;
        }

        if ($this->amountReceived < $this->cartTotal) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Valor recebido insuficiente!'
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            // Converter items para coleção com atributos do carrinho
            $cartItemsForCalc = collect($this->cartItems)->map(function($item) {
                return (object)[
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'attributes' => [
                        'discount_percent' => 0,
                        'tax_rate' => $item->attributes->tax_rate ?? 14,
                    ]
                ];
            });
            
            // Usar helper de cálculos AGT
            $calculations = \App\Helpers\InvoiceCalculationHelper::calculateTotals(
                $cartItemsForCalc,
                $this->cartDiscount, // commercial_discount
                0,  // discountAmount
                0,  // financial_discount
                false // isService
            );

            // Buscar ou criar série padrão POS (FR A)
            // O método getDefaultSeries cria automaticamente se não existir
            $series = InvoicingSeries::getDefaultSeries(activeTenantId(), 'pos');
            
            // Gerar número de fatura no formato AGT: FR A 2025/000001
            $invoiceNumber = $series->getNextNumber();

            // Criar fatura usando tabela existente
            $invoice = SalesInvoice::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $this->selectedClient->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'due_date' => now(),
                'status' => 'paid',
                'subtotal' => $calculations['subtotal'],
                'tax_amount' => $calculations['tax_amount'],
                'discount_amount' => $calculations['desconto_comercial_total'],
                'total' => $calculations['total'],
                'paid_amount' => $this->amountReceived,
                'notes' => $this->notes,
                'payment_method' => $this->paymentMethod,
                'created_by' => auth()->id(),
            ]);

            // Adicionar itens
            foreach ($this->cartItems as $item) {
                $taxRate = $item->attributes->tax_rate ?? 14;
                $subtotal = $item->price * $item->quantity;
                $taxAmount = $subtotal * ($taxRate / 100);
                
                // Verificar se é serviço (ID começa com 'service_')
                $isService = str_starts_with($item->id, 'service_');
                // Extrair ID numérico: 'service_21' -> 21, ou ID do produto
                $productId = $isService ? (int) str_replace('service_', '', $item->id) : $item->id;
                
                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $productId,
                    'product_name' => $item->name,
                    'description' => $isService ? '[SERVIÇO] ' . $item->name : $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotal,
                    'total' => $subtotal + $taxAmount,
                ]);

                // Atualizar stock apenas para produtos
                if (!$isService) {
                    $product = Product::find($item->id);
                    if ($product) {
                        $product->stock_quantity -= $item->quantity;
                        $product->save();
                    }
                }
            }

            // Criar transação no Treasury (Fatura-Recibo)
            $this->createTreasuryTransaction($invoice);
            
            // Registrar transação no turno POS
            if ($this->currentShift) {
                // Recarregar turno para garantir que está aberto
                $this->currentShift->refresh();
                
                try {
                    $this->currentShift->addTransaction([
                        'type' => 'invoice',
                        'reference_type' => 'App\\Models\\Invoicing\\SalesInvoice',
                        'reference_id' => $invoice->id,
                        'reference_number' => $invoice->invoice_number,
                        'payment_method' => $this->paymentMethod,
                        'amount' => $invoice->total,
                        'description' => 'Venda POS - ' . $invoice->invoice_number,
                        'metadata' => [
                            'client_id' => $this->selectedClient->id,
                            'client_name' => $this->selectedClient->name,
                            'items_count' => $this->cartItems->count(),
                            'amount_received' => $this->amountReceived,
                            'change' => $this->change,
                        ],
                    ]);
                    
                    \Log::info('Venda registrada no turno com sucesso', [
                        'shift_number' => $this->currentShift->shift_number,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoice->total,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('ERRO ao registrar venda no turno', [
                        'shift_id' => $this->currentShift->id,
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Não bloqueia a venda, apenas registra o erro
                }
            } else {
                \Log::warning('Venda realizada sem turno aberto', [
                    'invoice_number' => $invoice->invoice_number,
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            // Guardar fatura para impressão
            $this->lastInvoice = $invoice->load(['client', 'items.product', 'tenant']);

            // Gerar QR Code AGT
            try {
                $this->lastInvoiceQR = getAGTQRData($this->lastInvoice, 120);
            } catch (\Exception $e) {
                \Log::error('POS: Erro ao gerar QR Code', ['error' => $e->getMessage()]);
                $this->lastInvoiceQR = ['data' => '', 'image' => null, 'atcud' => ''];
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Venda concluída! Fatura: ' . $invoice->invoice_number . ' | Troco: ' . number_format($this->change, 2) . ' Kz'
            ]);

            // Limpar carrinho e resetar
            $this->showPaymentModal = false;
            $this->clearCart();
            
            // Manter cliente como Consumidor Final
            $this->selectedClient = Client::where('tenant_id', activeTenantId())
                ->where('nif', '999999999')
                ->first();
            
            $this->amountReceived = 0;
            $this->notes = '';
            $this->change = 0;
            $this->discount = 0;
            
            // Abrir modal de impressão
            $this->showPrintModal = true;

        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Erro ao concluir venda POS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao concluir venda: ' . $e->getMessage()
            ]);
        }
    }

    public function openPaymentModal()
    {
        if ($this->cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Carrinho vazio! Adicione produtos primeiro.'
            ]);
            return;
        }
        
        if (!$this->selectedClient) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ Selecione um cliente primeiro!'
            ]);
            return;
        }
        
        $this->showPaymentModal = true;
        $this->amountReceived = $this->cartTotal;
        $this->calculateChange();
    }
    
    private function createTreasuryTransaction($invoice)
    {
        // Mapear métodos de pagamento para categorias
        $paymentMethodMap = [
            'cash' => 'cash',
            'transfer' => 'bank_transfer',
            'multicaixa' => 'card',
            'tpa' => 'card',
            'mbway' => 'digital_payment',
        ];

        $category = $paymentMethodMap[$this->paymentMethod] ?? 'cash';

        // Buscar payment method do Treasury (se existir)
        $treasuryPaymentMethod = TreasuryPaymentMethod::where('tenant_id', activeTenantId())
            ->where('code', $this->paymentMethod)
            ->first();

        // Para pagamento em dinheiro, buscar caixa ativa
        $cashRegisterId = null;
        if ($this->paymentMethod === 'cash') {
            $activeCashRegister = CashRegister::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('status', 'open')
                ->first();
            
            if ($activeCashRegister) {
                $cashRegisterId = $activeCashRegister->id;
            }
        }

        // Criar transação de entrada (receita)
        Transaction::create([
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'cash_register_id' => $cashRegisterId,
            'payment_method_id' => $treasuryPaymentMethod?->id,
            'invoice_id' => $invoice->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type' => 'income',
            'category' => $category,
            'amount' => $invoice->total,
            'currency' => 'AOA',
            'transaction_date' => now(),
            'reference' => $invoice->invoice_number,
            'description' => 'Venda POS - Fatura: ' . $invoice->invoice_number . ' - Cliente: ' . $invoice->client->name,
            'notes' => $this->notes,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);

        // Se for pagamento em dinheiro e houver caixa ativo, atualizar saldo
        if ($cashRegisterId) {
            $activeCashRegister->current_balance += $invoice->total;
            $activeCashRegister->save();
        }
    }

    public function render()
    {
        // Produtos com stock disponível
        $productsQuery = Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0); // Mostrar apenas com stock

        if ($this->search) {
            $productsQuery->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->selectedCategory) {
            $productsQuery->where('category_id', $this->selectedCategory);
        }

        $products = $productsQuery->limit(50)->get();

        // Categorias
        $categories = Category::where('tenant_id', activeTenantId())
            ->withCount('products')
            ->get();

        // Clientes para modal
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchClient . '%');
            });
        }
        
        $clients = $clientsQuery->limit(10)->get();

        return view('livewire.pos.possystem', [
            'products' => $products,
            'categories' => $categories,
            'clients' => $clients,
        ]);
    }
}

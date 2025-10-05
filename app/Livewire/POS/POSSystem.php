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
    public $search = '';
    public $selectedCategory = null;
    public $selectedClient = null;
    public $searchClient = '';
    public $showClientModal = false;
    public $showPaymentModal = false;
    public $showPrintModal = false;
    public $lastInvoice = null;
    
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
    public $cartDiscount = 0;
    public $cartQuantity = 0;
    public $change = 0;
    
    // Quick amounts (trocos rápidos)
    public $quickAmounts = [1000, 2000, 5000, 10000, 20000, 50000, 100000];

    public function mount()
    {
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
        
        // Garantir que desconto seja numérico
        $discount = is_numeric($this->discount) ? floatval($this->discount) : 0;
        
        // Calcular desconto
        if ($this->discountType === 'percentage') {
            $this->cartDiscount = ($this->cartSubtotal * $discount) / 100;
        } else {
            $this->cartDiscount = $discount;
        }
        
        // Calcular IVA (14%)
        $subtotalAfterDiscount = $this->cartSubtotal - $this->cartDiscount;
        $this->cartTax = $subtotalAfterDiscount * 0.14;
        
        // Total final
        $this->cartTotal = $subtotalAfterDiscount + $this->cartTax;
        
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
        
        // Validar stock disponível
        if ($newQuantity > $product->stock_quantity) {
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
        
        // Validar stock disponível
        if ($quantity > $product->stock_quantity) {
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
        $product = Product::find($itemId);
        $cartItem = Cart::session(auth()->id())->get($itemId);
        
        if (!$product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Produto não encontrado!'
            ]);
            return;
        }
        
        $newQuantity = $cartItem->quantity + 1;
        
        // Validar stock disponível
        if ($newQuantity > $product->stock_quantity) {
            // Disparar som de erro
            $this->dispatch('stock-error');
            
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ Stock máximo atingido! Disponível: ' . $product->stock_quantity . ' un'
            ]);
            return;
        }
        
        Cart::session(auth()->id())->update($itemId, [
            'quantity' => 1
        ]);
        $this->loadCart();
        
        // Disparar evento para tocar som
        $this->dispatch('cart-updated', ['action' => 'add']);
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => '📈 Quantidade: ' . $newQuantity . '/' . $product->stock_quantity . ' un'
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
                
                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotal,
                    'total' => $subtotal + $taxAmount,
                ]);

                // Atualizar stock
                $product = Product::find($item->id);
                if ($product) {
                    $product->stock_quantity -= $item->quantity;
                    $product->save();
                }
            }

            // Criar transação no Treasury (Fatura-Recibo)
            $this->createTreasuryTransaction($invoice);

            DB::commit();

            // Guardar fatura para impressão
            $this->lastInvoice = $invoice->load(['client', 'items.product']);

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

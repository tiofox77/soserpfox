<?php

namespace App\Livewire\POS;

use App\Models\Product;
use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Category;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
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
    
    // Pagamento
    public $paymentMethod = 'cash';
    public $amountReceived = 0;
    public $notes = '';
    
    // Calculados
    public $cartItems = [];
    public $cartTotal = 0;
    public $cartQuantity = 0;
    public $change = 0;

    public function mount()
    {
        // Não limpar carrinho automaticamente - apenas carregar
        $this->loadCart();
    }

    public function loadCart()
    {
        $this->cartItems = Cart::session(auth()->id())->getContent();
        $this->cartTotal = Cart::session(auth()->id())->getTotal();
        $this->cartQuantity = Cart::session(auth()->id())->getTotalQuantity();
        $this->calculateChange();
    }

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        
        if (!$product || $product->stock <= 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Produto sem stock disponível!'
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
                'tax_rate' => $product->tax_rate ?? 0,
            ]
        ]);

        $this->loadCart();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Produto adicionado ao carrinho!'
        ]);
    }

    public function updateQuantity($itemId, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($itemId);
            return;
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
        Cart::session(auth()->id())->update($itemId, [
            'quantity' => 1
        ]);
        $this->loadCart();
    }

    public function decreaseQuantity($itemId)
    {
        $item = Cart::session(auth()->id())->get($itemId);
        if ($item->quantity > 1) {
            Cart::session(auth()->id())->update($itemId, [
                'quantity' => -1
            ]);
        } else {
            $this->removeFromCart($itemId);
        }
        $this->loadCart();
    }

    public function removeFromCart($itemId)
    {
        Cart::session(auth()->id())->remove($itemId);
        $this->loadCart();
        
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
        $this->calculateChange();
    }

    public function calculateChange()
    {
        $this->change = $this->amountReceived - $this->cartTotal;
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
            // Preparar dados dos itens para cálculo
            $items = [];
            foreach ($this->cartItems as $item) {
                $items[] = [
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percentage' => 0,
                    'tax_rate' => $item->attributes->tax_rate ?? 14,
                    'is_service' => false, // Assume produto, ajustar se necessário
                ];
            }

            // Usar helper de cálculos AGT
            $calculations = \App\Helpers\InvoiceCalculationHelper::calculateInvoiceTotals(
                $items,
                0, // commercial_discount
                0  // financial_discount
            );

            // Criar fatura usando tabela existente
            $invoice = SalesInvoice::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $this->selectedClient->id,
                'invoice_date' => now(),
                'due_date' => now(),
                'status' => 'paid',
                'subtotal' => $calculations['gross_total'],
                'tax_amount' => $calculations['total_iva'],
                'discount_amount' => 0,
                'total' => $calculations['total_payable'],
                'paid_amount' => $this->amountReceived,
                'notes' => $this->notes,
                'payment_method' => $this->paymentMethod,
                'created_by' => auth()->id(),
            ]);

            // Adicionar itens
            $index = 0;
            foreach ($this->cartItems as $item) {
                $itemCalc = $items[$index];
                
                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percentage' => 0,
                    'tax_rate' => $item->attributes->tax_rate ?? 14,
                    'tax_amount' => ($item->price * $item->quantity) * (($item->attributes->tax_rate ?? 14) / 100),
                    'subtotal' => $item->price * $item->quantity,
                ]);

                // Atualizar stock
                $product = Product::find($item->id);
                if ($product) {
                    $product->stock -= $item->quantity;
                    $product->save();
                }
                
                $index++;
            }

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Venda concluída! Fatura: ' . $invoice->invoice_number . ' | Troco: ' . number_format($this->change, 2) . ' Kz'
            ]);

            // Limpar carrinho e resetar
            $this->clearCart();
            $this->selectedClient = null;
            $this->amountReceived = 0;
            $this->notes = '';
            $this->change = 0;

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

    public function render()
    {
        // Produtos
        $productsQuery = Product::where('tenant_id', activeTenantId())
            ->where('is_active', true);

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

        return view('livewire.p-o-s.p-o-s-system', [
            'products' => $products,
            'categories' => $categories,
            'clients' => $clients,
        ]);
    }
}

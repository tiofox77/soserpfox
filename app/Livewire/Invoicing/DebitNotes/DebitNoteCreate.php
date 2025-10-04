<?php

namespace App\Livewire\Invoicing\DebitNotes;

use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\DebitNoteItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Client;
use App\Models\Product;
use App\Helpers\InvoiceCalculationHelper;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Nova Nota de Débito')]
class DebitNoteCreate extends Component
{
    public $debitNoteId = null;
    public $isEdit = false;
    
    // Campos básicos
    public $client_id = '';
    public $invoice_id = '';
    public $issue_date;
    public $due_date;
    public $reason = 'interest';
    public $notes = '';
    
    // Cart
    public $cartInstance;
    public $showProductModal = false;
    public $searchProduct = '';
    
    // Search
    public $searchClient = '';

    protected function rules()
    {
        return [
            'client_id' => 'required',
            'issue_date' => 'required|date',
            'reason' => 'required',
        ];
    }

    public function mount($id = null)
    {
        $this->issue_date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
        $this->cartInstance = 'debit_note_' . uniqid();
        
        if ($id) {
            $this->isEdit = true;
            $this->debitNoteId = $id;
        }
    }

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->invoice_id = '';
    }

    public function updatedInvoiceId($value)
    {
        if ($value) {
            $this->loadInvoiceItems($value);
        }
    }

    public function loadInvoiceItems($invoiceId)
    {
        $invoice = SalesInvoice::with('items.product')->findOrFail($invoiceId);
        
        // Limpar carrinho atual
        Cart::session($this->cartInstance)->clear();
        
        // Adicionar todos os items da fatura ao carrinho
        foreach ($invoice->items as $index => $item) {
            // Usar um ID único para cada item (product_id ou índice)
            $itemId = $item->product_id ?? 'item_' . $index;
            
            Cart::session($this->cartInstance)->add([
                'id' => $itemId,
                'name' => $item->description ?? 'Produto',
                'price' => $item->unit_price ?? 0,
                'quantity' => $item->quantity ?? 1,
                'attributes' => [
                    'tax_rate' => $item->tax_rate ?? 14,
                    'discount_percent' => $item->discount_percent ?? 0,
                ]
            ]);
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => count($invoice->items) . ' produtos carregados da fatura!'
        ]);
    }

    public function addProduct($productId)
    {
        $product = Product::with('taxRate')->findOrFail($productId);
        
        Cart::session($this->cartInstance)->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'attributes' => [
                'tax_rate' => $product->taxRate->rate ?? 14,
                'discount_percent' => 0,
            ]
        ]);

        $this->showProductModal = false;
        $this->searchProduct = '';
    }

    public function removeItem($itemId)
    {
        Cart::session($this->cartInstance)->remove($itemId);
    }

    public function updateQuantity($itemId, $quantity)
    {
        if ($quantity > 0) {
            Cart::session($this->cartInstance)->update($itemId, ['quantity' => $quantity]);
        }
    }

    public function save()
    {
        $this->validate();

        $cartItems = Cart::session($this->cartInstance)->getContent();
        
        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Adicione pelo menos um item à nota de débito.'
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            // Calcular totais
            $totals = InvoiceCalculationHelper::calculateTotals(
                $cartItems,
                0, 0, 0, false
            );

            // Criar nota de débito
            $debitNote = DebitNote::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $this->client_id,
                'invoice_id' => $this->invoice_id ?: null,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'subtotal' => $totals['subtotal_original'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'status' => 'issued',
                'created_by' => auth()->id(),
            ]);

            // Criar items
            foreach ($cartItems as $item) {
                $itemTotals = InvoiceCalculationHelper::calculateItemTotals(
                    $item->price,
                    $item->quantity,
                    $item->attributes['discount_percent'],
                    $item->attributes['tax_rate']
                );

                DebitNoteItem::create([
                    'debit_note_id' => $debitNote->id,
                    'product_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => 'un',
                    'unit_price' => $item->price,
                    'discount_percent' => $item->attributes['discount_percent'],
                    'discount_amount' => $itemTotals['discount_amount'],
                    'subtotal' => $itemTotals['subtotal'],
                    'tax_rate' => $item->attributes['tax_rate'],
                    'tax_amount' => $itemTotals['tax_amount'],
                    'total' => $itemTotals['total'],
                ]);
            }

            Cart::session($this->cartInstance)->clear();
            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Nota de Débito criada com sucesso!'
            ]);

            return redirect()->route('invoicing.debit-notes.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao criar nota de débito: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        // Buscar clientes
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchClient . '%');
            });
        } elseif ($this->client_id) {
            $clientsQuery->where('id', $this->client_id);
        }
        
        $clients = $clientsQuery->orderBy('name')->limit(50)->get();

        // Buscar faturas do cliente
        $invoices = collect();
        if ($this->client_id) {
            $invoices = SalesInvoice::where('tenant_id', activeTenantId())
                ->where('client_id', $this->client_id)
                ->whereIn('status', ['pending', 'partially_paid', 'paid'])
                ->orderBy('invoice_date', 'desc')
                ->get();
        }

        // Buscar produtos
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

            $products = $query->orderBy('name')->limit(50)->get();
        }

        $cartItems = Cart::session($this->cartInstance)->getContent();

        // Calcular totais
        $totals = InvoiceCalculationHelper::calculateTotals(
            $cartItems,
            0, 0, 0, false
        );

        return view('livewire.invoicing.debit-notes.debit-note-create', array_merge([
            'clients' => $clients,
            'invoices' => $invoices,
            'products' => $products,
            'cartItems' => $cartItems,
        ], $totals));
    }
}

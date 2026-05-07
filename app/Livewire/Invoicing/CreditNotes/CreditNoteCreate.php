<?php

namespace App\Livewire\Invoicing\CreditNotes;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\CreditNoteItem;
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
#[Title('Nova Nota de Crédito')]
class CreditNoteCreate extends Component
{
    public $creditNoteId = null;
    public $isEdit = false;
    
    // Campos básicos
    public $client_id = '';
    public $invoice_id = '';
    public $issue_date;
    public $reason = 'return';
    public $type = 'partial';
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
            'type' => 'required',
        ];
    }

    public function mount($id = null)
    {
        $this->issue_date = date('Y-m-d');
        $this->cartInstance = 'credit_note_' . uniqid();
        
        if ($id) {
            $this->isEdit = true;
            $this->creditNoteId = $id;
        }
    }

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->invoice_id = ''; // Reset fatura ao mudar cliente
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
        
        // Adicionar todos os items da fatura ao carrinho — preservar referência AGT (NC obrig.)
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
                    // AGT v1.2 — referenceInfo da linha original
                    'reference_invoice_no'   => $invoice->invoice_number,
                    'reference_item_line_no' => $item->order ?? ($index + 1),
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
                'message' => 'Adicione pelo menos um item à nota de crédito.'
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            // Calcular totais
            $totals = InvoiceCalculationHelper::calculateTotals(
                $cartItems,
                0, // sem desconto comercial
                0, // sem desconto valor
                0, // sem desconto financeiro
                false // não é serviço
            );

            // Expressão obrigatória conforme Art. 12º RJF (Decreto 71/25)
            $reasonExpression = ($this->type === 'total') ? 'Anulação' : 'Rectificação';

            // Criar nota de crédito
            // Campos SAFT-AO obrigatórios (Decreto 71/25)
            $netTotal = $totals['subtotal_original'];
            $taxPayable = $totals['tax_amount'];
            $grossTotal = $netTotal + $taxPayable;

            $creditNote = CreditNote::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $this->client_id,
                'invoice_id' => $this->invoice_id ?: null,
                'issue_date' => $this->issue_date,
                'system_entry_date' => now(),
                'reason' => $this->reason,
                'reason_text' => $reasonExpression,
                'type' => $this->type,
                'notes' => $this->notes,
                'subtotal' => $totals['subtotal_original'],
                'net_total' => $netTotal,
                'tax_amount' => $totals['tax_amount'],
                'tax_payable' => $taxPayable,
                'total' => $totals['total'],
                'gross_total' => $grossTotal,
                'status' => 'issued',
                'invoice_status' => 'F',
                'source_billing' => 'P',
                'created_by' => auth()->id(),
            ]);

            // Gerar HASH SAFT-AO conforme Decreto 71/25 (usar gross_total)
            $previousCN = CreditNote::where('tenant_id', activeTenantId())
                ->where('id', '<', $creditNote->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();

            $hash = \App\Helpers\SAFTHelper::generateHash(
                $creditNote->issue_date->format('Y-m-d'),
                $creditNote->system_entry_date->format('Y-m-d H:i:s'),
                $creditNote->credit_note_number,
                $creditNote->gross_total,
                $previousCN->saft_hash ?? null
            );

            if ($hash) {
                $creditNote->update([
                    'saft_hash' => $hash,
                    'hash' => $hash,
                    'hash_previous' => $previousCN->saft_hash ?? '',
                    'hash_control' => '1',
                ]);
            }

            // Criar items
            $orderNo = 0;
            foreach ($cartItems as $item) {
                $orderNo++;
                $itemTotals = InvoiceCalculationHelper::calculateItemTotals(
                    $item->price,
                    $item->quantity,
                    $item->attributes['discount_percent'],
                    $item->attributes['tax_rate']
                );

                $netLine = $itemTotals['subtotal'];

                CreditNoteItem::create([
                    'credit_note_id' => $creditNote->id,
                    'product_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => 'un',
                    'unit_price' => $item->price,
                    'unit_price_base' => $item->price,
                    'discount_percent' => $item->attributes['discount_percent'],
                    'discount_amount' => $itemTotals['discount_amount'],
                    'subtotal' => $netLine,
                    'tax_rate' => $item->attributes['tax_rate'],
                    'tax_amount' => $itemTotals['tax_amount'],
                    'total' => $itemTotals['total'],
                    'order' => $orderNo,
                    // AGT v1.2 — line-level
                    'debit_amount'         => $netLine,
                    'credit_amount'        => 0,
                    'settlement_amount'    => $itemTotals['discount_amount'] ?? 0,
                    'tax_country_region'   => 'AO',
                    'tax_code'             => ($item->attributes['tax_rate'] ?? 14) > 0 ? 'NOR' : 'ISE',
                    // referenceInfo (NC obrigatório)
                    'reference_invoice_no'   => $item->attributes['reference_invoice_no']   ?? null,
                    'reference_item_line_no' => $item->attributes['reference_item_line_no'] ?? $orderNo,
                    'reference_reason'       => $reasonExpression,
                ]);
            }

            // Limpar carrinho
            Cart::session($this->cartInstance)->clear();

            DB::commit();

            // Auto-submeter à AGT se habilitado
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
            if (!empty($settings->agt_auto_submit)) {
                $agtResult = $creditNote->submitToAGT();
                if ($agtResult['success'] ?? false) {
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => 'NC criada e submetida à AGT (requestID: ' . ($agtResult['requestID'] ?? '—') . ')',
                    ]);
                } else {
                    $this->dispatch('notify', [
                        'type' => 'warning',
                        'message' => 'NC criada mas erro ao submeter à AGT: ' . ($agtResult['error'] ?? 'desconhecido'),
                    ]);
                }
            } else {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Nota de Crédito criada com sucesso!'
                ]);
            }

            return redirect()->route('invoicing.credit-notes.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao criar nota de crédito: ' . $e->getMessage()
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

        return view('livewire.invoicing.credit-notes.credit-note-create', array_merge([
            'clients' => $clients,
            'invoices' => $invoices,
            'products' => $products,
            'cartItems' => $cartItems,
        ], $totals));
    }
}

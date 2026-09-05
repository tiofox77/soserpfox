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
    // Uma nota nasce de uma factura: se a factura não é sua, a nota também não.
    use \App\Traits\EscopoDeAutor;

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

    public function mount($id = null, $invoice = null)
    {
        $this->issue_date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
        $this->cartInstance = 'debit_note_' . uniqid();
        
        if ($id) {
            $this->isEdit = true;
            $this->debitNoteId = $id;
            return;
        }

        // Pré-seleccionar a fatura quando se vem do botão "Nota de Débito" da
        // lista de faturas (?invoice=123) — scoped ao tenant activo.
        $invoiceId = $invoice ?: request()->query('invoice');
        if ($invoiceId) {
            $invoice = SalesInvoice::where("tenant_id", activeTenantId())->tap(fn ($q) => $this->escoparAoAutor($q))->find($invoiceId);
            if ($invoice) {
                $this->client_id = $invoice->client_id;
                $this->invoice_id = $invoice->id;
                $this->loadInvoiceItems($invoice->id);
            }
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
        // Scoped ao tenant (evita carregar fatura de outra empresa pelo id)
        $invoice = SalesInvoice::where("tenant_id", activeTenantId())
            ->with('items.product')
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->with('items.product')
            ->findOrFail($invoiceId);
        
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
                    // Herdar o imposto da linha ORIGINAL (nunca 14 hardcoded).
                    'tax_rate' => $item->tax_rate ?? 0,
                    'tax_type' => ($item->tax_rate ?? 0) > 0 ? 'iva' : 'isento',
                    'exemption_reason' => $item->tax_exemption_code ?? null,
                    'discount_percent' => $item->discount_percent ?? 0,
                    // Região e código SAFT da linha original: fixá-los depois em
                    // AO/NOR fazia uma ND sobre factura de Cabinda ou a 7% sair
                    // declarada como continental à taxa normal.
                    'tax_code'           => $item->tax_code ?? null,
                    'tax_country_region' => $item->tax_country_region ?? 'AO',
                    // referenceInfo: a ND é documento rectificativo e tem de
                    // apontar o documento e a linha que corrige.
                    'reference_invoice_no'   => $invoice->invoice_number,
                    'reference_item_line_no' => $index + 1,
                    // Origem, para copiar o IEC/IS da linha rectificada
                    'origem_line_type' => get_class($item),
                    'origem_line_id'   => $item->id,
                ]
            ]);
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => trans_choice(
                ':n produto carregado da fatura!|:n produtos carregados da fatura!',
                count($invoice->items),
                ['n' => count($invoice->items)]
            ),
        ]);
    }

    public function addProduct($productId)
    {
        // Scope à empresa: o id vem do browser. Sem o filtro, uma chamada
        // Livewire forjada punha o nome, o preço e o imposto de um artigo de
        // OUTRA empresa dentro de um documento fiscal desta — assinado,
        // encadeado e comunicado à AGT.
        $product = Product::with('taxRate')
            ->where('tenant_id', activeTenantId())
            ->find($productId);

        if (!$product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Produto não encontrado nesta empresa.'),
            ]);
            return;
        }

        // Resolver UMA vez: eram três chamadas idênticas ao resolvedor por
        // cada produto adicionado.
        $tx = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());

        Cart::session($this->cartInstance)->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'attributes' => [
                // Fonte única (regime + produto): antes aplicava 14% mesmo a
                // produtos isentos, ignorando tax_type.
                'tax_rate' => $tx['rate'],
                'tax_type' => $tx['type'],
                'exemption_reason' => $tx['exemption_code'],
                // O código SAFT tem de acompanhar a taxa: com 'NOR' fixo, uma
                // taxa reduzida (7%) ou intermédia (5%) era declarada à AGT
                // como taxa normal.
                'tax_code' => $tx['tax_code'],
                // Região fiscal do ADQUIRENTE. Fixar 'AO' declarava como
                // continental uma nota a cliente de Cabinda, que tem regime
                // próprio (AO-CAB).
                'tax_country_region' => \App\Services\Invoicing\TaxResolver::regionForClient(
                    \App\Models\Client::where('tenant_id', activeTenantId())->find($this->client_id)
                ),
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

    /**
     * A QUANTIDADE ESCRITA SUBSTITUI. NÃO SOMA.
     *
     * O carrinho trata uma quantidade escalar como RELATIVA — acrescenta em vez
     * de trocar. Nas notas de crédito isso fez sair uma nota a anular o dobro do
     * que a factura tinha, e a AGT recusou-a. A nota de débito partilha o mesmo
     * ecrã e o mesmo erro; corrige-se junto, antes de acontecer também aqui.
     */
    public function updateQuantity($itemId, $quantity)
    {
        if ($quantity > 0) {
            Cart::session($this->cartInstance)->update($itemId, [
                'quantity' => [
                    'relative' => false,
                    'value'    => $quantity,
                ],
            ]);
        }
    }

    public function save()
    {
        $this->validate();

        $cartItems = Cart::session($this->cartInstance)->getContent();
        
        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Adicione pelo menos um item à nota de débito.')
            ]);
            return;
        }

        /*
         * TUDO O QUE É FISCAL VIVE NO `EmissorDeNotas` — ver a nota de crédito.
         */
        try {
            $emitido = app(\App\Services\Invoicing\EmissorDeNotas::class)->emitirDebito([
                'client_id' => $this->client_id,
                'invoice_id' => $this->invoice_id ?: null,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
            ], collect($cartItems->values()->all()));
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao criar nota de débito: :erro', ['erro' => $e->getMessage()])
            ]);

            return;
        }

        Cart::session($this->cartInstance)->clear();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $emitido['fila']['enfileirado']
                ? __('Nota de Débito criada — a comunicar à AGT.')
                : __('Nota de Débito criada com sucesso!'),
        ]);

        return redirect()->route('invoicing.debit-notes.index');
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
                // Escolher a factura de origem numa lista com as dos colegas
                // era vê-las — número, data e valor.
                ->tap(fn ($q) => $this->escoparAoAutor($q))
                ->where('client_id', $this->client_id)
                // O AVESSO: fica de fora o que ainda não existe (rascunho) e o
                // que já não existe (anulada). Nomear os estados que entram
                // deixava de fora as `sent` — o estado normal de uma factura
                // emitida e por pagar — e as `overdue`.
                ->whereNotIn('status', ['draft', 'cancelled'])
                // E a factura que veio no endereço entra sempre, aconteça o que
                // acontecer: sem a opção no <select>, o Livewire devolvia vazio
                // e a nota ficava sem referência à factura.
                ->when($this->invoice_id, fn ($q) => $q->orWhere('id', $this->invoice_id))
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

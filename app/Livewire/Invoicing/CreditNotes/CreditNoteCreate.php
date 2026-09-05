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
    // Uma nota nasce de uma factura: se a factura não é sua, a nota também não.
    use \App\Traits\EscopoDeAutor;

    public $creditNoteId = null;
    public $isEdit = false;
    
    /**
     * O número da factura que se tentou creditar e já não tem nada por anular.
     *
     * Vazio é o caso normal. Quando tem valor, o ecrã abre a dizer porque é
     * que não pré-encheu nada, em vez de abrir vazio sem explicação.
     */
    public $semSaldoPorAnular = '';

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

    public function mount($id = null, $invoice = null)
    {
        $this->issue_date = date('Y-m-d');
        $this->cartInstance = 'credit_note_' . uniqid();
        
        if ($id) {
            $this->isEdit = true;
            $this->creditNoteId = $id;
            return;
        }

        // Pré-seleccionar a fatura quando se vem do botão "Nota de Crédito" da
        // lista de faturas (?invoice=123) — scoped ao tenant activo.
        $invoiceId = $invoice ?: request()->query('invoice');
        if ($invoiceId) {
            $invoice = SalesInvoice::where("tenant_id", activeTenantId())->tap(fn ($q) => $this->escoparAoAutor($q))->find($invoiceId);

            /*
             * UMA FACTURA JÁ INTEIRAMENTE ANULADA NÃO ENTRA.
             *
             * O botão da lista já não a oferece, mas o endereço continua a
             * poder ser escrito à mão — e a `?invoice=` é o que a preenchia
             * toda. Diz-se aqui, e não no fim: descobrir que não havia saldo
             * depois de a nota estar preenchida é o pior sítio para o saber.
             */
            if ($invoice && $invoice->jaTotalmenteCreditada()) {
                $this->semSaldoPorAnular = $invoice->invoice_number;

                return;
            }

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
        // Scoped ao tenant: sem isto era possível carregar as linhas de uma
        // fatura de OUTRA empresa passando o id.
        $invoice = SalesInvoice::where("tenant_id", activeTenantId())
            ->with('items.product')
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->with('items.product')
            ->findOrFail($invoiceId);
        
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
                    // Herdar o imposto da linha ORIGINAL (nunca 14 hardcoded): uma
                    // nota de crédito de fatura isenta tem de sair isenta.
                    'tax_rate' => $item->tax_rate ?? 0,
                    'tax_type' => ($item->tax_rate ?? 0) > 0 ? 'iva' : 'isento',
                    'exemption_reason' => $item->tax_exemption_code ?? null,
                    'discount_percent' => $item->discount_percent ?? 0,
                    // Herdar TAMBÉM o código SAFT e a região da linha original:
                    // creditar uma factura a 7% (RED) ou de Cabinda (AO-CAB) tem
                    // de reproduzir esses valores, senão o crédito declara à AGT
                    // um imposto diferente do que foi liquidado.
                    'tax_code'           => $item->tax_code ?? null,
                    'tax_country_region' => $item->tax_country_region ?? 'AO',
                    // Impostos extra (IEC/IS) da linha original, para o crédito
                    // reverter o documento por inteiro e não só o IVA.
                    'origem_line_type' => get_class($item),
                    'origem_line_id'   => $item->id,
                    // AGT v1.2 — referenceInfo da linha original
                    'reference_invoice_no'   => $invoice->invoice_number,
                    'reference_item_line_no' => $item->order ?? ($index + 1),
                ]
            ]);
        }

        // Plural a sério: "1 produtos carregados" não existe em língua nenhuma.
        $quantosItens = count($invoice->items);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => trans_choice(
                ':n produto carregado da fatura!|:n produtos carregados da fatura!',
                $quantosItens,
                ['n' => $quantosItens]
            ),
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
                // Fonte única (regime + produto): antes aplicava 14% mesmo a
                // produtos isentos, ignorando tax_type.
                'tax_rate' => \App\Services\Invoicing\TaxResolver::forProduct($product)['rate'],
                'tax_type' => \App\Services\Invoicing\TaxResolver::forProduct($product)['type'],
                'exemption_reason' => \App\Services\Invoicing\TaxResolver::forProduct($product)['exemption_code'],
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
     * O carrinho trata uma quantidade escalar como RELATIVA: `update($id,
     * ['quantity' => 35])` ACRESCENTA 35 ao que já lá está. A linha entrava com
     * as unidades da factura, quem corrigia o número via-o somar-se ao antigo,
     * e a nota saía a anular mais do que se tinha vendido.
     *
     * Aconteceu a sério: uma factura com 34 almoços gerou uma nota com 69 (34
     * mais os 35 escritos), e a AGT recusou-a com E43 — «excede o montante
     * ainda não anulado do documento base». As facturas, as proformas e o POS
     * sempre passaram `relative => false`; foram só as notas que ficaram de fora.
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
                'message' => __('Adicione pelo menos um item à nota de crédito.')
            ]);
            return;
        }

        /*
         * TUDO O QUE É FISCAL VIVE NO `EmissorDeNotas`: o travão do E43 (pelo
         * total e por linha), os campos SAFT, o IEC/IS herdado da linha
         * original, o hash encadeado e a fila da AGT. Este ecrã e o ecrã em
         * React chamam o mesmo — foi por isso que saiu daqui.
         */
        try {
            $emitido = app(\App\Services\Invoicing\EmissorDeNotas::class)->emitirCredito([
                'client_id' => $this->client_id,
                'invoice_id' => $this->invoice_id ?: null,
                'issue_date' => $this->issue_date,
                'reason' => $this->reason,
                'type' => $this->type,
                'notes' => $this->notes,
            ], collect($cartItems->values()->all()));
        } catch (\DomainException $e) {
            // O travão falou: a nota não nasceu, e diz-se porquê.
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao criar nota de crédito: :erro', ['erro' => $e->getMessage()])
            ]);

            return;
        }

        Cart::session($this->cartInstance)->clear();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $emitido['fila']['enfileirado']
                ? __('Nota de Crédito criada — a comunicar à AGT.')
                : __('Nota de Crédito criada com sucesso!'),
        ]);

        return redirect()->route('invoicing.credit-notes.index');
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
                // E o que já foi anulado por inteiro também sai da lista: não
                // se oferece o que a AGT recusaria com E43. Pelo SALDO, que uma
                // factura creditada em parte continua a dar para o resto.
                ->comCreditado()
                // E a factura que veio no endereço entra sempre, aconteça o que
                // acontecer: sem a opção no <select>, o Livewire devolvia vazio
                // e a nota ficava sem referência à factura.
                ->when($this->invoice_id, fn ($q) => $q->orWhere('id', $this->invoice_id))
                ->orderBy('invoice_date', 'desc')
                ->get()
                // O corte fica em PHP e não em SQL: filtrar por uma soma exige
                // HAVING, e o HAVING sobre coluna não agrupada passa aqui e
                // rebenta em produção com ONLY_FULL_GROUP_BY. A lista é de um
                // cliente só, já veio inteira.
                ->filter(fn ($f) => (int) $f->id === (int) $this->invoice_id
                    || ! $f->jaTotalmenteCreditada($this->creditNoteId ?: null))
                ->values();
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

<?php

namespace App\Livewire\Invoicing\Receipts;

use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Client;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Novo Recibo')]
class ReceiptCreate extends Component
{
    // Editar por URL o documento de um colega é vê-lo por inteiro.
    use \App\Traits\EscopoDeAutor;

    public $receiptId = null;
    public $isEdit = false;
    
    // Campos do formulário
    public $type = 'sale';
    public $client_id = '';
    public $supplier_id = '';
    public $invoice_id = '';
    public $payment_date;
    public $payment_method = 'cash';
    public $amount_paid = 0;
    public $reference = '';
    public $notes = '';
    
    // Search
    public $searchClient = '';
    public $searchSupplier = '';
    
    protected function rules()
    {
        return [
            'type' => 'required|in:sale,purchase',
            'client_id' => 'required_if:type,sale',
            'supplier_id' => 'required_if:type,purchase',
            'payment_date' => 'required|date',
            'payment_method' => 'required',
            'amount_paid' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * Método e não propriedade: as mensagens passam por __() e uma propriedade
     * só aceita valores constantes. O Livewire prefere messages() à propriedade
     * quando as duas existem, e assim a mensagem é traduzida no momento em que
     * a validação corre — não uma vez, quando a classe é carregada.
     */
    protected function messages()
    {
        return [
            'client_id.required_if' => __('O cliente é obrigatório para recibos de venda.'),
            'supplier_id.required_if' => __('O fornecedor é obrigatório para recibos de compra.'),
            'amount_paid.required' => __('O valor pago é obrigatório.'),
            'amount_paid.min' => __('O valor deve ser maior que zero.'),
        ];
    }

    public function mount($id = null, $invoice = null)
    {
        $this->payment_date = date('Y-m-d');

        if ($id) {
            $this->isEdit = true;
            $this->receiptId = $id;
            $this->loadReceipt($id);

            return;
        }

        // Vindo com a factura no endereço (?invoice=123), fica tudo escolhido:
        // o cliente, a factura e o valor que falta receber — que é o que a
        // caixa recebe quase sempre. Scoped ao tenant: o id vem do browser.
        $invoiceId = $invoice ?: request()->query('invoice');

        if (! $invoiceId) {
            return;
        }

        $factura = SalesInvoice::where('tenant_id', activeTenantId())->find($invoiceId);

        if (! $factura) {
            return;
        }

        $this->type        = 'sale';
        $this->client_id   = $factura->client_id;
        $this->invoice_id  = $factura->id;
        $this->amount_paid = max(0, round((float) $factura->total - (float) $factura->paid_amount, 2));
    }

    public function loadReceipt($id)
    {
        $receipt = Receipt::where('tenant_id', activeTenantId())
            ->tap(fn ($q) => $this->escoparAoAutor($q))
            ->findOrFail($id);

        $this->type = $receipt->type;
        $this->client_id = $receipt->client_id;
        $this->supplier_id = $receipt->supplier_id;
        $this->invoice_id = $receipt->invoice_id;
        $this->payment_date = $receipt->payment_date->format('Y-m-d');
        $this->payment_method = $receipt->payment_method;
        $this->amount_paid = $receipt->amount_paid;
        $this->reference = $receipt->reference;
        $this->notes = $receipt->notes;
    }

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->invoice_id = ''; // Reset invoice quando muda cliente
    }

    public function selectSupplier($supplierId)
    {
        $this->supplier_id = $supplierId;
        $this->searchSupplier = '';
        $this->invoice_id = ''; // Reset invoice quando muda fornecedor
    }

    public function updatedType()
    {
        // Reset campos ao mudar tipo
        $this->client_id = '';
        $this->supplier_id = '';
        $this->invoice_id = '';
        $this->searchClient = '';
        $this->searchSupplier = '';
    }

    public function save()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $receipt = Receipt::where('tenant_id', activeTenantId())
                    ->tap(fn ($q) => $this->escoparAoAutor($q))
                    ->findOrFail($this->receiptId);
                
                $receipt->update([
                    'type' => $this->type,
                    'client_id' => $this->type === 'sale' ? $this->client_id : null,
                    'supplier_id' => $this->type === 'purchase' ? $this->supplier_id : null,
                    // Cada tipo na SUA coluna: `invoice_id` tem chave
                    // estrangeira para as facturas de VENDA, e o id de uma
                    // compra escrito ali fazia a base recusar a linha.
                    'invoice_id' => $this->type === 'sale' ? ($this->invoice_id ?: null) : null,
                    'purchase_invoice_id' => $this->type === 'purchase' ? ($this->invoice_id ?: null) : null,
                    'payment_date' => $this->payment_date,
                    'payment_method' => $this->payment_method,
                    'amount_paid' => $this->amount_paid,
                    'reference' => $this->reference,
                    'notes' => $this->notes,
                ]);
            } else {
                $receipt = Receipt::create([
                    'tenant_id' => activeTenantId(),
                    'type' => $this->type,
                    'client_id' => $this->type === 'sale' ? $this->client_id : null,
                    'supplier_id' => $this->type === 'purchase' ? $this->supplier_id : null,
                    // Cada tipo na SUA coluna: `invoice_id` tem chave
                    // estrangeira para as facturas de VENDA, e o id de uma
                    // compra escrito ali fazia a base recusar a linha.
                    'invoice_id' => $this->type === 'sale' ? ($this->invoice_id ?: null) : null,
                    'purchase_invoice_id' => $this->type === 'purchase' ? ($this->invoice_id ?: null) : null,
                    'payment_date' => $this->payment_date,
                    'payment_method' => $this->payment_method,
                    'amount_paid' => $this->amount_paid,
                    'reference' => $this->reference,
                    'notes' => $this->notes,
                    'status' => 'issued',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            // O recibo é documento fiscal (RC/RG) como qualquer outro, e não
            // era enviado à AGT — ficava só na aplicação. Depois do commit: o
            // recibo já está gravado e uma falha da AGT não o pode desfazer.
            // Frase inteira por cada caso, e não "Recibo" + verbo + "com sucesso":
            // montada aos pedaços não há língua nenhuma em que se possa traduzir.
            $mensagem = $this->isEdit
                ? __('Recibo atualizado com sucesso!')
                : __('Recibo criado com sucesso!');

            if (!$this->isEdit && isset($receipt)) {
                $agt = \App\Services\AGT\AutoSubmissao::submeter($receipt);

                if ($agt['enviado']) {
                    $mensagem .= ' ' . __('Submetido à AGT.');
                } elseif ($agt['erro']) {
                    $mensagem .= ' ' . __('Por submeter à AGT: :erro', ['erro' => $agt['erro']]);
                }
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $mensagem,
            ]);

            return redirect()->route('invoicing.receipts.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao guardar recibo: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }

    /**
     * O que falta receber da factura escolhida.
     *
     * Fonte única: o `paid_amount` da factura, que os recibos mantêm a par
     * (ver `SalesInvoice::recalcularPago`).
     *
     * @return array{total: float, pago: float, falta: float}|null
     */
    public function getSaldoDaFacturaProperty(): ?array
    {
        if (! $this->invoice_id) {
            return null;
        }

        $f = $this->type === 'purchase'
            ? PurchaseInvoice::where('tenant_id', activeTenantId())->find($this->invoice_id)
            : SalesInvoice::where('tenant_id', activeTenantId())->find($this->invoice_id);

        if (! $f) {
            return null;
        }

        $total = round((float) $f->total, 2);
        $pago  = round((float) $f->paid_amount, 2);

        return [
            'total' => $total,
            'pago'  => $pago,
            'falta' => max(0, round($total - $pago, 2)),
        ];
    }

    /**
     * Escolher a factura propõe logo o que falta.
     *
     * É o que a caixa recebe nove em cada dez vezes, e escrevê-lo de cabeça é
     * como nascem os enganos.
     */
    public function updatedInvoiceId($valor): void
    {
        $saldo = $this->saldoDaFactura;

        if ($saldo && $saldo['falta'] > 0) {
            $this->amount_paid = $saldo['falta'];
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

        // Buscar fornecedores
        $suppliersQuery = Supplier::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchSupplier) {
            $suppliersQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchSupplier . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchSupplier . '%');
            });
            $suppliersQuery->where('id', $this->supplier_id);
        }
        
        $suppliers = $suppliersQuery->orderBy('name')->limit(50)->get();

        // Buscar faturas do cliente/fornecedor (apenas não pagas ou parcialmente pagas)
        //
        // DE PROPÓSITO SEM A REGRA DO AUTOR (ver App\Traits\EscopoDeAutor):
        // receber um pagamento não é o mesmo que espreitar as vendas de um
        // colega — na prática é o caixa que recebe o que o vendedor facturou,
        // e prender esta lista ao autor partiria a cobrança. A LISTA de
        // recibos, essa, já segue a regra.
        $invoices = collect();
        if ($this->client_id || $this->supplier_id) {
            $query = SalesInvoice::where('tenant_id', activeTenantId());
            
            if ($this->type === 'sale' && $this->client_id) {
                $query->where('client_id', $this->client_id);
            } elseif ($this->type === 'purchase' && $this->supplier_id) {
                // O FORNECEDOR TAMBÉM FILTRA. A consulta era substituída por
                // uma nova e o `supplier_id` ficava pelo caminho: a lista das
                // compras trazia as facturas de todos os fornecedores.
                $query = PurchaseInvoice::where('tenant_id', activeTenantId())
                    ->where('supplier_id', $this->supplier_id);
            }

            /*
             * O QUE AINDA FALTA RECEBER — pelo saldo, não pelo nome do estado.
             *
             * A lista era `pending` e `partially_paid`. Uma factura emitida e
             * por pagar tem estado `sent`, e uma atrasada `overdue`: nenhuma
             * aparecia, e portanto não havia como lhe passar recibo. Medido
             * nesta base: 42 facturas na lista contra 317 com saldo, num total
             * de 15,4 milhões por cobrar.
             *
             * É a mesma regra do painel da facturação e do portal do cliente.
             */
            $invoices = $query
                ->whereNotIn('status', ['draft', 'cancelled', 'credited'])
                ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) > 0.01')
                // E a factura que veio no endereço entra sempre: sem a opção no
                // <select>, o Livewire devolvia vazio e o recibo ficava sem
                // factura nenhuma.
                ->when($this->invoice_id, fn ($q) => $q->orWhere('id', $this->invoice_id))
                ->orderBy('invoice_date', 'desc')
                ->get();
        }

        return view('livewire.invoicing.receipts.create', [
            'clients' => $clients,
            'suppliers' => $suppliers,
            'invoices' => $invoices,
        ]);
    }
}

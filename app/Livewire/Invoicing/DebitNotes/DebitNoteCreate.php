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
            $invoice = SalesInvoice::where('tenant_id', activeTenantId())->find($invoiceId);
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
        $invoice = SalesInvoice::where('tenant_id', activeTenantId())
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
            'message' => count($invoice->items) . ' produtos carregados da fatura!'
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
                'message' => 'Produto não encontrado nesta empresa.',
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

            // Expressão obrigatória conforme Art. 12º RJF (Decreto 71/25):
            // a ND acresce ao documento original, é sempre uma rectificação.
            $reasonExpression = 'Rectificação';

            // Criar nota de débito
            // Campos SAFT-AO obrigatórios (Decreto 71/25) — antes a ND gravava
            // só subtotal/tax_amount/total e seguia para a AGT sem net_total,
            // tax_payable, gross_total, invoice_status nem hash.
            $netTotal = $totals['subtotal_original'];

            $debitNote = DebitNote::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $this->client_id,
                'invoice_id' => $this->invoice_id ?: null,
                'issue_date' => $this->issue_date,
                'system_entry_date' => now(),
                'due_date' => $this->due_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'subtotal' => $totals['subtotal_original'],
                'net_total' => $netTotal,
                'tax_amount' => $totals['tax_amount'],
                'tax_payable' => $totals['tax_amount'],
                'total' => $totals['total'],
                'gross_total' => $netTotal + $totals['tax_amount'],
                'status' => 'issued',
                'invoice_status' => 'F',
                'source_billing' => 'P',
                'created_by' => auth()->id(),
            ]);

            // Criar items
            // O IEC/IS só é conhecido depois de copiado da linha original, por
            // isso os totais (e o hash, que assina o gross_total) fecham no fim.
            $orderNo = 0;
            $ndExtraTaxes = 0.0;   // IEC + IS de todas as linhas
            $ndIvaAmount  = 0.0;   // só IVA
            $ndNetTotal   = 0.0;   // base tributável, JÁ com os descontos de linha

            foreach ($cartItems as $item) {
                $orderNo++;
                $itemTotals = InvoiceCalculationHelper::calculateItemTotals(
                    $item->price,
                    $item->quantity,
                    $item->attributes['discount_percent'],
                    $item->attributes['tax_rate']
                );

                $netLine = $itemTotals['subtotal'];

                // Base do documento = soma das bases das LINHAS, já líquidas de
                // desconto. O net_total era o subtotal BRUTO
                // (`subtotal_original`), enquanto cada linha descontava — o
                // documento ficava acima da soma das suas próprias linhas e o
                // cliente era debitado a mais exactamente no valor do desconto.
                $ndNetTotal += (float) $netLine - (float) ($itemTotals['discount_amount'] ?? 0);

                $debitNoteItem = DebitNoteItem::create([
                    'debit_note_id' => $debitNote->id,
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
                    // AGT v1.2 — a ND acresce à dívida, logo é creditAmount
                    // (ao contrário da NC, que preenche debitAmount).
                    'debit_amount'         => 0,
                    'credit_amount'        => $netLine,
                    'settlement_amount'    => $itemTotals['discount_amount'] ?? 0,
                    // Região e código SAFT herdados da linha original
                    'tax_country_region'   => $item->attributes['tax_country_region'] ?? 'AO',
                    'tax_code'             => ($item->attributes['tax_code'] ?? null)
                        ?: (($item->attributes['tax_rate'] ?? 0) > 0 ? 'NOR' : 'ISE'),
                    // Campos AGT: linha isenta TEM de levar motivo
                    'tax_exemption_code'   => ($item->attributes['tax_rate'] ?? 0) > 0
                        ? null
                        : \App\Models\Product::normalizeExemptionCode($item->attributes['exemption_reason'] ?? null),
                    'tax_exemption_reason' => ($item->attributes['tax_rate'] ?? 0) > 0
                        ? null
                        : \App\Models\Product::exemptionReasonText($item->attributes['exemption_reason'] ?? null),
                    // referenceInfo (documento rectificativo)
                    'reference_invoice_no'   => $item->attributes['reference_invoice_no']   ?? null,
                    'reference_item_line_no' => $item->attributes['reference_item_line_no'] ?? $orderNo,
                    'reference_reason'       => $reasonExpression,
                ]);

                // Replicar o IEC e o Imposto de Selo da linha original: sem isto
                // a ND rectificava só o IVA e o imposto especial ficava de fora.
                if (!empty($item->attributes['origem_line_id'])) {
                    $originais = \App\Models\Invoicing\LineTax::where('line_type', $item->attributes['origem_line_type'])
                        ->where('line_id', $item->attributes['origem_line_id'])
                        ->get();

                    $iecLinha = 0.0;
                    foreach ($originais as $orig) {
                        \App\Models\Invoicing\LineTax::create([
                            'tenant_id'            => activeTenantId(),
                            'line_type'            => get_class($debitNoteItem),
                            'line_id'              => $debitNoteItem->id,
                            'tax_type'             => $orig->tax_type,
                            'tax_country_region'   => $orig->tax_country_region,
                            'tax_code'             => $orig->tax_code,
                            'tax_percentage'       => $orig->tax_percentage,
                            'tax_amount'           => (float) $orig->tax_amount,
                            'pautal_code'          => $orig->pautal_code,
                            'verba_no'             => $orig->verba_no,
                            'description'          => $orig->description,
                            'tax_exemption_code'   => $orig->tax_exemption_code,
                            'tax_exemption_reason' => $orig->tax_exemption_reason,
                        ]);

                        $valor = (float) $orig->tax_amount;
                        $ndExtraTaxes += $valor;
                        if ($orig->tax_type === \App\Models\Invoicing\LineTax::TIPO_IEC) {
                            $iecLinha += $valor;
                        }
                    }

                    // O IVA incide sobre o líquido ACRESCIDO do IEC, igual à
                    // factura de origem. Sem este reajuste a AGT recusava com
                    // "taxContribution não corresponde ao imposto apurado".
                    if ($iecLinha > 0) {
                        $ivaLinha = round(
                            ($netLine - $itemTotals['discount_amount'] + $iecLinha)
                                * ((float) $item->attributes['tax_rate']) / 100,
                            2
                        );
                        $debitNoteItem->tax_amount = $ivaLinha;
                        $debitNoteItem->total = $netLine - $itemTotals['discount_amount']
                            + $ivaLinha + $iecLinha;
                        $debitNoteItem->save();
                    }
                }

                $ndIvaAmount += (float) $debitNoteItem->tax_amount;
            }

            // Replicar a retenção na fonte da factura rectificada: a ND acresce
            // ao valor tributável, logo a retenção também tem de constar.
            if ($this->invoice_id) {
                $retencoes = DB::table('invoicing_withholding_taxes')
                    ->where('document_type', SalesInvoice::class)
                    ->where('document_id', $this->invoice_id)
                    ->get();

                foreach ($retencoes as $ret) {
                    // A retenção incide sobre a base DESTA nota, não sobre a da
                    // factura rectificada.
                    //
                    // Copiar o `withholding_tax_amount` tal e qual fazia uma ND
                    // de 10.000 Kz sobre uma factura de 1.000.000 Kz declarar à
                    // AGT 65.000 Kz de retenção — o valor da factura inteira.
                    $percentagem = (float) ($ret->withholding_tax_percentage ?? 0);

                    // $ndNetTotal e não $debitNote->net_total: os totais do
                    // documento só fecham mais abaixo, pelo que a propriedade
                    // ainda tem o valor bruto posto no create().
                    $valor = $percentagem > 0
                        ? round($ndNetTotal * $percentagem / 100, 2)
                        : 0.0;

                    if ($valor <= 0) {
                        continue;   // sem base nesta nota não há retenção a declarar
                    }

                    DB::table('invoicing_withholding_taxes')->insert([
                        'tenant_id'                   => activeTenantId(),
                        'document_type'               => get_class($debitNote),
                        'document_id'                 => $debitNote->id,
                        'withholding_tax_type'        => $ret->withholding_tax_type,
                        'withholding_tax_description' => $ret->withholding_tax_description,
                        'withholding_tax_percentage'  => $percentagem,
                        'withholding_tax_amount'      => $valor,
                        'created_at'                  => now(),
                        'updated_at'                  => now(),
                    ]);
                }
            }

            // Totais do documento com IEC/IS incluídos
            $debitNote->net_total   = round($ndNetTotal, 2);         // já com descontos de linha
            $debitNote->tax_amount  = $ndIvaAmount;                  // só IVA (compatibilidade)
            $debitNote->tax_payable = $ndIvaAmount + $ndExtraTaxes;  // imposto total AGT
            $debitNote->gross_total = (float) $debitNote->net_total + $debitNote->tax_payable;
            $debitNote->total       = $debitNote->gross_total;
            $debitNote->save();

            // HASH SAFT-AO (Decreto 71/25): assina o gross_total, por isso só
            // pode ser gerado depois de os totais estarem fechados. Antes a ND
            // era gravada sem hash nenhum e ficava fora da cadeia.
            $previousDN = DebitNote::where('tenant_id', activeTenantId())
                ->where('id', '<', $debitNote->id)
                ->whereNotNull('saft_hash')
                ->orderBy('id', 'desc')
                ->first();

            $hash = \App\Helpers\SAFTHelper::generateHash(
                $debitNote->issue_date->format('Y-m-d'),
                $debitNote->system_entry_date->format('Y-m-d H:i:s'),
                $debitNote->debit_note_number,
                $debitNote->gross_total,
                $previousDN->saft_hash ?? null
            );

            if ($hash) {
                $debitNote->update([
                    'saft_hash' => $hash,
                    'hash' => $hash,
                    'hash_previous' => $previousDN->saft_hash ?? '',
                    'hash_control' => '1',
                ]);
            }

            Cart::session($this->cartInstance)->clear();
            DB::commit();

            // Auto-submeter à AGT, como a nota de crédito já fazia.
            //
            // A ND é documento fiscal comunicável e o modelo até usa o trait
            // HasAGTSignature — mas NADA no sistema a submetia. Saía com série,
            // número e hash encadeado, entrava na cadeia, e para a AGT não
            // existia. Só a NC era comunicada.
            //
            // Depois do commit: a nota já está gravada e uma AGT em baixo não
            // a pode desfazer nem prender o utilizador.
            $mensagem = 'Nota de Débito criada com sucesso!';
            $tipo = 'success';

            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());

            if (!empty($settings->agt_auto_submit)) {
                try {
                    $agtResult = $debitNote->fresh()->submitToAGT();
                } catch (\Throwable $e) {
                    $agtResult = ['success' => false, 'error' => $e->getMessage()];
                }

                if ($agtResult['success'] ?? false) {
                    $mensagem .= ' Submetida à AGT (requestID: ' . ($agtResult['requestID'] ?? '—') . ').';
                } else {
                    $tipo = 'warning';
                    $mensagem .= ' POR COMUNICAR à AGT: ' . ($agtResult['error'] ?? 'erro desconhecido')
                        . '. Pode reenviar no ecrã de Submissões.';
                }
            }

            $this->dispatch('notify', ['type' => $tipo, 'message' => $mensagem]);

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

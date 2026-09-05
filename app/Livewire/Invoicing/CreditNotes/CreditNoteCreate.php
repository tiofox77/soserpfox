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

    /**
     * As linhas desta nota que pedem mais do que a factura ainda tem.
     *
     * Compara-se por ARTIGO, e pela descrição quando a linha não tem artigo do
     * catálogo. O que já foi creditado conta: duas notas parciais não podem
     * passar juntas o que nenhuma delas passava sozinha.
     *
     * @return array<int,string>  uma frase por linha em excesso
     */
    private function linhasQueExcedemAFactura($factura, $cartItems): array
    {
        $chave = fn ($artigo, $descricao) => $artigo
            ? 'p:' . $artigo
            : 'd:' . mb_strtolower(trim((string) $descricao));

        // O que a factura tem, por linha.
        $naFactura = [];

        foreach ($factura->items as $linha) {
            $k = $chave($linha->product_id, $linha->description);
            $naFactura[$k] = ($naFactura[$k] ?? 0) + (float) $linha->quantity;
        }

        // O que outras notas EMITIDAS já tiraram.
        $jaCreditado = [];

        $outras = CreditNote::where('invoice_id', $factura->id)
            ->where('status', 'issued')
            ->when($this->creditNoteId, fn ($q) => $q->where('id', '!=', $this->creditNoteId))
            ->with('items')
            ->get();

        foreach ($outras as $nota) {
            foreach ($nota->items as $linha) {
                $k = $chave($linha->product_id, $linha->description);
                $jaCreditado[$k] = ($jaCreditado[$k] ?? 0) + (float) $linha->quantity;
            }
        }

        // E o que esta nota pede.
        $pedido = [];

        foreach ($cartItems as $item) {
            $artigo = is_numeric($item->id) ? (int) $item->id : null;
            $k = $chave($artigo, $item->name);
            $pedido[$k] = ($pedido[$k] ?? 0) + (float) $item->quantity;
        }

        $excessos = [];

        foreach ($pedido as $k => $quantidade) {
            $disponivel = ($naFactura[$k] ?? 0) - ($jaCreditado[$k] ?? 0);

            // Uma linha que a factura nem tem é excesso por inteiro.
            if ($quantidade > $disponivel + 0.001) {
                $nome = collect($cartItems)->first(fn ($i) => $chave(is_numeric($i->id) ? (int) $i->id : null, $i->name) === $k)?->name;

                $excessos[] = __(':artigo (pede :pedido, disponível :disponivel)', [
                    'artigo'     => \Illuminate\Support\Str::limit((string) $nome, 40),
                    'pedido'     => rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ','),
                    'disponivel' => rtrim(rtrim(number_format(max(0, $disponivel), 3, ',', '.'), '0'), ','),
                ]);
            }
        }

        return $excessos;
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

            /*
             * NÃO SE ANULA MAIS DO QUE SE VENDEU.
             *
             * A AGT recusa com E43 quando a soma das notas passa o que a
             * factura ainda tem por anular — e recusa DEPOIS de o documento já
             * ter número fiscal, hash e assinatura. Nasce inválido, e só a
             * resposta da AGT o diz, dias depois. O travão tem de estar aqui,
             * antes de o número ser atribuído.
             *
             * Conta-se o que outras notas JÁ tiraram a esta factura, não só
             * esta: duas notas parciais podem passar o total sem que nenhuma
             * delas, sozinha, o pareça.
             */
            if ($this->invoice_id) {
                $factura = \App\Models\Invoicing\SalesInvoice::find($this->invoice_id);

                if ($factura) {
                    // Pela mesma fonte que decide o botão da lista e o
                    // selector — três respostas diferentes à mesma pergunta é
                    // como isto começou.
                    $porAnular = $factura->porCreditar($this->creditNoteId ?: null);

                    /*
                     * E AS QUANTIDADES, LINHA A LINHA.
                     *
                     * O travão do valor não chega: uma nota pode bater certo no
                     * total e creditar 69 unidades de uma linha que só teve 34,
                     * com outra linha a menos a compensar. Foi assim que saiu a
                     * NC4226S46906N/000002, que a AGT recusou com E43 — o erro
                     * que diz que a nota não corresponde ao documento que corrige.
                     */
                    $excessos = $this->linhasQueExcedemAFactura($factura, $cartItems);

                    if ($excessos !== []) {
                        DB::rollBack();

                        $this->dispatch('notify', [
                            'type'    => 'error',
                            'message' => __('A factura :factura não tem essas quantidades por anular: :linhas', [
                                'factura' => $factura->invoice_number,
                                'linhas'  => implode('; ', $excessos),
                            ]),
                        ]);

                        return;
                    }

                    if (round((float) $totals['total'], 2) > $porAnular + 0.01) {
                        DB::rollBack();

                        $this->dispatch('notify', [
                            'type' => 'error',
                            'message' => __('Esta nota anula :nota, mas a factura :factura só tem :saldo por anular. A AGT recusaria (E43).', [
                                'nota'    => number_format((float) $totals['total'], 2, ',', '.'),
                                'factura' => $factura->invoice_number,
                                'saldo'   => number_format($porAnular, 2, ',', '.'),
                            ]),
                        ]);

                        return;
                    }
                }
            }

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

            // Criar items
            // O IEC/IS só é conhecido depois de copiado da linha original, por
            // isso os totais do documento (e o hash, que assina o gross_total)
            // são apurados no fim — ver mais abaixo.
            $orderNo = 0;
            $ncExtraTaxes = 0.0;   // IEC + IS de todas as linhas
            $ncIvaAmount  = 0.0;   // só IVA
            foreach ($cartItems as $item) {
                $orderNo++;
                $itemTotals = InvoiceCalculationHelper::calculateItemTotals(
                    $item->price,
                    $item->quantity,
                    $item->attributes['discount_percent'],
                    $item->attributes['tax_rate']
                );

                $netLine = $itemTotals['subtotal'];

                $creditNoteItem = CreditNoteItem::create([
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
                    // Região e código SAFT herdados da linha original (ver
                    // loadInvoiceItems): fixá-los aqui fazia o crédito de uma
                    // factura de Cabinda ou a 7% sair como AO / taxa normal.
                    'tax_country_region'   => $item->attributes['tax_country_region'] ?? 'AO',
                    'tax_code'             => $item->attributes['tax_code']
                        ?: (($item->attributes['tax_rate'] ?? 0) > 0 ? 'NOR' : 'ISE'),
                    // Linha isenta TEM de levar motivo (a AGT rejeita sem código)
                    'tax_exemption_code'   => ($item->attributes['tax_rate'] ?? 0) > 0
                        ? null
                        : \App\Models\Product::normalizeExemptionCode($item->attributes['exemption_reason'] ?? null),
                    'tax_exemption_reason' => ($item->attributes['tax_rate'] ?? 0) > 0
                        ? null
                        : \App\Models\Product::exemptionReasonText($item->attributes['exemption_reason'] ?? null),
                    // referenceInfo (NC obrigatório)
                    'reference_invoice_no'   => $item->attributes['reference_invoice_no']   ?? null,
                    'reference_item_line_no' => $item->attributes['reference_item_line_no'] ?? $orderNo,
                    'reference_reason'       => $reasonExpression,
                ]);

                // Creditar TAMBÉM o IEC e o Imposto de Selo da linha original.
                // Sem isto uma nota de crédito sobre uma factura com IEC anulava
                // só o IVA, deixando o imposto especial liquidado sem contrapartida.
                if (!empty($item->attributes['origem_line_id'])) {
                    $originais = \App\Models\Invoicing\LineTax::where('line_type', $item->attributes['origem_line_type'])
                        ->where('line_id', $item->attributes['origem_line_id'])
                        ->get();

                    $iecLinha = 0.0;
                    foreach ($originais as $orig) {
                        \App\Models\Invoicing\LineTax::create([
                            'tenant_id'            => activeTenantId(),
                            'line_type'            => get_class($creditNoteItem),
                            'line_id'              => $creditNoteItem->id,
                            'tax_type'             => $orig->tax_type,
                            'tax_country_region'   => $orig->tax_country_region,
                            'tax_code'             => $orig->tax_code,
                            'tax_percentage'       => $orig->tax_percentage,
                            // Proporcional à quantidade creditada, quando é parcial
                            'tax_amount'           => $item->quantity > 0 && $orig->tax_amount > 0
                                ? round((float) $orig->tax_amount * ($item->quantity / max(1, $item->quantity)), 2)
                                : (float) $orig->tax_amount,
                            'pautal_code'          => $orig->pautal_code,
                            'verba_no'             => $orig->verba_no,
                            'description'          => $orig->description,
                            'tax_exemption_code'   => $orig->tax_exemption_code,
                            'tax_exemption_reason' => $orig->tax_exemption_reason,
                        ]);

                        $valor = (float) $orig->tax_amount;
                        $ncExtraTaxes += $valor;
                        if ($orig->tax_type === \App\Models\Invoicing\LineTax::TIPO_IEC) {
                            $iecLinha += $valor;
                        }
                    }

                    // O IVA incide sobre o líquido ACRESCIDO do IEC — igual à
                    // factura de origem. Sem este reajuste a NC creditava um
                    // IVA menor do que o que foi liquidado e a AGT recusava com
                    // "taxContribution não corresponde ao imposto apurado".
                    if ($iecLinha > 0) {
                        $ivaLinha = round(
                            ($netLine - $itemTotals['discount_amount'] + $iecLinha)
                                * ((float) $item->attributes['tax_rate']) / 100,
                            2
                        );
                        $creditNoteItem->tax_amount = $ivaLinha;
                        $creditNoteItem->total = $netLine - $itemTotals['discount_amount']
                            + $ivaLinha + $iecLinha;
                        $creditNoteItem->save();
                    }
                }

                $ncIvaAmount += (float) $creditNoteItem->tax_amount;
            }

            // Totais do documento com IEC/IS incluídos. Só agora se conhece o
            // imposto total, porque as linhas de IEC/IS são copiadas acima.
            $creditNote->tax_amount  = $ncIvaAmount;                  // só IVA (compatibilidade)
            $creditNote->tax_payable = $ncIvaAmount + $ncExtraTaxes;  // imposto total AGT
            $creditNote->gross_total = (float) $creditNote->net_total + $creditNote->tax_payable;
            $creditNote->total       = $creditNote->gross_total;
            $creditNote->save();

            // HASH SAFT-AO (Decreto 71/25): assina o gross_total, por isso só
            // pode ser gerado depois de os totais estarem fechados.
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

            // Reverter também a retenção na fonte da factura creditada: uma NC
            // sobre uma factura com IRT/IAC tem de devolver a retenção, senão o
            // crédito fica incompleto e a AGT recebe um documento sem a
            // withholdingTaxList que o original declarou.
            if ($this->invoice_id) {
                $retencoes = DB::table('invoicing_withholding_taxes')
                    ->where('document_type', \App\Models\Invoicing\SalesInvoice::class)
                    ->where('document_id', $this->invoice_id)
                    ->get();

                foreach ($retencoes as $ret) {
                    DB::table('invoicing_withholding_taxes')->insert([
                        'tenant_id'                   => activeTenantId(),
                        'document_type'               => get_class($creditNote),
                        'document_id'                 => $creditNote->id,
                        'withholding_tax_type'        => $ret->withholding_tax_type,
                        'withholding_tax_description' => $ret->withholding_tax_description,
                        'withholding_tax_percentage'  => $ret->withholding_tax_percentage,
                        'withholding_tax_amount'      => $ret->withholding_tax_amount,
                        'created_at'                  => now(),
                        'updated_at'                  => now(),
                    ]);
                }
            }

            // Limpar carrinho
            Cart::session($this->cartInstance)->clear();

            DB::commit();

            // À AGT em SEGUNDO passo: a nota já está gravada, e comunicá-la
            // não prende o utilizador à espera do fisco. Só se ENFILEIRA; o
            // DespacharAgtPendentes envia à boleia do tráfego.
            $fila = \App\Services\AGT\AutoSubmissao::enfileirar($creditNote);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $fila['enfileirado']
                    ? __('Nota de Crédito criada — a comunicar à AGT.')
                    : __('Nota de Crédito criada com sucesso!'),
            ]);

            return redirect()->route('invoicing.credit-notes.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao criar nota de crédito: :erro', ['erro' => $e->getMessage()])
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

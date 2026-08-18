<?php

namespace App\Livewire\POS;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Invoicing\SalesInvoice;
use App\Services\POS\PosSalesReportQuery;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\CreditNoteItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Helpers\InvoiceCalculationHelper;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Relatório de Vendas POS')]
class SalesReport extends Component
{
    use WithPagination;

    public $startDate;
    public $endDate;
    public $search = '';
    public $status = '';
    public $paymentMethod = '';

    /**
     * Tipo de documento: '' (tudo), 'FR' (facturas) ou 'NC' (notas de crédito).
     *
     * O mapa mostrava só facturas. As devoluções não apareciam em lado nenhum —
     * nem como linha, nem descontadas do total — e quem lia o mapa não tinha
     * como notar a diferença.
     */
    public $documentType = '';

    /**
     * Filtro por operador. Só serve a quem pode ver as vendas de todos.
     *
     * Quem não tem `pos.reports.all` já está preso às próprias vendas pelo
     * applyScope; este campo é ignorado nesse caso, e tem de ser — senão
     * bastava escolher outro nome na lista para dar a volta à permissão.
     */
    public $userId = '';

    // Modal
    public $showDetailsModal = false;
    public $showPrintModal = false;
    public $selectedInvoice = null;
    
    // Credit Note Modal
    public $showCreditNoteModal = false;
    public $creditNoteInvoice = null;
    public $creditNoteReason = 'return';
    public $creditNoteType = 'total';
    public $creditNoteNotes = '';
    public $creditNoteItems = [];
    
    /**
     * Estatísticas do período.
     *
     * `totalRevenue` era um `sum('total')` sobre tudo: as facturas anuladas
     * contavam como receita e as devoluções não desciam nada. Passa a haver
     * bruto, devoluções e líquido, e o bruto já não inclui anuladas.
     *
     * Os totais são sempre do PERÍODO inteiro e não seguem o filtro de tipo —
     * é o que permite ver só as notas de crédito na lista sem perder de vista
     * o bruto contra o qual elas pesam.
     */
    public array $totais = [];

    // Mantidos para não partir a view antiga nem quem lhes chame de fora.
    public $totalSales = 0;
    public $totalRevenue = 0;
    public $totalTax = 0;
    public $totalDiscount = 0;

    public function mount()
    {
        abort_unless(auth()->user()?->can('invoicing.pos.reports'), 403, __('Sem permissão para ver relatórios POS.'));
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->loadStatistics();
    }

    /**
     * Aplica filtro de scope: caixas (sem 'pos.reports.all') só vêem as próprias vendas.
     */
    protected function applyScope($query)
    {
        if (!auth()->user()?->can('invoicing.pos.reports.all')) {
            $query->where('created_by', auth()->id());

            // Sai já: o filtro por operador não se aplica a quem só pode
            // ver as suas. Deixá-lo passar aqui era dar a volta à
            // permissão escolhendo outro nome na lista.
            return $query;
        }

        if ($this->userId) {
            $query->where('created_by', $this->userId);
        }

        return $query;
    }

    /** Quem emitiu vendas nesta empresa — só esses valem como filtro. */
    public function getOperadoresProperty()
    {
        if ($this->ownOnly) {
            return collect();
        }

        return \App\Models\User::whereIn('id', 
            \App\Models\Invoicing\SalesInvoice::where('tenant_id', activeTenantId())
                ->whereNotNull('created_by')
                ->distinct()
                ->pluck('created_by')
        )->orderBy('name')->get(['id', 'name']);
    }

    public function updatedUserId()
    {
        $this->loadStatistics();
        $this->resetPage();
    }

    /**
     * Indica à view se o user está restrito às próprias vendas.
     */
    public function getOwnOnlyProperty(): bool
    {
        return !auth()->user()?->can('invoicing.pos.reports.all');
    }

    public function updatedStartDate()
    {
        $this->loadStatistics();
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->loadStatistics();
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function updatedPaymentMethod()
    {
        $this->resetPage();
    }

    /** Filtros no formato que o PosSalesReportQuery espera. */
    protected function filtros(): array
    {
        return [
            'start_date'     => $this->startDate,
            'end_date'       => $this->endDate,
            'search'         => $this->search,
            'status'         => $this->status,
            'payment_method' => $this->paymentMethod,
            'document_type'  => $this->documentType,
            // A restrição por operador vale também para as notas de crédito:
            // sem isso, o mapa restrito mostrava devoluções de colegas.
            'only_user_id'   => auth()->user()?->can('invoicing.pos.reports.all') ? null : auth()->id(),
        ];
    }

    protected function consulta(): PosSalesReportQuery
    {
        return new PosSalesReportQuery(activeTenantId(), $this->filtros());
    }

    public function loadStatistics()
    {
        $this->totais = $this->consulta()->totais();

        // Compatibilidade com quem ainda leia as antigas.
        $this->totalSales    = $this->totais['facturas_n'];
        $this->totalRevenue  = $this->totais['liquido'];
        $this->totalTax      = $this->totais['imposto'];
        $this->totalDiscount = $this->totais['desconto'];
    }

    public function updatedDocumentType()
    {
        $this->resetPage();
    }

    public function limparFiltros()
    {
        $this->search        = '';
        $this->status        = '';
        $this->paymentMethod = '';
        $this->documentType  = '';
        $this->userId        = '';
        $this->resetPage();
        $this->loadStatistics();
    }

    public function viewDetails($invoiceId)
    {
        $query = SalesInvoice::with(['client', 'items.product'])
            ->where('tenant_id', activeTenantId());
        $this->applyScope($query);
        $this->selectedInvoice = $query->find($invoiceId);
        abort_unless($this->selectedInvoice, 404, __('Fatura não encontrada ou sem acesso.'));
        $this->showDetailsModal = true;
    }

    public function printInvoice($invoiceId)
    {
        $query = SalesInvoice::with(['client', 'items.product'])
            ->where('tenant_id', activeTenantId());
        $this->applyScope($query);
        $this->selectedInvoice = $query->find($invoiceId);
        abort_unless($this->selectedInvoice, 404, __('Fatura não encontrada ou sem acesso.'));
        $this->showPrintModal = true;
    }

    /**
     * Fecha o talão e liberta a factura do estado.
     *
     * O partial livewire.pos.partials.print-modal é partilhado por três
     * componentes (POS, POS do salão e este) e passou a fechar por este método
     * em vez de $set('showPrintModal', false), para libertar a factura do
     * estado do componente. Sem o método aqui, o botão Fechar do talão neste
     * ecrã rebentava com MethodNotFoundException.
     */
    public function closePrintModal()
    {
        $this->showPrintModal = false;
        $this->selectedInvoice = null;
    }

    public function closeModals()
    {
        $this->showDetailsModal = false;
        $this->showPrintModal = false;
        $this->showCreditNoteModal = false;
        $this->selectedInvoice = null;
        $this->creditNoteInvoice = null;
        $this->creditNoteItems = [];
        $this->creditNoteReason = 'return';
        $this->creditNoteType = 'total';
        $this->creditNoteNotes = '';
    }

    public function openCreditNote($invoiceId)
    {
        $query = SalesInvoice::with(['client', 'items.product'])
            ->where('tenant_id', activeTenantId());
        $this->applyScope($query);
        $invoice = $query->find($invoiceId);
        abort_unless($invoice, 404, __('Fatura não encontrada ou sem acesso.'));

        if (in_array($invoice->status, ['cancelled', 'credited'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Esta fatura já está cancelada ou totalmente creditada.')
            ]);
            return;
        }

        $this->creditNoteInvoice = $invoice;
        $this->creditNoteItems = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? $item->description,
                'quantity' => (float) $item->quantity,
                'max_quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'selected' => true,
            ];
        })->toArray();

        $this->creditNoteReason = 'return';
        $this->creditNoteType = 'total';
        $this->creditNoteNotes = '';
        $this->showCreditNoteModal = true;
    }

    public function toggleCreditNoteItem($index)
    {
        $this->creditNoteItems[$index]['selected'] = !$this->creditNoteItems[$index]['selected'];
        $hasPartial = collect($this->creditNoteItems)->contains('selected', false);
        $this->creditNoteType = $hasPartial ? 'partial' : 'total';
    }

    public function updateCreditNoteQty($index, $qty)
    {
        $max = $this->creditNoteItems[$index]['max_quantity'];
        $this->creditNoteItems[$index]['quantity'] = max(0.001, min($qty, $max));
        if ($qty < $max) {
            $this->creditNoteType = 'partial';
        }
    }

    public function saveCreditNote()
    {
        if (!$this->creditNoteInvoice) return;

        $selectedItems = collect($this->creditNoteItems)->where('selected', true);
        if ($selectedItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Selecione pelo menos um item para a nota de crédito.')
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            $invoice = $this->creditNoteInvoice;

            // Build cart-like collection for calculation
            $cartItems = $selectedItems->map(function ($item) {
                return (object)[
                    'price' => (float) $item['unit_price'],
                    'quantity' => (float) $item['quantity'],
                    'attributes' => [
                        'discount_percent' => 0,
                        'tax_rate' => (float) $item['tax_rate'],
                    ]
                ];
            });

            $totals = InvoiceCalculationHelper::calculateTotals($cartItems, 0, 0, 0, false);

            // Conteúdo do documento fiscal (reason_text e reference_reason da NC,
            // que vão para o SAFT-AO): fica em português, sempre — a língua legal
            // do documento é a angolana, não a de quem carregou no botão.
            $reasonExpression = ($this->creditNoteType === 'total') ? 'Anulação' : 'Rectificação';
            $netTotal = $totals['subtotal_original'];
            $taxPayable = $totals['tax_amount'];
            $grossTotal = $netTotal + $taxPayable;

            $creditNote = CreditNote::create([
                'tenant_id' => activeTenantId(),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'warehouse_id' => $invoice->warehouse_id,
                'issue_date' => now()->toDateString(),
                'system_entry_date' => now(),
                'reason' => $this->creditNoteReason,
                'reason_text' => $reasonExpression,
                'type' => $this->creditNoteType,
                'notes' => $this->creditNoteNotes,
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

            // Generate SAFT hash
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

            // Create items + revert stock
            $orderNo = 0;
            foreach ($selectedItems as $item) {
                $orderNo++;
                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];
                $taxRate = (float) $item['tax_rate'];
                $subtotal = round($price * $qty, 2);
                $taxAmount = round($subtotal * ($taxRate / 100), 2);
                $total = $subtotal + $taxAmount;

                CreditNoteItem::create([
                    'credit_note_id' => $creditNote->id,
                    'product_id' => $item['product_id'],
                    'description' => $item['product_name'],
                    'quantity' => $qty,
                    'unit' => 'un',
                    'unit_price' => $price,
                    'unit_price_base' => $price,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'order' => $orderNo,
                    'debit_amount' => $subtotal,
                    'credit_amount' => 0,
                    'settlement_amount' => 0,
                    'tax_country_region' => 'AO',
                    'tax_code' => $taxRate > 0 ? 'NOR' : 'ISE',
                    // Linha isenta TEM de levar motivo (a AGT rejeita ISE sem código)
                    'tax_exemption_code' => $taxRate > 0 ? null
                        : \App\Services\Invoicing\TaxResolver::forProductId($item['product_id'] ?? null)['exemption_code'],
                    'tax_exemption_reason' => $taxRate > 0 ? null
                        : \App\Services\Invoicing\TaxResolver::forProductId($item['product_id'] ?? null)['exemption_reason'],
                    'reference_invoice_no' => $invoice->invoice_number,
                    'reference_item_line_no' => $orderNo,
                    'reference_reason' => $reasonExpression,
                ]);

                // Devolver stock. A linha do armazém é a fonte de verdade — o
                // agregado products.stock_quantity é mantido pelo StockObserver.
                // (Somar aos dois provocava DUPLA reposição na devolução.)
                if ($item['product_id']) {
                    $product = \App\Models\Product::find($item['product_id']);
                    if ($product) {
                        $stockRow = $invoice->warehouse_id
                            ? \App\Models\Invoicing\Stock::where('tenant_id', activeTenantId())
                                ->where('warehouse_id', $invoice->warehouse_id)
                                ->where('product_id', $product->id)
                                ->first()
                            : null;

                        if ($stockRow) {
                            $stockRow->quantity = (float) $stockRow->quantity + $qty;
                            $stockRow->save();   // observer ressincroniza o agregado

                            // Ledger da devolução (semAplicarStock: o stock já subiu acima)
                            \App\Models\Invoicing\StockMovement::semAplicarStock(function () use ($invoice, $product, $qty) {
                                \App\Models\Invoicing\StockMovement::create([
                                    'tenant_id'      => activeTenantId(),
                                    'warehouse_id'   => $invoice->warehouse_id,
                                    'product_id'     => $product->id,
                                    'type'           => 'in',
                                    'quantity'       => $qty,
                                    'unit_cost'      => $product->cost,
                                    'reference_type' => \App\Models\Invoicing\SalesInvoice::class,
                                    'reference_id'   => $invoice->id,
                                    'user_id'        => auth()->id(),
                                    // Gravado na base: é registo, não ecrã. Traduzi-lo punha
                                    // o histórico de stock em três línguas conforme a língua
                                    // de quem fez a devolução.
                                    'notes'          => 'Devolução (nota de crédito) - ' . $invoice->invoice_number,
                                ]);
                            });
                        } else {
                            // Tenant legado sem linhas de armazém: o agregado é a store
                            $product->stock_quantity = (float) $product->stock_quantity + $qty;
                            $product->save();
                        }
                    }
                }
            }

            // Update invoice status
            $creditNote->updateInvoiceBalance();

            DB::commit();

            $this->showCreditNoteModal = false;
            $this->creditNoteInvoice = null;
            $this->creditNoteItems = [];
            $this->loadStatistics();

            // A devolução no POS era a única via de emitir uma nota de crédito
            // que não a enviava à AGT — ficava só na aplicação, e no portal
            // nunca aparecia. Depois do commit: a nota já está emitida e uma
            // falha da AGT não a pode desfazer.
            $agt = \App\Services\AGT\AutoSubmissao::submeter($creditNote);

            // Frases inteiras, e o número como marcador: 'Nota de Crédito ' . $n
            // . ' emitida' não se traduz — noutras línguas o número não fica no
            // meio da frase. O que se junta é sempre outra frase completa.
            $mensagem = __('Nota de Crédito :numero emitida com sucesso!', [
                'numero' => $creditNote->credit_note_number,
            ]);
            if ($agt['enviado']) {
                $mensagem .= ' ' . __('Submetida à AGT.');
            } elseif ($agt['erro']) {
                $mensagem .= ' ' . __('Por submeter à AGT: :erro', ['erro' => $agt['erro']]);
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $mensagem,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('POS CreditNote error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao criar nota de crédito: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }

    public function exportExcel()
    {
        return redirect()->to(route('invoicing.pos.export.sales-excel', $this->exportFilters()));
    }

    public function exportPdf()
    {
        return redirect()->to(route('invoicing.pos.export.sales-pdf', $this->exportFilters()));
    }

    /**
     * Filtros enviados como query string para o controller de export.
     */
    protected function exportFilters(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'search' => $this->search,
            'status' => $this->status,
            'payment_method' => $this->paymentMethod,
            // Sem isto, o ficheiro exportado não era o que estava no ecrã.
            'document_type' => $this->documentType,
        ];
    }

    public function render()
    {
        // A listagem vem do PosSalesReportQuery — o mesmo que o PDF e o Excel
        // usam. Antes cada um montava a sua consulta e nada garantia que
        // dissessem o mesmo número.
        $documentos = $this->consulta()->listagem()->paginate(15);

        return view('livewire.p-o-s.sales-report', [
            'documentos' => $documentos,
            'totais'     => $this->totais ?: $this->consulta()->totais(),
        ]);
    }
}

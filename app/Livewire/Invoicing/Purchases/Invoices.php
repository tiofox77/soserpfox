<?php

namespace App\Livewire\Invoicing\Purchases;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Faturas de Compra')]
class Invoices extends Component
{
    use WithPagination;
    // Cada um vê os documentos que emitiu; com
    // `invoicing.documents.all` vê os de todos e ganha o filtro por autor.
    use \App\Traits\DocumentosPorAutor;
    use \App\Traits\ResolveDocumentoDaEmpresa;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $warehouseFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;

    // Delete Modal
    public $showDeleteModal = false;
    public $invoiceToDelete = null;

    public $showViewModal = false;
    public $selectedInvoice = null;
    
    // History Modal
    public $showHistoryModal = false;
    public $invoiceHistory = null;

    protected $queryString = ['search', 'statusFilter'];
    
    protected $listeners = ['paymentRegistered' => '$refresh'];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\PurchaseInvoice::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['supplier', 'warehouse', 'creator']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('supplier', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Status Filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Warehouse Filter
        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        // Date Range
        if ($this->dateFrom) {
            $query->whereDate('invoice_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('invoice_date', '<=', $this->dateTo);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        // Stats
        $stats = [
            'total' => $this->baseDoAutor()->count(),
            'draft' => $this->baseDoAutor()->where('status', 'draft')->count(),
            'pending' => $this->baseDoAutor()->where('status', 'pending')->count(),
            'paid' => $this->baseDoAutor()->where('status', 'paid')->count(),
            'total_amount' => $this->baseDoAutor()->sum('total'),
        ];

        return view('livewire.invoicing.faturas-compra.invoices', [
            'invoices' => $invoices,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    /**
     * Não abre nada: as facturas de compra não se eliminam (ver deleteInvoice).
     *
     * Fica de pé porque o id pode continuar a chegar do browser — um separador
     * aberto de antes, um atalho guardado. O que não pode é abrir a porta.
     */
    public function confirmDelete($invoiceId)
    {
        $this->deleteInvoice();
    }

    /**
     * Uma factura de compra NÃO se apaga. Anula-se.
     *
     * O documento é do FORNECEDOR e o que aqui fica registado é que ele foi
     * recebido: deu entrada de stock, criou dívida a pagar, entrou na
     * contabilidade e vai para o SAFT-AO. Apagar a linha não desfaz nada disso
     * — desfaz só a prova de que aconteceu, e deixa o stock e as contas a
     * apontar para um documento que já não existe.
     *
     * A anulação faz o que é preciso: o PurchaseInvoiceObserver reverte a
     * entrada de stock, o estado passa a `cancelled` e o documento continua
     * visível e auditável. É o mesmo princípio que a AGT impõe aos documentos
     * emitidos — anular, nunca eliminar — e é o único caminho coerente com uma
     * trilha de auditoria que não se pode reescrever.
     *
     * Este método permanece para não partir chamadas antigas e para dizer isto
     * a quem lá chegar; nunca apaga.
     */
    public function deleteInvoice()
    {
        $this->showDeleteModal = false;
        $this->invoiceToDelete = null;

        $this->dispatch('notify', [
            'type' => 'error',
            'message' => __('As facturas de compra não se eliminam. Use "Anular" — reverte o stock e mantém o registo.'),
        ]);
    }

    /**
     * Anula uma fatura de compra já recebida. O PurchaseInvoiceObserver reverte
     * o stock que tinha entrado (e regista o movimento no histórico).
     */
    public function cancelInvoice($invoiceId)
    {
        $invoice = $this->documentoDaEmpresa(\App\Models\Invoicing\PurchaseInvoice::class, $invoiceId);
        if (!$invoice) { return; }

        if ($invoice->status === 'cancelled') {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Esta fatura já está anulada.')]);
            return;
        }

        if ($invoice->status === 'draft') {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Um rascunho não precisa de ser anulado — pode ser eliminado.')]);
            return;
        }

        try {
            $invoice->status = 'cancelled';
            $invoice->save();   // observer reverte o stock

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Fatura de compra anulada. O stock foi revertido.'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Purchases\Invoices::cancelInvoice', ['invoice' => $invoiceId, 'error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Erro ao anular: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function markAsPaid($invoiceId)
    {
        $invoice = $this->documentoDaEmpresa(\App\Models\Invoicing\PurchaseInvoice::class, $invoiceId);
        if (!$invoice) { return; }

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $invoice->status === 'paid'
                    ? __('Esta fatura já está paga.')
                    : __('Esta fatura já está cancelada.')
            ]);
            return;
        }

        try {
            $invoice->status = 'paid';
            $invoice->paid_amount = $invoice->total;
            $invoice->save();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Fatura marcada como paga!')
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao atualizar fatura: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    
    public function viewInvoice($invoiceId)
    {
        $this->selectedInvoice = $this->documentoDaEmpresa(\App\Models\Invoicing\PurchaseInvoice::class, $invoiceId, ['supplier', 'warehouse', 'items.product', 'creator']);
        if (!$this->selectedInvoice) { return; }
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedInvoice = null;
    }
    
    public function downloadPdf($invoiceId)
    {
        $invoice = $this->documentoDaEmpresa(\App\Models\Invoicing\PurchaseInvoice::class, $invoiceId);
        if (!$invoice) { return; }
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.purchases.invoices.pdf', $invoice->id);
    }
}

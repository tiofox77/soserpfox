<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Orçamentos')]
class Quotes extends Component
{
    use WithPagination;
    // Cada um vê os documentos que emitiu; com
    // `invoicing.documents.all` vê os de todos e ganha o filtro por autor.
    use \App\Traits\DocumentosPorAutor;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $warehouseFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;

    // Delete Modal
    public $showDeleteModal = false;
    public $quoteToDelete = null;

    // View Modal
    public $showViewModal = false;
    public $selectedQuote = null;

    // History Modal
    public $showHistoryModal = false;
    public $quoteHistory = null;
    public $relatedInvoices = [];

    protected $queryString = ['search', 'statusFilter'];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\SalesQuote::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['client', 'warehouse', 'creator']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('quote_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('quote_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('quote_date', '<=', $this->dateTo);
        }

        $quotes = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $stats = [
            'total' => $this->baseDoAutor()->count(),
            'draft' => $this->baseDoAutor()->where('status', 'draft')->count(),
            'sent' => $this->baseDoAutor()->where('status', 'sent')->count(),
            'accepted' => $this->baseDoAutor()->where('status', 'accepted')->count(),
            'total_amount' => $this->baseDoAutor()->sum('total'),
        ];

        return view('livewire.invoicing.orcamentos-venda.orcamentos', [
            'quotes' => $quotes,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    public function confirmDelete($quoteId)
    {
        $this->quoteToDelete = $quoteId;
        $this->showDeleteModal = true;
    }

    public function deleteQuote()
    {
        if ($this->quoteToDelete) {
            $quote = $this->baseDoAutor()
                ->findOrFail($this->quoteToDelete);

            // Não deixar apagar um orçamento que já deu origem a facturas.
            if ($quote->invoices()->count() > 0) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Não é possível eliminar um orçamento que já gerou faturas. Elimine as faturas primeiro.')
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $quote->delete();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Orçamento eliminado com sucesso!')
            ]);
        }

        $this->showDeleteModal = false;
        $this->quoteToDelete = null;
    }

    public function convertToInvoice($quoteId)
    {
        $quote = $this->baseDoAutor()
            ->findOrFail($quoteId);

        try {
            $invoice = $quote->convertToInvoice();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Orçamento convertido em fatura com sucesso! Fatura: :numero', ['numero' => $invoice->invoice_number])
            ]);

            return redirect()->route('invoicing.sales.invoices');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao converter: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }

    public function showHistory($quoteId)
    {
        $this->quoteHistory = $this->baseDoAutor()
            ->with(['client', 'warehouse'])
            ->findOrFail($quoteId);

        $this->relatedInvoices = $this->quoteHistory->invoices()
            ->with(['client', 'warehouse', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
        $this->quoteHistory = null;
        $this->relatedInvoices = [];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function viewQuote($quoteId)
    {
        $this->selectedQuote = $this->baseDoAutor()
            ->with(['client', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($quoteId);

        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedQuote = null;
    }

    public function downloadPdf($quoteId)
    {
        $quote = $this->baseDoAutor()
            ->findOrFail($quoteId);

        return redirect()->route('invoicing.sales.quotes.pdf', $quote->id);
    }
}

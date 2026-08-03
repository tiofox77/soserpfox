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

    public function render()
    {
        $query = PurchaseInvoice::where('tenant_id', activeTenantId())
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
            'total' => PurchaseInvoice::where('tenant_id', activeTenantId())->count(),
            'draft' => PurchaseInvoice::where('tenant_id', activeTenantId())->where('status', 'draft')->count(),
            'pending' => PurchaseInvoice::where('tenant_id', activeTenantId())->where('status', 'pending')->count(),
            'paid' => PurchaseInvoice::where('tenant_id', activeTenantId())->where('status', 'paid')->count(),
            'total_amount' => PurchaseInvoice::where('tenant_id', activeTenantId())->sum('total'),
        ];

        return view('livewire.invoicing.faturas-compra.invoices', [
            'invoices' => $invoices,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    public function confirmDelete($invoiceId)
    {
        $this->invoiceToDelete = $invoiceId;
        $this->showDeleteModal = true;
    }

    public function deleteInvoice()
    {
        if ($this->invoiceToDelete) {
            // Verificar bloqueio de eliminação via Software Settings
            if (isDeleteBlocked('sales_invoice')) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'A eliminação de Faturas está bloqueada pelo administrador. Apenas anulações são permitidas.'
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $invoice = PurchaseInvoice::where('tenant_id', activeTenantId())
                ->findOrFail($this->invoiceToDelete);

            // Só rascunhos: uma compra já recebida deu entrada de stock e tem
            // histórico — anula-se, não se apaga.
            if ($invoice->status !== 'draft') {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Só é possível eliminar faturas de compra em rascunho. Anule a fatura para reverter o stock.',
                ]);
                $this->showDeleteModal = false;
                return;
            }

            // Pagamentos associados (a relação payments() não existe — rebentava
            // com BadMethodCallException; verificar pelos dados reais).
            $temPagamentos = (float) ($invoice->paid_amount ?? 0) > 0
                || \App\Models\Treasury\Transaction::where('tenant_id', activeTenantId())
                    ->where('invoice_id', $invoice->id)->exists();

            if ($temPagamentos) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Não é possível eliminar uma fatura que já tem pagamentos associados.'
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $invoice->delete();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fatura eliminada com sucesso!'
            ]);
        }

        $this->invoiceToDelete = null;
    }

    /**
     * Anula uma fatura de compra já recebida. O PurchaseInvoiceObserver reverte
     * o stock que tinha entrado (e regista o movimento no histórico).
     */
    public function cancelInvoice($invoiceId)
    {
        $invoice = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->findOrFail($invoiceId);

        if ($invoice->status === 'cancelled') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Esta fatura já está anulada.']);
            return;
        }

        if ($invoice->status === 'draft') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Um rascunho não precisa de ser anulado — pode ser eliminado.']);
            return;
        }

        try {
            $invoice->status = 'cancelled';
            $invoice->save();   // observer reverte o stock

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fatura de compra anulada. O stock foi revertido.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Purchases\Invoices::cancelInvoice', ['invoice' => $invoiceId, 'error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Erro ao anular: ' . $e->getMessage()]);
        }
    }

    public function markAsPaid($invoiceId)
    {
        $invoice = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->findOrFail($invoiceId);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Esta fatura já está ' . ($invoice->status === 'paid' ? 'paga' : 'cancelada') . '.'
            ]);
            return;
        }

        try {
            $invoice->status = 'paid';
            $invoice->paid_amount = $invoice->total;
            $invoice->save();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Fatura marcada como paga!'
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao atualizar fatura: ' . $e->getMessage()
            ]);
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
        $this->selectedInvoice = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->with(['supplier', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($invoiceId);
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedInvoice = null;
    }
    
    public function downloadPdf($invoiceId)
    {
        $invoice = PurchaseInvoice::where('tenant_id', activeTenantId())
            ->findOrFail($invoiceId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.purchases.invoices.pdf', $invoice->id);
    }
}

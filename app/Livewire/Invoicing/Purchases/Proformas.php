<?php

namespace App\Livewire\Invoicing\Purchases;

use App\Models\Invoicing\PurchaseProforma;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Proformas de Compra')]
class Proformas extends Component
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
    public $proformaToDelete = null;
    
    // View Modal
    public $showViewModal = false;
    public $selectedProforma = null;
    
    // History Modal
    public $showHistoryModal = false;
    public $proformaHistory = null;
    public $relatedInvoices = [];

    protected $queryString = ['search', 'statusFilter'];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    protected function modeloDoDocumento(): string
    {
        return \App\Models\Invoicing\PurchaseProforma::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['supplier', 'warehouse', 'creator']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('proforma_number', 'like', '%' . $this->search . '%')
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
            $query->whereDate('proforma_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('proforma_date', '<=', $this->dateTo);
        }

        $proformas = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        // Stats
        $stats = [
            'total' => $this->baseDoAutor()->count(),
            'draft' => $this->baseDoAutor()->where('status', 'draft')->count(),
            'sent' => $this->baseDoAutor()->where('status', 'sent')->count(),
            'accepted' => $this->baseDoAutor()->where('status', 'accepted')->count(),
            'total_amount' => $this->baseDoAutor()->sum('total'),
        ];

        return view('livewire.invoicing.proformas-compra.proformas', [
            'proformas' => $proformas,
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }

    public function confirmDelete($proformaId)
    {
        $this->proformaToDelete = $proformaId;
        $this->showDeleteModal = true;
    }

    public function deleteProforma()
    {
        if ($this->proformaToDelete) {
            // Verificar bloqueio de eliminação via Software Settings
            if (isDeleteBlocked('proforma')) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('A eliminação de Proformas está bloqueada pelo administrador. Apenas anulações são permitidas.')
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $proforma = $this->baseDoAutor()
                ->findOrFail($this->proformaToDelete);

            // Verificar se tem faturas associadas
            if ($proforma->purchaseInvoice()->exists()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Não é possível eliminar uma proforma que já foi convertida em fatura. Elimine a fatura primeiro.')
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $proforma->delete();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Proforma eliminada com sucesso!')
            ]);
        }

        $this->showDeleteModal = false;
        $this->proformaToDelete = null;
    }

    public function convertToInvoice($proformaId)
    {
        $proforma = $this->baseDoAutor()
            ->findOrFail($proformaId);

        try {
            $invoice = $proforma->convertToInvoice();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Proforma convertida em fatura com sucesso! Fatura: :detalhe', ['detalhe' => $invoice->invoice_number])]);
            
            return redirect()->route('invoicing.purchases.invoices');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao converter: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }
    
    public function showHistory($proformaId)
    {
        $this->proformaHistory = $this->baseDoAutor()
            ->with(['supplier', 'warehouse'])
            ->findOrFail($proformaId);
        
        // Para purchase, é hasOne, não hasMany
        $relatedInvoice = $this->proformaHistory->purchaseInvoice;
        $this->relatedInvoices = $relatedInvoice ? [$relatedInvoice] : [];
        
        $this->showHistoryModal = true;
    }
    
    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
        $this->proformaHistory = null;
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
    
    public function viewProforma($proformaId)
    {
        $this->selectedProforma = $this->baseDoAutor()
            ->with(['supplier', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($proformaId);
        
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedProforma = null;
    }
    
    public function downloadPdf($proformaId)
    {
        $proforma = $this->baseDoAutor()
            ->with(['supplier', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($proformaId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.purchases.proformas.pdf', $proforma->id);
    }
}

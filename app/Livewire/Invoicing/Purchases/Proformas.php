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

    public function render()
    {
        $query = PurchaseProforma::where('tenant_id', activeTenantId())
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
            'total' => PurchaseProforma::where('tenant_id', activeTenantId())->count(),
            'draft' => PurchaseProforma::where('tenant_id', activeTenantId())->where('status', 'draft')->count(),
            'sent' => PurchaseProforma::where('tenant_id', activeTenantId())->where('status', 'sent')->count(),
            'accepted' => PurchaseProforma::where('tenant_id', activeTenantId())->where('status', 'accepted')->count(),
            'total_amount' => PurchaseProforma::where('tenant_id', activeTenantId())->sum('total'),
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
            $proforma = PurchaseProforma::where('tenant_id', activeTenantId())
                ->findOrFail($this->proformaToDelete);

            // Verificar se tem faturas associadas
            if ($proforma->purchaseInvoice()->exists()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Não é possível eliminar uma proforma que já foi convertida em fatura. Elimine a fatura primeiro.'
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $proforma->delete();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Proforma eliminada com sucesso!'
            ]);
        }

        $this->showDeleteModal = false;
        $this->proformaToDelete = null;
    }

    public function convertToInvoice($proformaId)
    {
        $proforma = PurchaseProforma::where('tenant_id', activeTenantId())
            ->findOrFail($proformaId);

        try {
            $invoice = $proforma->convertToInvoice();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Proforma convertida em fatura com sucesso! Fatura: ' . $invoice->invoice_number
            ]);
            
            return redirect()->route('invoicing.purchases.invoices');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao converter: ' . $e->getMessage()
            ]);
        }
    }
    
    public function showHistory($proformaId)
    {
        $this->proformaHistory = PurchaseProforma::where('tenant_id', activeTenantId())
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
        $this->selectedProforma = PurchaseProforma::where('tenant_id', activeTenantId())
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
        $proforma = PurchaseProforma::where('tenant_id', activeTenantId())
            ->with(['supplier', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($proformaId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.purchases.proformas.pdf', $proforma->id);
    }
}

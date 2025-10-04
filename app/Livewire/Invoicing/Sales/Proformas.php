<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\Warehouse;
use App\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Proformas de Venda')]
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
        $query = SalesProforma::where('tenant_id', activeTenantId())
            ->with(['client', 'warehouse', 'creator']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('proforma_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function ($q2) {
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
            'total' => SalesProforma::where('tenant_id', activeTenantId())->count(),
            'draft' => SalesProforma::where('tenant_id', activeTenantId())->where('status', 'draft')->count(),
            'sent' => SalesProforma::where('tenant_id', activeTenantId())->where('status', 'sent')->count(),
            'accepted' => SalesProforma::where('tenant_id', activeTenantId())->where('status', 'accepted')->count(),
            'total_amount' => SalesProforma::where('tenant_id', activeTenantId())->sum('total'),
        ];

        return view('livewire.invoicing.proformas-venda.proformas', [
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
            $proforma = SalesProforma::where('tenant_id', activeTenantId())
                ->findOrFail($this->proformaToDelete);

            // Verificar se tem faturas associadas
            if ($proforma->invoices()->count() > 0) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Não é possível eliminar uma proforma que já gerou faturas. Elimine as faturas primeiro.'
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
        $proforma = SalesProforma::where('tenant_id', activeTenantId())
            ->findOrFail($proformaId);

        try {
            $invoice = $proforma->convertToInvoice();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Proforma convertida em fatura com sucesso! Fatura: ' . $invoice->invoice_number
            ]);
            
            // Não mudar status para 'converted' - permitir múltiplas conversões
            // $proforma->update(['status' => 'converted']);
            
            return redirect()->route('invoicing.sales.invoices');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao converter: ' . $e->getMessage()
            ]);
        }
    }
    
    public function showHistory($proformaId)
    {
        $this->proformaHistory = SalesProforma::where('tenant_id', activeTenantId())
            ->with(['client', 'warehouse'])
            ->findOrFail($proformaId);
        
        $this->relatedInvoices = $this->proformaHistory->invoices()
            ->with(['client', 'warehouse', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
        
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
        $this->selectedProforma = SalesProforma::where('tenant_id', activeTenantId())
            ->with(['client', 'warehouse', 'items.product', 'creator'])
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
        $proforma = SalesProforma::where('tenant_id', activeTenantId())
            ->with(['client', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($proformaId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.sales.proformas.pdf', $proforma->id);
    }
}

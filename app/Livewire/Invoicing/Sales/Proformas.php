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
        return \App\Models\Invoicing\SalesProforma::class;
    }

    public function render()
    {
        $query = $this->baseDoAutor()
            ->with(['client', 'warehouse', 'creator']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('proforma_number', 'like', '%' . $this->search . '%')
                  // A SÉRIE INTERNA PRIMEIRO: é a que a empresa reconhece
                  // (SOSNC), e não o código críptico que a AGT devolve e que
                  // vai gravado no número. Procura-se também pela da AGT, para
                  // quem venha do portal com o código na mão.
                  ->orWhereHas('series', function ($q3) {
                      $q3->where('series_code', 'like', '%' . $this->search . '%')
                         ->orWhere('agt_series_id', 'like', '%' . $this->search . '%');
                  })
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
            'total' => $this->baseDoAutor()->count(),
            'draft' => $this->baseDoAutor()->where('status', 'draft')->count(),
            'sent' => $this->baseDoAutor()->where('status', 'sent')->count(),
            'accepted' => $this->baseDoAutor()->where('status', 'accepted')->count(),
            'total_amount' => $this->baseDoAutor()->sum('total'),
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
            if ($proforma->invoices()->count() > 0) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Não é possível eliminar uma proforma que já gerou faturas. Elimine as faturas primeiro.')
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
                'message' => __('Proforma convertida em fatura com sucesso! Fatura: :numero', ['numero' => $invoice->invoice_number])
            ]);
            
            // Não mudar status para 'converted' - permitir múltiplas conversões
            // $proforma->update(['status' => 'converted']);
            
            return redirect()->route('invoicing.sales.invoices');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao converter: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }
    
    public function showHistory($proformaId)
    {
        $this->proformaHistory = $this->baseDoAutor()
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
        $this->selectedProforma = $this->baseDoAutor()
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
        $proforma = $this->baseDoAutor()
            ->with(['client', 'warehouse', 'items.product', 'creator'])
            ->findOrFail($proformaId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.sales.proformas.pdf', $proforma->id);
    }
}

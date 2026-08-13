<?php

namespace App\Livewire\Invoicing\Sales;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Warehouse;
use App\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Faturas de Venda')]
class Invoices extends Component
{
    use WithPagination;

    // Filters
    public $search = '';
    public $statusFilter = '';
    /** Tipo de documento AGT: '' = todos, FT = Fatura, FR = Fatura-Recibo */
    public $typeFilter = '';
    public $warehouseFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;

    // Delete Modal
    public $showDeleteModal = false;
    public $invoiceToDelete = null;
    
    // View Modal
    public $showViewModal = false;
    public $selectedInvoice = null;
    
    // History Modal
    public $showHistoryModal = false;
    public $invoiceHistory = null;
    public $relatedDocuments = [];

    // 'type' na URL permite ligar o menu directamente às Faturas-Recibo (?type=FR)
    protected $queryString = ['search', 'statusFilter', 'typeFilter' => ['as' => 'type']];
    
    protected $listeners = ['paymentRegistered' => '$refresh'];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        $query = SalesInvoice::where('tenant_id', activeTenantId())
            ->with(['Client', 'warehouse', 'creator']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('Client', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Status Filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Tipo de documento (FT / FR)
        if ($this->typeFilter) {
            $query->where('invoice_type', $this->typeFilter);
        }

        // Warehouse Filter
        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        // Período. Comparação directa e não `whereDate`: o `whereDate` gera
        // DATE(invoice_date), e uma função sobre a coluna impede o MySQL de
        // usar o índice — nesta tabela são milhares de linhas por empresa. A
        // coluna já é DATE, portanto a comparação é exacta na mesma.
        if ($this->dateFrom) {
            $query->where('invoice_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('invoice_date', '<=', $this->dateTo);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($this->perPage);

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        // Stats
        $stats = [
            'total' => SalesInvoice::where('tenant_id', activeTenantId())->count(),
            'draft' => SalesInvoice::where('tenant_id', activeTenantId())->where('status', 'draft')->count(),
            'pending' => SalesInvoice::where('tenant_id', activeTenantId())->where('status', 'pending')->count(),
            'paid' => SalesInvoice::where('tenant_id', activeTenantId())->where('status', 'paid')->count(),
            'total_amount' => SalesInvoice::where('tenant_id', activeTenantId())->sum('total'),
        ];

        return view('livewire.invoicing.faturas-venda.invoices', [
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
                    'message' => __('A eliminação de Faturas de Venda está bloqueada pelo administrador. Apenas anulações são permitidas.')
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $invoice = SalesInvoice::where('tenant_id', activeTenantId())
                ->findOrFail($this->invoiceToDelete);

            // Documento fiscal emitido (finalizado/assinado) NUNCA pode ser
            // eliminado — Decreto 71/25 exige rectificação por Nota de Crédito.
            if ($invoice->invoice_status === 'F' || $invoice->status !== 'draft') {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Documento fiscal emitido não pode ser eliminado. Emita uma Nota de Crédito para rectificar (Decreto 71/25).'),
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
                    'message' => __('Não é possível eliminar uma fatura que já tem pagamentos associados.')
                ]);
                $this->showDeleteModal = false;
                return;
            }

            $invoice->delete();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Fatura eliminada com sucesso!')
            ]);
        }

        $this->invoiceToDelete = null;
    }

    public function markAsPaid($invoiceId)
    {
        $invoice = SalesInvoice::where('tenant_id', activeTenantId())
            ->findOrFail($invoiceId);

        // A Fatura-Recibo é liquidada no acto da venda: marcá-la como paga de
        // novo (ou registar outro recebimento) duplicaria o valor recebido. O
        // botão já não aparece na listagem, mas o método continua acessível
        // por Livewire — a defesa tem de estar aqui.
        if (($invoice->invoice_type ?? 'FT') === 'FR') {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => __('A Fatura-Recibo já é paga no acto da venda.'),
            ]);
            return;
        }

        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            // Duas frases inteiras, e não uma frase colada a um adjectivo: noutras
            // línguas a concordância e a ordem das palavras não são as portuguesas.
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => $invoice->status === 'paid'
                    ? __('Esta fatura já está paga.')
                    : __('Esta fatura já está cancelada.'),
            ]);
            return;
        }

        try {
            $invoice->status = 'paid';
            $invoice->save();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Fatura marcada como paga!')
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao atualizar fatura: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }

    /**
     * Cada filtro tem de voltar à primeira página.
     *
     * Só a pesquisa, o estado e o tipo o faziam. Mudar o armazém ou as datas
     * estando na página 12 mantinha a página 12 — e como o resultado filtrado
     * costuma ter menos páginas do que isso, o ecrã ficava VAZIO. É o sintoma
     * clássico de "o filtro não funciona".
     */
    public function updatingWarehouseFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function limparFiltros()
    {
        $this->search          = '';
        $this->statusFilter    = '';
        $this->typeFilter      = '';
        $this->warehouseFilter = '';
        $this->dateFrom        = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo          = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }
    
    public function viewInvoice($invoiceId)
    {
        $this->selectedInvoice = SalesInvoice::where('tenant_id', activeTenantId())
            ->with(['Client', 'warehouse', 'items.product', 'creator'])
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
        $invoice = SalesInvoice::where('tenant_id', activeTenantId())
            ->findOrFail($invoiceId);
        
        // Redirecionar para rota de PDF
        return redirect()->route('invoicing.sales.invoices.pdf', $invoice->id);
    }
}

<?php

namespace App\Livewire\POS;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Invoicing\SalesInvoice;
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
    
    // Modal
    public $showDetailsModal = false;
    public $showPrintModal = false;
    public $selectedInvoice = null;
    
    // Estatísticas
    public $totalSales = 0;
    public $totalRevenue = 0;
    public $totalTax = 0;
    public $totalDiscount = 0;

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->loadStatistics();
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

    public function loadStatistics()
    {
        $query = SalesInvoice::where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate]);

        $this->totalSales = $query->count();
        $this->totalRevenue = $query->sum('total');
        $this->totalTax = $query->sum('tax_amount');
        $this->totalDiscount = $query->sum('discount_amount');
    }

    public function viewDetails($invoiceId)
    {
        $this->selectedInvoice = SalesInvoice::with(['client', 'items.product'])
            ->find($invoiceId);
        $this->showDetailsModal = true;
    }

    public function printInvoice($invoiceId)
    {
        $this->selectedInvoice = SalesInvoice::with(['client', 'items.product'])
            ->find($invoiceId);
        $this->showPrintModal = true;
    }

    public function closeModals()
    {
        $this->showDetailsModal = false;
        $this->showPrintModal = false;
        $this->selectedInvoice = null;
    }

    public function exportExcel()
    {
        // TODO: Implementar exportação para Excel
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Exportação Excel será implementada em breve'
        ]);
    }

    public function exportPdf()
    {
        // TODO: Implementar exportação para PDF
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Exportação PDF será implementada em breve'
        ]);
    }

    public function render()
    {
        $invoices = SalesInvoice::where('tenant_id', activeTenantId())
            ->with(['client'])
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function ($clientQuery) {
                          $clientQuery->where('name', 'like', '%' . $this->search . '%')
                                    ->orWhere('nif', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->paymentMethod, function ($query) {
                $query->where('payment_method', $this->paymentMethod);
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('livewire.p-o-s.sales-report', [
            'invoices' => $invoices
        ]);
    }
}

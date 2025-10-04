<?php

namespace App\Livewire\Invoicing;

use App\Models\{InvoicingInvoice, Client, Product};
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Faturas')]
class Invoices extends Component
{
    use WithPagination;
    public $search = '';
    public $statusFilter = '';
    public $showModal = false;
    public $editingInvoiceId = null;
    
    // Delete confirmation
    public $showDeleteModal = false;
    public $deletingInvoiceId = null;
    public $deletingInvoiceName = '';
    
    // Filters
    public $clientFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;
    
    // Form fields
    public $client_id;
    public $invoice_number;
    public $invoice_date;
    public $due_date;
    public $status = 'draft';

    protected $rules = [
        'client_id' => 'required|exists:invoicing_clients,id',
        'invoice_number' => 'required|unique:invoicing_invoices,invoice_number',
        'invoice_date' => 'required|date',
        'due_date' => 'required|date|after_or_equal:invoice_date',
        'status' => 'required|in:draft,sent,paid,cancelled',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingClientFilter()
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

    public function clearFilters()
    {
        $this->reset(['statusFilter', 'clientFilter', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->generateInvoiceNumber();
        $this->invoice_date = now()->format('Y-m-d');
        $this->due_date = now()->addDays(30)->format('Y-m-d');
        $this->showModal = true;
    }

    private function generateInvoiceNumber()
    {
        $year = date('Y');
        $lastInvoice = InvoicingInvoice::where('tenant_id', activeTenantId())
            ->whereYear('invoice_date', $year)
            ->latest('invoice_number')
            ->first();
        
        if ($lastInvoice) {
            $lastNumber = (int)substr($lastInvoice->invoice_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $this->invoice_number = 'FT ' . $year . '/' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function edit($id)
    {
        $invoice = InvoicingInvoice::findOrFail($id);
        
        if ($invoice->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->editingInvoiceId = $id;
        $this->client_id = $invoice->client_id;
        $this->invoice_number = $invoice->invoice_number;
        $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
        $this->due_date = $invoice->due_date->format('Y-m-d');
        $this->notes = $invoice->notes;
        $this->status = $invoice->status;
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editingInvoiceId) {
            $this->rules['invoice_number'] = 'required|unique:invoices,invoice_number,' . $this->editingInvoiceId;
        }

        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'client_id' => $this->client_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'notes' => $this->notes,
            'status' => $this->status,
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
        ];

        if ($this->editingInvoiceId) {
            $invoice = InvoicingInvoice::findOrFail($this->editingInvoiceId);
            
            if ($invoice->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $invoice->update($data);
            $this->dispatch('success', message: 'Fatura atualizada com sucesso!');
        } else {
            InvoicingInvoice::create($data);
            $this->dispatch('success', message: 'Fatura criada com sucesso!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $invoice = InvoicingInvoice::findOrFail($id);
        
        if ($invoice->tenant_id !== activeTenantId()) {
            abort(403);
        }
        
        $this->deletingInvoiceId = $id;
        $this->deletingInvoiceName = $invoice->invoice_number;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        try {
            $invoice = InvoicingInvoice::findOrFail($this->deletingInvoiceId);
            
            if ($invoice->tenant_id !== activeTenantId()) {
                abort(403);
            }
            
            $invoice->delete();
            $this->showDeleteModal = false;
            $this->reset(['deletingInvoiceId', 'deletingInvoiceName']);
            $this->dispatch('success', message: 'Fatura excluída com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir fatura!');
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingInvoiceId', 'deletingInvoiceName']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['client_id', 'invoice_number', 'invoice_date', 'due_date', 'notes', 'editingInvoiceId']);
        $this->status = 'draft';
    }

    public function render()
    {
        $invoices = InvoicingInvoice::where('tenant_id', activeTenantId())
            ->with('client')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function ($cq) {
                          $cq->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('nif', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->clientFilter, function ($query) {
                $query->where('client_id', $this->clientFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('invoice_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('invoice_date', '<=', $this->dateTo);
            })
            ->latest('invoice_date')
            ->paginate($this->perPage);

        $clients = Client::where('tenant_id', activeTenantId())
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.invoices.invoices', compact('invoices', 'clients'));
    }
}

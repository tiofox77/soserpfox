<?php

namespace App\Livewire\Invoicing\TransportGuides;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Invoicing\TransportGuide;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Models\Client;
use App\Services\Invoicing\EmissorDeGuias;

/*
 * A EMISSÃO VIVE NO `EmissorDeGuias`: número por tipo e ano, série
 * `transport`, valor da factura de origem, anular e comunicar à AGT. Este
 * ecrã e o ecrã em React chamam o mesmo.
 */
#[Layout('layouts.app')]
#[Title('Guias de Transporte')]
class TransportGuides extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showDeleteModal = false;
    public $guideToDelete = null;

    // Form
    public $type = 'GT';
    public $sourceInvoiceId = '';
    public $client_id = '';
    public $issue_date;
    public $loading_datetime = '';
    public $vehicle_plate = '';
    public $driver_name = '';
    public $driver_document = '';
    public $load_address = '';
    public $unload_address = '';
    public $notes = '';
    public $items = []; // [{product_id, product_name, description, quantity, unit}]
    public $productToAdd = '';

    public function mount()
    {
        $this->issue_date = now()->format('Y-m-d');
    }

    protected function rules()
    {
        return [
            'type' => 'required|in:GT,GR',
            'client_id' => 'required',
            'issue_date' => 'required|date',
            'vehicle_plate' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:120',
        ];
    }

    public function create()
    {
        $this->reset([
            'sourceInvoiceId', 'client_id', 'loading_datetime', 'vehicle_plate',
            'driver_name', 'driver_document', 'load_address', 'unload_address', 'notes', 'items', 'productToAdd',
        ]);
        $this->type = 'GT';
        $this->issue_date = now()->format('Y-m-d');
        $this->items = [];
        $this->resetValidation();
        $this->showModal = true;
    }

    /** Ao escolher fatura de origem: copia cliente + itens */
    public function updatedSourceInvoiceId($value)
    {
        if (!$value) {
            return;
        }
        $invoice = SalesInvoice::with('items')->where('tenant_id', activeTenantId())->find($value);
        if (!$invoice) {
            return;
        }
        $this->client_id = $invoice->client_id;
        $this->items = app(EmissorDeGuias::class)->linhasDaFactura($invoice);
    }

    public function addProduct()
    {
        if (!$this->productToAdd) {
            return;
        }
        $p = Product::where('tenant_id', activeTenantId())->find($this->productToAdd);
        if (!$p) {
            return;
        }
        $this->items[] = [
            'product_id' => $p->id,
            'product_name' => $p->name,
            'description' => $p->name,
            'quantity' => 1,
            'unit' => $p->unit ?? 'un',
        ];
        $this->productToAdd = '';
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save()
    {
        $this->validate();

        try {
            app(EmissorDeGuias::class)->emitir([
                'type' => $this->type,
                'client_id' => $this->client_id,
                'invoice_id' => $this->sourceInvoiceId ?: null,
                'issue_date' => $this->issue_date,
                'loading_datetime' => $this->loading_datetime,
                'vehicle_plate' => $this->vehicle_plate,
                'driver_name' => $this->driver_name,
                'driver_document' => $this->driver_document,
                'load_address' => $this->load_address,
                'unload_address' => $this->unload_address,
                'notes' => $this->notes,
            ], $this->items, activeTenantId(), auth()->id());
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());

            return;
        }

        $this->showModal = false;
        $this->dispatch('success', message: 'Guia registada com sucesso!');
    }

    /** Comunicar a guia à AGT (DS.120) — conforme o ambiente da empresa (sandbox/produção). */
    public function submitAgt($id)
    {
        $guide = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);

        $r = app(EmissorDeGuias::class)->comunicar($guide);

        $this->dispatch($r['ok'] ? 'success' : 'error', message: $r['mensagem']);
    }

    public function confirmDelete($id)
    {
        $this->guideToDelete = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function deleteGuide()
    {
        if ($this->guideToDelete) {
            app(EmissorDeGuias::class)->anular($this->guideToDelete);
            $this->showDeleteModal = false;
            $this->guideToDelete = null;
            $this->dispatch('success', message: 'Guia anulada.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDeleteModal = false;
        $this->guideToDelete = null;
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $guides = TransportGuide::with(['client'])
            ->where('tenant_id', $tenantId)
            ->when($this->search, fn ($q) => $q->where('guide_number', 'like', '%' . $this->search . '%'))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(15);

        $invoices = SalesInvoice::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'credited'])
            ->orderByDesc('invoice_date')->limit(200)
            ->get(['id', 'invoice_number', 'client_id']);

        $clients = Client::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('tenant_id', $tenantId)->orderBy('name')->limit(500)->get(['id', 'name']);

        return view('livewire.invoicing.transport-guides.transport-guides', compact('guides', 'invoices', 'clients', 'products'));
    }
}

<?php

namespace App\Livewire\Invoicing\TransportGuides;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use App\Models\Invoicing\TransportGuide;
use App\Models\Invoicing\TransportGuideItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Models\Client;

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
        $this->items = $invoice->items->map(fn ($it) => [
            'product_id' => $it->product_id,
            'product_name' => $it->product_name ?? $it->description,
            'description' => $it->description,
            'quantity' => (float) $it->quantity,
            'unit' => $it->unit ?? 'un',
        ])->values()->all();
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

    private function nextNumber(int $tenantId, string $type): string
    {
        $last = TransportGuide::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereYear('created_at', date('Y'))
            ->orderByDesc('id')->first();
        $n = $last ? ((int) substr($last->guide_number, -4)) + 1 : 1;
        return $type . '-' . date('Y') . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    public function save()
    {
        $this->validate();

        $items = array_values(array_filter($this->items, fn ($i) => (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) {
            $this->addError('items', 'Adicione pelo menos um item com quantidade.');
            return;
        }

        DB::transaction(function () use ($items) {
            $tenantId = activeTenantId();
            // Valor da mercadoria transportada (da fatura de origem, se houver) — usado na assinatura SAFT-AO
            $grossTotal = $this->sourceInvoiceId
                ? (float) (SalesInvoice::where('tenant_id', $tenantId)->where('id', $this->sourceInvoiceId)->value('total') ?? 0)
                : 0;

            // Série AGT do tipo "transport" (se registada) — fornece o código de validação do ATCUD
            $series = \App\Models\Invoicing\InvoicingSeries::where('tenant_id', $tenantId)
                ->where('document_type', 'transport')
                ->where('is_active', true)
                ->orderByDesc('id')->first();

            $guide = TransportGuide::create([
                'tenant_id' => $tenantId,
                'guide_number' => $this->nextNumber($tenantId, $this->type),
                'type' => $this->type,
                'series_id' => $series?->id,
                'invoice_id' => $this->sourceInvoiceId ?: null,
                'client_id' => $this->client_id,
                'gross_total' => $grossTotal,
                'system_entry_date' => now(),
                'issue_date' => $this->issue_date,
                'loading_datetime' => $this->loading_datetime ?: null,
                'vehicle_plate' => $this->vehicle_plate,
                'driver_name' => $this->driver_name,
                'driver_document' => $this->driver_document,
                'load_address' => $this->load_address,
                'unload_address' => $this->unload_address,
                'notes' => $this->notes,
                'status' => 'issued',
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $it) {
                TransportGuideItem::create([
                    'transport_guide_id' => $guide->id,
                    'product_id' => $it['product_id'] ?? null,
                    'product_name' => $it['product_name'] ?? null,
                    'description' => $it['description'] ?? ($it['product_name'] ?? ''),
                    'quantity' => (float) $it['quantity'],
                    'unit' => $it['unit'] ?? 'un',
                ]);
            }
        });

        $this->showModal = false;
        $this->dispatch('success', message: 'Guia registada com sucesso!');
    }

    /** Comunicar a guia à AGT (DS.120) — transmite ao webservice conforme o ambiente do tenant (sandbox/produção). */
    public function submitAgt($id)
    {
        $guide = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);
        if (empty($guide->jws_signature)) {
            $this->dispatch('error', message: 'A guia não está assinada (o tenant não tem chaves AGT).');
            return;
        }
        try {
            $result = $guide->submitToAGT();
            if ($result['success'] ?? false) {
                $this->dispatch('success', message: 'Guia comunicada à AGT. Referência: ' . ($result['requestID'] ?? '—'));
            } else {
                $this->dispatch('error', message: 'AGT: ' . ($result['error'] ?? 'submissão rejeitada'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Erro ao comunicar à AGT: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $this->guideToDelete = TransportGuide::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function deleteGuide()
    {
        if ($this->guideToDelete) {
            $this->guideToDelete->update(['status' => 'cancelled']);
            $this->guideToDelete->delete();
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

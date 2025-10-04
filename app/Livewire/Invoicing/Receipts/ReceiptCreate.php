<?php

namespace App\Livewire\Invoicing\Receipts;

use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Client;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Novo Recibo')]
class ReceiptCreate extends Component
{
    public $receiptId = null;
    public $isEdit = false;
    
    // Campos do formulário
    public $type = 'sale';
    public $client_id = '';
    public $supplier_id = '';
    public $invoice_id = '';
    public $payment_date;
    public $payment_method = 'cash';
    public $amount_paid = 0;
    public $reference = '';
    public $notes = '';
    
    // Search
    public $searchClient = '';
    public $searchSupplier = '';
    
    protected function rules()
    {
        return [
            'type' => 'required|in:sale,purchase',
            'client_id' => 'required_if:type,sale',
            'supplier_id' => 'required_if:type,purchase',
            'payment_date' => 'required|date',
            'payment_method' => 'required',
            'amount_paid' => 'required|numeric|min:0.01',
        ];
    }

    protected $messages = [
        'client_id.required_if' => 'O cliente é obrigatório para recibos de venda.',
        'supplier_id.required_if' => 'O fornecedor é obrigatório para recibos de compra.',
        'amount_paid.required' => 'O valor pago é obrigatório.',
        'amount_paid.min' => 'O valor deve ser maior que zero.',
    ];

    public function mount($id = null)
    {
        $this->payment_date = date('Y-m-d');
        
        if ($id) {
            $this->isEdit = true;
            $this->receiptId = $id;
            $this->loadReceipt($id);
        }
    }

    public function loadReceipt($id)
    {
        $receipt = Receipt::where('tenant_id', activeTenantId())
            ->findOrFail($id);

        $this->type = $receipt->type;
        $this->client_id = $receipt->client_id;
        $this->supplier_id = $receipt->supplier_id;
        $this->invoice_id = $receipt->invoice_id;
        $this->payment_date = $receipt->payment_date->format('Y-m-d');
        $this->payment_method = $receipt->payment_method;
        $this->amount_paid = $receipt->amount_paid;
        $this->reference = $receipt->reference;
        $this->notes = $receipt->notes;
    }

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
        $this->invoice_id = ''; // Reset invoice quando muda cliente
    }

    public function selectSupplier($supplierId)
    {
        $this->supplier_id = $supplierId;
        $this->searchSupplier = '';
        $this->invoice_id = ''; // Reset invoice quando muda fornecedor
    }

    public function updatedType()
    {
        // Reset campos ao mudar tipo
        $this->client_id = '';
        $this->supplier_id = '';
        $this->invoice_id = '';
        $this->searchClient = '';
        $this->searchSupplier = '';
    }

    public function save()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $receipt = Receipt::where('tenant_id', activeTenantId())
                    ->findOrFail($this->receiptId);
                
                $receipt->update([
                    'type' => $this->type,
                    'client_id' => $this->type === 'sale' ? $this->client_id : null,
                    'supplier_id' => $this->type === 'purchase' ? $this->supplier_id : null,
                    'invoice_id' => $this->invoice_id ?: null,
                    'payment_date' => $this->payment_date,
                    'payment_method' => $this->payment_method,
                    'amount_paid' => $this->amount_paid,
                    'reference' => $this->reference,
                    'notes' => $this->notes,
                ]);
            } else {
                $receipt = Receipt::create([
                    'tenant_id' => activeTenantId(),
                    'type' => $this->type,
                    'client_id' => $this->type === 'sale' ? $this->client_id : null,
                    'supplier_id' => $this->type === 'purchase' ? $this->supplier_id : null,
                    'invoice_id' => $this->invoice_id ?: null,
                    'payment_date' => $this->payment_date,
                    'payment_method' => $this->payment_method,
                    'amount_paid' => $this->amount_paid,
                    'reference' => $this->reference,
                    'notes' => $this->notes,
                    'status' => 'issued',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Recibo ' . ($this->isEdit ? 'atualizado' : 'criado') . ' com sucesso!'
            ]);

            return redirect()->route('invoicing.receipts.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao salvar recibo: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        // Buscar clientes
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchClient . '%');
            });
        } elseif ($this->client_id) {
            $clientsQuery->where('id', $this->client_id);
        }
        
        $clients = $clientsQuery->orderBy('name')->limit(50)->get();

        // Buscar fornecedores
        $suppliersQuery = Supplier::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchSupplier) {
            $suppliersQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchSupplier . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchSupplier . '%');
            });
            $suppliersQuery->where('id', $this->supplier_id);
        }
        
        $suppliers = $suppliersQuery->orderBy('name')->limit(50)->get();

        // Buscar faturas do cliente/fornecedor (apenas não pagas ou parcialmente pagas)
        $invoices = collect();
        if ($this->client_id || $this->supplier_id) {
            $query = SalesInvoice::where('tenant_id', activeTenantId());
            
            if ($this->type === 'sale' && $this->client_id) {
                $query->where('client_id', $this->client_id);
            } elseif ($this->type === 'purchase' && $this->supplier_id) {
                // Para faturas de compra, ajustar para PurchaseInvoice
                $query = PurchaseInvoice::where('tenant_id', activeTenantId());
            }
            
            // Apenas faturas pendentes ou parcialmente pagas
            $invoices = $query->whereIn('status', ['pending', 'partially_paid'])
                ->orderBy('invoice_date', 'desc')
                ->get();
        }

        return view('livewire.invoicing.receipts.create', [
            'clients' => $clients,
            'suppliers' => $suppliers,
            'invoices' => $invoices,
        ]);
    }
}

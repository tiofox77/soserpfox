<?php

namespace App\Livewire\Invoicing\ProductBatches;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use App\Models\Invoicing\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Lotes e Validades')]
class ProductBatches extends Component
{
    use WithPagination;

    // Filtros
    public $search = '';
    public $filterProduct = '';
    public $filterWarehouse = '';
    public $filterStatus = '';
    
    // Modal
    public $showModal = false;
    public $editingId = null;
    
    // Form
    public $product_id;
    public $warehouse_id;
    public $batch_number;
    public $manufacturing_date;
    public $expiry_date;
    public $quantity;
    public $cost_price;
    public $alert_days = 30;
    public $notes;

    protected function rules()
    {
        return [
            'product_id' => 'required|exists:invoicing_products,id',
            'warehouse_id' => 'nullable|exists:invoicing_warehouses,id',
            'batch_number' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:manufacturing_date',
            'quantity' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'alert_days' => 'required|integer|min:1|max:365',
            'notes' => 'nullable|string',
        ];
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('invoicing.product-batches.create'), 403, 'Sem permissão para criar lotes.');
        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * O lote é procurado pelo id E pela empresa activa.
     *
     * O id chega do cliente, e sem o filtro qualquer id servia — inclusive o
     * de outra empresa. Hoje o modelo já tem o BelongsToTenant e o global
     * scope faz isto sozinho, mas a condição fica escrita: uma chamada a
     * withoutGlobalScopes() em qualquer ponto da cadeia não pode reabrir a
     * porta.
     */
    private function loteDaEmpresa($id): ProductBatch
    {
        return ProductBatch::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function edit($id)
    {
        abort_unless(auth()->user()?->can('invoicing.product-batches.edit'), 403, 'Sem permissão para editar lotes.');
        $batch = $this->loteDaEmpresa($id);

        $this->editingId = $id;
        $this->product_id = $batch->product_id;
        $this->warehouse_id = $batch->warehouse_id;
        $this->batch_number = $batch->batch_number;
        $this->manufacturing_date = $batch->manufacturing_date?->format('Y-m-d');
        $this->expiry_date = $batch->expiry_date?->format('Y-m-d');
        $this->quantity = $batch->quantity;
        $this->cost_price = $batch->cost_price;
        $this->alert_days = $batch->alert_days;
        $this->notes = $batch->notes;
        
        $this->showModal = true;
    }

    public function save()
    {
        abort_unless(
            auth()->user()?->can('invoicing.product-batches.create') ||
            auth()->user()?->can('invoicing.product-batches.edit'),
            403, 'Sem permissão para guardar lote.'
        );
        $gestor = app(\App\Services\Invoicing\GestorDeLotes::class);

        $this->validate($gestor->regras());

        // O REGISTO VIVE NO `GestorDeLotes`: o que já saiu do lote é um facto
        // e o disponível é o total novo menos isso. Este ecrã e o ecrã em
        // React chamam o mesmo.
        $dados = [
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'batch_number' => $this->batch_number,
            'manufacturing_date' => $this->manufacturing_date,
            'expiry_date' => $this->expiry_date,
            'quantity' => $this->quantity,
            'cost_price' => $this->cost_price,
            'alert_days' => $this->alert_days,
            'notes' => $this->notes,
        ];

        try {
            if ($this->editingId) {
                $gestor->actualizar($this->loteDaEmpresa($this->editingId), $dados);
                $message = 'Lote atualizado com sucesso!';
            } else {
                $gestor->criar($dados, activeTenantId());
                $message = 'Lote criado com sucesso!';
            }

            $this->dispatch('success', message: $message);
            $this->closeModal();

        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        abort_unless(auth()->user()?->can('invoicing.product-batches.delete'), 403, 'Sem permissão para excluir lotes.');
        try {
            app(\App\Services\Invoicing\GestorDeLotes::class)->apagar($this->loteDaEmpresa($id));
            $this->dispatch('success', message: 'Lote excluído com sucesso!');
            
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset([
            'editingId',
            'product_id',
            'warehouse_id',
            'batch_number',
            'manufacturing_date',
            'expiry_date',
            'quantity',
            'cost_price',
            'notes'
        ]);
        $this->alert_days = 30;
    }

    public function render()
    {
        $batches = ProductBatch::with(['product', 'warehouse'])
            ->where('tenant_id', activeTenantId())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('batch_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('product', function ($p) {
                          $p->where('name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->filterProduct, function ($query) {
                $query->where('product_id', $this->filterProduct);
            })
            ->when($this->filterWarehouse, function ($query) {
                $query->where('warehouse_id', $this->filterWarehouse);
            })
            ->when($this->filterStatus, function ($query) {
                if ($this->filterStatus === 'expiring_soon') {
                    $query->expiringSoon();
                } elseif ($this->filterStatus === 'expired') {
                    $query->expired();
                } else {
                    $query->where('status', $this->filterStatus);
                }
            })
            ->orderBy('expiry_date', 'asc')
            ->paginate(15);
            
        // Stats
        $expiringCount = ProductBatch::where('tenant_id', activeTenantId())
            ->expiringSoon()
            ->count();
            
        $expiredCount = ProductBatch::where('tenant_id', activeTenantId())
            ->expired()
            ->count();
            
        $activeCount = ProductBatch::where('tenant_id', activeTenantId())
            ->active()
            ->count();
        
        $products = Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.product-batches.product-batches', compact(
            'batches',
            'expiringCount',
            'expiredCount',
            'activeCount',
            'products',
            'warehouses'
        ));
    }
}

<?php

namespace App\Livewire\Invoicing\Imports;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Invoicing\Import;
use App\Models\Supplier;
use App\Models\Invoicing\Warehouse;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Importações')]
class Imports extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterStatus = '';
    public $filterSupplier = '';
    public $perPage = 15;
    
    // Modal create/edit
    public $showModal = false;
    public $editingImport = null;
    public $isEditing = false;
    
    // Form fields
    public $supplier_id = '';
    public $warehouse_id = '';
    public $reference = '';
    public $order_date = '';
    public $expected_arrival_date = '';
    public $origin_country = '';
    public $origin_port = '';
    public $destination_port = 'Luanda';
    public $shipping_company = '';
    public $transport_type = 'maritime';
    public $fob_value = 0;
    public $freight_cost = 0;
    public $insurance_cost = 0;
    public $cif_value = 0;
    public $notes = '';
    
    // Calcular CIF automaticamente quando mudar valores
    public function updatedFobValue()
    {
        $this->calculateCIF();
    }
    
    public function updatedFreightCost()
    {
        $this->calculateCIF();
    }
    
    public function updatedInsuranceCost()
    {
        $this->calculateCIF();
    }
    
    private function calculateCIF()
    {
        $this->cif_value = ($this->fob_value ?: 0) + ($this->freight_cost ?: 0) + ($this->insurance_cost ?: 0);
    }
    
    // Modal delete
    public $showDeleteModal = false;
    public $deletingImport = null;
    
    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterSupplier' => ['except' => ''],
    ];
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingFilterStatus()
    {
        $this->resetPage();
    }
    
    public function updatingFilterSupplier()
    {
        $this->resetPage();
    }
    
    public function openCreateModal()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }
    
    public function openEditModal($id)
    {
        $this->editingImport = Import::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->supplier_id = $this->editingImport->supplier_id;
        $this->warehouse_id = $this->editingImport->warehouse_id;
        $this->reference = $this->editingImport->reference;
        $this->order_date = $this->editingImport->order_date?->format('Y-m-d');
        $this->expected_arrival_date = $this->editingImport->expected_arrival_date?->format('Y-m-d');
        $this->origin_country = $this->editingImport->origin_country;
        $this->origin_port = $this->editingImport->origin_port;
        $this->destination_port = $this->editingImport->destination_port;
        $this->shipping_company = $this->editingImport->shipping_company;
        $this->transport_type = $this->editingImport->transport_type;
        $this->fob_value = $this->editingImport->fob_value;
        $this->freight_cost = $this->editingImport->freight_cost;
        $this->insurance_cost = $this->editingImport->insurance_cost;
        $this->cif_value = $this->editingImport->cif_value;
        $this->notes = $this->editingImport->notes;
        
        $this->isEditing = true;
        $this->showModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function save()
    {
        $this->validate([
            'supplier_id' => 'required|exists:invoicing_suppliers,id',
            'warehouse_id' => 'nullable|exists:invoicing_warehouses,id',
            'order_date' => 'required|date',
            'expected_arrival_date' => 'nullable|date|after_or_equal:order_date',
            'origin_country' => 'required|string|max:100',
            'transport_type' => 'required|in:maritime,air,land',
            'fob_value' => 'required|numeric|min:0',
            'freight_cost' => 'nullable|numeric|min:0',
            'insurance_cost' => 'nullable|numeric|min:0',
        ]);
        
        DB::beginTransaction();
        
        try {
            $data = [
                'tenant_id' => activeTenantId(),
                'supplier_id' => $this->supplier_id,
                'warehouse_id' => $this->warehouse_id,
                'reference' => $this->reference,
                'order_date' => $this->order_date,
                'expected_arrival_date' => $this->expected_arrival_date,
                'origin_country' => $this->origin_country,
                'origin_port' => $this->origin_port,
                'destination_port' => $this->destination_port ?: 'Luanda',
                'shipping_company' => $this->shipping_company,
                'transport_type' => $this->transport_type,
                'fob_value' => $this->fob_value ?: 0,
                'freight_cost' => $this->freight_cost ?: 0,
                'insurance_cost' => $this->insurance_cost ?: 0,
                'notes' => $this->notes,
                'status' => 'quotation',
            ];
            
            // Calcular CIF automaticamente
            $data['cif_value'] = $data['fob_value'] + $data['freight_cost'] + $data['insurance_cost'];
            
            if ($this->isEditing) {
                $this->editingImport->update($data);
                $message = 'Importação atualizada com sucesso!';
            } else {
                Import::create($data);
                $message = 'Importação criada com sucesso!';
            }
            
            DB::commit();
            
            $this->dispatch('success', message: $message);
            $this->closeModal();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Erro ao salvar importação: ' . $e->getMessage());
        }
    }
    
    public function confirmDelete($id)
    {
        $this->deletingImport = Import::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->showDeleteModal = true;
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingImport = null;
    }
    
    public function deleteImport()
    {
        if (!$this->deletingImport) {
            return;
        }
        
        try {
            $this->deletingImport->delete();
            $this->dispatch('success', message: 'Importação eliminada com sucesso!');
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao eliminar importação: ' . $e->getMessage());
        }
    }
    
    public function changeStatus($id, $newStatus)
    {
        try {
            $import = Import::where('tenant_id', activeTenantId())->findOrFail($id);
            $import->status = $newStatus;
            $import->save();
            
            $this->dispatch('success', message: 'Status atualizado para: ' . $import->status_label);
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao atualizar status: ' . $e->getMessage());
        }
    }
    
    public function viewImport($id)
    {
        // Redirecionar para página de detalhes ou abrir modal
        $this->dispatch('info', message: 'Visualização de importação em desenvolvimento');
        // TODO: Implementar modal de visualização ou redirect
    }
    
    public function printImport($id)
    {
        // Redirecionar para PDF ou abrir em nova aba
        $this->dispatch('info', message: 'Impressão de importação em desenvolvimento');
        // TODO: return redirect()->route('invoicing.imports.pdf', $id);
    }
    
    private function resetForm()
    {
        $this->reset([
            'editingImport',
            'supplier_id',
            'warehouse_id',
            'reference',
            'order_date',
            'expected_arrival_date',
            'origin_country',
            'origin_port',
            'destination_port',
            'shipping_company',
            'transport_type',
            'fob_value',
            'freight_cost',
            'insurance_cost',
            'notes',
        ]);
        
        $this->destination_port = 'Luanda';
        $this->transport_type = 'maritime';
    }
    
    public function render()
    {
        $query = Import::with(['supplier', 'warehouse'])
            ->where('tenant_id', activeTenantId());
        
        // Busca
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('import_number', 'like', '%' . $this->search . '%')
                  ->orWhere('reference', 'like', '%' . $this->search . '%')
                  ->orWhere('container_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('supplier', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }
        
        // Filtro por status
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        
        // Filtro por fornecedor
        if ($this->filterSupplier) {
            $query->where('supplier_id', $this->filterSupplier);
        }
        
        $imports = $query->orderBy('created_at', 'desc')->paginate($this->perPage);
        
        // Stats
        $stats = [
            'total' => Import::where('tenant_id', activeTenantId())->count(),
            'in_transit' => Import::where('tenant_id', activeTenantId())->where('status', 'in_transit')->count(),
            'customs_pending' => Import::where('tenant_id', activeTenantId())
                ->whereIn('status', ['customs_pending', 'customs_inspection'])->count(),
            'total_value' => Import::where('tenant_id', activeTenantId())
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->sum('cif_value'),
        ];
        
        $suppliers = Supplier::where('tenant_id', activeTenantId())
            ->orderBy('name')
            ->get();
        
        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        
        return view('livewire.invoicing.imports.imports', [
            'imports' => $imports,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ]);
    }
}

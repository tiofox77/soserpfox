<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Tax;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Gestão de Impostos')]
class TaxManagement extends Component
{
    use WithPagination;

    // Filtros
    public $search = '';
    public $filterType = '';
    
    // Modal
    public $showModal = false;
    public $taxId = null;
    public $isEdit = false;
    
    // Form fields
    public $code = '';
    public $name = '';
    public $description = '';
    public $rate = 14.00;
    public $type = 'iva';
    public $saft_code = 'NOR';
    public $saft_type = 'NOR';
    public $exemption_reason = '';
    public $is_default = false;
    public $is_active = true;
    public $include_in_price = false;
    
    protected $rules = [
        'code' => 'required|max:20',
        'name' => 'required|max:100',
        'rate' => 'required|numeric|min:0|max:100',
        'type' => 'required|in:iva,irt,other',
        'saft_type' => 'required|in:NOR,RED,ISE,NS,OUT',
    ];

    public function openCreateModal()
    {
        $this->reset([
            'taxId', 'code', 'name', 'description', 'rate', 'type',
            'saft_code', 'saft_type', 'exemption_reason', 'is_default',
            'is_active', 'include_in_price'
        ]);
        $this->isEdit = false;
        $this->rate = 14.00;
        $this->type = 'iva';
        $this->saft_code = 'NOR';
        $this->saft_type = 'NOR';
        $this->is_active = true;
        $this->showModal = true;
    }

    public function editTax($id)
    {
        $tax = Tax::forTenant(activeTenantId())->findOrFail($id);
        
        $this->taxId = $tax->id;
        $this->code = $tax->code;
        $this->name = $tax->name;
        $this->description = $tax->description;
        $this->rate = $tax->rate;
        $this->type = $tax->type;
        $this->saft_code = $tax->saft_code;
        $this->saft_type = $tax->saft_type;
        $this->exemption_reason = $tax->exemption_reason;
        $this->is_default = $tax->is_default;
        $this->is_active = $tax->is_active;
        $this->include_in_price = $tax->include_in_price;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            // Se marcar como padrão, desmarcar outros
            if ($this->is_default) {
                Tax::where('tenant_id', activeTenantId())
                    ->update(['is_default' => false]);
            }

            if ($this->isEdit) {
                $tax = Tax::forTenant(activeTenantId())->findOrFail($this->taxId);
                $tax->update([
                    'code' => $this->code,
                    'name' => $this->name,
                    'description' => $this->description,
                    'rate' => $this->rate,
                    'type' => $this->type,
                    'saft_code' => $this->saft_code,
                    'saft_type' => $this->saft_type,
                    'exemption_reason' => $this->exemption_reason,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'include_in_price' => $this->include_in_price,
                ]);
                
                $message = 'Imposto atualizado com sucesso!';
            } else {
                Tax::create([
                    'tenant_id' => activeTenantId(),
                    'code' => $this->code,
                    'name' => $this->name,
                    'description' => $this->description,
                    'rate' => $this->rate,
                    'type' => $this->type,
                    'saft_code' => $this->saft_code,
                    'saft_type' => $this->saft_type,
                    'exemption_reason' => $this->exemption_reason,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'include_in_price' => $this->include_in_price,
                    'compound_tax' => false,
                ]);
                
                $message = 'Imposto criado com sucesso!';
            }

            $this->showModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        $query = Tax::forTenant(activeTenantId())
            ->orderBy('is_default', 'desc')
            ->orderBy('type')
            ->orderBy('rate', 'desc');

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $taxes = $query->paginate(15);

        return view('livewire.invoicing.tax-management', [
            'taxes' => $taxes,
        ]);
    }
}

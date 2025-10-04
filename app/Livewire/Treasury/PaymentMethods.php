<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\PaymentMethod;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
#[Title('Métodos de Pagamento')]
class PaymentMethods extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterStatus = '';
    public $perPage = 10;
    
    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;
    
    public $methodId;
    public $methodToDeleteName;
    
    public $form = [
        'name' => '',
        'code' => '',
        'type' => 'manual',
        'description' => '',
        'icon' => 'fa-money-bill',
        'color' => 'green',
        'fee_percentage' => 0,
        'fee_fixed' => 0,
        'requires_account' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:treasury_payment_methods,code,' . ($this->methodId ?? 'NULL'),
            'form.type' => 'required|in:manual,automatic,online',
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.color' => 'nullable|string|max:255',
            'form.fee_percentage' => 'nullable|numeric|min:0|max:100',
            'form.fee_fixed' => 'nullable|numeric|min:0',
            'form.requires_account' => 'boolean',
            'form.is_active' => 'boolean',
            'form.sort_order' => 'nullable|integer',
        ];
    }
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingFilterStatus()
    {
        $this->resetPage();
    }
    
    public function create()
    {
        $this->reset(['form', 'methodId', 'editMode']);
        $this->form['is_active'] = true;
        $this->form['type'] = 'manual';
        $this->form['icon'] = 'fa-money-bill';
        $this->form['color'] = 'green';
        $this->showModal = true;
    }
    
    public function edit($id)
    {
        $method = PaymentMethod::findOrFail($id);
        
        $this->methodId = $method->id;
        $this->editMode = true;
        
        $this->form = [
            'name' => $method->name,
            'code' => $method->code,
            'type' => $method->type,
            'description' => $method->description,
            'icon' => $method->icon,
            'color' => $method->color,
            'fee_percentage' => $method->fee_percentage,
            'fee_fixed' => $method->fee_fixed,
            'requires_account' => $method->requires_account,
            'is_active' => $method->is_active,
            'sort_order' => $method->sort_order,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();
        
        $data = array_merge($this->form, [
            'tenant_id' => auth()->user()->tenant_id,
        ]);
        
        if ($this->editMode) {
            $method = PaymentMethod::findOrFail($this->methodId);
            $method->update($data);
            
            $this->dispatch('success', message: 'Método de pagamento atualizado com sucesso!');
        } else {
            PaymentMethod::create($data);
            
            $this->dispatch('success', message: 'Método de pagamento criado com sucesso!');
        }
        
        $this->closeModal();
        $this->dispatch('refreshComponent');
    }
    
    public function toggleStatus($id)
    {
        $method = PaymentMethod::findOrFail($id);
        $method->update(['is_active' => !$method->is_active]);
        
        $status = $method->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Método de pagamento {$status} com sucesso!");
    }
    
    public function confirmDelete($id)
    {
        $method = PaymentMethod::findOrFail($id);
        $this->methodId = $method->id;
        $this->methodToDeleteName = $method->name;
        $this->showDeleteModal = true;
    }
    
    public function deletePaymentMethod()
    {
        PaymentMethod::findOrFail($this->methodId)->delete();
        
        $this->dispatch('success', message: 'Método de pagamento eliminado com sucesso!');
        
        $this->closeDeleteModal();
        $this->dispatch('refreshComponent');
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['form', 'methodId', 'editMode']);
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->reset(['methodId', 'methodToDeleteName']);
    }
    
    public function render()
    {
        $query = PaymentMethod::where('tenant_id', auth()->user()->tenant_id);
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }
        
        // Filter by status
        if ($this->filterStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false);
        }
        
        $paymentMethods = $query->orderBy('sort_order')->orderBy('name')->paginate($this->perPage);
        
        $activeCount = PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)->count();
            
        $inactiveCount = PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', false)->count();
        
        return view('livewire.treasury.payment-methods.payment-methods', [
            'paymentMethods' => $paymentMethods,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ]);
    }
}

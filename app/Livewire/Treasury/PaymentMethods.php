<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
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
        'default_account_id' => null,
        'default_cash_register_id' => null,
        'is_active' => true,
        'sort_order' => 0,
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.name' => 'required|string|max:255',
            // Unicidade é POR EMPRESA (índice real: tenant_id + code). A regra
            // global fazia o ecrã recusar 'CASH', 'TPA', etc. só porque outra
            // empresa já os tinha — e este é o único ecrã onde um cliente sem
            // métodos de pagamento se podia desenrascar.
            'form.code' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('treasury_payment_methods', 'code')
                    ->where(fn($q) => $q->where('tenant_id', activeTenantId()))
                    ->ignore($this->methodId),
            ],
            // Tipos REAIS da tabela (antes exigia manual/automatic/online, que
            // não existem — nenhum método podia ser criado).
            'form.type' => 'required|in:cash,card,bank_transfer,digital_wallet,check,other',
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.color' => 'nullable|string|max:255',
            'form.fee_percentage' => 'nullable|numeric|min:0|max:100',
            'form.fee_fixed' => 'nullable|numeric|min:0',
            'form.requires_account' => 'boolean',
            'form.default_account_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('treasury_accounts', 'id')->where('tenant_id', activeTenantId()),
            ],
            'form.default_cash_register_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('treasury_cash_registers', 'id')->where('tenant_id', activeTenantId()),
            ],
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
        $this->form['type'] = 'cash';
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
            'default_account_id' => $method->default_account_id,
            'default_cash_register_id' => $method->default_cash_register_id,
            'is_active' => $method->is_active,
            'sort_order' => $method->sort_order,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();

        if ($this->form['type'] === 'cash') {
            $this->form['default_account_id'] = null;
            $this->form['requires_account'] = false;
        } else {
            $this->form['default_cash_register_id'] = null;
            $this->form['requires_account'] = true;
        }
        
        $data = array_merge($this->form, [
            'tenant_id' => activeTenantId(),
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
        $query = PaymentMethod::with(['defaultAccount.bank', 'defaultCashRegister'])
            ->where('tenant_id', activeTenantId());
        
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
        
        $activeCount = PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)->count();
            
        $inactiveCount = PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', false)->count();

        $accounts = Account::where('tenant_id', activeTenantId())->where('is_active', true)
            ->with('bank')->orderByDesc('is_default')->orderBy('account_name')->get();
        $cashRegisters = CashRegister::where('tenant_id', activeTenantId())->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->get();
        
        return view('livewire.treasury.payment-methods.payment-methods', [
            'paymentMethods' => $paymentMethods,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'accounts' => $accounts,
            'cashRegisters' => $cashRegisters,
        ]);
    }
}

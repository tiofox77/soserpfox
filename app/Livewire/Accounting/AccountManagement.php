<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Tax;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class AccountManagement extends Component
{
    use WithPagination;
    
    public $search = '';
    public $typeFilter = '';
    public $levelFilter = '';
    public $natureFilter = '';
    public $statusFilter = '';
    public $showModal = false;
    public $showViewModal = false;
    public $editMode = false;
    public $viewAccount;
    
    // Resetar paginação quando filtros mudarem
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingTypeFilter()
    {
        $this->resetPage();
    }
    
    public function updatingLevelFilter()
    {
        $this->resetPage();
    }
    
    public function updatingNatureFilter()
    {
        $this->resetPage();
    }
    
    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    
    // Form fields
    public $accountId;
    public $code;
    public $name;
    public $type;
    public $nature;
    public $parent_id;
    public $level = 1;
    public $is_view = false;
    public $blocked = false;
    public $description;
    
    // Novos campos avançados
    public $default_tax_id;
    public $debit_reflection_account_id;
    public $credit_reflection_account_id;
    public $default_cost_center_id;
    public $account_key;
    public $is_fixed_cost = false;
    public $account_subtype;
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $accounts = Account::where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('code', 'like', '%'.$this->search.'%')
                      ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->typeFilter, function($query) {
                $query->where('type', $this->typeFilter);
            })
            ->when($this->levelFilter, function($query) {
                $query->where('level', $this->levelFilter);
            })
            ->when($this->natureFilter, function($query) {
                $query->where('nature', $this->natureFilter);
            })
            ->when($this->statusFilter !== '', function($query) {
                if ($this->statusFilter === 'active') {
                    $query->where('blocked', false);
                } elseif ($this->statusFilter === 'blocked') {
                    $query->where('blocked', true);
                }
            })
            ->orderBy('code')
            ->paginate(20);
            
        // Stats
        $totalAccounts = Account::where('tenant_id', $tenantId)->count();
        $totalAssets = Account::where('tenant_id', $tenantId)->where('type', 'asset')->count();
        $totalLiabilities = Account::where('tenant_id', $tenantId)->where('type', 'liability')->count();
        $totalRevenue = Account::where('tenant_id', $tenantId)->where('type', 'revenue')->count();
        
        $parentAccounts = Account::where('tenant_id', $tenantId)
            ->where('is_view', true)
            ->orderBy('code')
            ->get();
        
        // Carregar dados para aba avançada
        $availableTaxes = Tax::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get();
        
        $availableCostCenters = CostCenter::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        
        return view('livewire.accounting.accounts.accounts', [
            'accounts' => $accounts,
            'totalAccounts' => $totalAccounts,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalRevenue' => $totalRevenue,
            'parentAccounts' => $parentAccounts,
            'availableTaxes' => $availableTaxes,
            'availableCostCenters' => $availableCostCenters,
        ]);
    }
    
    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
        $this->editMode = false;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function view($id)
    {
        $this->viewAccount = Account::with([
            'parent',
            'defaultTax',
            'defaultCostCenter',
            'debitReflectionAccount',
            'creditReflectionAccount'
        ])->find($id);
        
        $this->showViewModal = true;
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewAccount = null;
    }
    
    public function edit($id)
    {
        $account = Account::find($id);
        
        $this->accountId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->nature = $account->nature;
        $this->parent_id = $account->parent_id;
        $this->level = $account->level;
        $this->is_view = $account->is_view;
        $this->blocked = $account->blocked;
        $this->description = $account->description;
        
        // Novos campos avançados
        $this->default_tax_id = $account->default_tax_id;
        $this->debit_reflection_account_id = $account->debit_reflection_account_id;
        $this->credit_reflection_account_id = $account->credit_reflection_account_id;
        $this->default_cost_center_id = $account->default_cost_center_id;
        $this->account_key = $account->account_key;
        $this->is_fixed_cost = $account->is_fixed_cost;
        $this->account_subtype = $account->account_subtype;
        
        $this->editMode = true;
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate([
            'code' => 'required|max:20',
            'name' => 'required|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'nature' => 'required|in:debit,credit',
        ]);
        
        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'nature' => $this->nature,
            'parent_id' => $this->parent_id,
            'level' => $this->level,
            'is_view' => $this->is_view,
            'blocked' => $this->blocked,
            'description' => $this->description,
            // Novos campos avançados
            'default_tax_id' => $this->default_tax_id ?: null,
            'debit_reflection_account_id' => $this->debit_reflection_account_id ?: null,
            'credit_reflection_account_id' => $this->credit_reflection_account_id ?: null,
            'default_cost_center_id' => $this->default_cost_center_id ?: null,
            'account_key' => $this->account_key,
            'is_fixed_cost' => $this->is_fixed_cost,
            'account_subtype' => $this->account_subtype,
        ];
        
        if ($this->editMode) {
            Account::find($this->accountId)->update($data);
            session()->flash('message', 'Conta atualizada com sucesso!');
        } else {
            Account::create($data);
            session()->flash('message', 'Conta criada com sucesso!');
        }
        
        $this->closeModal();
    }
    
    public function delete($id)
    {
        Account::find($id)->delete();
        session()->flash('message', 'Conta excluída com sucesso!');
    }
    
    public function clearFilters()
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->levelFilter = '';
        $this->natureFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }
    
    private function resetForm()
    {
        $this->accountId = null;
        $this->code = '';
        $this->name = '';
        $this->type = '';
        $this->nature = 'debit';
        $this->parent_id = null;
        $this->level = 1;
        $this->is_view = false;
        $this->blocked = false;
        $this->description = '';
        
        // Resetar novos campos avançados
        $this->default_tax_id = null;
        $this->debit_reflection_account_id = null;
        $this->credit_reflection_account_id = null;
        $this->default_cost_center_id = null;
        $this->account_key = '';
        $this->is_fixed_cost = false;
        $this->account_subtype = '';
    }
}

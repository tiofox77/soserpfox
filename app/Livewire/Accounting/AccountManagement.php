<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Accounting\Account;

#[Layout('layouts.app')]
class AccountManagement extends Component
{
    use WithPagination;
    
    public $search = '';
    public $typeFilter = '';
    public $showModal = false;
    public $editMode = false;
    
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
        
        return view('livewire.accounting.accounts.accounts', [
            'accounts' => $accounts,
            'totalAccounts' => $totalAccounts,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalRevenue' => $totalRevenue,
            'parentAccounts' => $parentAccounts,
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
    }
}

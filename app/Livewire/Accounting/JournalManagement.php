<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Account;

#[Layout('layouts.app')]
class JournalManagement extends Component
{
    use WithPagination;
    
    public $search = '';
    public $typeFilter = '';
    public $showModal = false;
    public $editMode = false;
    
    // Form fields
    public $journalId;
    public $code;
    public $name;
    public $type;
    public $sequence_prefix;
    public $last_number = 0;
    public $default_debit_account_id;
    public $default_credit_account_id;
    public $active = true;
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $journals = Journal::where('tenant_id', $tenantId)
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
            ->paginate(15);
            
        $accounts = Account::where('tenant_id', $tenantId)
            ->where('blocked', false)
            ->orderBy('code')
            ->get();
        
        // Stats
        $allJournals = Journal::where('tenant_id', $tenantId)->get();
        $stats = [
            'total' => $allJournals->count(),
            'active' => $allJournals->where('active', true)->count(),
            'sales_purchase' => $allJournals->whereIn('type', ['sale', 'purchase'])->count(),
            'bank_cash' => $allJournals->whereIn('type', ['bank', 'cash'])->count(),
        ];
        
        return view('livewire.accounting.journals.journals', [
            'journals' => $journals,
            'accounts' => $accounts,
            'stats' => $stats,
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
        $journal = Journal::find($id);
        
        $this->journalId = $journal->id;
        $this->code = $journal->code;
        $this->name = $journal->name;
        $this->type = $journal->type;
        $this->sequence_prefix = $journal->sequence_prefix;
        $this->last_number = $journal->last_number;
        $this->default_debit_account_id = $journal->default_debit_account_id;
        $this->default_credit_account_id = $journal->default_credit_account_id;
        $this->active = $journal->active;
        
        $this->editMode = true;
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate([
            'code' => 'required|max:20',
            'name' => 'required|max:255',
            'type' => 'required|in:sale,purchase,cash,bank,payroll,adjustment',
            'sequence_prefix' => 'required|max:10',
        ]);
        
        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'sequence_prefix' => $this->sequence_prefix,
            'last_number' => $this->last_number,
            'default_debit_account_id' => $this->default_debit_account_id,
            'default_credit_account_id' => $this->default_credit_account_id,
            'active' => $this->active,
        ];
        
        if ($this->editMode) {
            Journal::find($this->journalId)->update($data);
            session()->flash('message', 'Diário atualizado com sucesso!');
        } else {
            Journal::create($data);
            session()->flash('message', 'Diário criado com sucesso!');
        }
        
        $this->closeModal();
    }
    
    public function delete($id)
    {
        Journal::find($id)->delete();
        session()->flash('message', 'Diário excluído com sucesso!');
    }
    
    private function resetForm()
    {
        $this->journalId = null;
        $this->code = '';
        $this->name = '';
        $this->type = '';
        $this->sequence_prefix = '';
        $this->last_number = 0;
        $this->default_debit_account_id = null;
        $this->default_credit_account_id = null;
        $this->active = true;
    }
}

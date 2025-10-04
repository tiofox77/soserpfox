<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;

#[Layout('layouts.app')]
#[Title('Contas Bancárias')]
class Accounts extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterCurrency = '';
    public $perPage = 10;
    
    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;
    
    public $accountId;
    public $accountToDeleteName;
    
    public $form = [
        'bank_id' => '',
        'account_name' => '',
        'account_number' => '',
        'iban' => '',
        'currency' => 'AOA',
        'account_type' => 'checking',
        'initial_balance' => 0,
        'manager_name' => '',
        'manager_phone' => '',
        'manager_email' => '',
        'notes' => '',
        'is_active' => true,
        'is_default' => false,
        'show_on_invoice' => false,
        'invoice_display_order' => null,
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.bank_id' => 'required|exists:treasury_banks,id',
            'form.account_name' => 'required|string|max:255',
            'form.account_number' => 'required|string|max:255',
            'form.iban' => 'nullable|string|max:255',
            'form.currency' => 'required|in:AOA,USD,EUR',
            'form.account_type' => 'required|in:checking,savings,investment',
            'form.initial_balance' => 'nullable|numeric|min:0',
            'form.manager_name' => 'nullable|string|max:255',
            'form.manager_phone' => 'nullable|string|max:255',
            'form.manager_email' => 'nullable|email|max:255',
            'form.notes' => 'nullable|string',
            'form.is_active' => 'boolean',
            'form.is_default' => 'boolean',
            'form.show_on_invoice' => 'boolean',
            'form.invoice_display_order' => 'nullable|integer|min:1|max:4',
        ];
    }
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingFilterCurrency()
    {
        $this->resetPage();
    }
    
    public function create()
    {
        $this->reset(['form', 'accountId', 'editMode']);
        $this->form['is_active'] = true;
        $this->form['currency'] = 'AOA';
        $this->form['account_type'] = 'checking';
        $this->form['initial_balance'] = 0;
        $this->showModal = true;
    }
    
    public function edit($id)
    {
        $account = Account::with('bank')->findOrFail($id);
        
        $this->accountId = $account->id;
        $this->editMode = true;
        
        $this->form = [
            'bank_id' => $account->bank_id,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'iban' => $account->iban,
            'currency' => $account->currency,
            'account_type' => $account->account_type,
            'initial_balance' => $account->initial_balance,
            'manager_name' => $account->manager_name,
            'manager_phone' => $account->manager_phone,
            'manager_email' => $account->manager_email,
            'notes' => $account->notes,
            'is_active' => $account->is_active,
            'is_default' => $account->is_default,
            'show_on_invoice' => $account->show_on_invoice,
            'invoice_display_order' => $account->invoice_display_order,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();
        
        // Verificar limite de 4 contas na fatura
        if ($this->form['show_on_invoice']) {
            $currentCount = Account::where('tenant_id', auth()->user()->tenant_id)
                ->where('show_on_invoice', true)
                ->when($this->editMode, fn($q) => $q->where('id', '!=', $this->accountId))
                ->count();
            
            if ($currentCount >= 4) {
                $this->addError('form.show_on_invoice', 'Já existem 4 contas marcadas para exibir na fatura. Desmarque uma antes de adicionar outra.');
                return;
            }
            
            // Se não definiu ordem, pegar a próxima disponível
            if (!$this->form['invoice_display_order']) {
                $maxOrder = Account::where('tenant_id', auth()->user()->tenant_id)
                    ->where('show_on_invoice', true)
                    ->max('invoice_display_order');
                $this->form['invoice_display_order'] = ($maxOrder ?? 0) + 1;
            }
        } else {
            // Se desmarcou, limpar a ordem
            $this->form['invoice_display_order'] = null;
        }
        
        $data = array_merge($this->form, [
            'tenant_id' => auth()->user()->tenant_id,
        ]);
        
        // Se está marcando como padrão, desmarcar outras contas da mesma moeda
        if ($this->form['is_default']) {
            Account::where('tenant_id', auth()->user()->tenant_id)
                ->where('currency', $this->form['currency'])
                ->update(['is_default' => false]);
        }
        
        if ($this->editMode) {
            $account = Account::findOrFail($this->accountId);
            
            // Não permitir alterar saldo inicial se já existem transações
            unset($data['initial_balance']);
            
            $account->update($data);
            
            session()->flash('message', 'Conta bancária atualizada com sucesso!');
        } else {
            // Ao criar, o saldo atual = saldo inicial
            $data['current_balance'] = $data['initial_balance'];
            
            Account::create($data);
            
            session()->flash('message', 'Conta bancária criada com sucesso!');
        }
        
        $this->closeModal();
        $this->dispatch('refreshComponent');
    }
    
    public function toggleStatus($id)
    {
        $account = Account::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);
        
        $status = $account->is_active ? 'ativada' : 'desativada';
        $this->dispatch('success', message: "Conta {$status} com sucesso!");
    }
    
    public function confirmDelete($id)
    {
        $account = Account::findOrFail($id);
        $this->accountId = $account->id;
        $this->accountToDeleteName = $account->account_name;
        $this->showDeleteModal = true;
    }
    
    public function deleteAccount()
    {
        Account::findOrFail($this->accountId)->delete();
        
        session()->flash('message', 'Conta bancária eliminada com sucesso!');
        
        $this->closeDeleteModal();
        $this->dispatch('refreshComponent');
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['form', 'accountId', 'editMode']);
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->reset(['accountId', 'accountToDeleteName']);
    }
    
    public function render()
    {
        $query = Account::with('bank')
            ->where('tenant_id', auth()->user()->tenant_id);
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('account_name', 'like', '%' . $this->search . '%')
                  ->orWhere('account_number', 'like', '%' . $this->search . '%')
                  ->orWhere('iban', 'like', '%' . $this->search . '%');
            });
        }
        
        // Filter by currency
        if ($this->filterCurrency) {
            $query->where('currency', $this->filterCurrency);
        }
        
        $accounts = $query->orderBy('is_default', 'desc')
            ->orderBy('account_name')
            ->paginate($this->perPage);
        
        $activeCount = Account::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)->count();
            
        $totalBalanceAOA = Account::where('tenant_id', auth()->user()->tenant_id)
            ->where('currency', 'AOA')
            ->where('is_active', true)
            ->sum('current_balance');
            
        $totalBalanceUSD = Account::where('tenant_id', auth()->user()->tenant_id)
            ->where('currency', 'USD')
            ->where('is_active', true)
            ->sum('current_balance');
        
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        
        return view('livewire.treasury.accounts.accounts', [
            'accounts' => $accounts,
            'activeCount' => $activeCount,
            'totalBalanceAOA' => $totalBalanceAOA,
            'totalBalanceUSD' => $totalBalanceUSD,
            'banks' => $banks,
        ]);
    }
}

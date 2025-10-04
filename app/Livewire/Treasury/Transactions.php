<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;

#[Layout('layouts.app')]
#[Title('Transações')]
class Transactions extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterType = '';
    public $filterStatus = '';
    public $perPage = 10;
    
    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;
    
    public $transactionId;
    public $transactionToDeleteNumber;
    
    public $form = [
        'type' => '',
        'category' => '',
        'amount' => 0,
        'currency' => 'AOA',
        'transaction_date' => '',
        'payment_method_id' => '',
        'account_id' => '',
        'cash_register_id' => '',
        'reference' => '',
        'description' => '',
        'notes' => '',
        'status' => 'completed',
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.type' => 'required|in:income,expense,transfer',
            'form.category' => 'nullable|string|max:255',
            'form.amount' => 'required|numeric|min:0.01',
            'form.currency' => 'required|in:AOA,USD,EUR',
            'form.transaction_date' => 'required|date',
            'form.payment_method_id' => 'required|exists:treasury_payment_methods,id',
            'form.account_id' => 'nullable|exists:treasury_accounts,id',
            'form.cash_register_id' => 'nullable|exists:treasury_cash_registers,id',
            'form.reference' => 'nullable|string|max:255',
            'form.description' => 'required|string',
            'form.notes' => 'nullable|string',
            'form.status' => 'required|in:pending,completed,cancelled',
        ];
    }
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updatingFilterType()
    {
        $this->resetPage();
    }
    
    public function updatingFilterStatus()
    {
        $this->resetPage();
    }
    
    public function create()
    {
        $this->reset(['form', 'transactionId', 'editMode']);
        $this->form['currency'] = 'AOA';
        $this->form['transaction_date'] = date('Y-m-d');
        $this->form['status'] = 'completed';
        $this->showModal = true;
    }
    
    public function edit($id)
    {
        $transaction = Transaction::findOrFail($id);
        
        $this->transactionId = $transaction->id;
        $this->editMode = true;
        
        $this->form = [
            'type' => $transaction->type,
            'category' => $transaction->category,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
            'payment_method_id' => $transaction->payment_method_id,
            'account_id' => $transaction->account_id,
            'cash_register_id' => $transaction->cash_register_id,
            'reference' => $transaction->reference,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'status' => $transaction->status,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();
        
        // Gerar número da transação se for nova
        if (!$this->editMode) {
            $lastTransaction = Transaction::where('tenant_id', auth()->user()->tenant_id)
                ->whereYear('created_at', date('Y'))
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNumber = $lastTransaction ? ((int) substr($lastTransaction->transaction_number, -4)) + 1 : 1;
            $transactionNumber = 'TRX-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }
        
        $data = array_merge($this->form, [
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
        ]);
        
        if (!$this->editMode) {
            $data['transaction_number'] = $transactionNumber;
        }
        
        if ($this->editMode) {
            $transaction = Transaction::findOrFail($this->transactionId);
            $transaction->update($data);
            
            $this->dispatch('success', message: 'Transação atualizada com sucesso!');
        } else {
            Transaction::create($data);
            
            // Atualizar saldo da conta/caixa se status for completed
            if ($this->form['status'] === 'completed') {
                $this->updateBalance($data);
            }
            
            $this->dispatch('success', message: 'Transação criada com sucesso!');
        }
        
        $this->closeModal();
        $this->dispatch('refreshComponent');
    }
    
    private function updateBalance($data)
    {
        $amount = $data['amount'];
        
        if ($data['account_id']) {
            $account = Account::findOrFail($data['account_id']);
            if ($data['type'] === 'income') {
                $account->increment('current_balance', $amount);
            } else {
                $account->decrement('current_balance', $amount);
            }
        }
        
        if ($data['cash_register_id']) {
            $cashRegister = CashRegister::findOrFail($data['cash_register_id']);
            if ($data['type'] === 'income') {
                $cashRegister->increment('current_balance', $amount);
            } else {
                $cashRegister->decrement('current_balance', $amount);
            }
        }
    }
    
    public function confirmDelete($id)
    {
        $transaction = Transaction::findOrFail($id);
        $this->transactionId = $transaction->id;
        $this->transactionToDeleteNumber = $transaction->transaction_number;
        $this->showDeleteModal = true;
    }
    
    public function deleteTransaction()
    {
        Transaction::findOrFail($this->transactionId)->delete();
        
        session()->flash('message', 'Transação eliminada com sucesso!');
        
        $this->closeDeleteModal();
        $this->dispatch('refreshComponent');
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['form', 'transactionId', 'editMode']);
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->reset(['transactionId', 'transactionToDeleteNumber']);
    }
    
    public function render()
    {
        $query = Transaction::with(['paymentMethod', 'account', 'cashRegister', 'user'])
            ->where('tenant_id', auth()->user()->tenant_id);
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('transaction_number', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhere('reference', 'like', '%' . $this->search . '%');
            });
        }
        
        // Filter by type
        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }
        
        // Filter by status
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);
        
        $totalIncome = Transaction::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'income')
            ->where('status', 'completed')
            ->sum('amount');
            
        $totalExpense = Transaction::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->sum('amount');
        
        $paymentMethods = PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $accounts = Account::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();
            
        $cashRegisters = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        return view('livewire.treasury.transactions.transactions', [
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'paymentMethods' => $paymentMethods,
            'accounts' => $accounts,
            'cashRegisters' => $cashRegisters,
        ]);
    }
}

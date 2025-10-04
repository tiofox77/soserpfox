<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\CashRegister;
use App\Models\User;

#[Layout('layouts.app')]
#[Title('Caixas')]
class CashRegisters extends Component
{
    use WithPagination;
    
    public $search = '';
    public $filterStatus = '';
    public $perPage = 10;
    
    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;
    
    public $cashRegisterId;
    public $cashRegisterToDeleteName;
    
    public $form = [
        'user_id' => '',
        'name' => '',
        'code' => '',
        'opening_balance' => 0,
        'opening_notes' => '',
        'is_active' => true,
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.user_id' => 'required|exists:users,id',
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:treasury_cash_registers,code,' . ($this->cashRegisterId ?? 'NULL'),
            'form.opening_balance' => 'nullable|numeric|min:0',
            'form.opening_notes' => 'nullable|string',
            'form.is_active' => 'boolean',
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
        $this->reset(['form', 'cashRegisterId', 'editMode']);
        $this->form['is_active'] = true;
        $this->form['opening_balance'] = 0;
        $this->showModal = true;
    }
    
    public function edit($id)
    {
        $cashRegister = CashRegister::with('user')->findOrFail($id);
        
        $this->cashRegisterId = $cashRegister->id;
        $this->editMode = true;
        
        $this->form = [
            'user_id' => $cashRegister->user_id,
            'name' => $cashRegister->name,
            'code' => $cashRegister->code,
            'opening_balance' => $cashRegister->opening_balance,
            'opening_notes' => $cashRegister->opening_notes,
            'is_active' => $cashRegister->is_active,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();
        
        $data = array_merge($this->form, [
            'tenant_id' => auth()->user()->tenant_id,
            'status' => 'closed', // Sempre inicia fechado
        ]);
        
        if ($this->editMode) {
            $cashRegister = CashRegister::findOrFail($this->cashRegisterId);
            $cashRegister->update($data);
            
            session()->flash('message', 'Caixa atualizado com sucesso!');
        } else {
            $data['current_balance'] = 0;
            $data['expected_balance'] = 0;
            
            CashRegister::create($data);
            
            session()->flash('message', 'Caixa criado com sucesso!');
        }
        
        $this->closeModal();
        $this->dispatch('refreshComponent');
    }
    
    public function openCashRegister($id)
    {
        $cashRegister = CashRegister::findOrFail($id);
        
        $cashRegister->update([
            'status' => 'open',
            'opened_at' => now(),
            'current_balance' => $cashRegister->opening_balance,
        ]);
        
        $this->dispatch('success', message: 'Caixa aberto com sucesso!');
    }
    
    public function closeCashRegister($id)
    {
        $cashRegister = CashRegister::findOrFail($id);
        
        $cashRegister->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        
        $this->dispatch('success', message: 'Caixa fechado com sucesso!');
    }
    
    public function confirmDelete($id)
    {
        $cashRegister = CashRegister::findOrFail($id);
        $this->cashRegisterId = $cashRegister->id;
        $this->cashRegisterToDeleteName = $cashRegister->name;
        $this->showDeleteModal = true;
    }
    
    public function deleteCashRegister()
    {
        CashRegister::findOrFail($this->cashRegisterId)->delete();
        
        session()->flash('message', 'Caixa eliminado com sucesso!');
        
        $this->closeDeleteModal();
        $this->dispatch('refreshComponent');
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['form', 'cashRegisterId', 'editMode']);
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->reset(['cashRegisterId', 'cashRegisterToDeleteName']);
    }
    
    public function render()
    {
        $query = CashRegister::with('user')
            ->where('tenant_id', auth()->user()->tenant_id);
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }
        
        // Filter by status
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        
        $cashRegisters = $query->orderBy('status', 'desc')
            ->orderBy('name')
            ->paginate($this->perPage);
        
        $openCount = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'open')->count();
            
        $closedCount = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'closed')->count();
            
        $totalBalance = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'open')
            ->sum('current_balance');
        
        $users = User::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();
        
        return view('livewire.treasury.cash-registers.cash-registers', [
            'cashRegisters' => $cashRegisters,
            'openCount' => $openCount,
            'closedCount' => $closedCount,
            'totalBalance' => $totalBalance,
            'users' => $users,
        ]);
    }
}

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
            // Tem de ser alguém DESTA empresa. `exists:users,id` aceitava
            // qualquer utilizador do sistema — bastava trocar o valor no
            // pedido para pôr uma pessoa de outra empresa como responsável de
            // um caixa que não é dela.
            'form.user_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('tenant_user', 'user_id')
                    ->where('tenant_id', activeTenantId()),
            ],
            'form.name' => 'required|string|max:255',
            'form.code' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('treasury_cash_registers', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', activeTenantId()))
                    ->ignore($this->cashRegisterId),
            ],
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
            'tenant_id' => activeTenantId(),
            'status' => 'closed', // Sempre inicia fechado
        ]);
        
        if ($this->editMode) {
            $cashRegister = CashRegister::findOrFail($this->cashRegisterId);
            $cashRegister->update($data);
            
            $this->dispatch('success', message: 'Caixa atualizado com sucesso!');
        } else {
            $data['current_balance'] = (float) ($data['opening_balance'] ?? 0);
            $data['expected_balance'] = (float) ($data['opening_balance'] ?? 0);
            
            CashRegister::create($data);
            
            $this->dispatch('success', message: 'Caixa criado com sucesso!');
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
        
        $this->dispatch('success', message: 'Caixa eliminado com sucesso!');
        
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
            ->where('tenant_id', activeTenantId());
        
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
        
        $openCount = CashRegister::where('tenant_id', activeTenantId())
            ->where('status', 'open')->count();
            
        $closedCount = CashRegister::where('tenant_id', activeTenantId())
            ->where('status', 'closed')->count();
            
        $totalBalance = CashRegister::where('tenant_id', activeTenantId())
            ->where('status', 'open')
            ->sum('current_balance');
        
        // Os utilizadores DESTA empresa, pelo pivô `tenant_user`.
        //
        // Isto procurava por `users.tenant_id`. A coluna existe e está quase
        // toda preenchida, mas NÃO é a fonte de verdade: quem entra numa
        // empresa entra pelo pivô, e quem for acrescentado sem passar pela
        // coluna antiga desaparece da lista. Medido em produção: uma empresa
        // com 9 pessoas no pivô mostrava 8 — e a que faltava não podia ser
        // escolhida como responsável de caixa nenhum.
        //
        // `users.is_active` QUALIFICADO: a coluna existe nas duas tabelas e
        // sem prefixo o MySQL recusa a consulta por ambiguidade.
        $empresa = \App\Models\Tenant::find(activeTenantId());

        $users = $empresa
            ? $empresa->users()
                ->where('users.is_active', true)
                ->wherePivot('is_active', true)
                ->orderBy('users.name')
                ->get()
            : collect();
        
        return view('livewire.treasury.cash-registers.cash-registers', [
            'cashRegisters' => $cashRegisters,
            'openCount' => $openCount,
            'closedCount' => $closedCount,
            'totalBalance' => $totalBalance,
            'users' => $users,
        ]);
    }
}

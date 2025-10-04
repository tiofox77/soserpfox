<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Treasury\Bank;

#[Layout('layouts.app')]
#[Title('Bancos')]
class Banks extends Component
{
    use WithPagination;
    
    public $search = '';
    public $perPage = 10;
    
    public $showModal = false;
    public $showDeleteModal = false;
    public $editMode = false;
    
    public $bankId;
    public $bankToDeleteName;
    
    public $form = [
        'name' => '',
        'code' => '',
        'swift_code' => '',
        'country' => 'AO',
        'logo_url' => '',
        'website' => '',
        'phone' => '',
        'is_active' => true,
    ];
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function rules()
    {
        return [
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:treasury_banks,code,' . ($this->bankId ?? 'NULL'),
            'form.swift_code' => 'nullable|string|max:255',
            'form.country' => 'required|string|max:2',
            'form.logo_url' => 'nullable|url',
            'form.website' => 'nullable|url',
            'form.phone' => 'nullable|string|max:255',
            'form.is_active' => 'boolean',
        ];
    }
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function create()
    {
        $this->reset(['form', 'bankId', 'editMode']);
        $this->form['is_active'] = true;
        $this->form['country'] = 'AO';
        $this->showModal = true;
    }
    
    public function edit($id)
    {
        $bank = Bank::findOrFail($id);
        
        $this->bankId = $bank->id;
        $this->editMode = true;
        
        $this->form = [
            'name' => $bank->name,
            'code' => $bank->code,
            'swift_code' => $bank->swift_code,
            'country' => $bank->country,
            'logo_url' => $bank->logo_url,
            'website' => $bank->website,
            'phone' => $bank->phone,
            'is_active' => $bank->is_active,
        ];
        
        $this->showModal = true;
    }
    
    public function save()
    {
        $this->validate();
        
        if ($this->editMode) {
            $bank = Bank::findOrFail($this->bankId);
            $bank->update($this->form);
            
            session()->flash('message', 'Banco atualizado com sucesso!');
        } else {
            Bank::create($this->form);
            
            session()->flash('message', 'Banco criado com sucesso!');
        }
        
        $this->closeModal();
        $this->dispatch('refreshComponent');
    }
    
    public function toggleStatus($id)
    {
        $bank = Bank::findOrFail($id);
        $bank->update(['is_active' => !$bank->is_active]);
        
        $status = $bank->is_active ? 'ativado' : 'desativado';
        $this->dispatch('success', message: "Banco {$status} com sucesso!");
    }
    
    public function confirmDelete($id)
    {
        $bank = Bank::findOrFail($id);
        $this->bankId = $bank->id;
        $this->bankToDeleteName = $bank->name;
        $this->showDeleteModal = true;
    }
    
    public function deleteBank()
    {
        Bank::findOrFail($this->bankId)->delete();
        
        session()->flash('message', 'Banco eliminado com sucesso!');
        
        $this->closeDeleteModal();
        $this->dispatch('refreshComponent');
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['form', 'bankId', 'editMode']);
    }
    
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->reset(['bankId', 'bankToDeleteName']);
    }
    
    public function render()
    {
        $query = Bank::query();
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('swift_code', 'like', '%' . $this->search . '%');
            });
        }
        
        $banks = $query->orderBy('name')->paginate($this->perPage);
        
        $activeCount = Bank::where('is_active', true)->count();
        
        return view('livewire.treasury.banks.banks', [
            'banks' => $banks,
            'activeCount' => $activeCount,
        ]);
    }
}

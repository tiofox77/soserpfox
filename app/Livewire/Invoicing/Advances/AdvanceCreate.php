<?php

namespace App\Livewire\Invoicing\Advances;

use App\Models\Invoicing\Advance;
use App\Models\Client;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Novo Adiantamento')]
class AdvanceCreate extends Component
{
    public $advanceId = null;
    public $isEdit = false;
    
    // Campos
    public $client_id = '';
    public $payment_date;
    public $amount = 0;
    public $payment_method = 'cash';
    public $purpose = '';
    public $notes = '';
    
    // Search
    public $searchClient = '';

    protected function rules()
    {
        return [
            'client_id' => 'required',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required',
        ];
    }

    public function mount($id = null)
    {
        $this->payment_date = date('Y-m-d');
        
        if ($id) {
            $this->isEdit = true;
            $this->advanceId = $id;
            $this->loadAdvance();
        }
    }

    public function loadAdvance()
    {
        $advance = Advance::where('tenant_id', activeTenantId())->findOrFail($this->advanceId);
        
        $this->client_id = $advance->client_id;
        $this->payment_date = $advance->payment_date->format('Y-m-d');
        $this->amount = $advance->amount;
        $this->payment_method = $advance->payment_method;
        $this->purpose = $advance->purpose;
        $this->notes = $advance->notes;
    }

    public function selectClient($clientId)
    {
        $this->client_id = $clientId;
        $this->searchClient = '';
    }

    public function save()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                $advance = Advance::where('tenant_id', activeTenantId())->findOrFail($this->advanceId);
                
                if ($advance->used_amount > 0) {
                    throw new \Exception('Não é possível editar adiantamento já utilizado.');
                }
                
                $advance->update([
                    'client_id' => $this->client_id,
                    'payment_date' => $this->payment_date,
                    'amount' => $this->amount,
                    'remaining_amount' => $this->amount,
                    'payment_method' => $this->payment_method,
                    'purpose' => $this->purpose,
                    'notes' => $this->notes,
                ]);
            } else {
                $advance = Advance::create([
                    'tenant_id' => activeTenantId(),
                    'type' => 'sale',
                    'client_id' => $this->client_id,
                    'payment_date' => $this->payment_date,
                    'amount' => $this->amount,
                    'payment_method' => $this->payment_method,
                    'purpose' => $this->purpose,
                    'notes' => $this->notes,
                    'status' => 'available',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Adiantamento ' . ($this->isEdit ? 'atualizado' : 'criado') . ' com sucesso!'
            ]);

            return redirect()->route('invoicing.advances.index');

        } catch (\Exception $e) {
            DB::rollback();
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao salvar adiantamento: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        // Buscar clientes
        $clientsQuery = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true);
            
        if ($this->searchClient) {
            $clientsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchClient . '%')
                  ->orWhere('nif', 'like', '%' . $this->searchClient . '%');
            });
        } elseif ($this->client_id) {
            $clientsQuery->where('id', $this->client_id);
        }
        
        $clients = $clientsQuery->orderBy('name')->limit(50)->get();

        return view('livewire.invoicing.advances.advance-create', [
            'clients' => $clients,
        ]);
    }
}

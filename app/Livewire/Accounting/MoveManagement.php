<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Period;
use App\Models\Accounting\Account;
use App\Models\Accounting\DocumentType;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class MoveManagement extends Component
{
    use WithPagination;
    
    public $search = '';
    public $stateFilter = '';
    public $journalFilter = '';
    public $showModal = false;
    public $editMode = false;
    
    // Form fields
    public $moveId;
    public $journal_id;
    public $period_id;
    public $document_type_id;
    public $date;
    public $ref;
    public $narration;
    public $state = 'draft';
    
    // Lines
    public $lines = [];
    
    public function mount()
    {
        $this->date = now()->format('Y-m-d');
        $this->setCurrentPeriod();
        $this->addLine();
    }
    
    public function updated($propertyName)
    {
        // Gerar referência automática quando diário for selecionado
        if ($propertyName === 'journal_id' && $this->journal_id) {
            $this->generateReference();
        }
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $moves = Move::where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where('ref', 'like', '%'.$this->search.'%');
            })
            ->when($this->stateFilter, function($query) {
                $query->where('state', $this->stateFilter);
            })
            ->when($this->journalFilter, function($query) {
                $query->where('journal_id', $this->journalFilter);
            })
            ->with(['journal', 'period'])
            ->latest('date')
            ->paginate(15);
            
        $journals = Journal::where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get();
        $periods = Period::where('tenant_id', $tenantId)->where('state', 'open')->orderBy('date_start', 'desc')->get();
        $accounts = Account::where('tenant_id', $tenantId)->where('blocked', false)->orderBy('code')->get();
        $documentTypes = DocumentType::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('code')->get();
        
        // Stats
        $allMoves = Move::where('tenant_id', $tenantId)->get();
        $stats = [
            'total' => $allMoves->count(),
            'draft' => $allMoves->where('state', 'draft')->count(),
            'posted' => $allMoves->where('state', 'posted')->count(),
            'this_month' => $allMoves->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];
        
        return view('livewire.accounting.moves.moves', [
            'moves' => $moves,
            'journals' => $journals,
            'periods' => $periods,
            'accounts' => $accounts,
            'documentTypes' => $documentTypes,
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
    
    public function addLine()
    {
        $this->lines[] = [
            'account_id' => '',
            'debit' => 0,
            'credit' => 0,
            'narration' => '',
        ];
    }
    
    public function removeLine($index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }
    
    public function save()
    {
        $this->validate([
            'journal_id' => 'required',
            'period_id' => 'required',
            'date' => 'required|date',
            'ref' => 'required|max:50',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);
        
        // Verificar se o período está fechado
        $period = Period::find($this->period_id);
        if ($period && $period->state === 'closed') {
            session()->flash('error', 'Erro: Período contabilístico está fechado! Não é possível criar lançamentos.');
            return;
        }
        
        $totalDebit = collect($this->lines)->sum('debit');
        $totalCredit = collect($this->lines)->sum('credit');
        
        if (abs($totalDebit - $totalCredit) > 0.01) {
            session()->flash('error', 'Erro: Débito e Crédito devem estar balanceados!');
            return;
        }
        
        DB::transaction(function() use ($totalDebit, $totalCredit) {
            $move = Move::create([
                'tenant_id' => auth()->user()->tenant_id,
                'journal_id' => $this->journal_id,
                'period_id' => $this->period_id,
                'document_type_id' => $this->document_type_id,
                'date' => $this->date,
                'ref' => $this->ref,
                'narration' => $this->narration,
                'state' => $this->state,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => auth()->id(),
            ]);
            
            foreach ($this->lines as $line) {
                MoveLine::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'move_id' => $move->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'balance' => $line['debit'] - $line['credit'],
                    'narration' => $line['narration'] ?? null,
                ]);
            }
            
            // Atualizar último número do diário
            $journal = Journal::find($this->journal_id);
            if ($journal) {
                $journal->increment('last_number');
            }
        });
        
        session()->flash('message', 'Lançamento criado com sucesso!');
        $this->closeModal();
    }
    
    public function post($id)
    {
        $move = Move::find($id);
        $move->update([
            'state' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
        session()->flash('message', 'Lançamento confirmado!');
    }
    
    public function delete($id)
    {
        Move::find($id)->delete();
        session()->flash('message', 'Lançamento excluído com sucesso!');
    }
    
    /**
     * Define o período contabilístico do mês atual
     */
    private function setCurrentPeriod()
    {
        $tenantId = auth()->user()->tenant_id;
        $currentMonth = now()->format('Y-m');
        
        // Buscar período do mês atual
        $period = Period::where('tenant_id', $tenantId)
            ->where('state', 'open')
            ->whereRaw("DATE_FORMAT(date_start, '%Y-%m') = ?", [$currentMonth])
            ->first();
        
        if ($period) {
            $this->period_id = $period->id;
        }
    }
    
    /**
     * Gera referência automática baseada no diário selecionado
     */
    private function generateReference()
    {
        $journal = Journal::find($this->journal_id);
        
        if (!$journal) {
            return;
        }
        
        // Incrementar o último número do diário
        $nextNumber = $journal->last_number + 1;
        
        // Gerar referência: Prefixo + Número
        // Exemplo: DG-00001, VD-00042, CP-00123
        $this->ref = $journal->sequence_prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        
        // Atualizar last_number do diário será feito ao salvar
    }
    
    private function resetForm()
    {
        $this->moveId = null;
        $this->journal_id = null;
        $this->period_id = null;
        $this->document_type_id = null;
        $this->date = now()->format('Y-m-d');
        $this->ref = '';
        $this->narration = '';
        $this->state = 'draft';
        $this->lines = [];
        $this->setCurrentPeriod();
        $this->addLine();
        $this->addLine();
    }
}

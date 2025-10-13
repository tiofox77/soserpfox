<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\Account;
use App\Services\Accounting\BankReconciliationService;

#[Layout('layouts.app')]
class BankReconciliationManagement extends Component
{
    use WithFileUploads;
    
    public $accountId;
    public $statementDate;
    public $file;
    public $fileType = 'csv';
    public $selectedReconciliation;
    
    public function mount()
    {
        $this->statementDate = date('Y-m-d');
    }
    
    public function importFile()
    {
        $this->validate([
            'accountId' => 'required',
            'file' => 'required|file|max:10240',
            'fileType' => 'required|in:csv,mt940,ofx',
        ]);
        
        $service = new BankReconciliationService();
        $tenantId = auth()->user()->tenant_id;
        
        try {
            $reconciliation = $service->importStatementFile(
                $this->file,
                $tenantId,
                $this->accountId,
                $this->fileType
            );
            
            session()->flash('success', 'Extrato importado com sucesso! ' . $reconciliation->items()->count() . ' transações.');
            $this->reset(['file', 'fileType']);
            $this->selectedReconciliation = $reconciliation->id;
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao importar: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $bankAccounts = Account::where('tenant_id', $tenantId)
            ->where(function($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('code', 'like', '12%');
            })
            ->get();
        
        $reconciliations = BankReconciliation::where('tenant_id', $tenantId)
            ->with('account')
            ->orderBy('statement_date', 'desc')
            ->paginate(20);
        
        // Stats
        $allReconciliations = BankReconciliation::where('tenant_id', $tenantId)->get();
        $stats = [
            'total' => $allReconciliations->count(),
            'pending' => $allReconciliations->where('status', 'draft')->count(),
            'reconciled' => $allReconciliations->where('status', 'reconciled')->count(),
            'differences' => $allReconciliations->where('difference', '!=', 0)->count(),
        ];
        
        return view('livewire.accounting.bank-reconciliation.bank-reconciliation', [
            'bankAccounts' => $bankAccounts,
            'reconciliations' => $reconciliations,
            'stats' => $stats,
        ]);
    }
}

<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\Account;
use App\Models\Accounting\Move;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public $dateFrom;
    public $dateTo;
    
    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Total Ativo
        $totalAssets = Account::where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->where('blocked', false)
            ->count();
            
        // Total Passivo
        $totalLiabilities = Account::where('tenant_id', $tenantId)
            ->where('type', 'liability')
            ->where('blocked', false)
            ->count();
            
        // Total Receitas
        $totalRevenue = Account::where('tenant_id', $tenantId)
            ->where('type', 'revenue')
            ->where('blocked', false)
            ->count();
            
        // Total Gastos
        $totalExpenses = Account::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->where('blocked', false)
            ->count();
            
        // Lançamentos recentes
        $recentMoves = Move::where('tenant_id', $tenantId)
            ->where('state', 'posted')
            ->latest()
            ->take(10)
            ->with(['journal', 'creator'])
            ->get();
        
        // Total de lançamentos
        $totalMoves = Move::where('tenant_id', $tenantId)->count();
        $postedMoves = Move::where('tenant_id', $tenantId)->where('state', 'posted')->count();
        $draftMoves = Move::where('tenant_id', $tenantId)->where('state', 'draft')->count();
        
        return view('livewire.accounting.dashboard.dashboard', [
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'recentMoves' => $recentMoves,
            'totalMoves' => $totalMoves,
            'postedMoves' => $postedMoves,
            'draftMoves' => $draftMoves,
        ]);
    }
}

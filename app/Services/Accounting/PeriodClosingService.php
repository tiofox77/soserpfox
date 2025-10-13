<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Period;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PeriodClosingService
{
    /**
     * Fecha um período contabilístico
     * 
     * @param Period $period
     * @return array
     */
    public function closePeriod(Period $period)
    {
        return DB::transaction(function () use ($period) {
            // Validações
            if ($period->state === 'closed') {
                throw new \Exception('Período já está fechado');
            }
            
            // Verificar se há lançamentos não postados
            $draftMoves = Move::where('tenant_id', $period->tenant_id)
                ->where('period_id', $period->id)
                ->where('state', 'draft')
                ->count();
            
            if ($draftMoves > 0) {
                throw new \Exception("Existem {$draftMoves} lançamentos em rascunho. Poste ou delete antes de fechar o período.");
            }
            
            // Verificar balancete balanceado
            $balance = $this->checkBalance($period);
            if (!$balance['balanced']) {
                throw new \Exception('Balancete não está balanceado. Diferença: ' . number_format($balance['difference'], 2));
            }
            
            // Fechar período
            $period->update([
                'state' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);
            
            return [
                'success' => true,
                'message' => 'Período fechado com sucesso',
                'period' => $period->fresh(),
                'balance' => $balance,
            ];
        });
    }
    
    /**
     * Reabre um período contabilístico
     * 
     * @param Period $period
     * @return array
     */
    public function reopenPeriod(Period $period)
    {
        return DB::transaction(function () use ($period) {
            if ($period->state !== 'closed') {
                throw new \Exception('Período não está fechado');
            }
            
            // Verificar se não há períodos posteriores fechados
            $laterClosedPeriods = Period::where('tenant_id', $period->tenant_id)
                ->where('date_start', '>', $period->date_start)
                ->where('state', 'closed')
                ->count();
            
            if ($laterClosedPeriods > 0) {
                throw new \Exception('Não é possível reabrir. Existem períodos posteriores já fechados.');
            }
            
            $period->update([
                'state' => 'open',
                'closed_at' => null,
                'closed_by' => null,
            ]);
            
            return [
                'success' => true,
                'message' => 'Período reaberto com sucesso',
                'period' => $period->fresh(),
            ];
        });
    }
    
    /**
     * Verifica se o balancete está balanceado
     * 
     * @param Period $period
     * @return array
     */
    protected function checkBalance(Period $period)
    {
        $moves = Move::where('tenant_id', $period->tenant_id)
            ->where('period_id', $period->id)
            ->where('state', 'posted')
            ->get();
        
        $totalDebit = 0;
        $totalCredit = 0;
        
        foreach ($moves as $move) {
            $totalDebit += $move->lines()->sum('debit');
            $totalCredit += $move->lines()->sum('credit');
        }
        
        $difference = $totalDebit - $totalCredit;
        $balanced = abs($difference) < 0.01;
        
        return [
            'balanced' => $balanced,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'moves_count' => $moves->count(),
        ];
    }
    
    /**
     * Calcula resultado do exercício
     * 
     * @param int $tenantId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function calculateYearResult($tenantId, $dateFrom, $dateTo)
    {
        // Rendimentos (Classe 7 - Crédito)
        $revenues = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) {
                $q->where('code', 'like', '7%');
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('SUM(credit) - SUM(debit) as total')
            ->value('total') ?? 0;
        
        // Gastos (Classe 6 - Débito)
        $expenses = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) {
                $q->where('code', 'like', '6%');
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->selectRaw('SUM(debit) - SUM(credit) as total')
            ->value('total') ?? 0;
        
        $netIncome = $revenues - $expenses;
        
        return [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'net_income' => $netIncome,
            'is_profit' => $netIncome >= 0,
        ];
    }
    
    /**
     * Gera lançamento de apuramento de resultado
     * 
     * @param int $tenantId
     * @param string $date
     * @return Move
     */
    public function postYearEndClosing($tenantId, $date)
    {
        return DB::transaction(function () use ($tenantId, $date) {
            $year = Carbon::parse($date)->year;
            $dateFrom = "{$year}-01-01";
            $dateTo = "{$year}-12-31";
            
            $result = $this->calculateYearResult($tenantId, $dateFrom, $dateTo);
            
            if ($result['net_income'] == 0) {
                throw new \Exception('Resultado do exercício é zero. Nenhum lançamento necessário.');
            }
            
            // Buscar diário de ajustes
            $journal = \App\Models\Accounting\Journal::where('tenant_id', $tenantId)
                ->where('type', 'general')
                ->first();
            
            if (!$journal) {
                throw new \Exception('Diário de ajustes não encontrado');
            }
            
            // Buscar período
            $period = Period::where('tenant_id', $tenantId)
                ->whereDate('date_start', '<=', $date)
                ->whereDate('date_end', '>=', $date)
                ->first();
            
            if (!$period) {
                throw new \Exception('Período não encontrado');
            }
            
            // Criar lançamento
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'period_id' => $period->id,
                'date' => $date,
                'ref' => 'APURAMENTO-' . $year,
                'narration' => 'Apuramento do Resultado do Exercício ' . $year,
                'state' => 'draft',
            ]);
            
            // Buscar conta de resultado
            $resultAccount = Account::where('tenant_id', $tenantId)
                ->where('code', 'like', '81%') // Resultado Líquido do Período
                ->first();
            
            if (!$resultAccount) {
                throw new \Exception('Conta de resultado não encontrada (81)');
            }
            
            $lines = [];
            
            if ($result['is_profit']) {
                // Lucro: Débito Resultado, Crédito Reservas/Resultados Transitados
                $lines[] = [
                    'tenant_id' => $tenantId,
                    'move_id' => $move->id,
                    'account_id' => $resultAccount->id,
                    'name' => 'Resultado Líquido do Exercício ' . $year,
                    'debit' => $result['net_income'],
                    'credit' => 0,
                ];
                
                // Buscar conta de resultados transitados
                $retainedAccount = Account::where('tenant_id', $tenantId)
                    ->where('code', 'like', '56%')
                    ->first();
                
                if ($retainedAccount) {
                    $lines[] = [
                        'tenant_id' => $tenantId,
                        'move_id' => $move->id,
                        'account_id' => $retainedAccount->id,
                        'name' => 'Transferência para Resultados Transitados',
                        'debit' => 0,
                        'credit' => $result['net_income'],
                    ];
                }
            } else {
                // Prejuízo: inverso
                $lines[] = [
                    'tenant_id' => $tenantId,
                    'move_id' => $move->id,
                    'account_id' => $resultAccount->id,
                    'name' => 'Resultado Líquido do Exercício ' . $year,
                    'debit' => 0,
                    'credit' => abs($result['net_income']),
                ];
                
                $retainedAccount = Account::where('tenant_id', $tenantId)
                    ->where('code', 'like', '56%')
                    ->first();
                
                if ($retainedAccount) {
                    $lines[] = [
                        'tenant_id' => $tenantId,
                        'move_id' => $move->id,
                        'account_id' => $retainedAccount->id,
                        'name' => 'Transferência de Prejuízo',
                        'debit' => abs($result['net_income']),
                        'credit' => 0,
                    ];
                }
            }
            
            // Criar linhas
            foreach ($lines as $lineData) {
                MoveLine::create($lineData);
            }
            
            return $move;
        });
    }
}
